<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$uid  = (int)$user['id'];

/* Auto-migrazione colonne */
try { $pdo->exec('ALTER TABLE utenti ADD COLUMN telefono VARCHAR(30) DEFAULT NULL'); } catch (Throwable) {}
try { $pdo->exec('ALTER TABLE contatti ADD COLUMN utente_id INT DEFAULT NULL'); } catch (Throwable) {}
try { $pdo->exec('ALTER TABLE contatti ADD COLUMN sistema TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable) {}

$ok  = '';
$err = '';

/* =========================================================
   POST
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    /* Cambio nome visualizzato */
    if ($az === 'nome') {
        $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 80);
        if ($nome === '') { $err = 'Il nome non può essere vuoto.'; }
        else {
            $pdo->prepare('UPDATE utenti SET nome=? WHERE id=?')->execute([$nome, $uid]);
            audit('profilo_nome', 'utenti', $uid, "nuovo=$nome");
            $me2 = $pdo->prepare('SELECT * FROM utenti WHERE id=?'); $me2->execute([$uid]); $me2 = $me2->fetch();
            sync_contact_utente($pdo, $uid, $nome, $me2['telefono'] ?? '', $me2['email'] ?? '', $me2['ruolo'] ?? '');
            header('Location: profilo.php?ok=nome'); exit;
        }
    }

    /* Salva numero di telefono */
    if ($az === 'telefono') {
        $tel = mb_substr(trim($_POST['telefono'] ?? ''), 0, 30);
        $pdo->prepare('UPDATE utenti SET telefono=? WHERE id=?')->execute([$tel ?: null, $uid]);
        audit('profilo_telefono', 'utenti', $uid, $tel ?: '(rimosso)');
        $me2 = $pdo->prepare('SELECT * FROM utenti WHERE id=?'); $me2->execute([$uid]); $me2 = $me2->fetch();
        sync_contact_utente($pdo, $uid, $me2['nome'] ?: $me2['username'], $tel, $me2['email'] ?? '', $me2['ruolo'] ?? '');
        header('Location: profilo.php?ok=tel'); exit;
    }

    /* Cambio password */
    if ($az === 'password') {
        $vecchia = $_POST['password_vecchia'] ?? '';
        $nuova   = $_POST['password_nuova']   ?? '';
        $ripeti  = $_POST['password_ripeti']  ?? '';
        $st = $pdo->prepare('SELECT password_hash FROM utenti WHERE id=?');
        $st->execute([$uid]);
        $row = $st->fetch();
        if (!$row || !password_verify($vecchia, $row['password_hash'])) {
            $err = 'Password attuale non corretta.';
        } elseif (mb_strlen($nuova) < 6) {
            $err = 'La nuova password deve avere almeno 6 caratteri.';
        } elseif ($nuova !== $ripeti) {
            $err = 'Le due password non coincidono.';
        } else {
            $hash = password_hash($nuova, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE utenti SET password_hash=? WHERE id=?')->execute([$hash, $uid]);
            audit('profilo_password', 'utenti', $uid);
            header('Location: profilo.php?ok=pwd'); exit;
        }
    }

    /* Upload foto profilo */
    if ($az === 'foto') {
        $isXhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        $file = $_FILES['foto'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $err = 'Errore nel caricamento del file.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $err = 'Formato non supportato. Usa JPG, PNG o WebP.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $err = 'File troppo grande. Massimo 2 MB.';
            } else {
                $dir = __DIR__ . '/uploads/profili/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                    $old = $pdo->prepare('SELECT foto FROM utenti WHERE id=?');
                    $old->execute([$uid]);
                    $oldFoto = ($old->fetch())['foto'] ?? null;
                    if ($oldFoto && file_exists($dir . $oldFoto)) @unlink($dir . $oldFoto);
                    $pdo->prepare('UPDATE utenti SET foto=? WHERE id=?')->execute([$fname, $uid]);
                    audit('profilo_foto', 'utenti', $uid);
                    if ($isXhr) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'src' => 'uploads/profili/' . $fname]); exit; }
                    header('Location: profilo.php?ok=foto'); exit;
                } else {
                    $err = 'Impossibile salvare il file.';
                }
            }
        }
        if ($isXhr && $err) { header('Content-Type: application/json'); echo json_encode(['err' => $err]); exit; }
    }

    /* Rimuovi foto */
    if ($az === 'del_foto') {
        $old = $pdo->prepare('SELECT foto FROM utenti WHERE id=?');
        $old->execute([$uid]);
        $oldFoto = ($old->fetch())['foto'] ?? null;
        $dir = __DIR__ . '/uploads/profili/';
        if ($oldFoto && file_exists($dir . $oldFoto)) @unlink($dir . $oldFoto);
        $pdo->prepare('UPDATE utenti SET foto=NULL WHERE id=?')->execute([$uid]);
        audit('profilo_foto_rimossa', 'utenti', $uid);
        header('Location: profilo.php?ok=foto'); exit;
    }
}

/* =========================================================
   GET
   ========================================================= */
$st = $pdo->prepare('SELECT * FROM utenti WHERE id=?');
$st->execute([$uid]);
$me = $st->fetch();

$okMsg = match ($_GET['ok'] ?? '') {
    'nome' => 'Nome aggiornato.',
    'pwd'  => 'Password cambiata.',
    'foto' => 'Foto profilo aggiornata.',
    'tel'  => 'Telefono aggiornato.',
    default => ''
};

$fotoUrl     = ($me['foto'] ?? null) ? 'uploads/profili/' . $me['foto'] : null;
$rawName     = $me['nome'] ?: $me['username'];
$initial     = avatar_initials($rawName);
$avatarStyle = avatar_style($rawName);
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= csrf_token() ?>">
<title>Profilo</title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/profilo.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div><strong>Profilo</strong></div>
</header>

<?php if ($okMsg): ?><div class="ok"><?= $h($okMsg) ?></div><?php endif; ?>
<?php if ($err):    ?><div class="warn"><?= $h($err) ?></div><?php endif; ?>

<div class="profilo-page">

  <!-- ===== Avatar ===== -->
  <section class="profilo-avatar-section">
    <div class="profilo-avatar-wrap">
      <?php if ($fotoUrl): ?>
        <img src="<?= $h($fotoUrl) ?>" class="profilo-avatar-img" alt="Foto profilo">
      <?php else: ?>
        <div class="profilo-avatar-initial" style="<?= $avatarStyle ?>"><?= $h($initial) ?></div>
      <?php endif; ?>
    </div>
    <div class="profilo-avatar-info">
      <strong><?= $h($me['nome'] ?: $me['username']) ?></strong>
      <span class="muted-text" style="font-size:12px">@<?= $h($me['username']) ?> · <?= $me['ruolo'] === 'responsabile' ? 'Responsabile' : 'Operatore' ?></span>
    </div>
    <div class="profilo-foto-forms">
      <form method="post" enctype="multipart/form-data" class="profilo-foto-upload">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="foto">
        <label class="btn ghost btn-sm" style="cursor:pointer">
          Cambia foto
          <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" style="display:none">
        </label>
      </form>
      <?php if ($fotoUrl): ?>
      <form method="post" onsubmit="return confirm('Rimuovere la foto profilo?')">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="del_foto">
        <button type="submit" class="btn ghost btn-sm" style="color:var(--red)">Rimuovi</button>
      </form>
      <?php endif; ?>
    </div>
  </section>

  <div class="profilo-grid">

    <!-- ===== Nome visualizzato ===== -->
    <section class="profilo-card">
      <h2 class="profilo-card-title">Nome visualizzato</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="nome">
        <div class="field">
          <label for="pnome">Nome</label>
          <input id="pnome" type="text" name="nome" value="<?= $h($me['nome'] ?? '') ?>" placeholder="Nome visualizzato" maxlength="80" required style="width:220px">
        </div>
        <div class="field" style="margin-top:6px">
          <label style="color:var(--faint);font-size:11px">Username (non modificabile)</label>
          <input type="text" value="<?= $h($me['username']) ?>" disabled style="width:220px;opacity:.5">
        </div>
        <button type="submit" style="margin-top:10px">Salva nome</button>
      </form>
    </section>

    <!-- ===== Recapiti ===== -->
    <section class="profilo-card">
      <h2 class="profilo-card-title">Recapiti</h2>
      <form method="post">
        <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="telefono">
        <div class="field">
          <label for="ptel">Numero di telefono</label>
          <input id="ptel" type="tel" name="telefono" value="<?= $h($me['telefono'] ?? '') ?>"
                 placeholder="+39 333 000 0000" maxlength="30" style="width:220px">
        </div>
        <p class="profilo-hint">Viene salvato automaticamente nella rubrica Contatti della sala.</p>
        <button type="submit" style="margin-top:10px">Salva telefono</button>
      </form>
    </section>

    <!-- ===== Password ===== -->
    <section class="profilo-card">
      <h2 class="profilo-card-title">Cambia password</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="password">
        <div class="field">
          <label for="pvecchia">Password attuale</label>
          <input id="pvecchia" type="password" name="password_vecchia" required autocomplete="current-password" style="width:220px">
        </div>
        <div class="field" style="margin-top:6px">
          <label for="pnuova">Nuova password</label>
          <input id="pnuova" type="password" name="password_nuova" required minlength="6" autocomplete="new-password" style="width:220px">
        </div>
        <div class="field" style="margin-top:6px">
          <label for="pripeti">Ripeti nuova password</label>
          <input id="pripeti" type="password" name="password_ripeti" required autocomplete="new-password" style="width:220px">
        </div>
        <button type="submit" style="margin-top:10px">Cambia password</button>
      </form>
    </section>

  </div>
</div>

<div id="crop-modal" class="crop-modal" role="dialog" aria-modal="true" aria-label="Ritaglia foto profilo" hidden>
  <div class="crop-panel">
    <div class="crop-hd">
      <span class="crop-title">Posiziona la foto</span>
      <button class="crop-close" id="crop-close" aria-label="Chiudi">&#x2715;</button>
    </div>
    <div id="crop-stage" class="crop-stage">
      <img id="crop-img" alt="" draggable="false">
    </div>
    <div class="crop-zoom-row">
      <button class="crop-zoom-btn" id="crop-zoom-out" aria-label="Riduci zoom" tabindex="-1">&#x2212;</button>
      <input type="range" id="crop-zoom" class="crop-zoom-range" min="100" max="300" value="100" aria-label="Zoom">
      <button class="crop-zoom-btn" id="crop-zoom-in" aria-label="Aumenta zoom" tabindex="-1">&#x2b;</button>
    </div>
    <div class="crop-foot">
      <button class="btn ghost btn-sm" id="crop-cancel">Annulla</button>
      <button class="btn btn-sm" id="crop-confirm">Usa questa foto</button>
    </div>
  </div>
  <canvas id="crop-canvas" width="256" height="256" hidden></canvas>
</div>

<script src="<?= asset_url('assets/js/profilo.js') ?>"></script>
</body></html>
