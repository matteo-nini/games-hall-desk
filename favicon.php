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

$fs = mb_strlen($initials) > 1 ? '13' : '18';
$cy = mb_strlen($initials) > 1 ? '21' : '22';

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <rect width="32" height="32" rx="7" fill="#3b5bdb"/>
  <text x="16" y="<?= $cy ?>" text-anchor="middle"
        font-family="system-ui,-apple-system,Helvetica,Arial,sans-serif"
        font-size="<?= $fs ?>" font-weight="700" fill="white"><?= htmlspecialchars($initials, ENT_XML1) ?></text>
</svg>
