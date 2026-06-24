<?php
require_once __DIR__ . '/includes/lib.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$cfg  = config();
$nome = $cfg['nome_sala'] ?? 'Cassa Sala';
$words = preg_split('/\s+/', trim($nome));
$short = '';
foreach (array_slice($words, 0, 2) as $w) {
    if ($w !== '') $short .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
}
if (!$short) $short = 'CS';
echo json_encode([
    'name'             => $nome,
    'short_name'       => $short,
    'description'      => 'Gestione cassa sala giochi VLT/AWP',
    'start_url'        => base_url('index.php'),
    'scope'            => base_url(''),
    'display'          => 'standalone',
    'background_color' => '#ffffff',
    'theme_color'      => '#2563eb',
    'icons'            => [
        [
            'src'     => base_url('assets/img/gp-icon.svg'),
            'sizes'   => 'any',
            'type'    => 'image/svg+xml',
            'purpose' => 'any',
        ],
        [
            'src'     => base_url('icon.php') . '?size=192',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => base_url('icon.php') . '?size=512',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => base_url('icon.php') . '?size=192',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
