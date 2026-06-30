<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
start_session();

if (current_user()) { header('Location: dashboard.php'); exit; }

$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$sett = get_settings($pdo);

$token    = trim($_GET['token'] ?? '');
$tokenRow = null;
$done     = false;
$err      = '';

if ($token !== '') {
    $st = $pdo->prepare(
        'SELECT pr.id, pr.utente_id, u.username
         FROM password_reset pr
         JOIN utenti u ON u.id = pr.utente_id
         WHERE pr.token=? AND pr.usato=0 AND pr.scade_il > NOW()'
    );
    $st->execute([$token]);
    $tokenRow = $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $postToken = trim($_POST['token'] ?? '');
    $pw1 = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if ($postToken === '') { $err = 'Token mancante.'; }
    elseif (strlen($pw1) < 8) { $err = 'La password deve essere di almeno 8 caratteri.'; }
    elseif ($pw1 !== $pw2)    { $err = 'Le password non coincidono.'; }
    else {
        /* Ri-verifica token al momento del POST (non fidarsi dello stato GET) */
        $st2 = $pdo->prepare(
            'SELECT pr.id, pr.utente_id FROM password_reset pr
             WHERE pr.token=? AND pr.usato=0 AND pr.scade_il > NOW()'
        );
        $st2->execute([$postToken]);
        $row2 = $st2->fetch();
        if (!$row2) {
            $err = 'Link scaduto o già usato. Richiedine uno nuovo.';
        } else {
            $pdo->prepare('UPDATE utenti SET password_hash=? WHERE id=?')
                ->execute([password_hash($pw1, PASSWORD_DEFAULT), $row2['utente_id']]);
            $pdo->prepare('UPDATE password_reset SET usato=1 WHERE id=?')
                ->execute([$row2['id']]);
            audit('password_reset_completato', 'utenti', (int)$row2['utente_id'], null);
            $done = true;
        }
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
<title>Nuova password &middot; <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
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
    <p class="login-sub">Nuova password</p>

    <?php if ($done): ?>
    <div style="background:color-mix(in srgb,var(--green) 12%,transparent);border:1px solid color-mix(in srgb,var(--green) 30%,transparent);border-radius:var(--rs);padding:14px 16px;font-size:13px;color:var(--green-ink);margin-bottom:20px">
      Password aggiornata con successo.
    </div>
    <a href="login.php" class="login-btn" style="display:block;text-align:center;text-decoration:none">Vai al login &rarr;</a>

    <?php elseif (!$tokenRow && !$done): ?>
    <div class="login-err">
      <?= $err ?: 'Link non valido o scaduto.' ?>
    </div>
    <p style="text-align:center;margin-top:16px">
      <a href="reset_password.php" style="color:var(--accent);font-size:13px">Richiedi un nuovo link</a>
    </p>

    <?php else: ?>
    <?php if ($err): ?><div class="login-err"><?= $h($err) ?></div><?php endif; ?>
    <form method="post" onsubmit="return validatePw(this)">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="token" value="<?= $h($token) ?>">
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
        Scegli una nuova password per <strong><?= $h($tokenRow['username']) ?></strong>.
      </p>
      <div class="login-field">
        <label for="np">Nuova password</label>
        <input id="np" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="minimo 8 caratteri">
      </div>
      <div class="login-field">
        <label for="np2">Ripeti password</label>
        <input id="np2" name="password2" type="password" required minlength="8" autocomplete="new-password" placeholder="ripeti">
      </div>
      <button type="submit" class="login-btn">Aggiorna password &rarr;</button>
    </form>
    <script>
    function validatePw(f) {
      var p1 = document.getElementById('np');
      var p2 = document.getElementById('np2');
      if (p1.value !== p2.value) { p2.setCustomValidity('Le password non coincidono'); p2.reportValidity(); return false; }
      p2.setCustomValidity(''); return true;
    }
    </script>
    <?php endif; ?>

    <p class="login-foot">Cassa Sala &middot; gestione cassa VLT/AWP</p>
  </div>
</div>
</body></html>
