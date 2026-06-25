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

$h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$pct      = fn($p,$g) => $g > 0 ? number_format($p/$g*100,1,',','.').'%' : '—';
$fornitori = get_fornitori($pdo);
$opFiltro = (int)($_GET['op'] ?? 0);
$operatori = $pdo->query("SELECT id, COALESCE(NULLIF(nome,''), username) AS nome FROM utenti WHERE attivo=1 AND ruolo IN ('operatore','responsabile') ORDER BY nome")->fetchAll();

// cassa per giorno
$righe = []; $tot = ['incasso'=>0,'ticket'=>0,'bancomat'=>0,'versamento'=>0];
for ($d = 1; $d <= $ngiorni; $d++) {
    $data = sprintf('%04d-%02d-%02d', $anno, $mese, $d);
    $r = riepilogo_giornata($pdo, $data, $opFiltro);
    $righe[$d] = $r;
    $tot['incasso']    += $r['incasso_vlt'];
    $tot['ticket']     += $r['ticket'];
    $tot['bancomat']   += $r['bancomat'];
    $tot['versamento'] += $r['versamento'];
}
// bet/win per fornitore (mese)
$primo = sprintf('%04d-%02d-01', $anno, $mese);
$ultimo= sprintf('%04d-%02d-%02d', $anno, $mese, $ngiorni);
$bw = array_fill_keys($fornitori, ['g'=>0,'p'=>0]);
$st = $pdo->prepare('SELECT fornitore, SUM(giocato) g, SUM(pagato) p FROM snai_betwin WHERE data BETWEEN ? AND ? GROUP BY fornitore');
$st->execute([$primo,$ultimo]);
foreach ($st as $row) if (isset($bw[$row['fornitore']])) $bw[$row['fornitore']] = ['g'=>(float)$row['g'],'p'=>(float)$row['p']];
$ins = array_fill_keys($fornitori, 0);
foreach ($righe as $r) foreach ($fornitori as $f) $ins[$f] = ($ins[$f] ?? 0) + ($r['scass'][$f] ?? 0);
// delta vs mese precedente
$mesePre = $mese - 1; $annoPre = $anno;
if ($mesePre < 1) { $mesePre = 12; $annoPre--; }
$ngPre = (int)date('t', mktime(0,0,0,$mesePre,1,$annoPre));
$totPre = ['incasso'=>0,'ticket'=>0,'bancomat'=>0,'versamento'=>0];
for ($d = 1; $d <= $ngPre; $d++) {
    $rp = riepilogo_giornata($pdo, sprintf('%04d-%02d-%02d', $annoPre, $mesePre, $d), $opFiltro);
    $totPre['incasso']    += $rp['incasso_vlt'];
    $totPre['ticket']     += $rp['ticket'];
    $totPre['bancomat']   += $rp['bancomat'];
    $totPre['versamento'] += $rp['versamento'];
}
$delta = function(float $cur, float $pre): string {
    if ($pre == 0) return $cur > 0 ? '<span class="delta-pos">+∞</span>' : '—';
    $pct = ($cur - $pre) / abs($pre) * 100;
    $cls = $pct >= 0 ? 'delta-pos' : 'delta-neg';
    return '<span class="' . $cls . '">' . ($pct >= 0 ? '+' : '') . number_format($pct, 1, ',', '.') . '%</span>';
};
// incasso per macchina VLT nel mese
$opJoin  = $opFiltro > 0 ? ' AND t.operatore_id = ?' : '';
$vltArgs = $opFiltro > 0 ? [$opFiltro, $primo, $ultimo] : [$primo, $ultimo];
$stVlt = $pdo->prepare("
    SELECT m.codice, m.fornitore, COALESCE(SUM(s.importo),0) AS tot
    FROM macchine m
    LEFT JOIN scassettamenti s ON s.macchina_id = m.id
    LEFT JOIN turni t ON t.id = s.turno_id{$opJoin}
    LEFT JOIN giornate g ON g.id = t.giornata_id AND g.data BETWEEN ? AND ?
    WHERE m.tipo = 'VLT' AND m.attiva = 1
    GROUP BY m.id, m.codice, m.fornitore
    ORDER BY tot DESC
");
$stVlt->execute($vltArgs);
$vltMacchine = $stVlt->fetchAll();

$mesi = nomi_mesi();
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Riepilogo <?= $h($mesi[$mese].' '.$anno) ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<style>
.delta-row td { font-size:12px; color:var(--muted); padding:4px 8px }
.delta-pos { color:var(--green); font-weight:600 }
.delta-neg { color:var(--red); font-weight:600 }
</style>
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>
<header class="topbar no-print">
  <div><strong><?= $h($cfg['nome_sala']) ?></strong> · Riepilogo mensile</div>
  <form class="nav" method="get">
    <select name="mese"><?php foreach ($mesi as $k=>$v): ?><option value="<?= $k ?>" <?= $k==$mese?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select>
    <input type="number" name="anno" value="<?= $anno ?>" style="width:90px">
    <select name="op">
      <option value="0">Tutti gli operatori</option>
      <?php foreach ($operatori as $op): ?>
      <option value="<?= $op['id'] ?>" <?= $op['id']==$opFiltro?'selected':'' ?>><?= $h($op['nome']) ?></option>
      <?php endforeach; ?>
    </select>
    <button>Mostra</button>
  </form>
  <div class="topbar-actions">
    <a class="topbar-action-btn" href="<?= base_url('utils/export.php') ?>?anno=<?= $anno ?>&mese=<?= $mese ?>">&#8595; CSV</a>
    <button class="topbar-action-btn no-print" onclick="window.print()" type="button">&#128438; Stampa</button>
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
    <tr class="delta-row"><td>vs <?= $h($mesi[$mesePre]) ?></td>
        <td class="rt"><?= $delta($tot['incasso'], $totPre['incasso']) ?></td>
        <td class="rt"><?= $delta($tot['ticket'], $totPre['ticket']) ?></td>
        <td class="rt"><?= $delta($tot['bancomat'], $totPre['bancomat']) ?></td>
        <td class="rt"><?= $delta($tot['versamento'], $totPre['versamento']) ?></td></tr>
  </table>
</div>

<div class="riepilogo">
  <h3>Bet/Win SNAI per fornitore</h3>
  <table class="grid">
    <tr><th>Fornitore</th><th class="rt">Giocato</th><th class="rt">Pagato</th><th class="rt">Ricavo</th><th class="rt">Inserito</th><th class="rt">Payout</th></tr>
    <?php $TG=0;$TP=0;$TI=0; foreach ($fornitori as $f): $g=$bw[$f]['g'];$p=$bw[$f]['p'];$TG+=$g;$TP+=$p;$TI+=$ins[$f]; ?>
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

<?php if ($vltMacchine): $totVlt = array_sum(array_column($vltMacchine, 'tot')); ?>
<div class="cols" style="margin-top:0">
<div class="riepilogo">
  <h3>Incasso VLT per macchina — <?= $h($mesi[$mese].' '.$anno) ?></h3>
  <table class="grid">
    <tr><th>Macchina</th><th>Fornitore</th><th class="rt">Incasso</th><th class="rt">% sul totale</th></tr>
    <?php foreach ($vltMacchine as $mac): ?>
    <tr>
      <td><?= $h($mac['codice']) ?></td>
      <td><?= $h($mac['fornitore']) ?></td>
      <td class="rt"><?= eur((float)$mac['tot']) ?></td>
      <td class="rt"><?= $totVlt > 0 ? number_format((float)$mac['tot'] / $totVlt * 100, 1, ',', '.') . '%' : '—' ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="tot"><td colspan="2">TOTALE</td><td class="rt"><?= eur($totVlt) ?></td><td class="rt">100%</td></tr>
  </table>
</div>
</div>
<?php endif; ?>
</body></html>
