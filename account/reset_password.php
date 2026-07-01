<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
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
            /* Invalida token precedenti per questo utente */
            $pdo->prepare('UPDATE password_reset SET usato=1 WHERE utente_id=? AND usato=0')
                ->execute([$u['id']]);

            $token   = bin2hex(random_bytes(32));
            $scade   = date('Y-m-d H:i:s', time() + 3600);
            $pdo->prepare('INSERT INTO password_reset (utente_id, token, scade_il) VALUES (?,?,?)')
                ->execute([$u['id'], $token, $scade]);

            $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetUrl  = $scheme . '://' . $host . base_url('account/reset_confirm.php') . '?token=' . urlencode($token);
            $nomeSala  = $cfg['nome_sala'] ?? 'Cassa Sala';
            $from      = $sett['mail_from'] ?? '';
            $accent    = ($sett['brand_accent'] ?? '') ?: '#111827';
            $logoPath  = $sett['logo_path'] ?? null;
            $logoUrl   = $logoPath ? ($scheme . '://' . $host . base_url('account/uploads/sala/' . rawurlencode($logoPath))) : null;
            $hE        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

            $subject = "Reset password \xe2\x80\x94 $nomeSala";

            $hdr = '<div style="background:' . $accent . ';padding:24px 28px">';
            if ($logoUrl) {
                $hdr .= '<img src="' . $hE($logoUrl) . '" alt="' . $hE($nomeSala)
                      . '" style="height:44px;width:auto;display:block;margin-bottom:10px">';
                $hdr .= '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700">Reset password</h1>';
            } else {
                $hdr .= '<p style="margin:0 0 3px;color:rgba(255,255,255,.65);font-size:11px;letter-spacing:.08em;text-transform:uppercase">' . $hE($nomeSala) . '</p>'
                      . '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700">Reset password</h1>';
            }
            $hdr .= '</div>';

            $body = '<!doctype html><html lang="it"><head><meta charset="utf-8">'
                  . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
                  . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif">'
                  . '<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb">'
                  . $hdr
                  . '<div style="padding:28px 28px 20px">'
                  . '<p style="margin:0 0 14px;font-size:15px;color:#111827">Ciao,</p>'
                  . '<p style="margin:0 0 24px;font-size:14px;color:#374151;line-height:1.7">'
                  . 'Hai richiesto il reset della password su <strong>' . $hE($nomeSala) . '</strong>.<br>'
                  . 'Clicca il pulsante qui sotto per sceglierne una nuova. Il link è valido per <strong>1&nbsp;ora</strong>.'
                  . '</p>'
                  . '<div style="text-align:center;margin:28px 0">'
                  . '<a href="' . $hE($resetUrl) . '" style="display:inline-block;background:' . $accent
                  . ';color:#fff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 32px;border-radius:8px">'
                  . 'Reimposta la password &rarr;</a>'
                  . '</div>'
                  . '<p style="margin:0 0 6px;font-size:12px;color:#9ca3af">Se il pulsante non funziona, copia questo link nel browser:</p>'
                  . '<p style="margin:0 0 24px;font-size:11px;color:#6b7280;word-break:break-all">' . $hE($resetUrl) . '</p>'
                  . '<div style="background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:12px 14px">'
                  . '<p style="margin:0;font-size:12px;color:#854d0e;line-height:1.5">'
                  . '&#9888;&nbsp; Se non hai richiesto tu questo reset, ignora questa email. La tua password rimarr&agrave; invariata.'
                  . '</p></div>'
                  . '</div>'
                  . '<div style="padding:14px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af">'
                  . $hE($nomeSala) . ' &middot; sistema gestione cassa VLT/AWP'
                  . '</div>'
                  . '</div></body></html>';

            $headers  = 'From: ' . ($from ?: 'noreply@cassasala.it') . "\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n";

            @mail($u['email'], $subject, $body, $headers);
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
