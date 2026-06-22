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

function require_responsabile(): array {
    $u = require_login();
    if ($u['ruolo'] !== 'responsabile') { http_response_code(403); exit('Riservato al responsabile.'); }
    return $u;
}
