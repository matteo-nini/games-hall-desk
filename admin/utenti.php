<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$ok   = '';
$err  = '';

/* =========================================================
   POST
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    if ($az === 'add') {
        $username = mb_substr(trim($_POST['username'] ?? ''), 0, 60);
        $nome     = mb_substr(trim($_POST['nome']     ?? ''), 0, 80) ?: null;
        $pw       = $_POST['password'] ?? '';
        $ruolo    = ($_POST['ruolo'] ?? '') === 'responsabile' ? 'responsabile' : 'operatore';
        if ($username === '' || strlen($pw) < 8) {
            $err = 'Username obbligatorio e password di almeno 8 caratteri.';
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO utenti (username, password_hash, nome, ruolo) VALUES (?,?,?,?)'
                )->execute([$username, password_hash($pw, PASSWORD_DEFAULT), $nome, $ruolo]);
                audit('utente_aggiunto', 'utenti', (int)$pdo->lastInsertId(), $username);
                header('Location: utenti.php?ok=add'); exit;
            } catch (Throwable $e) {
                $err = 'Username già in uso oppure errore di sistema.';
            }
        }
    }

    if ($az === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$user['id']) {
            $err = 'Non puoi disattivare il tuo stesso account.';
        } elseif ($id > 0) {
            $pdo->prepare('UPDATE utenti SET attivo = 1 - attivo WHERE id=?')->execute([$id]);
            audit('utente_toggle', 'utenti', $id);
            header('Location: utenti.php?ok=toggle'); exit;
        }
    }

    if ($az === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $pw = $_POST['password'] ?? '';
        if ($id <= 0 || strlen($pw) < 8) {
            $err = 'Password di almeno 8 caratteri richiesta.';
        } else {
            $pdo->prepare('UPDATE utenti SET password_hash=? WHERE id=?')
                ->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
            audit('utente_reset_pw', 'utenti', $id);
            header('Location: utenti.php?ok=reset'); exit;
        }
    }

    if ($az === 'edit_nome') {
        $id   = (int)($_POST['id'] ?? 0);
        $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 80);
        if ($id > 0) {
            $pdo->prepare('UPDATE utenti SET nome=? WHERE id=?')->execute([$nome ?: null, $id]);
            audit('utente_edit_nome', 'utenti', $id, $nome);
            header('Location: utenti.php?ok=nome'); exit;
        }
    }
}

/* =========================================================
   GET
   ========================================================= */
$utenti   = $pdo->query('SELECT * FROM utenti ORDER BY attivo DESC, ruolo DESC, username')->fetchAll();
$n_attivi = count(array_filter($utenti, fn($u) => (int)$u['attivo'] === 1));
$n_totale = count($utenti);

$okMsg = match ($_GET['ok'] ?? '') {
    'add'    => 'Utente creato.',
    'toggle' => 'Stato aggiornato.',
    'reset'  => 'Password aggiornata.',
    'nome'   => 'Nome aggiornato.',
    default  => ''
};
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Utenti</title>
<link rel="stylesheet" href="<?= base_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/utenti.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div style="display:flex;align-items:center;gap:10px">
    <strong>Gestione utenti</strong>
    <span class="ul-count-chip"><?= $n_attivi ?> attivi su <?= $n_totale ?></span>
  </div>
  <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-nuovo').showModal()">+ Nuovo utente</button>
</header>

<?php if ($okMsg): ?><div class="ok"><?= $h($okMsg) ?></div><?php endif; ?>
<?php if ($err):   ?><div class="warn"><?= $h($err) ?></div><?php endif; ?>

<!-- ========== Dialog: nuovo utente ========== -->
<dialog id="dlg-nuovo" class="form-dialog">
  <div class="dlg-head">
    <strong>Nuovo utente</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form" id="form-nuovo" onsubmit="return validateNuovo(this)">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="add">
    <div class="ul-form-grid">
      <div class="field">
        <label for="nu-nome">Nome visualizzato</label>
        <input id="nu-nome" type="text" name="nome" placeholder="es. Mario Rossi" maxlength="80">
      </div>
      <div class="field">
        <label for="nu-user">Username <span class="ul-req">*</span></label>
        <input id="nu-user" type="text" name="username" required placeholder="es. mario.rossi" maxlength="60" autocomplete="off">
      </div>
      <div class="field">
        <label for="nu-pw">Password <span class="ul-req">*</span></label>
        <input id="nu-pw" type="password" name="password" required minlength="8" placeholder="minimo 8 caratteri" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="nu-rpw">Ripeti password <span class="ul-req">*</span></label>
        <input id="nu-rpw" type="password" required minlength="8" placeholder="ripeti" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="nu-ruolo">Ruolo</label>
        <select id="nu-ruolo" name="ruolo">
          <option value="operatore">Operatore</option>
          <option value="responsabile">Responsabile</option>
        </select>
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Crea utente</button>
    </div>
  </form>
</dialog>

<!-- ========== Dialog: reset password ========== -->
<dialog id="dlg-reset" class="form-dialog">
  <div class="dlg-head">
    <strong>Reset password</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form" onsubmit="return validateReset(this)">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="reset">
    <input type="hidden" name="id" id="reset-uid">
    <p class="ul-reset-for" id="reset-name"></p>
    <div class="ul-form-grid">
      <div class="field">
        <label>Nuova password <span class="ul-req">*</span></label>
        <input type="password" name="password" id="reset-pw" required minlength="8" placeholder="minimo 8 caratteri" autocomplete="new-password">
      </div>
      <div class="field">
        <label>Ripeti <span class="ul-req">*</span></label>
        <input type="password" id="reset-rpw" required minlength="8" placeholder="ripeti" autocomplete="new-password">
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Aggiorna password</button>
    </div>
  </form>
</dialog>

<!-- ========== Dialog: edit nome ========== -->
<dialog id="dlg-nome" class="form-dialog">
  <div class="dlg-head">
    <strong>Modifica nome</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="edit_nome">
    <input type="hidden" name="id" id="nome-uid">
    <div class="field">
      <label>Nome visualizzato</label>
      <input type="text" name="nome" id="nome-val" maxlength="80" placeholder="es. Mario Rossi" style="width:100%">
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Salva</button>
    </div>
  </form>
</dialog>

<!-- ========== Tabella utenti ========== -->
<div class="ul-page">
  <?php if (empty($utenti)): ?>
  <div class="ul-empty">
    <div class="ul-empty-icon">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <p>Nessun utente ancora. Crea il primo con il pulsante in alto a destra.</p>
  </div>
  <?php else: ?>

  <div class="ul-table-wrap">
    <table class="ul-table">
      <thead>
        <tr>
          <th class="ul-th-ava"></th>
          <th>Utente</th>
          <th>Ruolo</th>
          <th>Stato</th>
          <th class="ul-th-act">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($utenti as $u):
          $isMe     = (int)$u['id'] === (int)$user['id'];
          $isActive = (bool)(int)$u['attivo'];
          $isResp   = $u['ruolo'] === 'responsabile';
          $displayN = $u['nome'] ?: $u['username'];
          $initial  = mb_strtoupper(mb_substr($displayN, 0, 1, 'UTF-8'), 'UTF-8');
          $foto     = $u['foto'] ?? null;
        ?>
        <tr class="ul-row<?= $isActive ? '' : ' ul-row-off' ?>">

          <td class="ul-td-ava">
            <?php if ($foto): ?>
              <img src="uploads/profili/<?= $h($foto) ?>" class="ul-ava ul-ava-img" alt="">
            <?php else: ?>
              <div class="ul-ava <?= $isActive ? 'ul-ava-active' : 'ul-ava-inactive' ?>" aria-hidden="true"><?= $h($initial) ?></div>
            <?php endif; ?>
          </td>

          <td class="ul-td-user">
            <span class="ul-nome"><?= $h($displayN) ?></span>
            <?php if ($u['nome']): ?>
              <span class="ul-username">@<?= $h($u['username']) ?></span>
            <?php endif; ?>
            <?php if ($isMe): ?><span class="ul-you">tu</span><?php endif; ?>
          </td>

          <td>
            <span class="ul-badge ul-role-<?= $isResp ? 'resp' : 'op' ?>">
              <?= $isResp ? 'Responsabile' : 'Operatore' ?>
            </span>
          </td>

          <td>
            <span class="ul-badge ul-status-<?= $isActive ? 'on' : 'off' ?>">
              <?= $isActive ? 'Attivo' : 'Disattivo' ?>
            </span>
          </td>

          <td class="ul-td-act">
            <div class="ul-act-row">
              <button type="button" class="ul-btn ul-btn-nome"
                      data-uid="<?= (int)$u['id'] ?>"
                      data-name="<?= $h($displayN) ?>">
                Modifica nome
              </button>
              <button type="button" class="ul-btn ul-btn-reset"
                      data-uid="<?= (int)$u['id'] ?>"
                      data-name="<?= $h($displayN) ?>">
                Reset pw
              </button>
              <form method="post" class="ul-inline-form ul-toggle-form" data-name="<?= $h($displayN) ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="azione" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="ul-btn <?= $isActive ? 'ul-btn-off' : 'ul-btn-on' ?>"
                        <?= $isMe ? 'disabled title="Non puoi disattivare il tuo account"' : '' ?>>
                  <?= $isActive ? 'Disattiva' : 'Attiva' ?>
                </button>
              </form>
            </div>
          </td>

        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php endif; ?>
</div>

<script>
(function () {
  /* Toggle confirm via data-attrs */
  document.querySelectorAll('.ul-toggle-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var btn  = f.querySelector('button[type=submit]');
      var name = f.dataset.name || '';
      if (btn && btn.classList.contains('ul-btn-off') && name) {
        if (!confirm('Disattivare ' + name + '?')) e.preventDefault();
      }
    });
  });

  /* Reset pw dialog */
  document.querySelectorAll('.ul-btn-reset').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('reset-uid').value = btn.dataset.uid;
      document.getElementById('reset-name').textContent = 'Reset password per: ' + btn.dataset.name;
      document.getElementById('reset-pw').value  = '';
      document.getElementById('reset-rpw').value = '';
      document.getElementById('dlg-reset').showModal();
    });
  });

  /* Edit nome dialog */
  document.querySelectorAll('.ul-btn-nome').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('nome-uid').value = btn.dataset.uid;
      document.getElementById('nome-val').value = btn.dataset.name;
      document.getElementById('dlg-nome').showModal();
    });
  });

  function pairValidation(pw1Id, pw2Id) {
    [pw1Id, pw2Id].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', function () {
        document.getElementById(pw2Id).setCustomValidity('');
      });
    });
  }
  pairValidation('nu-pw', 'nu-rpw');
  pairValidation('reset-pw', 'reset-rpw');
}());

function validateNuovo(f) {
  var pw = document.getElementById('nu-pw');
  var rp = document.getElementById('nu-rpw');
  if (pw.value !== rp.value) { rp.setCustomValidity('Le password non coincidono'); rp.reportValidity(); return false; }
  rp.setCustomValidity(''); return true;
}
function validateReset(f) {
  var pw = document.getElementById('reset-pw');
  var rp = document.getElementById('reset-rpw');
  if (pw.value !== rp.value) { rp.setCustomValidity('Le password non coincidono'); rp.reportValidity(); return false; }
  rp.setCustomValidity(''); return true;
}
</script>
</body></html>
