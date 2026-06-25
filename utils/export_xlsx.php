<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
require_once __DIR__ . '/../includes/XlsxWriter.php';
$user = require_login();
$pdo  = db();

$anno = (int)($_GET['anno'] ?? date('Y'));
$mese = (int)($_GET['mese'] ?? date('n'));
if ($mese < 1 || $mese > 12) $mese = (int)date('n');
$ngiorni   = (int)date('t', mktime(0, 0, 0, $mese, 1, $anno));
$fornitori = get_fornitori($pdo);
$mesiNomi  = nomi_mesi();

audit('export_xlsx', 'mensile', null, sprintf('%04d-%02d', $anno, $mese));

// S = stringa, N = numero con 0..3 stile
$S  = fn($v, $st = 0) => [$v, 's', $st];
$N  = fn($v, $st = 0) => [$v, 'n', $st];

$xlsx = new XlsxWriter("Cassa {$mesiNomi[$mese]} {$anno}");

// -------- Sezione 1: cassa giornaliera --------
$xlsx->addRow([$S("Cassa per giorno — {$mesiNomi[$mese]} {$anno}", 1)]);
$header = [$S('Giorno', 1), $S('Incasso VLT', 1), $S('Ticket', 1), $S('Bancomat', 1), $S('Versamento', 1)];
foreach ($fornitori as $f) $header[] = $S($f, 1);
$xlsx->addRow($header);

$tot = array_fill_keys(['inc','tk','banc','vers'], 0.0);
$totForn = array_fill_keys($fornitori, 0.0);
for ($d = 1; $d <= $ngiorni; $d++) {
    $data = sprintf('%04d-%02d-%02d', $anno, $mese, $d);
    $r    = riepilogo_giornata($pdo, $data);
    $tot['inc']  += $r['incasso_vlt'];
    $tot['tk']   += $r['ticket'];
    $tot['banc'] += $r['bancomat'];
    $tot['vers'] += $r['versamento'];
    $row = [$S($d), $N($r['incasso_vlt'], 2), $N($r['ticket'], 2), $N($r['bancomat'], 2), $N($r['versamento'], 2)];
    foreach ($fornitori as $f) {
        $totForn[$f] += $r['scass'][$f] ?? 0;
        $row[] = $N($r['scass'][$f] ?? 0, 2);
    }
    $xlsx->addRow($row);
}
$totRow = [$S('TOTALE', 3), $N($tot['inc'], 3), $N($tot['tk'], 3), $N($tot['banc'], 3), $N($tot['vers'], 3)];
foreach ($fornitori as $f) $totRow[] = $N($totForn[$f], 3);
$xlsx->addRow($totRow);

// -------- Sezione 2: Bet/Win SNAI --------
$xlsx->addRow([]);
$xlsx->addRow([$S('Bet/Win SNAI per fornitore', 1)]);
$xlsx->addRow([$S('Fornitore', 1), $S('Giocato', 1), $S('Pagato', 1), $S('Ricavo', 1), $S('Inserito', 1), $S('Payout %', 1)]);
$primo  = sprintf('%04d-%02d-01', $anno, $mese);
$ultimo = sprintf('%04d-%02d-%02d', $anno, $mese, $ngiorni);
$bw     = array_fill_keys($fornitori, ['g' => 0.0, 'p' => 0.0]);
$st     = $pdo->prepare('SELECT fornitore, SUM(giocato) g, SUM(pagato) p FROM snai_betwin WHERE data BETWEEN ? AND ? GROUP BY fornitore');
$st->execute([$primo, $ultimo]);
foreach ($st as $row) if (isset($bw[$row['fornitore']])) $bw[$row['fornitore']] = ['g' => (float)$row['g'], 'p' => (float)$row['p']];
$TG = $TP = 0.0;
foreach ($fornitori as $f) {
    $g = $bw[$f]['g']; $p = $bw[$f]['p'];
    $TG += $g; $TP += $p;
    $pct = $g > 0 ? round($p / $g * 100, 1) : 0;
    $xlsx->addRow([$S($f), $N($g, 2), $N($p, 2), $N($g - $p, 2), $N($totForn[$f], 2), $N($pct)]);
}
$xlsx->addRow([$S('TOTALE', 3), $N($TG, 3), $N($TP, 3), $N($TG - $TP, 3), $N(array_sum($totForn), 3), $N($TG > 0 ? round($TP / $TG * 100, 1) : 0, 1)]);

// -------- Sezione 3: VLT per macchina --------
$stVlt = $pdo->prepare("
    SELECT m.codice, m.fornitore, COALESCE(SUM(s.importo),0) AS tot
    FROM macchine m
    LEFT JOIN scassettamenti s ON s.macchina_id = m.id
    LEFT JOIN turni t ON t.id = s.turno_id
    LEFT JOIN giornate g ON g.id = t.giornata_id AND g.data BETWEEN ? AND ?
    WHERE m.tipo = 'VLT' AND m.attiva = 1
    GROUP BY m.id, m.codice, m.fornitore
    ORDER BY tot DESC
");
$stVlt->execute([$primo, $ultimo]);
$vlt = $stVlt->fetchAll();
if ($vlt) {
    $xlsx->addRow([]);
    $xlsx->addRow([$S('Incasso VLT per macchina', 1)]);
    $xlsx->addRow([$S('Macchina', 1), $S('Fornitore', 1), $S('Incasso', 1)]);
    $totVlt = 0.0;
    foreach ($vlt as $mac) {
        $totVlt += (float)$mac['tot'];
        $xlsx->addRow([$S($mac['codice']), $S($mac['fornitore']), $N((float)$mac['tot'], 2)]);
    }
    $xlsx->addRow([$S('TOTALE', 3), $S('', 3), $N($totVlt, 3)]);
}

$xlsx->output("cassa_{$anno}_{$mese}.xlsx");
