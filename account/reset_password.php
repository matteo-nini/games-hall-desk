<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
require_once __DIR__ . '/../includes/mail/mailer.php';
start_session();

/* Già autenticato → redirect */
if (current_user()) { header('Location: dashboard.php'); exit; }

$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$sett = get_settings($pdo);

$sent = false;
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = mb_substr(trim($_POST['username'] ?? ''), 0, 60);

    if ($username === '') {
        $err = 'Inserisci il tuo username.';
    } else {
        $st = $pdo->prepare('SELECT id, email FROM utenti WHERE username=? AND attivo=1');
        $st->execute([$username]);
        $u = $st->fetch();

        if ($u && !empty($u['email'])) {
            mail_reset_password($pdo, (int)$u['id'], $u['email'], $sett, $cfg);
            audit('password_reset_richiesto', 'utenti', (int)$u['id'], $username);
        } elseif ($u && empty($u['email'])) {
            $err = 'Nessuna email configurata per questo account. Contatta il responsabile.';
        }
        /* Se utente non trovato → $sent = true senza rivelare l'esistenza */
        if (!$err) $sent = true;
    }
}

$brandAccent = $sett['brand_accent'] ?? null;
$brandCss    = '';
if ($brandAccent && preg_match('/^#[0-9a-fA-F]{6}$/', $brandAccent)) {
    $bvars = brand_derive($brandAccent);
    $brandCss = ':root{';
    foreach ($bvars as $prop => $val) $brandCss .= htmlspecialchars($prop) . ':' . htmlspecialchars($val) . ';';
    $brandCss .= '}';
}
$logoPath = $sett['logo_path'] ?? null;
?>
<!doctype html>
<html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset password &middot; <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/login.css') ?>">
<?php if ($brandCss): ?><style><?= $brandCss ?></style><?php endif; ?>
</head><body class="login-page">
<div class="login-wrap">
  <div class="login-box">
    <?php if ($logoPath): ?>
      <img class="login-logo" src="<?= asset_url('account/uploads/sala/' . $h($logoPath)) ?>" alt="Logo" style="display:block;height:100px;width:auto;max-width:100%">
    <?php else: ?>
      <div class="login-brand">&#9654;</div>
    <?php endif; ?>
    <h1><?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></h1>
    <p class="login-sub">Reset password</p>

    <?php if ($sent): ?>
    <div style="background:color-mix(in srgb,var(--green) 12%,transparent);border:1px solid color-mix(in srgb,var(--green) 30%,transparent);border-radius:var(--rs);padding:14px 16px;font-size:13px;color:var(--green-ink);margin-bottom:20px">
      Se esiste un account con questo username e un'email configurata, riceverai un link di reset entro qualche minuto.
    </div>
    <a href="login.php" class="login-btn" style="display:block;text-align:center;text-decoration:none">Torna al login &rarr;</a>
    <?php else: ?>
    <?php if ($err): ?><div class="login-err"><?= $h($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="login-field">
        <label for="ru">Username</label>
        <input id="ru" name="username" type="text" autofocus required autocomplete="username" placeholder="nome utente">
      </div>
      <button type="submit" class="login-btn">Invia link di reset &rarr;</button>
    </form>
    <p style="text-align:center;margin-top:16px"><a href="login.php" style="color:var(--muted);font-size:13px">&larr; Torna al login</a></p>
    <?php endif; ?>

    <p class="login-foot">Cassa Sala &middot; gestione cassa VLT/AWP</p>
  </div>
</div>
</body></html>
