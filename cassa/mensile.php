<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$cfg  = config();
$pdo  = db();

$anno = (int)($_GET['anno'] ?? date('Y'));
$mese = (int)($_GET['mese'] ?? date('n'));
if ($mese < 1 || $mese > 12) $mese = (int)date('n');
$ngiorni = (int)date('t', mktime(0,0,0,$mese,1,$anno));

$h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$pct= fn($p,$g) => $g > 0 ? number_format($p/$g*100,1,',','.').'%' : '—';

// cassa per giorno
$righe = []; $tot = ['incasso'=>0,'ticket'=>0,'bancomat'=>0,'versamento'=>0];
for ($d = 1; $d <= $ngiorni; $d++) {
    $data = sprintf('%04d-%02d-%02d', $anno, $mese, $d);
    $r = riepilogo_giornata($pdo, $data);
    $righe[$d] = $r;
    $tot['incasso']    += $r['incasso_vlt'];
    $tot['ticket']     += $r['ticket'];
    $tot['bancomat']   += $r['bancomat'];
    $tot['versamento'] += $r['versamento'];
}
// bet/win per fornitore (mese)
$primo = sprintf('%04d-%02d-01', $anno, $mese);
$ultimo= sprintf('%04d-%02d-%02d', $anno, $mese, $ngiorni);
$bw = ['NOVO'=>['g'=>0,'p'=>0],'INSPIRED'=>['g'=>0,'p'=>0],'SPIELO'=>['g'=>0,'p'=>0]];
$st = $pdo->prepare('SELECT fornitore, SUM(giocato) g, SUM(pagato) p FROM snai_betwin WHERE data BETWEEN ? AND ? GROUP BY fornitore');
$st->execute([$primo,$ultimo]);
foreach ($st as $row) $bw[$row['fornitore']] = ['g'=>(float)$row['g'],'p'=>(float)$row['p']];
$ins = ['NOVO'=>0,'INSPIRED'=>0,'SPIELO'=>0];
foreach ($righe as $r) foreach (fornitori() as $f) $ins[$f] += $r['scass'][$f];
$mesi = nomi_mesi();
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Riepilogo <?= $h($mesi[$mese].' '.$anno) ?></title><link rel="stylesheet" href="<?= base_url('assets/css/core.css') ?>"></head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>
<header class="topbar no-print">
  <div><strong><?= $h($cfg['nome_sala']) ?></strong> · Riepilogo mensile</div>
  <form class="nav" method="get">
    <select name="mese"><?php foreach ($mesi as $k=>$v): ?><option value="<?= $k ?>" <?= $k==$mese?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select>
    <input type="number" name="anno" value="<?= $anno ?>" style="width:90px">
    <button>Mostra</button>
  </form>
  <div>
    <button onclick="window.print()">Stampa / PDF</button>
    <a class="btnlink" href="<?= base_url('utils/export.php') ?>?anno=<?= $anno ?>&mese=<?= $mese ?>">Export CSV</a>
  </div>
</header>

<h2 class="ptitle"><?= $h($cfg['nome_sala']) ?> — <?= $h($mesi[$mese].' '.$anno) ?></h2>

<div class="cols">
<div class="riepilogo">
  <h3>Cassa per giorno</h3>
  <table class="grid">
    <tr><th>G.</th><th class="rt">Incasso VLT</th><th class="rt">Ticket</th><th class="rt">Bancomat</th><th class="rt">Versamento</th></tr>
    <?php for ($d=1;$d<=$ngiorni;$d++): $r=$righe[$d]; ?>
    <tr><td><?= $d ?></td><td class="rt"><?= eur($r['incasso_vlt']) ?></td><td class="rt"><?= eur($r['ticket']) ?></td>
        <td class="rt"><?= eur($r['bancomat']) ?></td><td class="rt"><?= eur($r['versamento']) ?></td></tr>
    <?php endfor; ?>
    <tr class="tot"><td>TOT</td><td class="rt"><?= eur($tot['incasso']) ?></td><td class="rt"><?= eur($tot['ticket']) ?></td>
        <td class="rt"><?= eur($tot['bancomat']) ?></td><td class="rt"><?= eur($tot['versamento']) ?></td></tr>
  </table>
</div>

<div class="riepilogo">
  <h3>Bet/Win SNAI per fornitore</h3>
  <table class="grid">
    <tr><th>Fornitore</th><th class="rt">Giocato</th><th class="rt">Pagato</th><th class="rt">Ricavo</th><th class="rt">Inserito</th><th class="rt">Payout</th></tr>
    <?php $TG=0;$TP=0;$TI=0; foreach (fornitori() as $f): $g=$bw[$f]['g'];$p=$bw[$f]['p'];$TG+=$g;$TP+=$p;$TI+=$ins[$f]; ?>
    <tr><td><?= $f ?></td><td class="rt"><?= eur($g) ?></td><td class="rt"><?= eur($p) ?></td>
        <td class="rt"><?= eur($g-$p) ?></td><td class="rt"><?= eur($ins[$f]) ?></td><td class="rt"><?= $pct($p,$g) ?></td></tr>
    <?php endforeach; ?>
    <tr class="tot"><td>TOTALE</td><td class="rt"><?= eur($TG) ?></td><td class="rt"><?= eur($TP) ?></td>
        <td class="rt"><?= eur($TG-$TP) ?></td><td class="rt"><?= eur($TI) ?></td><td class="rt"><?= $pct($TP,$TG) ?></td></tr>
  </table>
  <h3 style="margin-top:18px">Sintesi cassa</h3>
  <table class="grid">
    <tr><td>Incasso VLT mese</td><td class="rt"><?= eur($tot['incasso']) ?></td></tr>
    <tr><td>Ticket pagati mese</td><td class="rt"><?= eur($tot['ticket']) ?></td></tr>
    <tr><td>Bancomat mese</td><td class="rt"><?= eur($tot['bancomat']) ?></td></tr>
    <tr><td>Versamento mese</td><td class="rt"><?= eur($tot['versamento']) ?></td></tr>
    <tr class="tot"><td>Margine (cassa - ricavo)</td><td class="rt"><?= eur(($tot['bancomat']+$tot['versamento'])-($TG-$TP)) ?></td></tr>
  </table>
</div>
</div>
</body></html>
