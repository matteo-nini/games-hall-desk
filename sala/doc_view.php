<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
require_login();
$pdo  = db();
$sett = get_settings($pdo);
if (($sett['modulo_documenti'] ?? '1') !== '1') {
    http_response_code(403); exit;
}

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT filename, mime, nome FROM documenti WHERE id=? AND visibile=1');
$st->execute([$id]);
$doc = $st->fetch();
if (!$doc) { http_response_code(404); exit; }

$filepath = dirname(__DIR__) . '/account/uploads/documenti/' . $doc['filename'];
if (!file_exists($filepath) || !is_file($filepath)) { http_response_code(404); exit; }

$ext  = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
$disp = isset($_GET['dl']) ? 'attachment' : 'inline';
$name = rawurlencode($doc['nome']) . '.' . $ext;

header('Content-Type: ' . $doc['mime']);
header('Content-Disposition: ' . $disp . '; filename*=UTF-8\'\'' . $name);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($filepath);
exit;
