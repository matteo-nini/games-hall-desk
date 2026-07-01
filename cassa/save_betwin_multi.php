<?php
/**
 * Salva dati bet/win per più date in un colpo solo.
 * POST — richiede autenticazione operatore.
 *
 * Body (FormData):
 *   csrf   = TOKEN
 *   dates  = JSON {"YYYY-MM-DD": {"FORN": {giocato, pagato}}, ...}
 *
 * Response: {"ok": true, "saved": N} oppure {"ok": false, "error": "..."}
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';

$user = require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Metodo non valido']); exit;
}
if (is_revisore()) {
    http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Accesso negato']); exit;
}

try {
    check_csrf();

    $datesJson = $_POST['dates'] ?? '';
    $byDate = json_decode($datesJson, true);
    if (!is_array($byDate) || !$byDate) {
        throw new RuntimeException('Dati non validi');
    }

    $pdo        = db();
    $fornitori  = get_fornitori($pdo);
    $up = $pdo->prepare('INSERT INTO snai_betwin (data, fornitore, giocato, pagato) VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE giocato=VALUES(giocato), pagato=VALUES(pagato)');

    $saved = 0;
    $pdo->beginTransaction();
    foreach ($byDate as $data => $fornData) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) continue;
        foreach ($fornitori as $f) {
            $g = (float)($fornData[$f]['giocato'] ?? 0);
            $p = (float)($fornData[$f]['pagato']  ?? 0);
            if ($g == 0 && $p == 0) continue;
            $up->execute([$data, $f, $g, $p]);
            $saved++;
        }
    }
    $pdo->commit();

    $dateList = implode(', ', array_keys($byDate));
    audit('import_betwin_multi', 'snai_betwin', null, $dateList);

    echo json_encode(['ok' => true, 'saved' => $saved, 'count' => count($byDate)]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
