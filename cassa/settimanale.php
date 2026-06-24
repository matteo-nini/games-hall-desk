<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$cfg  = config();
$pdo  = db();

/* =========================================================
   Parametri: mese/anno/settimana
   ========================================================= */
$anno = (int)($_GET['anno'] ?? date('Y'));
$mese = (int)($_GET['mese'] ?? date('n'));
if ($mese < 1)  { $mese = 12; $anno--; }
if ($mese > 12) { $mese = 1;  $anno++; }
$anno = max(2020, min(2040, $anno));

$tsM1        = mktime(0, 0, 0, $mese, 1, $anno);
$giorniMese  = (int)date('t', $tsM1);
$numSettimane = (int)ceil($giorniMese / 7);

$sett = (int)($_GET['sett'] ?? ceil((int)date('j') / 7));
$sett = max(1, min($numSettimane, $sett));

/* Giorni della settimana selezionata */
$dayStart = ($sett - 1) * 7 + 1;
$dayEnd   = min($sett * 7, $giorniMese);
$giorni   = [];
for ($d = $dayStart; $d <= $dayEnd; $d++) {
    $giorni[] = date('Y-m-d', mktime(0, 0, 0, $mese, $d, $anno));
}

/* Navigazione prev/next settimana e mese */
$prevSett = $sett > 1
    ? ['anno' => $anno, 'mese' => $mese, 'sett' => $sett - 1]
    : ($mese > 1
        ? ['anno' => $anno,   'mese' => $mese - 1, 'sett' => (int)ceil(date('t', mktime(0,0,0,$mese-1,1,$anno)) / 7)]
        : ['anno' => $anno-1, 'mese' => 12,        'sett' => (int)ceil(date('t', mktime(0,0,0,12,1,$anno-1)) / 7)]);

$nextSett = $sett < $numSettimane
    ? ['anno' => $anno, 'mese' => $mese, 'sett' => $sett + 1]
    : ($mese < 12
        ? ['anno' => $anno,   'mese' => $mese + 1, 'sett' => 1]
        : ['anno' => $anno+1, 'mese' => 1,         'sett' => 1]);

$fornitori   = get_fornitori($pdo);
$nomiMesi    = nomi_mesi();
$nomiGiorniBr = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];

/* =========================================================
   POST — salva dati bet/win
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_revisore()) {
    check_csrf();
    $up = $pdo->prepare('INSERT INTO snai_betwin (data, fornitore, giocato, pagato) VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE giocato=VALUES(giocato), pagato=VALUES(pagato)');
    $num = fn($v) => is_numeric($v) ? (float)$v : 0.0;
    $pdo->beginTransaction();
    foreach (($_POST['bw'] ?? []) as $data => $forn) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) continue;
        foreach ($fornitori as $f) {
            $g = $num($forn[$f]['giocato'] ?? 0);
            $p = $num($forn[$f]['pagato'] ?? 0);
            $up->execute([$data, $f, $g, $p]);
        }
    }
    $pdo->commit();
    audit('salvataggio_settimana', 'snai_betwin', null, "$anno-$mese sett$sett");
    header("Location: settimanale.php?anno=$anno&mese=$mese&sett=$sett&ok=1"); exit;
}

/* =========================================================
   Dati
   ========================================================= */
$h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv = fn($v) => ($v == 0 ? '' : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'));
$tot     = array_fill_keys($fornitori, ['g' => 0, 'p' => 0, 'i' => 0]);
$tot_banc   = 0;
$tot_vers   = 0;
$tot_ticket = 0;
$rows       = [];
foreach ($giorni as $d) {
    $bw  = betwin_giorno($pdo, $d);
    $ri  = riepilogo_giornata($pdo, $d);
    $rows[$d] = ['bw' => $bw, 'ri' => $ri];
    foreach ($fornitori as $f) {
        $tot[$f]['g'] += $bw[$f]['giocato'];
        $tot[$f]['p'] += $bw[$f]['pagato'];
        $tot[$f]['i'] += $ri['scass'][$f];
    }
    $tot_banc   += $ri['bancomat'];
    $tot_vers   += $ri['versamento'];
    $tot_ticket += $ri['ticket'];
}
$pct = fn($p, $g) => $g > 0 ? number_format($p / $g * 100, 1, ',', '.') . '%' : '—';
$tg = array_sum(array_column($tot, 'g'));
$tp = array_sum(array_column($tot, 'p'));

/* Settimana precedente per confronto */
$prevDs   = ($prevSett['sett'] - 1) * 7 + 1;
$prevDays = (int)date('t', mktime(0, 0, 0, $prevSett['mese'], 1, $prevSett['anno']));
$prevDe   = min($prevSett['sett'] * 7, $prevDays);
$prevGiorni = [];
for ($d = $prevDs; $d <= $prevDe; $d++) {
    $prevGiorni[] = date('Y-m-d', mktime(0, 0, 0, $prevSett['mese'], $d, $prevSett['anno']));
}
$prev_tg = 0; $prev_tp = 0; $prev_banc = 0; $prev_vers = 0; $prev_ins = 0;
foreach ($prevGiorni as $d) {
    $bw = betwin_giorno($pdo, $d);
    $ri = riepilogo_giornata($pdo, $d);
    foreach ($fornitori as $f) { $prev_tg += $bw[$f]['giocato']; $prev_tp += $bw[$f]['pagato']; $prev_ins += $ri['scass'][$f]; }
    $prev_banc += $ri['bancomat'];
    $prev_vers += $ri['versamento'];
}
$delta = fn(float $cur, float $prv): string =>
    ($prv == 0) ? '' :
    sprintf('<span class="sett-delta %s">%s%s%%</span>',
        $cur >= $prv ? 'delta-up' : 'delta-dn',
        $cur >= $prv ? '+' : '',
        number_format(($cur - $prv) / $prv * 100, 1, ',', '.'));

/* =========================================================
   Export stampa (HTML print-friendly)
   ========================================================= */
if (($_GET['export'] ?? '') === 'print') {
    $nomiMesiP = nomi_mesi();
    header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="it"><head>
<meta charset="utf-8"><title>Settimanale <?= $anno ?>/<?= sprintf('%02d',$mese) ?> Sett.<?= $sett ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font:12px/1.4 system-ui,sans-serif;color:#111;padding:16px}
h1{font-size:14px;margin-bottom:12px}
h2{font-size:12px;margin:14px 0 4px;border-bottom:1px solid #ccc;padding-bottom:2px}
table{width:100%;border-collapse:collapse;font-size:11px;margin-bottom:10px}
th,td{border:1px solid #ccc;padding:3px 6px;text-align:left}
th{background:#f4f4f4;font-weight:600}
.rt{text-align:right}
.tot{font-weight:700;background:#f9f9f9}
.day-sep{height:10px;background:none;border:none}
@media print{
  body{padding:0}
  .no-print{display:none}
}
</style>
</head><body>
<h1><?= $h($cfg['nome_sala'] ?? '') ?> — Settimanale <?= $h($nomiMesiP[$mese]) ?> <?= $anno ?> · Settimana <?= $sett ?> (<?= $dayStart ?>–<?= $dayEnd ?>)</h1>
<table>
<thead><tr><th>Data</th><th>Fornitore</th><th class="rt">Giocato</th><th class="rt">Pagato</th><th class="rt">Inserito</th><th class="rt">Payout</th><th class="rt">Bancomat</th><th class="rt">Ticket</th><th class="rt">Versamento</th></tr></thead>
<tbody>
<?php foreach ($giorni as $d):
    $bw = $rows[$d]['bw']; $ri = $rows[$d]['ri']; $first = true;
    foreach (['INSPIRED','SPIELO','NOVO'] as $f):
        $g=$bw[$f]['giocato'];$p=$bw[$f]['pagato'];$ins=$ri['scass'][$f]; ?>
<tr>
  <td><?= $first ? $h(date('d/m/Y',strtotime($d))) : '' ?></td>
  <td><?= $f ?></td>
  <td class="rt"><?= eur($g) ?></td>
  <td class="rt"><?= eur($p) ?></td>
  <td class="rt"><?= eur($ins) ?></td>
  <td class="rt"><?= $pct($p,$g) ?></td>
  <td class="rt"><?= $first ? eur($ri['bancomat']) : '' ?></td>
  <td class="rt"><?= $first ? eur($ri['ticket']) : '' ?></td>
  <td class="rt"><?= $first ? eur($ri['versamento']) : '' ?></td>
</tr>
<?php $first=false; endforeach; ?>
<tr class="day-sep"><td colspan="9"></td></tr>
<?php endforeach; ?>
<tr class="tot"><td colspan="2">TOTALI SETTIMANA</td>
  <td class="rt"><?= eur($tg) ?></td><td class="rt"><?= eur($tp) ?></td>
  <td class="rt"><?= eur(array_sum(array_column($tot,'i'))) ?></td>
  <td class="rt"><?= $pct($tp,$tg) ?></td>
  <td class="rt"><?= eur($tot_banc) ?></td>
  <td class="rt"><?= eur($tot_ticket) ?></td>
  <td class="rt"><?= eur($tot_vers) ?></td>
</tr>
</tbody></table>
<script>window.print()</script>
</body></html>
<?php exit; }

/* =========================================================
   Export CSV
   ========================================================= */
if (($_GET['export'] ?? '') === 'csv') {
    $fname = "settimanale_{$anno}_{$mese}_s{$sett}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Data', 'Fornitore', 'Giocato', 'Pagato', 'Inserito', 'Payout%', 'Bancomat', 'Ticket', 'Versamento'], ';');
    foreach ($giorni as $d) {
        $bw = $rows[$d]['bw'];
        $ri = $rows[$d]['ri'];
        $first = true;
        foreach (['INSPIRED', 'SPIELO', 'NOVO'] as $f) {
            $row = [$d, $f,
                number_format($bw[$f]['giocato'], 2, ',', '.'),
                number_format($bw[$f]['pagato'],  2, ',', '.'),
                number_format($ri['scass'][$f],   2, ',', '.'),
                $pct($bw[$f]['pagato'], $bw[$f]['giocato']),
                $first ? number_format($ri['bancomat'],    2, ',', '.') : '',
                $first ? number_format($ri['ticket'],      2, ',', '.') : '',
                $first ? number_format($ri['versamento'],  2, ',', '.') : '',
            ];
            fputcsv($out, $row, ';');
            $first = false;
        }
        fputcsv($out, [], ';'); // riga vuota tra giorni
    }
    fputcsv($out, [], ';');
    fputcsv($out, ['TOTALI', '', number_format($tg, 2, ',', '.'), number_format($tp, 2, ',', '.'), '', $pct($tp, $tg),
                   number_format($tot_banc, 2, ',', '.'), number_format($tot_ticket, 2, ',', '.'),
                   number_format($tot_vers, 2, ',', '.')], ';');
    fclose($out);
    exit;
}
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settimanale GP</title><link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/settimanale.css') ?>"></head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div><strong><?= $h($cfg['nome_sala']) ?></strong> · Bet/Win SNAI</div>
  <div class="settimana-nav">
    <a class="settimana-nav-btn" href="?anno=<?= $prevSett['anno'] ?>&mese=<?= $prevSett['mese'] ?>&sett=<?= $prevSett['sett'] ?>" title="Settimana precedente">&#8592;</a>
    <span class="settimana-nav-label">
      <?= $h($nomiMesi[$mese]) ?> <?= $anno ?> &mdash;
      Settimana <?= $sett ?> &nbsp;
      <span class="settimana-nav-range"><?= $dayStart ?>&ndash;<?= $dayEnd ?></span>
    </span>
    <a class="settimana-nav-btn" href="?anno=<?= $nextSett['anno'] ?>&mese=<?= $nextSett['mese'] ?>&sett=<?= $nextSett['sett'] ?>" title="Settimana successiva">&#8594;</a>
  </div>
  <div class="sett-topbar-actions">
    <!-- Selezione rapida settimana del mese -->
    <?php for ($s = 1; $s <= $numSettimane; $s++):
        $ds = ($s-1)*7+1; $de = min($s*7,$giorniMese); ?>
    <a class="settimana-tab <?= $s===$sett?'active':'' ?>"
       href="?anno=<?= $anno ?>&mese=<?= $mese ?>&sett=<?= $s ?>"><?= $ds ?>&ndash;<?= $de ?></a>
    <?php endfor; ?>
  </div>
  <div class="topbar-actions">
    <a class="topbar-action-btn" href="?anno=<?= $anno ?>&mese=<?= $mese ?>&sett=<?= $sett ?>&export=csv">&#8595; CSV</a>
    <a class="topbar-action-btn" href="?anno=<?= $anno ?>&mese=<?= $mese ?>&sett=<?= $sett ?>&export=print" target="_blank">&#128438; Stampa</a>
  </div>
</header>

<?php if (isset($_GET['ok'])): ?><div class="ok">Salvato.</div><?php endif; ?>

<form method="post">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<div class="turni">
<?php foreach ($giorni as $d):
    $bw  = $rows[$d]['bw'];
    $ri  = $rows[$d]['ri'];
    $dg  = 0; $dp = 0;
    foreach ($fornitori as $f) { $dg += $bw[$f]['giocato']; $dp += $bw[$f]['pagato']; }
    $ricavo  = $dg - $dp;
    $vers    = $ri['versamento'];
    $cassa   = $ri['bancomat'] + $vers;
    $margine = $cassa - $ricavo; ?>
  <section class="turno t1">
    <h2><?= $h($nomiGiorniBr[(int)date('w', strtotime($d))] . ' ' . date('d/m', strtotime($d))) ?></h2>
    <table class="grid">
      <tr><th>Fornitore</th><th>Giocato</th><th>Pagato</th><th>Inserito</th><th>Payout</th></tr>
      <?php foreach ($fornitori as $f): $g = $bw[$f]['giocato']; $p = $bw[$f]['pagato']; $ins = $ri['scass'][$f]; ?>
      <tr><td><?= $f ?></td>
        <?php if (!is_revisore()): ?>
        <td><input type="number" step="0.01" name="bw[<?= $d ?>][<?= $f ?>][giocato]" value="<?= $h($nv($g)) ?>"></td>
        <td><input type="number" step="0.01" name="bw[<?= $d ?>][<?= $f ?>][pagato]"  value="<?= $h($nv($p)) ?>"></td>
        <?php else: ?>
        <td class="rt"><?= eur($g) ?></td>
        <td class="rt"><?= eur($p) ?></td>
        <?php endif; ?>
        <td class="rt"><?= eur($ins) ?></td>
        <td class="rt"><?= $pct($p, $g) ?></td></tr>
      <?php endforeach; ?>
      <tr class="tot"><td>TOTALE</td><td class="rt"><?= eur($dg) ?></td><td class="rt"><?= eur($dp) ?></td>
          <td class="rt"><?= eur(array_sum($ri['scass'])) ?></td><td class="rt"><?= $pct($dp, $dg) ?></td></tr>
    </table>
    <table class="grid">
      <tr><td>Bancomat</td><td class="rt"><?= eur($ri['bancomat']) ?></td></tr>
      <tr><td>Versamento</td><td class="rt"><?= eur($vers) ?></td></tr>
      <tr><td>Ricavo (G&minus;P)</td><td class="rt"><?= eur($ricavo) ?></td></tr>
      <tr class="<?= abs($margine) > 0.005 ? 'errore' : 'tot' ?>"><td>Margine</td><td class="rt"><?= eur($margine) ?></td></tr>
    </table>
  </section>
<?php endforeach; ?>
</div>
<?php if (!is_revisore()): ?><div class="actions sett-save"><button type="submit">Salva settimana</button></div><?php endif; ?>
</form>

<div class="riepilogo riepilogo-main">
  <h3>Totali settimana <?= $sett ?> (<?= $dayStart ?>&ndash;<?= $dayEnd ?> <?= $h($nomiMesi[$mese]) ?>)</h3>
  <table class="grid">
    <tr>
      <th>Fornitore</th>
      <th class="rt">Giocato</th><th class="rt">Pagato</th><th class="rt">Inserito</th>
      <th class="rt">Payout</th><th class="rt">G/Ins</th>
    </tr>
    <?php foreach ($fornitori as $f): ?>
    <tr>
      <td><?= $h($f) ?></td>
      <td class="rt"><?= eur($tot[$f]['g']) ?></td>
      <td class="rt"><?= eur($tot[$f]['p']) ?></td>
      <td class="rt"><?= eur($tot[$f]['i']) ?></td>
      <td class="rt"><?= $pct($tot[$f]['p'], $tot[$f]['g']) ?></td>
      <td class="rt"><?= $tot[$f]['i'] > 0 ? number_format($tot[$f]['g'] / $tot[$f]['i'], 2, ',', '.') : '—' ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="tot">
      <td>TOTALE BET</td>
      <td class="rt"><?= eur($tg) ?> <?= $delta($tg, $prev_tg) ?></td>
      <td class="rt"><?= eur($tp) ?> <?= $delta($tp, $prev_tp) ?></td>
      <td class="rt"><?= eur(array_sum(array_column($tot,'i'))) ?> <?= $delta(array_sum(array_column($tot,'i')), $prev_ins) ?></td>
      <td class="rt"><?= $pct($tp,$tg) ?></td>
      <td></td>
    </tr>
    <tr>
      <td>Bancomat</td>
      <td class="rt"><?= eur($tot_banc) ?> <?= $delta($tot_banc, $prev_banc) ?></td>
      <td colspan="4"></td>
    </tr>
    <tr>
      <td>Versamento</td>
      <td class="rt"><?= eur($tot_vers) ?> <?= $delta($tot_vers, $prev_vers) ?></td>
      <td colspan="4"></td>
    </tr>
    <?php $margine = $tot_banc + $tot_vers - ($tg - $tp); $prevMargine = $prev_banc + $prev_vers - ($prev_tg - $prev_tp); ?>
    <tr class="tot">
      <td>Margine</td>
      <td class="rt"><?= eur($margine) ?> <?= $delta($margine, $prevMargine) ?></td>
      <td colspan="4"></td>
    </tr>
  </table>
  <?php if ($prev_tg > 0 || $prev_banc > 0): ?>
  <p class="sett-compare-note">vs settimana <?= $prevSett['sett'] ?> (<?= $prevDs ?>&ndash;<?= $prevDe ?> <?= $h($nomiMesi[$prevSett['mese']]) ?>)</p>
  <?php endif; ?>
</div>
</body></html>
