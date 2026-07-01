<?php
/**
 * Endpoint: legge un file XLS/XLSX giornaliero SISAL e restituisce
 * i totali Giocato/Pagato per fornitore come JSON.
 * POST — richiede autenticazione operatore.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
require_once __DIR__ . '/../includes/SimpleXLS.php';

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

    if (!isset($_FILES['xls']) || $_FILES['xls']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nessun file ricevuto o errore di upload');
    }

    $ext = strtolower(pathinfo($_FILES['xls']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx'], true)) {
        throw new RuntimeException('Formato non supportato: carica un file .xls o .xlsx');
    }

    $rows = SimpleXLS::read($_FILES['xls']['tmp_name']);

    // Mapping: prefisso sistema XLS → keyword per trovare il fornitore in DB
    $prefixKeyword = [
        'NOVOMATIC'     => 'NOVO',
        'SPIELO'        => 'SPIELO',
        'INGGVLTSYSTEM' => 'INSPIRED',
        'INSPIRED'      => 'INSPIRED',
        'IGT'           => 'IGT',
        'MERKUR'        => 'MERKUR',
    ];

    // Costruisci mappa keyword → fornitore effettivo in DB
    $pdo = db();
    $dbFornitori = get_fornitori($pdo);
    $keywordToForn = [];
    foreach ($dbFornitori as $f) {
        foreach ($prefixKeyword as $keyword) {
            if (stripos($f, $keyword) !== false) $keywordToForn[strtoupper($keyword)] = $f;
        }
    }

    $result   = [];
    $dateISO  = null;

    foreach ($rows as $ri => $row) {
        if ($ri === 0) continue; // intestazione
        $sistema = trim((string)($row[0] ?? ''));
        $dataStr = trim((string)($row[7] ?? ''));
        $bet     = (float)($row[8] ?? 0);
        $win     = (float)($row[9] ?? 0);
        if (!$sistema || ($bet == 0 && $win == 0)) continue;

        // Estrai data (prima occorrenza valida)
        if (!$dateISO && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataStr, $m)) {
            $dateISO = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Risolvi fornitore
        $forn = null;
        foreach ($prefixKeyword as $prefix => $keyword) {
            if (stripos($sistema, $prefix) === 0) {
                $forn = $keywordToForn[strtoupper($keyword)] ?? null;
                break;
            }
        }
        if ($forn === null) continue;

        $result[$forn]['giocato'] = round(($result[$forn]['giocato'] ?? 0) + $bet, 2);
        $result[$forn]['pagato']  = round(($result[$forn]['pagato']  ?? 0) + $win, 2);
    }

    if (!$dateISO)    throw new RuntimeException('Data non trovata nel file');
    if (!$result)     throw new RuntimeException('Nessun dato fornitore trovato nel file');

    // Calcola URL di navigazione alla settimana giusta
    $day   = (int)date('j', strtotime($dateISO));
    $sett  = $day <= 7 ? 1 : ($day <= 15 ? 2 : ($day <= 23 ? 3 : 4));
    $yISO  = (int)date('Y', strtotime($dateISO));
    $mISO  = (int)date('n', strtotime($dateISO));
    $navUrl = "settimanale.php?anno=$yISO&mese=$mISO&sett=$sett";

    echo json_encode(['ok' => true, 'date' => $dateISO, 'data' => $result, 'nav_url' => $navUrl]);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
