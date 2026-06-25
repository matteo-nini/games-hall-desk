<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_responsabile();
$pdo  = db();

$oggi = date('Y-m-d');
$riepilogo = riepilogo_giornata($pdo, $oggi);

$st = $pdo->prepare('SELECT stato FROM giornate WHERE data=?');
$st->execute([$oggi]);
$stato = $st->fetchColumn() ?: null;

$meseInizio = date('Y-m-01');
$meseFine   = date('Y-m-t');
$st = $pdo->prepare('
    SELECT COUNT(DISTINCT g.id) AS giorni, COALESCE(SUM(s.importo),0) AS incasso
    FROM giornate g
    LEFT JOIN turni t ON t.giornata_id=g.id
    LEFT JOIN scassettamenti s ON s.turno_id=t.id
    WHERE g.data BETWEEN ? AND ?
');
$st->execute([$meseInizio, $meseFine]);
$mese = $st->fetch();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ts'              => time(),
    'stato'           => $stato,
    'incasso_vlt'     => (float)$riepilogo['incasso_vlt'],
    'versamento'      => (float)$riepilogo['versamento'],
    'incasso_mese'    => (float)$mese['incasso'],
    'giorni_mese'     => (int)$mese['giorni'],
]);
