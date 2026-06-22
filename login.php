<?php
require_once __DIR__ . '/includes/auth.php';
start_session();
$cfg = config();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        $dest = is_responsabile() ? 'account/responsabile.php' : 'account/dashboard.php';
        header('Location: ' . $dest); exit;
    }
    $err = 'Credenziali non valide.';
}
?>
<!doctype html>
<html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Accesso &middot; <?= htmlspecialchars($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/login.css">
</head><body class="login-page">
<div class="login-wrap">
  <div class="login-box">
    <div class="login-brand">&#9654;</div>
    <h1><?= htmlspecialchars($cfg['nome_sala'] ?? 'Cassa Sala') ?></h1>
    <p class="login-sub">Accesso area riservata</p>
    <?php if ($err): ?>
    <div class="login-err"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="login-field">
        <label for="lu">Utente</label>
        <input id="lu" name="username" type="text" autofocus required autocomplete="username" placeholder="nome utente">
      </div>
      <div class="login-field">
        <label for="lp">Password</label>
        <input id="lp" name="password" type="password" required autocomplete="current-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
      </div>
      <button type="submit" class="login-btn">Entra &rarr;</button>
    </form>
    <p class="login-foot">Cassa Sala &middot; gestione cassa VLT/AWP</p>
  </div>
</div>
</body></html>
