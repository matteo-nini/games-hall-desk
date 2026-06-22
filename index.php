<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lib.php';
$user = require_login();
$dest = ($user['ruolo'] ?? '') === 'responsabile' ? 'account/responsabile.php' : 'account/dashboard.php';
header('Location: ' . base_url($dest));
exit;
