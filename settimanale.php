<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib.php';
$user = require_login();
$cfg  = config();
$pdo  = db();

// settimana = 7 giorni a partire da 'inizio'
$inizio = $_GET['inizio'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inizio)) $inizio = date('Y-m-d');
$giorni = [];
for ($i = 0; $i < 7; $i++) $giorni[] = date('Y-m-d', strtotime("$inizio +$i day"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $up = $pdo->prepare('INSERT INTO snai_betwin (data, fornitore, giocato, pagato) VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE giocato=VALUES(giocato), pagato=VALUES(pagato)');
    $num = fn($v) => is_numeric($v) ? (float)$v : 0.0;
    $pdo->beginTransaction();
    foreach (($_POST['bw'] ?? []) as $data => $forn) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) continue;
        foreach (fornitori() as $f) {
            $g = $num($forn[$f]['giocato'] ?? 0);
            $p = $num($forn[$f]['pagato'] ?? 0);
            $up->execute([$data, $f, $g, $p]);
        }
    }
    $pdo->commit();
    audit('salvataggio_settimana', 'snai_betwin', null, $inizio);
    header("Location: settimanale.php?inizio=$inizio&ok=1"); exit;
}

// dati
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv = fn($v) => ($v == 0 ? '' : rtrim(rtrim(number_format((float)$v,2,'.',''),'0'),'.'));
$tot = ['NOVO'=>['g'=>0,'p'=>0,'i'=>0],'INSPIRED'=>['g'=>0,'p'=>0,'i'=>0],'SPIELO'=>['g'=>0,'p'=>0,'i'=>0]];
$tot_banc = 0; $tot_vers = 0;
$rows = [];
foreach ($giorni as $d) {
    $bw = betwin_giorno($pdo, $d);
    $ri = riepilogo_giornata($pdo, $d);
    $rows[$d] = ['bw'=>$bw,'ri'=>$ri];
    foreach (fornitori() as $f) {
        $tot[$f]['g'] += $bw[$f]['giocato'];
        $tot[$f]['p'] += $bw[$f]['pagato'];
        $tot[$f]['i'] += $ri['scass'][$f];
    }
    $tot_banc += $ri['bancomat']; $tot_vers += $ri['versamento'];
}
$prev = date('Y-m-d', strtotime("$inizio -7 day"));
$next = date('Y-m-d', strtotime("$inizio +7 day"));
$tg = array_sum(array_column($tot,'g')); $tp = array_sum(array_column($tot,'p'));
$pct = fn($p,$g) => $g > 0 ? number_format($p/$g*100,1,',','.').'%' : '—';
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settimanale GP</title><link rel="stylesheet" href="styles.css"></head><body>
<?php require __DIR__ . '/nav.php'; top_menu($user); ?>
<header class="topbar">
  <div><strong><?= $h($cfg['nome_sala']) ?></strong> · Bet/Win SNAI (settimana)</div>
  <div class="nav">
    <a href="?inizio=<?= $prev ?>">◀ 7gg</a>
    <input type="date" value="<?= $h($inizio) ?>" onchange="location='?inizio='+this.value">
    <a href="?inizio=<?= $next ?>">7gg ▶</a>
  </div>
  <div><?= $h($giorni[0]) ?> → <?= $h($giorni[6]) ?></div>
</header>
<?php if (isset($_GET['ok'])): ?><div class="ok">Salvato.</div><?php endif; ?>

<form method="post">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<div class="turni">
<?php foreach ($giorni as $d): $bw=$rows[$d]['bw']; $ri=$rows[$d]['ri'];
    $dg=0;$dp=0; foreach(fornitori() as $f){$dg+=$bw[$f]['giocato'];$dp+=$bw[$f]['pagato'];}
    $ricavo=$dg-$dp; $cassa=$ri['bancomat']+$ri['versamento']; $margine=$cassa-$ricavo; ?>
  <section class="turno t1" style="flex-basis:320px">
    <h2><?= $h(date('D d/m', strtotime($d))) ?></h2>
    <table class="grid">
      <tr><th>Fornitore</th><th>Giocato</th><th>Pagato</th><th>Inserito</th><th>Payout</th></tr>
      <?php foreach (fornitori() as $f): $g=$bw[$f]['giocato'];$p=$bw[$f]['pagato'];$ins=$ri['scass'][$f]; ?>
      <tr><td><?= $f ?></td>
        <td><input type="number" step="0.01" name="bw[<?= $d ?>][<?= $f ?>][giocato]" value="<?= $h($nv($g)) ?>"></td>
        <td><input type="number" step="0.01" name="bw[<?= $d ?>][<?= $f ?>][pagato]" value="<?= $h($nv($p)) ?>"></td>
        <td class="rt"><?= eur($ins) ?></td>
        <td class="rt"><?= $pct($p,$g) ?></td></tr>
      <?php endforeach; ?>
      <tr class="tot"><td>TOTALE</td><td class="rt"><?= eur($dg) ?></td><td class="rt"><?= eur($dp) ?></td>
          <td class="rt"><?= eur(array_sum($ri['scass'])) ?></td><td class="rt"><?= $pct($dp,$dg) ?></td></tr>
    </table>
    <table class="grid">
      <tr><td>Bancomat (B)</td><td class="rt"><?= eur($ri['bancomat']) ?></td></tr>
      <tr><td>Versamento (C)</td><td class="rt"><?= eur($ri['versamento']) ?></td></tr>
      <tr><td>Ricavo (G-P)</td><td class="rt"><?= eur($ricavo) ?></td></tr>
      <tr class="<?= abs($margine)>0.005?'errore':'tot' ?>"><td>Margine</td><td class="rt"><?= eur($margine) ?></td></tr>
    </table>
  </section>
<?php endforeach; ?>
</div>
<div class="actions"><button type="submit">Salva settimana</button></div>
</form>

<div class="riepilogo" style="max-width:640px">
  <h3>Totali settimana</h3>
  <table class="grid">
    <tr><th>Fornitore</th><th class="rt">Giocato</th><th class="rt">Pagato</th><th class="rt">Inserito</th><th class="rt">Payout</th><th class="rt">G/Ins</th></tr>
    <?php foreach (fornitori() as $f): ?>
    <tr><td><?= $f ?></td><td class="rt"><?= eur($tot[$f]['g']) ?></td><td class="rt"><?= eur($tot[$f]['p']) ?></td>
        <td class="rt"><?= eur($tot[$f]['i']) ?></td><td class="rt"><?= $pct($tot[$f]['p'],$tot[$f]['g']) ?></td>
        <td class="rt"><?= $tot[$f]['i']>0?number_format($tot[$f]['g']/$tot[$f]['i'],2,',','.'):'—' ?></td></tr>
    <?php endforeach; ?>
    <tr class="tot"><td>TOTALE</td><td class="rt"><?= eur($tg) ?></td><td class="rt"><?= eur($tp) ?></td>
        <td class="rt"><?= eur(array_sum(array_column($tot,'i'))) ?></td><td class="rt"><?= $pct($tp,$tg) ?></td><td></td></tr>
    <tr><td>Bancomat settimana</td><td class="rt"><?= eur($tot_banc) ?></td><td colspan="4"></td></tr>
    <tr><td>Versamento settimana</td><td class="rt"><?= eur($tot_vers) ?></td><td colspan="4"></td></tr>
    <tr class="tot"><td>Margine settimana</td><td class="rt"><?= eur($tot_banc+$tot_vers-($tg-$tp)) ?></td><td colspan="4"></td></tr>
  </table>
</div>
</body></html>
