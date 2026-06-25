<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
require_not_revisore();
$cfg  = config();
$pdo  = db();
$sett = get_settings($pdo);
$TOL  = (float)($cfg['tolleranza'] ?? 5);

$data = $_GET['data'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) $data = date('Y-m-d');

$turns    = get_turns($sett);
$fornitori = get_fornitori($pdo);
$lastTurn  = max(array_keys($turns));
$macchine = $pdo->query('SELECT * FROM macchine WHERE attiva = 1 AND tipo = "VLT" ORDER BY ordine')->fetchAll();
$byforn = [];
foreach ($macchine as $m) $byforn[$m['fornitore']][] = $m;

/* ---------------- POST ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $g = ensure_giornata($pdo, $data);
    if (($_POST['azione'] ?? '') === 'chiudi') {
        $pdo->prepare('UPDATE giornate SET stato="chiusa", chiusa_da=?, chiusa_il=NOW() WHERE id=?')->execute([$user['id'], $g['id']]);
        audit('chiusura_giornata','giornate',(int)$g['id'],$data);
        header("Location: giornaliero.php?data=$data&ok=1"); exit;
    }
    if (($_POST['azione'] ?? '') === 'riapri' && is_responsabile()) {
        $pdo->prepare('UPDATE giornate SET stato="aperta", chiusa_da=NULL, chiusa_il=NULL WHERE id=?')->execute([$g['id']]);
        audit('riapertura_giornata','giornate',(int)$g['id'],$data);
        header("Location: giornaliero.php?data=$data&ok=1"); exit;
    }
    if ($g['stato'] === 'chiusa' && !is_responsabile()) { http_response_code(403); exit('Giornata chiusa: modifica non consentita.'); }

    $in      = $_POST['turno'] ?? [];
    $turnNums = array_keys($turns);
    $st_n    = (int)($_POST['salva_turno'] ?? $lastTurn);
    $salva_n = is_responsabile() ? $turnNums : (in_array($st_n, $turnNums) ? [$st_n] : [$lastTurn]);
    try {
        $pdo->beginTransaction();
        foreach ($salva_n as $n) {
            $t = ensure_turno($pdo, (int)$g['id'], $n); $tid = (int)$t['id']; $d = $in[$n] ?? [];
            $num = fn($v) => is_numeric($v) ? (float)$v : 0.0;
            $note = trim((string)($d['note'] ?? '')); $note = $note === '' ? null : mb_substr($note, 0, 1000);
            $pdo->prepare('UPDATE turni SET fondo_cassa=?, monete=?, bancomat=?, differenze=?, ii_cassa=?, rientri=?, note=? WHERE id=?')
                ->execute([$num($d['fondo_cassa']??0),$num($d['monete']??0),$num($d['bancomat']??0),
                           $num($d['differenze']??0),$num($d['ii_cassa']??0),$num($d['rientri']??0),$note,$tid]);
            $pdo->prepare('DELETE FROM contanti WHERE turno_id=?')->execute([$tid]);
            $stc = $pdo->prepare('INSERT INTO contanti (turno_id,taglio,pezzi) VALUES (?,?,?)');
            foreach (tagli() as $tg) { $pz=(int)($d['contanti'][$tg]??0); if ($pz!==0) $stc->execute([$tid,$tg,$pz]); }
            $pdo->prepare('DELETE FROM scassettamenti WHERE turno_id=?')->execute([$tid]);
            $sts = $pdo->prepare('INSERT INTO scassettamenti (turno_id,macchina_id,importo) VALUES (?,?,?)');
            foreach ($macchine as $m) { $imp=$num($d['scass'][$m['id']]??0); if ($imp!=0.0) $sts->execute([$tid,(int)$m['id'],$imp]); }
            $pdo->prepare('DELETE FROM ticket WHERE turno_id=?')->execute([$tid]);
            $stt = $pdo->prepare('INSERT INTO ticket (turno_id,fornitore,importo) VALUES (?,?,?)');
            foreach ($fornitori as $f) { $imp=$num($d['ticket'][$f]??0); if ($imp!=0.0) $stt->execute([$tid,$f,$imp]); }
            $pdo->prepare('UPDATE turni SET operatore_id=? WHERE id=?')->execute([(int)$user['id'],$tid]);
        }
        $pdo->commit(); audit('salvataggio_giornata','giornate',(int)$g['id'],$data);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); exit('Errore salvataggio: '.htmlspecialchars($e->getMessage()));
    }
    header("Location: giornaliero.php?data=$data&ok=1"); exit;
}

/* ---------------- GET load ---------------- */
$g = ensure_giornata($pdo, $data);
$chiusa = ($g['stato'] === 'chiusa');
$readonly = $chiusa && !is_responsabile();
$turni = [];
foreach (array_keys($turns) as $n) {
    $t = ensure_turno($pdo, (int)$g['id'], $n); $tid = (int)$t['id'];
    $cont=[]; $st=$pdo->prepare('SELECT taglio,pezzi FROM contanti WHERE turno_id=?'); $st->execute([$tid]);
    foreach ($st as $r) $cont[(int)$r['taglio']]=(int)$r['pezzi'];
    $sc=[]; $st=$pdo->prepare('SELECT macchina_id,importo FROM scassettamenti WHERE turno_id=?'); $st->execute([$tid]);
    foreach ($st as $r) $sc[(int)$r['macchina_id']]=(float)$r['importo'];
    $st=$pdo->prepare('SELECT COALESCE(SUM(euro),0) v FROM refill_awp WHERE turno_id=?'); $st->execute([$tid]); $sum_refill=(float)$st->fetch()['v'];
    $tk=[]; $st=$pdo->prepare('SELECT fornitore,importo FROM ticket WHERE turno_id=?'); $st->execute([$tid]);
    foreach ($st as $r) $tk[$r['fornitore']]=(float)$r['importo'];
    $turni[$n] = ['t'=>$t,'cont'=>$cont,'sc'=>$sc,'tk'=>$tk,'sum_refill'=>$sum_refill];
}

/* Alert giornata precedente ancora aperta */
$prevDayAlert = false;
if ($data === date('Y-m-d')) {
    $stPrev = $pdo->prepare("SELECT stato FROM giornate WHERE data=DATE_SUB(CURDATE(),INTERVAL 1 DAY)");
    $stPrev->execute();
    $prevDay = $stPrev->fetch();
    $prevDayAlert = ($prevDay && $prevDay['stato'] === 'aperta');
}
$users = [];
foreach ($pdo->query('SELECT id, COALESCE(NULLIF(nome,""),username) nome FROM utenti') as $u) $users[(int)$u['id']] = $u['nome'];

/* Assegnazioni programmate per oggi (calendario turni) */
$programmati = [];
$stProg = $pdo->prepare('SELECT tp.numero, COALESCE(NULLIF(u.nome,""),u.username) AS nome
                          FROM turni_programmati tp JOIN utenti u ON u.id=tp.operatore_id
                          WHERE tp.data=?');
$stProg->execute([$data]);
foreach ($stProg as $r) $programmati[(int)$r['numero']] = $r['nome'];

/* Policy di modifica turni: configurabile da impostazioni.
   'libero' = qualsiasi utente può modificare qualsiasi turno (storico, correzioni).
   'assegnato' = l'operatore può modificare solo il turno assegnato a lui. */
$editLibero = ($sett['turno_edit_libero'] ?? '1') === '1';
$canEdit = [];
foreach (array_keys($turns) as $n) {
    $own     = $turni[$n]['t']['operatore_id'];
    $proprio = $own === null || (int)$own === (int)$user['id'];
    $canEdit[$n] = ($editLibero || is_responsabile() || $proprio)
                   && !($chiusa && !is_responsabile());
}
$anyEdit = in_array(true, $canEdit, true);

$schedName = fn($n) => $programmati[$n] ?? null;
$ownerName = fn($n) => ($turni[$n]['t']['operatore_id'] ? ($users[(int)$turni[$n]['t']['operatore_id']] ?? '—') : null);
$chiusaBy  = ($chiusa && !empty($g['chiusa_da'])) ? ($users[(int)$g['chiusa_da']] ?? '—') : null;
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv = fn($v) => ($v == 0 ? '' : rtrim(rtrim(number_format((float)$v,2,'.',''),'0'),'.'));
$prev = date('Y-m-d', strtotime("$data -1 day"));
$next = date('Y-m-d', strtotime("$data +1 day"));

/* ---------------- render schede input di un turno ---------------- */
$render = function($n) use ($h,$nv,$byforn,$fornitori,$turni,$turns,$TOL,$data,$canEdit,$ownerName) {
    $T = $turni[$n]; $ro = $canEdit[$n] ? '' : 'disabled'; $hide = $n===1 ? '1' : '0';
?>
  <section class="turno" id="turno-<?= $n ?>" role="tabpanel" aria-labelledby="tab-<?= $n ?>" data-turno="<?= $n ?>" data-hidden="<?= $hide ?>" data-refill="<?= $h($T['sum_refill']) ?>" data-tol="<?= $h($TOL) ?>">
    <div class="entry">

      <!-- Colonna 1: Valori / Refill / Note -->
      <div class="entry-col">
        <div class="panel">
          <h3>Valori cassa</h3>
          <div class="field"><label for="t<?= $n ?>-fondo">Fondo cassa</label><input id="t<?= $n ?>-fondo" type="number" inputmode="decimal" step="0.01" class="f-fondo_cassa" name="turno[<?= $n ?>][fondo_cassa]" value="<?= $h($nv($T['t']['fondo_cassa'])) ?>" <?= $ro ?>></div>
          <div class="field"><label for="t<?= $n ?>-monete">Monete</label><input id="t<?= $n ?>-monete" type="number" inputmode="decimal" step="0.01" class="f-monete" name="turno[<?= $n ?>][monete]" value="<?= $h($nv($T['t']['monete'])) ?>" <?= $ro ?>></div>
          <div class="field"><label for="t<?= $n ?>-bancomat">Bancomat</label><input id="t<?= $n ?>-bancomat" type="number" inputmode="decimal" step="0.01" class="f-bancomat" name="turno[<?= $n ?>][bancomat]" value="<?= $h($nv($T['t']['bancomat'])) ?>" <?= $ro ?>></div>
          <!-- <details class="adv"><summary>Rettifiche</summary> -->
          <div class="field"><label for="t<?= $n ?>-differenze">Differenze</label><input id="t<?= $n ?>-differenze" type="number" inputmode="decimal" step="0.01" class="f-differenze" name="turno[<?= $n ?>][differenze]" value="<?= $h($nv($T['t']['differenze'])) ?>" <?= $ro ?>></div>
          <div class="field"><label for="t<?= $n ?>-rientri">Rientri</label><input id="t<?= $n ?>-rientri" type="number" inputmode="decimal" step="0.01" class="f-rientri" name="turno[<?= $n ?>][rientri]" value="<?= $h($nv($T['t']['rientri'])) ?>" <?= $ro ?>></div>
          <div class="field"><label for="t<?= $n ?>-ii">2ª cassa</label><input id="t<?= $n ?>-ii" type="number" inputmode="decimal" step="0.01" class="f-ii_cassa" name="turno[<?= $n ?>][ii_cassa]" value="<?= $h($nv($T['t']['ii_cassa'])) ?>" <?= $ro ?>></div>
          <!-- </details> -->
        </div>
        <div class="panel panel-mini">
          <h3>Refill AWP</h3>
          <div class="field"><label>Totale</label><span class="hint"><?= eur($T['sum_refill']) ?> &middot; <a href="<?= base_url('sala/awp.php') ?>?data=<?= $h($data) ?>">scheda AWP</a></span></div>
        </div>
        <div class="panel panel-mini">
          <h3>Note</h3>
          <textarea id="note<?= $n ?>" class="note" name="turno[<?= $n ?>][note]" rows="2" placeholder="Annotazioni&hellip;" aria-label="Note turno <?= $n ?>" <?= $ro ?>><?= $h($T['t']['note'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Colonna 2: Contanti + Ticket -->
      <div class="entry-col">
        <div class="panel">
          <h3>Contanti</h3>
          <div class="contanti-grid">
          <?php foreach (tagli() as $tg): ?>
            <div class="field"><label>&euro;<?= $tg ?> &times; <input type="number" inputmode="numeric" min="0" step="1" class="pezzi" data-taglio="<?= $tg ?>"
               name="turno[<?= $n ?>][contanti][<?= $tg ?>]" value="<?= $h($T['cont'][$tg] ?? '') ?>" style="width:56px" <?= $ro ?>></label><span class="rr">0,00</span></div>
          <?php endforeach; ?>
          </div>
          <div class="ptot"><span>Totale contanti</span><span class="v o-cont">0,00</span></div>
        </div>
        <div class="panel">
          <h3>Ticket pagati</h3>
          <?php foreach ($fornitori as $f): $fid='t'.$n.'-tk-'.preg_replace('/[^a-z0-9]+/i','-',strtolower($f)); ?>
          <div class="field"><label for="<?= $fid ?>"><?= $h($f) ?></label><input id="<?= $fid ?>" type="number" inputmode="decimal" step="0.01" class="ticket" name="turno[<?= $n ?>][ticket][<?= $f ?>]" value="<?= $h($nv($T['tk'][$f] ?? 0)) ?>" <?= $ro ?>></div>
          <?php endforeach; ?>
          <div class="ptot"><span>Totale ticket</span><span class="v o-ticket">0,00</span></div>
        </div>
      </div>

      <!-- Colonna 3: Scassettamenti VLT (invariato) -->
      <div class="entry-col">
        <div class="panel">
          <h3>Scassettamenti VLT</h3>
          <?php foreach ($byforn as $forn=>$list): $sgid='seg-'.$n.'-'.preg_replace('/[^a-z0-9]+/i','-',strtolower($forn)); ?>
          <div role="group" aria-labelledby="<?= $sgid ?>">
            <div id="<?= $sgid ?>" class="seg">&#9638; <?= $h($forn) ?> <span class="st" data-forn="<?= $h($forn) ?>">0,00</span></div>
            <div class="machgrid">
            <?php foreach ($list as $m): $mid='t'.$n.'-scass-'.(int)$m['id']; ?>
            <div class="machrow"><label for="<?= $mid ?>"><?= $h($m['codice']) ?></label>
              <input id="<?= $mid ?>" type="number" inputmode="decimal" step="0.01" class="scass" data-forn="<?= $h($m['fornitore']) ?>"
                     name="turno[<?= $n ?>][scass][<?= $m['id'] ?>]" value="<?= $h($nv($T['sc'][$m['id']] ?? 0)) ?>" <?= $ro ?>></div>
            <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="ptot"><span>Totale incasso VLT</span><span class="v o-scass">0,00</span></div>
        </div>
      </div>

    </div>
  </section>
<?php
};
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cassa <?= $h($data) ?></title><link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/giornaliero.css') ?>"></head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>
<h1 class="sr-only">Cassa del <?= $h(date('d/m/Y', strtotime($data))) ?></h1>

<div class="stickyhead">

  <div class="sh-top">
    <div class="sh-nav">
      <a href="?data=<?= $prev ?>" aria-label="Giorno precedente">&#9664;</a>
      <input type="date" value="<?= $h($data) ?>" aria-label="Seleziona data" onchange="location='?data='+this.value">
      <a href="?data=<?= $next ?>" aria-label="Giorno successivo">&#9654;</a>
    </div>
    <div class="sh-tabbar">
      <div class="tabs" role="tablist" aria-label="Selezione turno">
        <?php foreach ($turns as $n => $turn): $isLast = ($n === $lastTurn); ?>
        <button type="button" id="tab-<?= $n ?>" role="tab" aria-selected="<?= $isLast ? 'true' : 'false' ?>" aria-controls="turno-<?= $n ?>" class="tab" data-tab="<?= $n ?>" onclick="showTab(<?= $n ?>)"><?= $h($turn['nome']) ?><?php
            if (!empty($turn['inizio']) || !empty($turn['fine'])): ?><span class="tab-hours"><?= $h($turn['inizio'] ?? '') ?>–<?= $h($turn['fine'] ?? '') ?></span><?php endif; ?><small><?php
            $parts = [];
            if ($schedName($n)) $parts[] = 'Assegnato: '.$h($schedName($n));
            if ($ownerName($n) && $ownerName($n) !== $schedName($n)) $parts[] = 'Salvato: '.$h($ownerName($n));
            elseif ($ownerName($n) && !$schedName($n)) $parts[] = 'Salvato: '.$h($ownerName($n));
            echo $parts ? implode(' · ', $parts) : ($isLast ? 'chiusura' : 'controllo');
        ?></small></button>
        <?php endforeach; ?>
      </div>
      <?php if (count($turns) > 1): ?>
      <div class="gp-swipe-hint" aria-hidden="true">
        <?php foreach ($turns as $n => $turn): ?>
        <span class="gp-swipe-dot<?= $n === $lastTurn ? ' active' : '' ?>" data-dot="<?= $n ?>"></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="sh-stats-wrap">
        <div class="sh-stats" aria-label="Dati chiusura turno">
          <span class="ss-item"><span class="ss-l">Fondo</span><span class="ss-v" id="m-fondo">—</span></span>
          <span class="ss-item"><span class="ss-l">Contanti</span><span class="ss-v" id="m-cont">—</span></span>
          <span class="ss-item"><span class="ss-l">Cassetto</span><span class="ss-v" id="m-cassetto">—</span></span>
          <span class="ss-item"><span class="ss-l">Monete</span><span class="ss-v" id="m-monete">—</span></span>
          <!-- <span class="ss-sep" aria-hidden="true"></span> -->
          <span class="ss-item"><span class="ss-l">Bancomat</span><span class="ss-v" id="g-bancomat">—</span></span>
          <span class="ss-item"><span class="ss-l">Ticket</span><span class="ss-v" id="g-ticket">—</span></span>
          <!-- <span class="ss-sep" aria-hidden="true"></span> -->
          <?php foreach ($fornitori as $f): $fid = 'g-'.preg_replace('/[^a-z0-9]+/i','-',strtolower($f)); ?>
          <span class="ss-item ss-sm"><span class="ss-l"><?= $h($f) ?></span><span class="ss-v" id="<?= $fid ?>">—</span></span>
          <?php endforeach; ?>
          <span class="ss-item"><span class="ss-l">VLT tot.</span><span class="ss-v" id="g-incasso">—</span></span>
        </div>
        <button class="ss-arr ss-arr-l" aria-hidden="true" tabindex="-1">&#8249;</button>
        <button class="ss-arr ss-arr-r" aria-hidden="true" tabindex="-1">&#8250;</button>
      </div>
    </div>
    <div class="sh-actions">
      <span class="badge <?= $chiusa?'closed':'open' ?>"><?= $chiusa?'CHIUSA':'APERTA' ?></span><?php if ($chiusaBy): ?><span class="sh-chiusa-by">chiusa da <?= $h($chiusaBy) ?></span><?php endif; ?>
      <?php if ($anyEdit): ?><button type="submit" form="frm" class="save-btn">Salva</button><?php endif; ?>
      <?php if (!$chiusa && $anyEdit): ?>
        <form method="post" class="actions">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button name="azione" value="chiudi" class="btn-close" onclick="return confirm('Chiudere la giornata?')">Chiudi</button>
        </form>
      <?php endif; ?>
      <?php if ($chiusa && is_responsabile()): ?>
        <form method="post" class="actions">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button name="azione" value="riapri" class="btn-reopen">Riapri</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="sh-hero">
    <div id="hm-scost" class="hero-box hb-scost ok" role="status" aria-live="polite">
      <div class="hb-main">
        <div class="hb-ico" id="v-ico" aria-hidden="true"></div>
        <div class="hb-msg" id="v-big">&mdash;</div>
        <div class="hb-num-wrap">
          <span class="hb-num" id="m-scost">&euro;&nbsp;0,00</span>
          <span class="hb-lbl">Scostamento</span>
        </div>
      </div>
      <div class="hb-formula">
        <span class="hbf-val" id="v-tot">&euro;&nbsp;0,00</span>
        <span class="hbf-eq" id="v-sign" aria-hidden="true">=</span>
        <span class="hbf-val" id="v-fondo">&euro;&nbsp;0,00</span>
        <span class="hbf-lbl">tot. cassa = fondo</span>
      </div>
    </div>
    <div class="hero-box hb-versamento">
      <div class="hb-main">
        <div class="hb-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg></div>
        <div class="hb-num-wrap hb-num-wrap--left">
          <span class="hb-num" id="m-versamento">&euro;&nbsp;0,00</span>
          <span class="hb-lbl">Versamento</span>
        </div>
      </div>
      <div class="hb-formula">
        <span class="hbf-val" id="m-vers-reale">&euro;&nbsp;0,00</span>
        <span class="hbf-lbl">reale: contanti &minus; fondo</span>
      </div>
    </div>
  </div>

</div>
<?php if ($prevDayAlert): ?>
<div class="prev-day-alert" role="alert">
  <svg class="pda-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <span class="pda-text">La giornata del <strong><?= $h(date('d/m/Y', strtotime($prev))) ?></strong> risulta ancora <strong>aperta</strong>.</span>
  <a href="?data=<?= $h($prev) ?>" class="pda-link">Vai e chiudi &rarr;</a>
</div>
<?php endif; ?>
<?php if ($readonly): ?><div class="warn">Giornata chiusa: sola lettura.</div><?php endif; ?>

<form method="post" id="frm">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<input type="hidden" name="salva_turno" id="salva_turno" value="<?= $lastTurn ?>">
<?php foreach (array_reverse(array_keys($turns)) as $n) $render($n); ?>
</form>

<?php if (isset($_GET['ok'])): ?>
<div class="ok">Salvato</div>
<?php endif; ?>

<?php
$gpSuppliers = [];
foreach ($fornitori as $f) {
    $gpSuppliers[preg_replace('/[^a-z0-9]+/i', '-', strtolower($f))] = $f;
}
$gpTurns = [];
foreach ($turns as $n => $t) $gpTurns[] = ['n' => $n, 'nome' => $t['nome']];
?>
<script>
var GP_SUPPLIERS = <?= json_encode($gpSuppliers) ?>;
var GP_LAST_TURN = <?= $lastTurn ?>;
var GP_TURNS     = <?= json_encode($gpTurns) ?>;
</script>
<script src="<?= asset_url('assets/js/giornaliero.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof GP_Tour === 'undefined') return;
  GP_Tour.init([
    { selector: '.tabs',        title: 'Turni del giorno',    body: 'Passa da Mattino a Sera usando i tab. Su mobile puoi anche scorrere con uno swipe.' },
    { selector: '.statusbar',   title: 'Stato in tempo reale', body: 'Cassetto, versamento e scostamento si aggiornano mentre compili i campi.' },
    { selector: '.f-fondo_cassa', title: 'Fondo cassa',       body: 'Inserisci l\'importo presente in cassa all\'inizio del turno.' },
    { selector: '.bills-grid',  title: 'Conta contanti',       body: 'Inserisci i pezzi per ogni taglio. Il totale contanti si calcola automaticamente.' },
    { selector: '.scassettamenti', title: 'Scassettamenti VLT', body: 'Importo prelevato da ogni cassetta VLT durante il turno.' },
    { selector: '.save-btn',    title: 'Salva e chiudi',       body: 'Salva il turno corrente. Dopo aver completato entrambi i turni, chiudi la giornata.' },
  ]);
});
</script>
</body></html>
