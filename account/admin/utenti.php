<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
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
        $ruolo    = in_array($_POST['ruolo'] ?? '', ['responsabile','revisore'], true) ? $_POST['ruolo'] : 'operatore';
        if ($username === '' || strlen($pw) < 8) {
            $err = 'Username obbligatorio e password di almeno 8 caratteri.';
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO utenti (username, password_hash, nome, ruolo) VALUES (?,?,?,?)'
                )->execute([$username, password_hash($pw, PASSWORD_DEFAULT), $nome, $ruolo]);
                audit('utente_aggiunto', 'utenti', (int)$pdo->lastInsertId(), $username);
                header('Location: utenti.php?ok=add'); exit;
            } catch (Throwable) {
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

    if ($az === 'change_ruolo') {
        $id    = (int)($_POST['id'] ?? 0);
        $ruolo = in_array($_POST['ruolo'] ?? '', ['responsabile','revisore'], true) ? $_POST['ruolo'] : 'operatore';
        if ($id > 0 && $id !== (int)$user['id']) {
            $pdo->prepare('UPDATE utenti SET ruolo=? WHERE id=?')->execute([$ruolo, $id]);
            audit('utente_change_ruolo', 'utenti', $id, $ruolo);
            header('Location: utenti.php?ok=ruolo'); exit;
        }
    }
}

/* =========================================================
   GET
   ========================================================= */
$utenti   = $pdo->query('SELECT * FROM utenti ORDER BY attivo DESC, ruolo DESC, username')->fetchAll();
$n_attivi = count(array_filter($utenti, fn($u) => (int)$u['attivo'] === 1));
$n_resp   = count(array_filter($utenti, fn($u) => $u['ruolo'] === 'responsabile'));
$n_rev    = count(array_filter($utenti, fn($u) => $u['ruolo'] === 'revisore'));

$okMsg = match ($_GET['ok'] ?? '') {
    'add'    => 'Utente creato.',
    'toggle' => 'Stato aggiornato.',
    'reset'  => 'Password aggiornata.',
    'nome'   => 'Nome aggiornato.',
    'ruolo'  => 'Ruolo aggiornato.',
    default  => ''
};
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Utenti · <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/utenti.css') ?>">
</head><body>
<?php require __DIR__ . '/../../includes/nav.php'; top_menu($user); ?>

<header class="topbar sh-top">
  <div class="ul-header-left">
    <strong>Utenti</strong>
    <div class="ul-chips">
      <span class="ul-chip"><?= $n_attivi ?> <?= $n_attivi === 1 ? 'attivo' : 'attivi' ?></span>
      <?php if ($n_resp > 0): ?>
      <span class="ul-chip ul-chip-accent"><?= $n_resp ?> <?= $n_resp === 1 ? 'responsabile' : 'responsabili' ?></span>
      <?php endif; ?>
      <?php if ($n_rev > 0): ?>
      <span class="ul-chip"><?= $n_rev ?> <?= $n_rev === 1 ? 'revisore' : 'revisori' ?></span>
      <?php endif; ?>
    </div>
  </div>
  <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-nuovo').showModal()">
    <svg width="11" height="11" viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5.5 1v9M1 5.5h9"/></svg>
    Nuovo utente
  </button>
</header>

<?php if ($okMsg): ?><div class="ok" role="alert"><?= $h($okMsg) ?></div><?php endif; ?>
<?php if ($err):   ?><div class="warn" role="alert"><?= $h($err) ?></div><?php endif; ?>

<!-- ========== Dialog: nuovo utente ========== -->
<dialog id="dlg-nuovo" class="form-dialog">
  <div class="dlg-head">
    <strong>Nuovo utente</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form" onsubmit="return validateNuovo(this)">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="add">
    <div class="ul-form-grid">
      <div class="ul-field ul-field-full">
        <label for="nu-nome">Nome visualizzato</label>
        <input id="nu-nome" type="text" name="nome" placeholder="es. Mario Rossi" maxlength="80">
      </div>
      <div class="ul-field">
        <label for="nu-user">Username <span class="ul-req">*</span></label>
        <input id="nu-user" type="text" name="username" required placeholder="es. mario.rossi" maxlength="60" autocomplete="off">
      </div>
      <div class="ul-field ul-field-full">
        <label for="nu-ruolo">Ruolo</label>
        <select id="nu-ruolo" name="ruolo" onchange="document.getElementById('ruolo-desc').textContent=({operatore:'Accesso completo alla cassa giornaliera e alle sezioni operative.',responsabile:'Accesso completo incluse impostazioni, gestione utenti e macchine.',revisore:'Solo visualizzazione report settimanali, mensili e annuali. Nessun accesso alle operazioni di cassa.'})[this.value]||''">
          <option value="operatore">Operatore</option>
          <option value="responsabile">Responsabile</option>
          <option value="revisore">Revisore</option>
        </select>
        <p id="ruolo-desc" class="ul-field-hint">Accesso completo alla cassa giornaliera e alle sezioni operative.</p>
      </div>
      <div class="ul-field">
        <label for="nu-pw">Password <span class="ul-req">*</span></label>
        <input id="nu-pw" type="password" name="password" required minlength="8" placeholder="minimo 8 caratteri" autocomplete="new-password">
      </div>
      <div class="ul-field">
        <label for="nu-rpw">Ripeti password <span class="ul-req">*</span></label>
        <input id="nu-rpw" type="password" required minlength="8" placeholder="ripeti" autocomplete="new-password">
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
    <p class="ul-reset-ctx" id="reset-name"></p>
    <div class="ul-form-grid">
      <div class="ul-field">
        <label>Nuova password <span class="ul-req">*</span></label>
        <input type="password" name="password" id="reset-pw" required minlength="8" placeholder="minimo 8 caratteri" autocomplete="new-password">
      </div>
      <div class="ul-field">
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

<!-- ========== Dialog: modifica nome ========== -->
<dialog id="dlg-nome" class="form-dialog">
  <div class="dlg-head">
    <strong>Modifica nome</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="edit_nome">
    <input type="hidden" name="id" id="nome-uid">
    <div class="ul-field">
      <label>Nome visualizzato</label>
      <input type="text" name="nome" id="nome-val" maxlength="80" placeholder="es. Mario Rossi">
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Salva</button>
    </div>
  </form>
</dialog>

<!-- ========== Dialog: cambia ruolo ========== -->
<dialog id="dlg-ruolo" class="form-dialog">
  <div class="dlg-head">
    <strong>Cambia ruolo</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="change_ruolo">
    <input type="hidden" name="id" id="ruolo-uid">
    <p class="ul-reset-ctx" id="ruolo-ctx"></p>
    <div class="ul-field ul-field-full">
      <label for="ruolo-select">Nuovo ruolo</label>
      <select id="ruolo-select" name="ruolo" onchange="document.getElementById('ruolo-chg-desc').textContent=({operatore:'Accesso completo alla cassa giornaliera e alle sezioni operative.',responsabile:'Accesso completo incluse impostazioni, gestione utenti e macchine.',revisore:'Solo visualizzazione report settimanali, mensili e annuali. Nessun accesso alle operazioni di cassa.'})[this.value]||''">
        <option value="operatore">Operatore</option>
        <option value="responsabile">Responsabile</option>
        <option value="revisore">Revisore</option>
      </select>
      <p id="ruolo-chg-desc" class="ul-field-hint"></p>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Aggiorna ruolo</button>
    </div>
  </form>
</dialog>

<!-- ========== Lista utenti ========== -->
<div class="ul-page">
  <?php if (empty($utenti)): ?>
  <div class="ul-empty">
    <div class="ul-empty-icon">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <p class="ul-empty-title">Nessun utente ancora</p>
    <p class="ul-empty-sub">Crea il primo con il pulsante in alto a destra.</p>
  </div>
  <?php else: ?>

  <div class="ul-table-wrap">
    <table class="ul-table">
      <thead>
        <tr>
          <th class="ul-th-ava" aria-hidden="true"></th>
          <th data-sort="text">Utente</th>
          <th data-sort="text">Ruolo</th>
          <th data-sort="text">Stato</th>
          <th class="ul-th-menu" aria-hidden="true"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($utenti as $u):
          $isMe     = (int)$u['id'] === (int)$user['id'];
          $isActive = (bool)(int)$u['attivo'];
          $isResp   = $u['ruolo'] === 'responsabile';
          $isRev    = $u['ruolo'] === 'revisore';
          $displayN = $u['nome'] ?: $u['username'];
          $initial  = mb_strtoupper(mb_substr($displayN, 0, 1, 'UTF-8'), 'UTF-8');
          $foto     = $u['foto'] ?? null;
          $cIdx     = abs(crc32($u['username'])) % 6;
        ?>
        <tr class="ul-row<?= $isActive ? '' : ' ul-row-off' ?>">

          <td class="ul-td-ava">
            <?php if ($foto): ?>
              <img src="<?= base_url('account/uploads/profili/') . $h($foto) ?>" class="ul-ava ul-ava-img" alt="">
            <?php else: ?>
              <div class="ul-ava ul-ava-c<?= $cIdx ?>" aria-hidden="true"><?= $h($initial) ?></div>
            <?php endif; ?>
          </td>

          <td class="ul-td-user" data-val="<?= $h(mb_strtolower($displayN, 'UTF-8')) ?>">
            <span class="ul-nome">
              <?= $h($displayN) ?>
              <?php if ($isMe): ?><span class="ul-you">tu</span><?php endif; ?>
            </span>
            <?php if ($u['nome']): ?>
              <span class="ul-username">@<?= $h($u['username']) ?></span>
            <?php endif; ?>
          </td>

          <td>
            <span class="ul-badge ul-role-<?= $isResp ? 'resp' : ($isRev ? 'rev' : 'op') ?>">
              <?= $isResp ? 'Responsabile' : ($isRev ? 'Revisore' : 'Operatore') ?>
            </span>
          </td>

          <td>
            <span class="ul-badge ul-status-<?= $isActive ? 'on' : 'off' ?>">
              <?= $isActive ? 'Attivo' : 'Disattivo' ?>
            </span>
          </td>

          <td class="ul-td-menu">
            <div class="ul-action">
              <button type="button" class="ul-menu-btn" aria-label="Azioni per <?= $h($displayN) ?>">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="currentColor" aria-hidden="true">
                  <circle cx="7.5" cy="2.5" r="1.5"/>
                  <circle cx="7.5" cy="7.5" r="1.5"/>
                  <circle cx="7.5" cy="12.5" r="1.5"/>
                </svg>
              </button>
              <div class="ul-menu" role="menu">
                <button type="button" class="ul-menu-item" role="menuitem"
                        data-action="nome" data-uid="<?= (int)$u['id'] ?>" data-name="<?= $h($displayN) ?>">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 2.5a2.1 2.1 0 0 1 2.5 2.5L5 14H2v-3L11 2.5z"/></svg>
                  Modifica nome
                </button>
                <button type="button" class="ul-menu-item" role="menuitem"
                        data-action="reset" data-uid="<?= (int)$u['id'] ?>" data-name="<?= $h($displayN) ?>">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="10" height="8" rx="1.5"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>
                  Reset password
                </button>
                <button type="button" class="ul-menu-item" role="menuitem"
                        data-action="ruolo" data-uid="<?= (int)$u['id'] ?>" data-name="<?= $h($displayN) ?>" data-ruolo="<?= $h($u['ruolo']) ?>"
                        <?= $isMe ? 'disabled title="Non puoi cambiare il tuo ruolo"' : '' ?>>
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 4h11M9 2l3 2-3 2M15 12H4M6 10l-3 2 3 2"/></svg>
                  Cambia ruolo
                </button>
                <div class="ul-menu-sep" role="separator"></div>
                <form method="post" class="ul-menu-form ul-toggle-form" data-name="<?= $h($displayN) ?>">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="azione" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" role="menuitem"
                          class="ul-menu-item<?= $isActive ? ' ul-menu-danger' : ' ul-menu-ok' ?>"
                          <?= $isMe ? 'disabled title="Non puoi disattivare il tuo account"' : '' ?>>
                    <?php if ($isActive): ?>
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6.5"/><path d="M5.5 5.5l5 5M10.5 5.5l-5 5"/></svg>
                    Disattiva utente
                    <?php else: ?>
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6.5"/><path d="M5.5 8.5l2 2 3-3.5"/></svg>
                    Attiva utente
                    <?php endif; ?>
                  </button>
                </form>
              </div>
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
  /* ---- Action dropdown (position:fixed to escape overflow:hidden) ---- */
  function closeAll() {
    document.querySelectorAll('.ul-menu.open').forEach(function (m) {
      m.classList.remove('open');
      m.removeAttribute('style');
    });
    document.querySelectorAll('.ul-menu-btn.active').forEach(function (b) {
      b.classList.remove('active');
    });
  }

  document.querySelectorAll('.ul-menu-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var menu   = btn.nextElementSibling;
      var isOpen = menu.classList.contains('open');
      closeAll();
      if (isOpen) return;
      var rect = btn.getBoundingClientRect();
      menu.style.top   = (rect.bottom + 4) + 'px';
      menu.style.right = (window.innerWidth - rect.right) + 'px';
      menu.classList.add('open');
      btn.classList.add('active');
    });
  });

  document.addEventListener('click', closeAll);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll();
  });

  /* ---- Menu item actions ---- */
  document.querySelectorAll('.ul-menu-item[data-action]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeAll();
      var uid  = btn.dataset.uid;
      var name = btn.dataset.name;
      if (btn.dataset.action === 'reset') {
        document.getElementById('reset-uid').value = uid;
        document.getElementById('reset-name').textContent = 'Reset password per: ' + name;
        document.getElementById('reset-pw').value  = '';
        document.getElementById('reset-rpw').value = '';
        document.getElementById('dlg-reset').showModal();
      }
      if (btn.dataset.action === 'nome') {
        document.getElementById('nome-uid').value = uid;
        document.getElementById('nome-val').value = name;
        document.getElementById('dlg-nome').showModal();
      }
      if (btn.dataset.action === 'ruolo') {
        document.getElementById('ruolo-uid').value = uid;
        document.getElementById('ruolo-ctx').textContent = 'Cambia ruolo per: ' + name;
        var sel = document.getElementById('ruolo-select');
        sel.value = btn.dataset.ruolo || 'operatore';
        var descs = {
          operatore:    'Accesso completo alla cassa giornaliera e alle sezioni operative.',
          responsabile: 'Accesso completo incluse impostazioni, gestione utenti e macchine.',
          revisore:     'Solo visualizzazione report settimanali, mensili e annuali. Nessun accesso alle operazioni di cassa.'
        };
        document.getElementById('ruolo-chg-desc').textContent = descs[sel.value] || '';
        document.getElementById('dlg-ruolo').showModal();
      }
    });
  });

  /* ---- Toggle confirm ---- */
  document.querySelectorAll('.ul-toggle-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var danger = f.querySelector('.ul-menu-danger');
      if (danger && !danger.disabled && f.dataset.name) {
        if (!confirm('Disattivare ' + f.dataset.name + '?')) e.preventDefault();
      }
    });
  });

  /* ---- Password pair validation ---- */
  function pairVal(id1, id2) {
    [id1, id2].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', function () {
        document.getElementById(id2).setCustomValidity('');
      });
    });
  }
  pairVal('nu-pw', 'nu-rpw');
  pairVal('reset-pw', 'reset-rpw');
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

(function () {
  var tbl  = document.querySelector('.ul-table');
  if (!tbl) return;
  var ths  = tbl.querySelectorAll('th[data-sort]');
  ths.forEach(function (th) {
    th.addEventListener('click', function () {
      var col  = th.cellIndex;
      var dir  = th.dataset.dir === 'asc' ? 'desc' : 'asc';
      ths.forEach(function (t) { delete t.dataset.dir; });
      th.dataset.dir = dir;
      var tbody = tbl.tBodies[0];
      var rows  = [].slice.call(tbody.rows);
      rows.sort(function (a, b) {
        var av = a.cells[col].dataset.val !== undefined ? a.cells[col].dataset.val : a.cells[col].textContent.trim();
        var bv = b.cells[col].dataset.val !== undefined ? b.cells[col].dataset.val : b.cells[col].textContent.trim();
        var r  = av.localeCompare(bv, 'it', { sensitivity: 'base' });
        return dir === 'asc' ? r : -r;
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
    });
  });
}());
</script>
</body></html>
