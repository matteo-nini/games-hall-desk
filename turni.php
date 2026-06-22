<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib.php';
$user = require_login();
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv   = fn($v) => number_format((float)$v, 2, ',', '.');

/* =========================================================
   POST
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    if ($az === 'prezzi' && is_responsabile()) {
        $pm = is_numeric($_POST['prezzo_mattino'] ?? '') ? abs((float)$_POST['prezzo_mattino']) : null;
        $ps = is_numeric($_POST['prezzo_sera']   ?? '') ? abs((float)$_POST['prezzo_sera'])   : null;
        if ($pm !== null) $pdo->prepare('UPDATE prezzi_turni SET prezzo=? WHERE nome="mattino"')->execute([$pm]);
        if ($ps !== null) $pdo->prepare('UPDATE prezzi_turni SET prezzo=? WHERE nome="sera"')->execute([$ps]);
        audit('prezzi_turni_aggiornati', null, null, "mattino=$pm sera=$ps");
        header('Location: turni.php?ok=1'); exit;
    }

    if ($az === 'programma' && is_responsabile()) {
        $data = $_POST['data'] ?? '';
        $n    = (int)($_POST['numero'] ?? 0);
        $oid  = (int)($_POST['operatore_id'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !in_array($n, [1,2]) || $oid <= 0) {
            header('Location: turni.php?err=1'); exit;
        }
        $pdo->prepare(
            'INSERT INTO turni_programmati (data, numero, operatore_id, creato_da)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE operatore_id=VALUES(operatore_id), creato_da=VALUES(creato_da), creato_il=NOW()'
        )->execute([$data, $n, $oid, $user['id']]);
        audit('turno_programmato', 'turni_programmati', null, "data=$data n=$n op=$oid");
        header('Location: turni.php?ok=1'); exit;
    }

    if ($az === 'rimuovi' && is_responsabile()) {
        $data = $_POST['data'] ?? '';
        $n    = (int)($_POST['numero'] ?? 0);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && in_array($n, [1,2])) {
            $pdo->prepare('DELETE FROM turni_programmati WHERE data=? AND numero=?')->execute([$data, $n]);
            audit('turno_rimosso', 'turni_programmati', null, "data=$data n=$n");
        }
        header('Location: turni.php?ok=1'); exit;
    }

    if ($az === 'inizia' && !is_responsabile()) {
        $n    = (int)($_POST['numero'] ?? 0);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !in_array($n, [1,2])) {
            header('Location: turni.php?err=1'); exit;
        }
        $g = ensure_giornata($pdo, $data);
        $t = ensure_turno($pdo, (int)$g['id'], $n);
        $pdo->prepare('UPDATE turni SET operatore_id=?, iniziato_il=NOW() WHERE id=?')
            ->execute([(int)$user['id'], (int)$t['id']]);
        audit('inizio_turno', 'turni', (int)$t['id'], "turno=$n data=$data");
        header('Location: turni.php?ok=1'); exit;
    }

    header('Location: turni.php'); exit;
}

/* =========================================================
   GET — parametri mese/anno
   ========================================================= */
$oggi = date('Y-m-d');
$anno = (int)($_GET['anno'] ?? date('Y'));
$mese = (int)($_GET['mese'] ?? date('n'));
if ($mese < 1)  { $mese = 12; $anno--; }
if ($mese > 12) { $mese = 1;  $anno++; }
$anno = max(2020, min(2040, $anno));

$prevMese = $mese === 1  ? ['anno' => $anno-1, 'mese' => 12] : ['anno' => $anno, 'mese' => $mese-1];
$nextMese = $mese === 12 ? ['anno' => $anno+1, 'mese' => 1]  : ['anno' => $anno, 'mese' => $mese+1];
$primoGiorno  = sprintf('%04d-%02d-01', $anno, $mese);
$ultimoGiorno = date('Y-m-t', strtotime($primoGiorno));
$giorniMese   = (int)date('t', strtotime($primoGiorno));
$nomiMesi     = nomi_mesi();

/* =========================================================
   GET — verifica migration (tutte e tre le dipendenze)
   ========================================================= */
$migrationOk = false;
try {
    $pdo->query('SELECT 1 FROM turni_programmati LIMIT 0');
    $pdo->query('SELECT 1 FROM prezzi_turni LIMIT 0');
    $pdo->query('SELECT iniziato_il FROM turni LIMIT 0');
    $migrationOk = true;
} catch (PDOException $e) {
    /* una o più tabelle/colonne mancanti */
}

/* =========================================================
   GET — dati (solo se migration ok)
   ========================================================= */
$uid           = (int)$user['id'];
$calendario    = [];   /* [data][numero] => row */
$operatori     = [];
$prezzoMattino = 60.0;
$prezzoSera    = 70.0;
$miei_turni    = [];
$guadagnato    = 0.0;
$previsto      = 0.0;
$nCorrente     = null; /* 1=mattino, 2=sera, null=fuori orario */
$turniOggi     = [];   /* [1|2] => row – assegnazioni di oggi */

if ($migrationOk) {

    /* Turni del mese visualizzato */
    $st = $pdo->prepare(
        'SELECT tp.data, tp.numero, tp.operatore_id,
                COALESCE(NULLIF(u.nome,""), u.username) AS nome
         FROM turni_programmati tp
         JOIN utenti u ON u.id = tp.operatore_id
         WHERE tp.data BETWEEN ? AND ?'
    );
    $st->execute([$primoGiorno, $ultimoGiorno]);
    foreach ($st as $r) $calendario[$r['data']][(int)$r['numero']] = $r;

    /* Operatori attivi (per form assegnazione responsabile) */
    $operatori = $pdo->query(
        'SELECT id, COALESCE(NULLIF(nome,""), username) AS nome
         FROM utenti WHERE attivo=1 ORDER BY nome'
    )->fetchAll();

    /* Prezzi correnti */
    foreach ($pdo->query('SELECT nome, prezzo FROM prezzi_turni') as $r) {
        if ($r['nome'] === 'mattino') $prezzoMattino = (float)$r['prezzo'];
        elseif ($r['nome'] === 'sera') $prezzoSera   = (float)$r['prezzo'];
    }

    /* I turni programmati dell'utente corrente (ultimi 3 mesi + futuri) */
    $st = $pdo->prepare(
        'SELECT tp.data, tp.numero, pt.prezzo
         FROM turni_programmati tp
         JOIN prezzi_turni pt ON pt.nome = CASE WHEN tp.numero=1 THEN "mattino" ELSE "sera" END
         WHERE tp.operatore_id = ?
           AND tp.data >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
         ORDER BY tp.data, tp.numero'
    );
    $st->execute([$uid]);
    $miei_turni = $st->fetchAll();

    foreach ($miei_turni as $mt) {
        if ($mt['data'] <= $oggi) $guadagnato += (float)$mt['prezzo'];
        else                      $previsto   += (float)$mt['prezzo'];
    }

    /* Turno corrente in base all'orario */
    $ora = (int)date('G');
    $nCorrente = ($ora >= 13 && $ora < 19) ? 1
               : (($ora >= 19 || $ora < 2) ? 2 : null);

    /* Assegnazioni di oggi (indipendenti dal mese visualizzato) */
    $st = $pdo->prepare(
        'SELECT tp.numero, tp.operatore_id,
                COALESCE(NULLIF(u.nome,""), u.username) AS nome
         FROM turni_programmati tp
         JOIN utenti u ON u.id = tp.operatore_id
         WHERE tp.data = ?'
    );
    $st->execute([$oggi]);
    foreach ($st as $r) $turniOggi[(int)$r['numero']] = $r;

} /* end if migrationOk */

/* =========================================================
   HTML
   ========================================================= */
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Turni — <?= $h($nomiMesi[$mese]) ?> <?= $anno ?></title>
<link rel="stylesheet" href="styles.css">
</head><body>
<?php require __DIR__ . '/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div><strong>Turni operatori</strong></div>
</header>

<?php if (isset($_GET['ok'])): ?><div class="ok">Salvato.</div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="warn">Compilare tutti i campi.</div><?php endif; ?>

<?php if (!$migrationOk): ?>
<div class="warn" style="margin:16px 24px;padding:14px 18px;border-radius:var(--r);font-size:13px;line-height:1.5">
  <strong>Setup incompleto.</strong> Eseguire <code>sql/004_turni_programmati.sql</code> sul database (phpMyAdmin o CLI MySQL) per attivare questa funzione.
</div>
<?php else: /* ===== migration ok — contenuto principale ===== */ ?>

<!-- ===== CARD "INIZIA TURNO" (solo operatori) ===== -->
<?php if (!is_responsabile()):
    $assegnatoA  = null;
    if ($nCorrente !== null && isset($turniOggi[$nCorrente])) {
        $assegnatoA = (int)$turniOggi[$nCorrente]['operatore_id'] === $uid
            ? 'me'
            : $turniOggi[$nCorrente]['nome'];
    }
    $labelTurno = $nCorrente === 1 ? 'Mattino (13:00 – 19:00)'
                : ($nCorrente === 2 ? 'Sera (19:00 – 01:00)' : null);

    /* Stato reale dal giornaliero (turno già iniziato oggi?) */
    $turnoGiornaliero = false;
    if ($nCorrente !== null) {
        $st = $pdo->prepare(
            'SELECT t.operatore_id, t.iniziato_il,
                    COALESCE(NULLIF(u.nome,""),u.username) AS nomeop
             FROM turni t
             JOIN giornate g ON g.id = t.giornata_id
             LEFT JOIN utenti u ON u.id = t.operatore_id
             WHERE g.data = ? AND t.numero = ?'
        );
        $st->execute([$oggi, $nCorrente]);
        $turnoGiornaliero = $st->fetch() ?: false;
    }
    $giaIniziato  = $turnoGiornaliero && !empty($turnoGiornaliero['iniziato_il']) && (int)$turnoGiornaliero['operatore_id'] === $uid;
    $altroInCorso = $turnoGiornaliero && !empty($turnoGiornaliero['iniziato_il']) && (int)($turnoGiornaliero['operatore_id'] ?? 0) !== $uid;
?>
<div class="tp-inizia-wrap">
  <?php if ($nCorrente !== null): ?>
  <div class="tp-inizia-card">
    <div class="tp-inizia-info">
      <span class="tp-inizia-label">Turno corrente</span>
      <span class="tp-inizia-turno"><?= $h($labelTurno) ?></span>
      <?php if ($assegnatoA === 'me'): ?>
        <span class="tp-inizia-stato ok-text">Sei assegnato a questo turno</span>
      <?php elseif ($assegnatoA !== null): ?>
        <span class="tp-inizia-stato warn-text">Assegnato a <?= $h($assegnatoA) ?></span>
      <?php else: ?>
        <span class="tp-inizia-stato muted-text">Nessun operatore assegnato</span>
      <?php endif; ?>
    </div>
    <?php if ($giaIniziato): ?>
      <div class="tp-gia-in-corso">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M5 12l5 5L20 7"/></svg>
        Turno iniziato alle <?= $h(date('H:i', strtotime($turnoGiornaliero['iniziato_il']))) ?>
      </div>
    <?php else: ?>
      <button type="button" class="btn-inizia-turno" id="btn-inizia"
              data-n="<?= (int)$nCorrente ?>"
              data-label="<?= $h($labelTurno) ?>"
              data-assegnato="<?= $h($assegnatoA ?? 'nessuno') ?>"
              data-altro-nome="<?= $h($altroInCorso ? ($turnoGiornaliero['nomeop'] ?? '') : '') ?>"
              data-altro="<?= $altroInCorso ? '1' : '0' ?>">
        Inizia turno
      </button>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <p class="tp-fuori-msg">Fuori orario. I turni iniziano alle 13:00 (mattino) o alle 19:00 (sera).</p>
  <?php endif; ?>
</div>

<!-- dialog conferma inizia turno -->
<dialog id="dlg-inizia" class="tp-dialog">
  <form method="post" id="frm-inizia">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="inizia">
    <input type="hidden" name="data"   value="<?= $h($oggi) ?>">
    <input type="hidden" name="numero" id="dlg-numero" value="">
    <div class="tp-dlg-header"><h2>Conferma inizio turno</h2></div>
    <div class="tp-dlg-body"  id="dlg-body"></div>
    <div class="tp-dlg-footer">
      <button type="button" id="dlg-cancel" class="ghost">Annulla</button>
      <button type="submit" class="btn-inizia-confirm">Conferma e inizia</button>
    </div>
  </form>
</dialog>
<?php endif; /* !is_responsabile */ ?>

<!-- ===== PREZZI TURNI (solo responsabile) ===== -->
<?php if (is_responsabile()): ?>
<details class="ticket-new-wrap" style="margin:8px 24px 0">
  <summary class="ticket-new-toggle">Prezzi turni</summary>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="prezzi">
    <div class="tnf-grid">
      <div class="field"><label>Mattino (€)</label>
        <input type="number" step="0.01" min="0" name="prezzo_mattino" value="<?= $h($nv($prezzoMattino)) ?>"></div>
      <div class="field"><label>Sera (€)</label>
        <input type="number" step="0.01" min="0" name="prezzo_sera" value="<?= $h($nv($prezzoSera)) ?>"></div>
    </div>
    <button type="submit">Aggiorna prezzi</button>
  </form>
</details>
<?php endif; ?>

<!-- ===== LAYOUT: calendario + riepilogo ===== -->
<div class="tp-page">

<!-- ===== CALENDARIO ===== -->
<section class="tp-cal-section">
  <div class="tp-cal-nav">
    <a href="?anno=<?= $prevMese['anno'] ?>&mese=<?= $prevMese['mese'] ?>" class="tp-cal-arrow" aria-label="Mese precedente">&#9664;</a>
    <h2 class="tp-cal-title"><?= $h($nomiMesi[$mese]) ?> <?= $anno ?></h2>
    <a href="?anno=<?= $nextMese['anno'] ?>&mese=<?= $nextMese['mese'] ?>" class="tp-cal-arrow" aria-label="Mese successivo">&#9654;</a>
  </div>

  <div class="tp-cal-grid">
    <?php foreach (['Lun','Mar','Mer','Gio','Ven','Sab','Dom'] as $dow): ?>
    <div class="tp-cal-dow"><?= $dow ?></div>
    <?php endforeach; ?>

    <?php
    /* celle vuote prima del 1° del mese */
    $offset = (int)date('N', strtotime($primoGiorno)) - 1;
    for ($i = 0; $i < $offset; $i++): ?>
      <div class="tp-cal-cell tp-cal-empty"></div>
    <?php endfor; ?>

    <?php for ($g = 1; $g <= $giorniMese; $g++):
        $dc      = sprintf('%04d-%02d-%02d', $anno, $mese, $g);
        $isToday = $dc === $oggi;
        $isPast  = $dc < $oggi;
        $slotM   = $calendario[$dc][1] ?? null;
        $slotS   = $calendario[$dc][2] ?? null;
    ?>
    <div class="tp-cal-cell <?= $isToday ? 'tp-oggi' : '' ?> <?= $isPast ? 'tp-passato' : '' ?>">
      <div class="tp-cal-day">
        <?= $g ?>
        <?php if ($isToday): ?><span class="tp-oggi-badge">oggi</span><?php endif; ?>
      </div>

      <!-- Slot mattino -->
      <div class="tp-slot <?= $slotM ? ($slotM['operatore_id'] == $uid ? 'tp-slot-mine' : 'tp-slot-other') : 'tp-slot-empty' ?>" title="Mattino 13:00–19:00">
        <span class="tp-slot-icon">☀</span>
        <?php if ($slotM): ?>
          <span class="tp-slot-name"><?= $h($slotM['nome']) ?></span>
          <?php if (is_responsabile()): ?>
          <form method="post" class="tp-del-form">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="rimuovi">
            <input type="hidden" name="data"   value="<?= $h($dc) ?>">
            <input type="hidden" name="numero" value="1">
            <button type="submit" class="tp-slot-del" title="Rimuovi">&times;</button>
          </form>
          <?php endif; ?>
        <?php elseif (is_responsabile()): ?>
          <button type="button" class="tp-slot-add"
                  data-data="<?= $h($dc) ?>" data-n="1"
                  data-label="Mattino <?= $g ?>/<?= $mese ?>">+</button>
        <?php else: ?>
          <span class="tp-slot-vuoto">—</span>
        <?php endif; ?>
      </div>

      <!-- Slot sera -->
      <div class="tp-slot <?= $slotS ? ($slotS['operatore_id'] == $uid ? 'tp-slot-mine' : 'tp-slot-other') : 'tp-slot-empty' ?>" title="Sera 19:00–01:00">
        <span class="tp-slot-icon">🌙</span>
        <?php if ($slotS): ?>
          <span class="tp-slot-name"><?= $h($slotS['nome']) ?></span>
          <?php if (is_responsabile()): ?>
          <form method="post" class="tp-del-form">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="rimuovi">
            <input type="hidden" name="data"   value="<?= $h($dc) ?>">
            <input type="hidden" name="numero" value="2">
            <button type="submit" class="tp-slot-del" title="Rimuovi">&times;</button>
          </form>
          <?php endif; ?>
        <?php elseif (is_responsabile()): ?>
          <button type="button" class="tp-slot-add"
                  data-data="<?= $h($dc) ?>" data-n="2"
                  data-label="Sera <?= $g ?>/<?= $mese ?>">+</button>
        <?php else: ?>
          <span class="tp-slot-vuoto">—</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endfor; ?>
  </div>

  <div class="tp-legenda">
    <span class="tp-legenda-item tp-slot-mine">I miei turni</span>
    <span class="tp-legenda-item tp-slot-other">Altri operatori</span>
    <span class="tp-legenda-item tp-slot-empty">Non assegnato</span>
    <span class="tp-legenda-price">Mattino <?= $h($nv($prezzoMattino)) ?> € · Sera <?= $h($nv($prezzoSera)) ?> €</span>
  </div>
</section>

<!-- ===== RIEPILOGO GUADAGNI ===== -->
<section class="tp-summary-section">
  <h3 class="tp-summary-title">I miei turni</h3>

  <div class="tp-summary-totals">
    <div class="tp-stot">
      <span class="tp-stot-lbl">Guadagnato</span>
      <span class="tp-stot-val"><?= $h($nv($guadagnato)) ?> €</span>
      <span class="tp-stot-sub">ultimi 3 mesi</span>
    </div>
    <div class="tp-stot">
      <span class="tp-stot-lbl">Previsto</span>
      <span class="tp-stot-val tp-stot-preview"><?= $h($nv($previsto)) ?> €</span>
      <span class="tp-stot-sub">turni futuri</span>
    </div>
  </div>

  <?php
    $passati = array_values(array_filter($miei_turni, fn($t) => $t['data'] <= $oggi));
    $futuri  = array_values(array_filter($miei_turni, fn($t) => $t['data'] >  $oggi));
    $labels  = [1 => 'Mattino', 2 => 'Sera'];
  ?>

  <?php if ($passati): ?>
  <div class="tp-summary-group">
    <div class="tp-summary-group-label">Turni effettuati</div>
    <div class="recent-list">
    <?php foreach (array_reverse($passati) as $mt):
        $n = (int)$mt['numero']; ?>
      <div class="recent-row">
        <span class="recent-date"><?= $h(date('d/m', strtotime($mt['data']))) ?></span>
        <span class="tp-tipo-badge tp-tipo-<?= $n === 1 ? 'matt' : 'sera' ?>"><?= $labels[$n] ?></span>
        <span class="muted-text" style="font-size:12px"><?= $n === 1 ? '13:00–19:00' : '19:00–01:00' ?></span>
        <span class="tp-earn"><?= $h($nv($mt['prezzo'])) ?> €</span>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($futuri): ?>
  <div class="tp-summary-group" style="margin-top:12px">
    <div class="tp-summary-group-label">Prossimi turni</div>
    <div class="recent-list">
    <?php foreach ($futuri as $mt):
        $n = (int)$mt['numero']; ?>
      <div class="recent-row">
        <span class="recent-date"><?= $h(date('d/m', strtotime($mt['data']))) ?></span>
        <span class="tp-tipo-badge tp-tipo-<?= $n === 1 ? 'matt' : 'sera' ?>"><?= $labels[$n] ?></span>
        <span class="muted-text" style="font-size:12px"><?= $n === 1 ? '13:00–19:00' : '19:00–01:00' ?></span>
        <span class="tp-earn tp-earn-preview"><?= $h($nv($mt['prezzo'])) ?> €</span>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($miei_turni)): ?>
  <p class="ticket-empty" style="margin-top:8px">Nessun turno nei prossimi 3 mesi.</p>
  <?php endif; ?>
</section>

</div><!-- /.tp-page -->

<!-- ===== DIALOG ASSEGNA (solo responsabile) ===== -->
<?php if (is_responsabile()): ?>
<dialog id="dlg-assegna" class="tp-dialog">
  <form method="post" id="frm-assegna">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="programma">
    <input type="hidden" name="data"   id="ass-data"   value="">
    <input type="hidden" name="numero" id="ass-numero" value="">
    <div class="tp-dlg-header"><h2 id="ass-title">Assegna turno</h2></div>
    <div class="tp-dlg-body">
      <div class="field">
        <label for="ass-op">Operatore</label>
        <select name="operatore_id" id="ass-op">
          <option value="">— seleziona —</option>
          <?php foreach ($operatori as $op): ?>
          <option value="<?= (int)$op['id'] ?>"><?= $h($op['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="tp-dlg-footer">
      <button type="button" id="ass-cancel" class="ghost">Annulla</button>
      <button type="submit">Assegna</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<script>
(function () {
  /* --- dialog inizia turno (operatore) --- */
  var btnI  = document.getElementById('btn-inizia');
  var dlgI  = document.getElementById('dlg-inizia');
  if (btnI && dlgI) {
    btnI.addEventListener('click', function () {
      var n         = this.dataset.n;
      var label     = this.dataset.label;
      var assegnato = this.dataset.assegnato;
      var altroNome = this.dataset.altroNome;
      var altro     = this.dataset.altro === '1';
      document.getElementById('dlg-numero').value = n;
      var html = '<p class="tp-dlg-turno"><strong>' + label + '</strong></p>';
      if (assegnato === 'me')
        html += '<p class="tp-dlg-ok">Sei assegnato a questo turno nel calendario.</p>';
      else if (assegnato === 'nessuno')
        html += '<p class="tp-dlg-warn">Non sei nel calendario per questo turno, ma puoi comunque iniziarlo.</p>';
      else
        html += '<p class="tp-dlg-warn">Il turno è assegnato a <strong>' + assegnato + '</strong>. Procedendo, risulterai tu come operatore.</p>';
      if (altro && altroNome)
        html += '<p class="tp-dlg-danger"><strong>Attenzione:</strong> ' + altroNome + ' ha già iniziato un turno oggi.</p>';
      document.getElementById('dlg-body').innerHTML = html;
      dlgI.showModal();
    });
    document.getElementById('dlg-cancel').addEventListener('click', function () { dlgI.close(); });
    dlgI.addEventListener('click', function (e) { if (e.target === dlgI) dlgI.close(); });
  }

  /* --- dialog assegna turno (responsabile) --- */
  var dlgA = document.getElementById('dlg-assegna');
  if (dlgA) {
    document.querySelectorAll('.tp-slot-add').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('ass-data').value   = this.dataset.data;
        document.getElementById('ass-numero').value = this.dataset.n;
        document.getElementById('ass-title').textContent = 'Assegna — ' + this.dataset.label;
        document.getElementById('ass-op').value = '';
        dlgA.showModal();
      });
    });
    document.getElementById('ass-cancel').addEventListener('click', function () { dlgA.close(); });
    dlgA.addEventListener('click', function (e) { if (e.target === dlgA) dlgA.close(); });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    [dlgI, dlgA].forEach(function (d) { if (d && d.open) d.close(); });
  });
}());
</script>

<?php endif; /* migrationOk */ ?>
</body></html>
