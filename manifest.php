<?php
require_once __DIR__ . '/includes/lib.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$cfg  = config();
$nome = $cfg['nome_sala'] ?? 'Games Palace Desk';
echo json_encode([
    'name'             => $nome,
    'short_name'       => 'GP',
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
            'purpose' => 'any maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
