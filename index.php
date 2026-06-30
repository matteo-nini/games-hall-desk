<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lib.php';
$user = require_login();
header('Location: ' . base_url('account/dashboard.php'));
exit;
