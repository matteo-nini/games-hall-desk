<?php
require_once __DIR__ . '/includes/lib.php';
$cfg  = config();
$nome = $cfg['nome_sala'] ?? '';

$words    = preg_split('/\s+/', trim($nome));
$initials = '';
foreach (array_slice($words, 0, 2) as $w) {
    if ($w !== '') $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
}
if (!$initials) $initials = 'CS';
$initials = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $initials));
$initials = substr($initials, 0, 2) ?: 'CS';

$size = (int)($_GET['size'] ?? 192);
if (!in_array($size, [192, 512], true)) $size = 192;

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(503);
    exit;
}

$out  = imagecreatetruecolor($size, $size);
$blue = imagecolorallocate($out, 59, 91, 219);
$wht  = imagecolorallocate($out, 255, 255, 255);
imagefilledrectangle($out, 0, 0, $size - 1, $size - 1, $blue);

$font = 5;
$cw   = imagefontwidth($font);
$ch   = imagefontheight($font);
$len  = strlen($initials);
$tw   = $cw * $len;

$small = imagecreatetruecolor($tw, $ch);
$sb    = imagecolorallocate($small, 59, 91, 219);
$sw    = imagecolorallocate($small, 255, 255, 255);
imagefilledrectangle($small, 0, 0, $tw - 1, $ch - 1, $sb);
imagestring($small, $font, 0, 0, $initials, $sw);

$th2 = (int)($size * 0.42);
$tw2 = (int)($tw * $th2 / $ch);
$ox  = (int)(($size - $tw2) / 2);
$oy  = (int)(($size - $th2) / 2);
imagecopyresampled($out, $small, $ox, $oy, 0, 0, $tw2, $th2, $tw, $ch);
imagedestroy($small);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
imagepng($out);
imagedestroy($out);
