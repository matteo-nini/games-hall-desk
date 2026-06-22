<?php
// =====================================================================
//  SETUP — crea il primo utente RESPONSABILE. Da eseguire una sola volta,
//  poi ELIMINARE questo file dal server.
// =====================================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth.php';

$n = (int) db()->query('SELECT COUNT(*) AS c FROM utenti')->fetch()['c'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $n === 0) {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    if ($u === '' || strlen($p) < 8) {
        $msg = 'Username obbligatorio e password di almeno 8 caratteri.';
    } else {
        db()->prepare('INSERT INTO utenti (username, password_hash, nome, ruolo) VALUES (?,?,?,"responsabile")')
            ->execute([$u, password_hash($p, PASSWORD_DEFAULT), $nome ?: null]);
        $msg = 'Utente creato. Ora ELIMINA setup.php e vai al login.';
        $n = 1;
    }
}
?>
<!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup</title><link rel="stylesheet" href="assets/css/core.css"></head><body>
<div class="login-box">
<h1>Setup iniziale</h1>
<?php if ($msg): ?><p class="<?= str_contains($msg,'creato')?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($n === 0): ?>
<form method="post">
  <label>Nome <input name="nome"></label>
  <label>Username <input name="username" required></label>
  <label>Password (min 8) <input type="password" name="password" required></label>
  <button type="submit">Crea responsabile</button>
</form>
<?php else: ?>
<p>Database gia configurato. <a href="login.php">Vai al login</a>. Ricorda di eliminare <code>setup.php</code>.</p>
<?php endif; ?>
</div></body></html>
