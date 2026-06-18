<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib.php';
$user = require_responsabile();
$pdo  = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['azione'] ?? '';
    if ($a === 'add') {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        $nome = trim($_POST['nome'] ?? '');
        $ruolo = ($_POST['ruolo'] ?? 'operatore') === 'responsabile' ? 'responsabile' : 'operatore';
        if ($u === '' || strlen($p) < 8) {
            $msg = 'Username obbligatorio e password di almeno 8 caratteri.';
        } else {
            try {
                $pdo->prepare('INSERT INTO utenti (username,password_hash,nome,ruolo) VALUES (?,?,?,?)')
                    ->execute([$u, password_hash($p, PASSWORD_DEFAULT), $nome ?: null, $ruolo]);
                audit('utente_add','utenti',null,$u); $msg = 'Utente creato.';
            } catch (Throwable $e) { $msg = 'Errore (username gia in uso?).'; }
        }
    } elseif ($a === 'toggle') {
        $id = (int)$_POST['id'];
        if ($id !== (int)$user['id']) { // non disattivare se stessi
            $pdo->prepare('UPDATE utenti SET attivo = 1-attivo WHERE id=?')->execute([$id]);
            audit('utente_toggle','utenti',$id);
        } else $msg = 'Non puoi disattivare il tuo stesso account.';
    } elseif ($a === 'reset') {
        $id = (int)$_POST['id']; $p = $_POST['password'] ?? '';
        if (strlen($p) < 8) $msg = 'Password troppo corta.';
        else { $pdo->prepare('UPDATE utenti SET password_hash=? WHERE id=?')
                   ->execute([password_hash($p, PASSWORD_DEFAULT), $id]);
               audit('utente_reset_pw','utenti',$id); $msg = 'Password aggiornata.'; }
    }
}
$utenti = $pdo->query('SELECT * FROM utenti ORDER BY attivo DESC, username')->fetchAll();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Utenti</title><link rel="stylesheet" href="styles.css"></head><body>
<?php require __DIR__ . '/nav.php'; top_menu($user); ?>
<header class="topbar"><div><strong>Utenti</strong></div></header>
<?php if ($msg): ?><div class="ok"><?= $h($msg) ?></div><?php endif; ?>

<div class="riepilogo" style="max-width:760px">
  <h3>Nuovo utente</h3>
  <form method="post" class="inline">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="azione" value="add">
    <input name="nome" placeholder="Nome">
    <input name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password (min 8)" required>
    <select name="ruolo"><option value="operatore">operatore</option><option value="responsabile">responsabile</option></select>
    <button>Crea</button>
  </form>

  <h3 style="margin-top:18px">Elenco</h3>
  <div class="rowhead"><span>Utente</span><span>Nome</span><span>Ruolo</span><span>Stato</span><span>Azioni</span></div>
  <?php foreach ($utenti as $u): ?>
  <div class="editrow">
    <span class="cell"><?= $h($u['username']) ?></span>
    <span class="cell"><?= $h($u['nome']) ?></span>
    <span class="cell"><?= $h($u['ruolo']) ?></span>
    <span class="cell"><?= $u['attivo'] ? 'attivo' : '<span style="color:#999">disattivo</span>' ?></span>
    <span class="cell actions-inline">
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>">
        <button <?= $u['id']==$user['id']?'disabled':'' ?>><?= $u['attivo']?'Disattiva':'Attiva' ?></button>
      </form>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="reset"><input type="hidden" name="id" value="<?= $u['id'] ?>">
        <input type="password" name="password" placeholder="nuova pw" style="width:110px">
        <button>Reset</button>
      </form>
    </span>
  </div>
  <?php endforeach; ?>
</div>
</body></html>
