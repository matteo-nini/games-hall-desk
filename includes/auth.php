<?php
ob_start();
// Autenticazione minima basata su sessione.
require_once __DIR__ . '/db.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            // In produzione (HTTPS) imposta 'cookie_secure' => true
        ]);
    }
}

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT * FROM utenti WHERE id = ? AND attivo = 1');
    $st->execute([$_SESSION['uid']]);
    return $st->fetch() ?: null;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        $appRoot   = realpath(dirname(__DIR__));
        $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $depth     = ($appRoot && $scriptDir && $scriptDir !== $appRoot && str_starts_with($scriptDir, $appRoot))
            ? substr_count(ltrim(str_replace($appRoot, '', $scriptDir), '/\\'), DIRECTORY_SEPARATOR) + 1
            : 0;
        header('Location: ' . str_repeat('../', $depth) . 'login.php');
        exit;
    }
    return $u;
}

function is_responsabile(): bool {
    $u = current_user();
    return $u && $u['ruolo'] === 'responsabile';
}

function login(string $username, string $password): bool {
    start_session();
    $st = db()->prepare('SELECT * FROM utenti WHERE username = ? AND attivo = 1');
    $st->execute([$username]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        return true;
    }
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
  <p class="err-brand">Games Palace · Gestione cassa</p>
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
    header('Location: ' . str_repeat('../', $depth) . 'setup.php');
    exit;
})();
