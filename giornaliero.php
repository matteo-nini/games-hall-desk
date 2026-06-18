<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib.php';
$user = require_login();
$cfg  = config();
$pdo  = db();
$TOL  = (float)($cfg['tolleranza'] ?? 5);

$data = $_GET['data'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) $data = date('Y-m-d');

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

    $in = $_POST['turno'] ?? [];
    $st_n = (int)($_POST['salva_turno'] ?? 2);
    $salva_n = is_responsabile() ? [1,2] : (in_array($st_n,[1,2]) ? [$st_n] : [2]);
    try {
        $pdo->beginTransaction();
        foreach ($salva_n as $n) {
            $t = ensure_turno($pdo, (int)$g['id'], $n); $tid = (int)$t['id']; $d = $in[$n] ?? [];
            $own = $t['operatore_id'];
            $puoi = is_responsabile() || $own === null || (int)$own === (int)$user['id'];
            if (!$puoi) continue; // non modificare il turno di un altro operatore
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
            foreach (fornitori() as $f) { $imp=$num($d['ticket'][$f]??0); if ($imp!=0.0) $stt->execute([$tid,$f,$imp]); }
            if ($own === null && $user['ruolo'] === 'operatore')
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
foreach ([1,2] as $n) {
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
$users = [];
foreach ($pdo->query('SELECT id, COALESCE(NULLIF(nome,""),username) nome FROM utenti') as $u) $users[(int)$u['id']] = $u['nome'];
$canEdit = [];
foreach ([1,2] as $n) {
    $own = $turni[$n]['t']['operatore_id'];
    $canEdit[$n] = (is_responsabile() || $own === null || (int)$own === (int)$user['id']) && !($chiusa && !is_responsabile());
}
$anyEdit = $canEdit[1] || $canEdit[2];
$ownerName = fn($n) => ($turni[$n]['t']['operatore_id'] ? ($users[(int)$turni[$n]['t']['operatore_id']] ?? '—') : null);
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv = fn($v) => ($v == 0 ? '' : rtrim(rtrim(number_format((float)$v,2,'.',''),'0'),'.'));
$prev = date('Y-m-d', strtotime("$data -1 day"));
$next = date('Y-m-d', strtotime("$data +1 day"));

/* ---------------- render schede input di un turno ---------------- */
$render = function($n) use ($h,$nv,$byforn,$turni,$TOL,$data,$canEdit,$ownerName) {
    $T = $turni[$n]; $ro = $canEdit[$n] ? '' : 'disabled'; $hide = $n===1 ? '1' : '0';
?>
  <section class="turno" id="turno-<?= $n ?>" role="tabpanel" aria-labelledby="tab-<?= $n ?>" data-turno="<?= $n ?>" data-hidden="<?= $hide ?>" data-refill="<?= $h($T['sum_refill']) ?>" data-tol="<?= $h($TOL) ?>">
    <div class="entry">
      <div class="panel cashpanel">
        <h3>Cassa &amp; ticket</h3>

        <div role="group" aria-labelledby="seg-valori-<?= $n ?>">
        <div id="seg-valori-<?= $n ?>" class="seg">Valori cassa</div>
        <div class="field"><label for="t<?= $n ?>-fondo">Fondo cassa</label><input id="t<?= $n ?>-fondo" type="number" inputmode="decimal" step="0.01" class="f-fondo_cassa" name="turno[<?= $n ?>][fondo_cassa]" value="<?= $h($nv($T['t']['fondo_cassa'])) ?>" <?= $ro ?>></div>
        <div class="field"><label for="t<?= $n ?>-monete">Monete</label><input id="t<?= $n ?>-monete" type="number" inputmode="decimal" step="0.01" class="f-monete" name="turno[<?= $n ?>][monete]" value="<?= $h($nv($T['t']['monete'])) ?>" <?= $ro ?>></div>
        <div class="field"><label for="t<?= $n ?>-bancomat">Bancomat</label><input id="t<?= $n ?>-bancomat" type="number" inputmode="decimal" step="0.01" class="f-bancomat" name="turno[<?= $n ?>][bancomat]" value="<?= $h($nv($T['t']['bancomat'])) ?>" <?= $ro ?>></div>
        <details class="adv"><summary>Rettifiche</summary>
          <div class="field"><label for="t<?= $n ?>-differenze">Differenze</label><input id="t<?= $n ?>-differenze" type="number" inputmode="decimal" step="0.01" class="f-differenze" name="turno[<?= $n ?>][differenze]" value="<?= $h($nv($T['t']['differenze'])) ?>" <?= $ro ?>></div>
          <div class="field"><label for="t<?= $n ?>-ii">II cassa</label><input id="t<?= $n ?>-ii" type="number" inputmode="decimal" step="0.01" class="f-ii_cassa" name="turno[<?= $n ?>][ii_cassa]" value="<?= $h($nv($T['t']['ii_cassa'])) ?>" <?= $ro ?>></div>
          <div class="field"><label for="t<?= $n ?>-rientri">Rientri</label><input id="t<?= $n ?>-rientri" type="number" inputmode="decimal" step="0.01" class="f-rientri" name="turno[<?= $n ?>][rientri]" value="<?= $h($nv($T['t']['rientri'])) ?>" <?= $ro ?>></div>
        </details>
        </div>

        <div role="group" aria-labelledby="seg-contanti-<?= $n ?>">
        <div id="seg-contanti-<?= $n ?>" class="seg">Contanti</div>
        <?php foreach (tagli() as $tg): ?>
        <div class="field"><label>&euro;<?= $tg ?> &times; <input type="number" inputmode="numeric" min="0" step="1" class="pezzi" data-taglio="<?= $tg ?>"
             name="turno[<?= $n ?>][contanti][<?= $tg ?>]" value="<?= $h($T['cont'][$tg] ?? '') ?>" style="width:64px" <?= $ro ?>></label>
          <span class="rr">0,00</span></div>
        <?php endforeach; ?>
        <div class="ptot"><span>Totale contanti</span><span class="v o-cont">0,00</span></div>
        </div>

        <div role="group" aria-labelledby="seg-ticket-<?= $n ?>">
        <div id="seg-ticket-<?= $n ?>" class="seg">Ticket pagati</div>
        <?php foreach (fornitori() as $f): $fid='t'.$n.'-tk-'.preg_replace('/[^a-z0-9]+/i','-',strtolower($f)); ?>
        <div class="field"><label for="<?= $fid ?>"><?= $h($f) ?></label><input id="<?= $fid ?>" type="number" inputmode="decimal" step="0.01" class="ticket" name="turno[<?= $n ?>][ticket][<?= $f ?>]" value="<?= $h($nv($T['tk'][$f] ?? 0)) ?>" <?= $ro ?>></div>
        <?php endforeach; ?>
        <div class="ptot"><span>Totale ticket</span><span class="v o-ticket">0,00</span></div>
        </div>

        <div class="seg">Refill AWP</div>
        <div class="field"><label>Totale refill</label><span class="hint"><?= eur($T['sum_refill']) ?> &middot; <a href="awp.php?data=<?= $h($data) ?>">scheda AWP</a></span></div>
      </div>

      <div class="panel vltpanel">
        <h3>Scassettamenti VLT</h3>
        <?php foreach ($byforn as $forn=>$list): $sgid='seg-'.$n.'-'.preg_replace('/[^a-z0-9]+/i','-',strtolower($forn)); ?>
        <div role="group" aria-labelledby="<?= $sgid ?>">
        <div id="<?= $sgid ?>" class="seg">&#9638; <?= $h($forn) ?> <span class="st" data-forn="<?= $h($forn) ?>">0,00</span></div>
        <?php foreach ($list as $m): $mid='t'.$n.'-scass-'.(int)$m['id']; ?>
        <div class="machrow"><label for="<?= $mid ?>"><?= $h($m['codice']) ?></label>
          <input id="<?= $mid ?>" type="number" inputmode="decimal" step="0.01" class="scass" data-forn="<?= $h($m['fornitore']) ?>"
                 name="turno[<?= $n ?>][scass][<?= $m['id'] ?>]" value="<?= $h($nv($T['sc'][$m['id']] ?? 0)) ?>" <?= $ro ?>></div>
        <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <div class="ptot"><span>Totale incasso VLT</span><span class="v o-scass">0,00</span></div>
      </div>
    </div>

    <div class="panel note-panel">
      <label class="notelbl" for="note<?= $n ?>">Note turno</label>
      <textarea id="note<?= $n ?>" class="note" name="turno[<?= $n ?>][note]" rows="2" placeholder="Annotazioni del turno: ammanchi, eventi, segnalazioni macchine&hellip;" <?= $ro ?>><?= $h($T['t']['note'] ?? '') ?></textarea>
    </div>
  </section>
<?php
};
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cassa <?= $h($data) ?></title><link rel="stylesheet" href="styles.css"></head><body>
<?php require __DIR__ . '/nav.php'; top_menu($user); ?>
<h1 class="sr-only">Cassa del <?= $h(date('d/m/Y', strtotime($data))) ?></h1>

<div class="stickyhead">

  <div class="sh-datebar">
    <div class="sh-nav">
      <a href="?data=<?= $prev ?>" aria-label="Giorno precedente">&#9664;</a>
      <input type="date" value="<?= $h($data) ?>" aria-label="Seleziona data" onchange="location='?data='+this.value">
      <a href="?data=<?= $next ?>" aria-label="Giorno successivo">&#9654;</a>
    </div>
    <span class="badge <?= $chiusa?'closed':'open' ?>"><?= $chiusa?'CHIUSA':'APERTA' ?></span>
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
      <div class="hb-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg></div>
      <div class="hb-num-wrap hb-num-wrap--left">
        <span class="hb-num" id="m-versamento">&euro;&nbsp;0,00</span>
        <span class="hb-lbl">Versamento</span>
        <span class="hb-sub">arrotondato ai 5&euro;</span>
      </div>
    </div>
  </div>

  <div class="sh-tabbar">
    <div class="tabs" role="tablist" aria-label="Selezione turno">
      <button type="button" id="tab-1" role="tab" aria-selected="false" aria-controls="turno-1" class="tab matt" data-tab="1" onclick="showTab(1)">Mattino<small>controllo<?php if ($ownerName(1)): ?> &middot; <?= $h($ownerName(1)) ?><?php endif; ?></small></button>
      <button type="button" id="tab-2" role="tab" aria-selected="true" aria-controls="turno-2" class="tab sera" data-tab="2" onclick="showTab(2)">Sera<small>chiusura<?php if ($ownerName(2)): ?> &middot; <?= $h($ownerName(2)) ?><?php endif; ?></small></button>
    </div>
    <?php if ($anyEdit): ?><button type="submit" form="frm" class="save-btn">Salva</button><?php endif; ?>
  </div>

</div>
<?php if ($readonly): ?><div class="warn">Giornata chiusa: sola lettura.</div><?php endif; ?>

<div class="day-metrics" aria-label="Riepilogo giornata">
  <div class="dm-main">
    <div class="dm-tile"><span class="dm-l">Cassetto</span><span class="dm-v" id="m-cassetto">0,00</span></div>
    <div class="dm-tile"><span class="dm-l">Bancomat</span><span class="dm-v" id="g-bancomat">0,00</span></div>
    <div class="dm-tile"><span class="dm-l">VLT</span><span class="dm-v" id="g-incasso">0,00</span></div>
    <div class="dm-tile"><span class="dm-l">Ticket</span><span class="dm-v" id="g-ticket">0,00</span></div>
  </div>
  <div class="dm-detail">
    <div class="dm-tile"><span class="dm-l">NOVO</span><span class="dm-v dm-v--sm" id="g-NOVO">0,00</span></div>
    <div class="dm-tile"><span class="dm-l">INSPIRED</span><span class="dm-v dm-v--sm" id="g-INSPIRED">0,00</span></div>
    <div class="dm-tile"><span class="dm-l">SPIELO</span><span class="dm-v dm-v--sm" id="g-SPIELO">0,00</span></div>
  </div>
</div>

<form method="post" id="frm">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<input type="hidden" name="salva_turno" id="salva_turno" value="2">
<?php $render(2); $render(1); ?>
</form>

<?php if (!$chiusa && $anyEdit): ?>
<form method="post" class="actions">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <button name="azione" value="chiudi" class="btn-close" onclick="return confirm('Chiudere la giornata?')">Chiudi giornata</button>
</form>
<?php endif; ?>
<?php if ($chiusa && is_responsabile()): ?>
<form method="post" class="actions">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <button name="azione" value="riapri" class="btn-reopen">Riapri giornata</button>
</form>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
<div id="toast" class="toast" role="alert"><span class="tk">&#10003;</span> Salvato</div>
<?php endif; ?>

<script>
var ICO_OK='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>';
var ICO_WARN='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>';
var ACTIVE=parseInt(localStorage.getItem('gp_tab'))||2, RES={};
function eur(v){v=Math.round(v*100)/100; if(Object.is(v,-0))v=0; return v.toLocaleString('it-IT',{minimumFractionDigits:2,maximumFractionDigits:2});}
function num(el){var v=parseFloat((el.value||'').toString().replace(',','.'));return isNaN(v)?0:v;}
function recalcTurno(sec){
  var cont=0;
  sec.querySelectorAll('.pezzi').forEach(function(p){var t=(+p.dataset.taglio)*(parseInt(p.value)||0);cont+=t;var rr=p.closest('.field').querySelector('.rr');if(rr)rr.textContent=eur(t);});
  var scass=0,forn={};
  sec.querySelectorAll('.scass').forEach(function(s){var v=num(s);scass+=v;forn[s.dataset.forn]=(forn[s.dataset.forn]||0)+v;});
  sec.querySelectorAll('.st').forEach(function(st){st.textContent=eur(forn[st.dataset.forn]||0);});
  var ticket=0;sec.querySelectorAll('.ticket').forEach(function(t){ticket+=num(t);});
  var refill=parseFloat(sec.dataset.refill)||0;
  function f(k){var e=sec.querySelector('.f-'+k);return e?num(e):0;}
  var cassetto=cont+refill+f('differenze')-f('ii_cassa')-f('rientri');
  var versvlt=scass-f('bancomat')-ticket;
  var incassato=cassetto+f('monete')+f('bancomat')+ticket;
  var totale=cassetto+f('monete')-versvlt;
  var fondo=f('fondo_cassa');
  var scost=totale-fondo;
  function set(c,v){var e=sec.querySelector(c);if(e)e.textContent=eur(v);}
  set('.o-cont',cont);set('.o-scass',scass);set('.o-ticket',ticket);
  return {bancomat:f('bancomat'),versamento:versvlt,ticket:ticket,incasso:scass,forn:forn,
          cassetto:cassetto,incassato:incassato,totale:totale,fondo:fondo,scost:scost,
          tol:(parseFloat(sec.dataset.tol)||0)};
}
function updateActive(){
  var r=RES[ACTIVE]; if(!r)return;
  var abs=Math.abs(r.scost), sera=(ACTIVE===2);
  var isGood=abs<4, isMid=abs>=4&&abs<=5, isBad=abs>5;
  var vd=document.getElementById('hm-scost');
  vd.classList.toggle('ok',isGood); vd.classList.toggle('warn',isMid); vd.classList.toggle('bad',isBad);
  document.getElementById('v-ico').innerHTML=isGood?ICO_OK:ICO_WARN;
  document.getElementById('v-big').textContent=sera?(isGood?'I conti tornano':'Scostamento da verificare'):(isGood?'Controllo: torna':'Controllo: scostamento');
  document.getElementById('m-scost').textContent=(r.scost>=0?'+':'')+eur(r.scost);
  document.getElementById('v-tot').textContent='\u20AC\u00A0'+eur(r.totale);
  document.getElementById('v-fondo').textContent='\u20AC\u00A0'+eur(r.fondo);
  document.getElementById('v-sign').textContent=isGood?'=':'\u2260';
  var el;
  el=document.getElementById('m-cassetto'); if(el) el.textContent=eur(r.cassetto);
  el=document.getElementById('m-versamento'); if(el) el.textContent='\u20AC\u00A0'+eur(Math.round(r.versamento/5)*5);
}
function recalcAll(){
  var g={bancomat:0,versamento:0,ticket:0,incasso:0,NOVO:0,INSPIRED:0,SPIELO:0};
  document.querySelectorAll('.turno').forEach(function(sec){
    var n=+sec.dataset.turno, r=recalcTurno(sec); RES[n]=r;
    g.bancomat+=r.bancomat;g.versamento+=r.versamento;g.ticket+=r.ticket;g.incasso+=r.incasso;
    for(var k in r.forn){if(g[k]!==undefined)g[k]+=r.forn[k];}
  });
  for(var k in g){var e=document.getElementById('g-'+k);if(e)e.textContent=eur(g[k]);}
  updateActive();
}
function showTab(n){
  ACTIVE=n;
  localStorage.setItem('gp_tab',n);
  var sf=document.getElementById('salva_turno');if(sf)sf.value=n;
  document.querySelectorAll('.turno').forEach(function(s){var a=+s.dataset.turno===n;s.dataset.hidden=a?'0':'1';s.setAttribute('aria-hidden',a?'false':'true');});
  document.querySelectorAll('.tab[role="tab"]').forEach(function(t){var a=+t.dataset.tab===n;t.classList.toggle('active',a);t.setAttribute('aria-selected',a?'true':'false');});
  updateActive();
}
document.addEventListener('input',function(e){if(e.target.closest('#frm'))recalcAll();});
document.getElementById('frm')?.addEventListener('submit',function(){
  var btn=document.querySelector('.save-btn');
  if(btn){btn.disabled=true;btn.textContent='Salvataggio…';}
});
recalcAll();
showTab(ACTIVE);
var tt=document.getElementById('toast');
if(tt){setTimeout(function(){tt.classList.add('hide');},2600);}
</script>
</body></html>
