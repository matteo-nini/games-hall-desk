<?php
/* Output buffer: inietta favicon + manifest nel <head> di ogni pagina automaticamente */
ob_start(function(string $html): string {
    $pos = strpos($html, '</head>');
    if ($pos === false || !function_exists('base_url')) return $html;
    try {
        $fav = htmlspecialchars(base_url('favicon.php'), ENT_QUOTES);
        $man = htmlspecialchars(base_url('manifest.php'), ENT_QUOTES);
    } catch (Throwable) { return $html; }
    $inject = '<link rel="icon" type="image/svg+xml" href="' . $fav . '">'
            . "\n<link rel=\"manifest\" href=\"" . $man . '">'
            . "\n<meta name=\"theme-color\" content=\"#2563eb\">"
            . "\n<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">"
            . "\n<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"black-translucent\">"
            . "\n<meta name=\"mobile-web-app-capable\" content=\"yes\">";
    return substr($html, 0, $pos) . $inject . "\n" . substr($html, $pos);
});
// Autenticazione minima basata su sessione.
require_once __DIR__ . '/db.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    }
}

function current_user(): ?array {
    static $memo = 'unset';
    if ($memo !== 'unset') return $memo;
    start_session();
    if (empty($_SESSION['uid'])) { $memo = null; return null; }
    $st = db()->prepare('SELECT * FROM utenti WHERE id = ? AND attivo = 1');
    $st->execute([$_SESSION['uid']]);
    $memo = $st->fetch() ?: null;
    return $memo;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        $appRoot   = realpath(dirname(__DIR__));
        $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $depth     = ($appRoot && $scriptDir && $scriptDir !== $appRoot && str_starts_with($scriptDir, $appRoot))
            ? substr_count(ltrim(str_replace($appRoot, '', $scriptDir), '/\\'), DIRECTORY_SEPARATOR) + 1
            : 0;
        header('Location: ' . str_repeat('../', $depth) . 'account/login.php');
        exit;
    }
    return $u;
}

function is_responsabile(): bool {
    $u = current_user();
    return $u && $u['ruolo'] === 'responsabile';
}

function is_revisore(): bool {
    $u = current_user();
    return $u && $u['ruolo'] === 'revisore';
}

function require_not_revisore(): void {
    if (is_revisore()) render_403('Questa sezione non è accessibile ai revisori.');
}

function rate_limit_check(string $ip): bool {
    try {
        $pdo = db();
        $window = date('Y-m-d H:i:s', time() - 900);
        $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at > ?');
        $st->execute([$ip, $window]);
        return (int)$st->fetchColumn() >= 5;
    } catch (Throwable) {
        // fail-closed: se il DB non risponde blocchiamo per sicurezza (issue S-02)
        return true;
    }
}

function rate_limit_record(string $ip): void {
    try {
        $pdo = db();
        $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        if (random_int(1, 30) === 1) {
            $old = date('Y-m-d H:i:s', time() - 3600);
            $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$old]);
        }
    } catch (Throwable) {}
}

function login(string $username, string $password): bool {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $pdo = db();
    start_session();
    $st = $pdo->prepare('SELECT * FROM utenti WHERE username = ? AND attivo = 1');
    $st->execute([$username]);
    $u = $st->fetch();
    if (!$u && str_contains($username, '@')) {
        try {
            $st2 = $pdo->prepare('SELECT * FROM utenti WHERE email = ? AND attivo = 1');
            $st2->execute([$username]);
            $rows = $st2->fetchAll();
            if (count($rows) === 1) $u = $rows[0];
        } catch (Throwable) {}
    }
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        try { $pdo->prepare('DELETE FROM login_attempts WHERE ip=?')->execute([$ip]); } catch (Throwable) {}
        return true;
    }
    rate_limit_record($ip);
    return false;
}

function logout(): void {
    start_session();
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    start_session();
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '__')) {
        http_response_code(400);
        exit('Token CSRF non valido. Ricarica la pagina.');
    }
}

function render_403(string $msg = 'Non hai i permessi per accedere a questa pagina.'): void {
    http_response_code(403);
    $appRoot   = realpath(dirname(__DIR__));
    $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $depth = ($appRoot && $scriptDir && $scriptDir !== $appRoot && str_starts_with($scriptDir, $appRoot))
        ? substr_count(ltrim(str_replace($appRoot, '', $scriptDir), '/\\'), DIRECTORY_SEPARATOR) + 1
        : 0;
    $pre = str_repeat('../', $depth);
    $m = htmlspecialchars($msg, ENT_QUOTES);
    echo '<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>403 — Accesso negato</title>
<link rel="stylesheet" href="' . $pre . 'assets/css/core.css">
<link rel="stylesheet" href="' . $pre . 'assets/css/error.css">
</head><body>
<div class="err-wrap">
  <div class="err-icon" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
  <p class="err-code">403</p>
  <h1 class="err-title">Accesso negato</h1>
  <p class="err-desc">' . $m . '</p>
  <div class="err-actions">
    <a href="javascript:history.back()" class="err-back err-back-ghost">&#8592; Indietro</a>
    <a href="' . $pre . 'index.php" class="err-back">Vai alla home</a>
  </div>
  <p class="err-brand">' . htmlspecialchars(function_exists('config') ? (config()['nome_sala'] ?? 'Cassa Sala') : 'Cassa Sala') . ' · Gestione cassa</p>
</div>
</body></html>';
    exit;
}

function require_responsabile(): array {
    $u = require_login();
    if ($u['ruolo'] !== 'responsabile') render_403('Questa sezione è riservata al responsabile.');
    return $u;
}

/* -------- Setup guard: redirect se l'app non è ancora configurata -------- */
(function () {
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (in_array($script, ['setup.php', 'login.php'], true)) return;

    try {
        $cnt = db()->query('SELECT COUNT(*) FROM utenti WHERE ruolo="responsabile"')->fetchColumn();
        if ((int)$cnt > 0) return;
    } catch (Throwable) {
        // DB non raggiungibile o tabelle mancanti
    }

    $appRoot   = realpath(dirname(__DIR__));
    $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $depth = ($appRoot && $scriptDir && $scriptDir !== $appRoot && str_starts_with($scriptDir, $appRoot))
        ? substr_count(ltrim(str_replace($appRoot, '', $scriptDir), '/\\'), DIRECTORY_SEPARATOR) + 1
        : 0;
    header('Location: ' . str_repeat('../', $depth) . 'install/setup.php');
    exit;
})();
