<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$cfg  = config();
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

$anno = (int)($_GET['anno'] ?? date('Y'));
$anno = max(2020, min(2040, $anno));

$mesi = [
    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo',    4 => 'Aprile',
    5 => 'Maggio',  6 => 'Giugno',   7 => 'Luglio',   8 => 'Agosto',
    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
];

/* ============================================================
   4 query aggregate — una per categoria, GROUP BY mese
   ============================================================ */

$st = $pdo->prepare('
    SELECT MONTH(g.data) m, SUM(s.importo) v
    FROM giornate g
    JOIN turni t ON t.giornata_id = g.id
    JOIN scassettamenti s ON s.turno_id = t.id
    WHERE YEAR(g.data) = ?
    GROUP BY MONTH(g.data)
');
$st->execute([$anno]);
$scassM = array_fill(1, 12, 0.0);
foreach ($st as $r) $scassM[(int)$r['m']] = (float)$r['v'];

$st = $pdo->prepare('
    SELECT MONTH(g.data) m, SUM(tk.importo) v
    FROM giornate g
    JOIN turni t ON t.giornata_id = g.id
    JOIN ticket tk ON tk.turno_id = t.id
    WHERE YEAR(g.data) = ?
    GROUP BY MONTH(g.data)
');
$st->execute([$anno]);
$ticketM = array_fill(1, 12, 0.0);
foreach ($st as $r) $ticketM[(int)$r['m']] = (float)$r['v'];

$st = $pdo->prepare('
    SELECT MONTH(g.data) m, SUM(t.bancomat) v
    FROM giornate g
    JOIN turni t ON t.giornata_id = g.id
    WHERE YEAR(g.data) = ?
    GROUP BY MONTH(g.data)
');
$st->execute([$anno]);
$bancM = array_fill(1, 12, 0.0);
foreach ($st as $r) $bancM[(int)$r['m']] = (float)$r['v'];

$st = $pdo->prepare('
    SELECT MONTH(g.data) m, COUNT(DISTINCT g.data) v
    FROM giornate g
    JOIN turni t ON t.giornata_id = g.id AND t.numero = 2
    WHERE YEAR(g.data) = ?
    GROUP BY MONTH(g.data)
');
$st->execute([$anno]);
$giorniM = array_fill(1, 12, 0);
foreach ($st as $r) $giorniM[(int)$r['m']] = (int)$r['v'];

/* Versamento mensile = sum(vers_cassa) per giorno sera = sum(cassetto+monete-fondo) */
$st = $pdo->prepare('
    SELECT MONTH(g.data) m,
           SUM(
               (SELECT COALESCE(SUM(c.taglio * c.pezzi), 0) FROM contanti c WHERE c.turno_id = t.id)
               + (SELECT COALESCE(SUM(r.euro), 0) FROM refill_awp r WHERE r.turno_id = t.id)
               + t.differenze - t.ii_cassa - t.rientri + t.monete - t.fondo_cassa
           ) v
    FROM giornate g
    JOIN turni t ON t.giornata_id = g.id AND t.numero = 2
    WHERE YEAR(g.data) = ?
    GROUP BY MONTH(g.data)
');
$st->execute([$anno]);
$versM = array_fill(1, 12, 0.0);
foreach ($st as $r) $versM[(int)$r['m']] = (float)$r['v'];

/* CSV export */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="annuale_' . $anno . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Mese', 'Giorni', 'Incasso VLT', 'Ticket', 'Bancomat', 'Versamento'], ';');
    for ($m = 1; $m <= 12; $m++) {
        fputcsv($out, [
            $mesi[$m],
            $giorniM[$m],
            number_format($scassM[$m],  2, ',', '.'),
            number_format($ticketM[$m], 2, ',', '.'),
            number_format($bancM[$m],   2, ',', '.'),
            number_format($versM[$m],   2, ',', '.'),
        ], ';');
    }
    fclose($out);
    exit;
}

$totScass  = array_sum($scassM);
$totTicket = array_sum($ticketM);
$totBanc   = array_sum($bancM);
$totVers   = array_sum($versM);
$totGiorni = array_sum($giorniM);
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Annuale <?= $anno ?> · <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/annuale.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar sh-top">
  <div class="ann-year-nav">
    <a class="settimana-nav-btn" href="?anno=<?= $anno - 1 ?>" title="Anno precedente" aria-label="Anno precedente">&#8592;</a>
    <strong><?= $anno ?></strong>
    <a class="settimana-nav-btn" href="?anno=<?= $anno + 1 ?>" title="Anno successivo" aria-label="Anno successivo">&#8594;</a>
    <form method="get" class="ann-year-form">
      <input type="number" name="anno" value="<?= $anno ?>" min="2020" max="2040" aria-label="Anno specifico">
      <button type="submit" class="ghost">Vai</button>
    </form>
  </div>
  <div style="display:flex;gap:6px">
    <a class="topbar-action-btn" href="?anno=<?= $anno ?>&export=csv">
      <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 1v7M3 5l3 3 3-3M1 10h10"/></svg>
      CSV
    </a>
    <button class="topbar-action-btn no-print" onclick="window.print()" type="button">&#128438; Stampa</button>
  </div>
</header>

<div class="ann-page">

  <div class="ann-summary">
    <div class="mini">
      <div class="l">Incasso VLT</div>
      <div class="v"><?= eur($totScass) ?></div>
    </div>
    <div class="mini">
      <div class="l">Versamento</div>
      <div class="v"><?= eur($totVers) ?></div>
    </div>
    <div class="mini">
      <div class="l">Bancomat</div>
      <div class="v"><?= eur($totBanc) ?></div>
    </div>
    <div class="mini">
      <div class="l">Ticket</div>
      <div class="v"><?= eur($totTicket) ?></div>
    </div>
    <div class="mini">
      <div class="l">Giorni operativi</div>
      <div class="v ann-giorni"><?= $totGiorni ?></div>
    </div>
  </div>

  <div class="riepilogo ann-riepilogo">
    <h3>Riepilogo mensile <?= $anno ?></h3>
    <table class="grid">
      <thead>
        <tr>
          <th>Mese</th>
          <th class="rt">Giorni</th>
          <th class="rt">Incasso VLT</th>
          <th class="rt">Ticket</th>
          <th class="rt">Bancomat</th>
          <th class="rt">Versamento</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($m = 1; $m <= 12; $m++):
          $vers    = $versM[$m];
          $hasData = $giorniM[$m] > 0;
        ?>
        <tr class="<?= $hasData ? '' : 'ann-row-empty' ?>">
          <td>
            <a href="<?= base_url('cassa/mensile.php') ?>?anno=<?= $anno ?>&mese=<?= $m ?>" class="ann-month-link"><?= $h($mesi[$m]) ?></a>
          </td>
          <td class="rt ann-days"><?= $hasData ? $giorniM[$m] : '—' ?></td>
          <td class="rt"><?= $hasData ? eur($scassM[$m])  : '—' ?></td>
          <td class="rt"><?= $hasData ? eur($ticketM[$m]) : '—' ?></td>
          <td class="rt"><?= $hasData ? eur($bancM[$m])   : '—' ?></td>
          <td class="rt"><?= $hasData ? eur($vers)        : '—' ?></td>
        </tr>
        <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr class="tot">
          <td>TOTALE <?= $anno ?></td>
          <td class="rt"><?= $totGiorni ?></td>
          <td class="rt"><?= eur($totScass) ?></td>
          <td class="rt"><?= eur($totTicket) ?></td>
          <td class="rt"><?= eur($totBanc) ?></td>
          <td class="rt"><?= eur($totVers) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

</div>
</body></html>
