<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$isRo = ($user['ruolo'] ?? '') === 'revisore';

/* ====================================================================
   POST (operatori e responsabili — revisori read-only)
   ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isRo) {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    if ($az === 'aggiungi') {
        $nome     = mb_substr(trim($_POST['nome']     ?? ''), 0, 100);
        $ruolo    = mb_substr(trim($_POST['ruolo']    ?? ''), 0, 100);
        $telefono = mb_substr(trim($_POST['telefono'] ?? ''), 0, 30);
        $email    = mb_substr(trim($_POST['email']    ?? ''), 0, 255);
        $note     = mb_substr(trim($_POST['note']     ?? ''), 0, 500);
        if ($nome !== '') {
            $max = (int)($pdo->query('SELECT COALESCE(MAX(ordine),0) FROM contatti')->fetchColumn());
            $pdo->prepare('INSERT INTO contatti (nome,ruolo,telefono,email,note,ordine,creato_da) VALUES (?,?,?,?,?,?,?)')
                ->execute([$nome, $ruolo ?: null, $telefono ?: null, $email ?: null, $note ?: null, $max + 1, $user['id']]);
            audit('contatto_add', 'contatti', null, $nome);
        }
        header('Location: contatti.php'); exit;

    } elseif ($az === 'modifica') {
        $id       = (int)($_POST['id'] ?? 0);
        $nome     = mb_substr(trim($_POST['nome']     ?? ''), 0, 100);
        $ruolo    = mb_substr(trim($_POST['ruolo']    ?? ''), 0, 100);
        $telefono = mb_substr(trim($_POST['telefono'] ?? ''), 0, 30);
        $email    = mb_substr(trim($_POST['email']    ?? ''), 0, 255);
        $note     = mb_substr(trim($_POST['note']     ?? ''), 0, 500);
        if ($id && $nome !== '') {
            $pdo->prepare('UPDATE contatti SET nome=?,ruolo=?,telefono=?,email=?,note=? WHERE id=?')
                ->execute([$nome, $ruolo ?: null, $telefono ?: null, $email ?: null, $note ?: null, $id]);
            audit('contatto_edit', 'contatti', $id, $nome);
        }
        header('Location: contatti.php'); exit;

    } elseif ($az === 'elimina') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM contatti WHERE id=?')->execute([$id]);
            audit('contatto_elimina', 'contatti', $id, null);
        }
        header('Location: contatti.php'); exit;
    }

    header('Location: contatti.php'); exit;
}

/* ====================================================================
   GET: dati
   ==================================================================== */
$contatti = $pdo->query('SELECT * FROM contatti ORDER BY ordine, nome')->fetchAll();
$total    = count($contatti);

$okMsg = '';
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contatti · <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/utenti.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar sh-top">
  <div class="ul-header-left">
    <strong>Contatti</strong>
    <div class="ul-chips">
      <span class="ul-chip"><?= $total ?> <?= $total === 1 ? 'contatto' : 'contatti' ?></span>
    </div>
  </div>
  <label class="topbar-search-wrap">
    <svg class="topbar-search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="search" class="topbar-search" id="tbl-search" placeholder="Cerca…" aria-label="Cerca contatti">
  </label>
  <?php if (!$isRo): ?>
  <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-cont-add').showModal()">
    <svg width="11" height="11" viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5.5 1v9M1 5.5h9"/></svg>
    Aggiungi
  </button>
  <?php endif; ?>
</header>

<!-- ========== Dialog: aggiungi contatto ========== -->
<?php if (!$isRo): ?>
<dialog id="dlg-cont-add" class="form-dialog">
  <div class="dlg-head">
    <strong>Aggiungi contatto</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="aggiungi">
    <div class="ul-form-grid">
      <div class="ul-field ul-field-full">
        <label for="ca-nome">Nome <span class="ul-req">*</span></label>
        <input id="ca-nome" type="text" name="nome" required maxlength="100" placeholder="Es. Mario Rossi" autofocus>
      </div>
      <div class="ul-field">
        <label for="ca-ruolo">Ruolo / categoria</label>
        <input id="ca-ruolo" type="text" name="ruolo" maxlength="100" placeholder="Es. Tecnico HVAC, Bar…">
      </div>
      <div class="ul-field">
        <label for="ca-tel">Telefono</label>
        <input id="ca-tel" type="tel" name="telefono" maxlength="30" placeholder="+39 333 000 0000">
      </div>
      <div class="ul-field">
        <label for="ca-email">Email</label>
        <input id="ca-email" type="email" name="email" maxlength="255" placeholder="mario@esempio.it">
      </div>
      <div class="ul-field ul-field-full">
        <label for="ca-note">Note</label>
        <input id="ca-note" type="text" name="note" maxlength="500" placeholder="Informazioni aggiuntive">
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Aggiungi</button>
    </div>
  </form>
</dialog>

<!-- ========== Dialog: modifica contatto ========== -->
<dialog id="dlg-cont-edit" class="form-dialog">
  <div class="dlg-head">
    <strong>Modifica contatto</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="modifica">
    <input type="hidden" name="id"     id="edit-id">
    <div class="ul-form-grid">
      <div class="ul-field ul-field-full">
        <label for="ce-nome">Nome <span class="ul-req">*</span></label>
        <input id="ce-nome" type="text" name="nome" required maxlength="100">
      </div>
      <div class="ul-field">
        <label for="ce-ruolo">Ruolo / categoria</label>
        <input id="ce-ruolo" type="text" name="ruolo" maxlength="100" placeholder="Es. Tecnico HVAC, Bar…">
      </div>
      <div class="ul-field">
        <label for="ce-tel">Telefono</label>
        <input id="ce-tel" type="tel" name="telefono" maxlength="30">
      </div>
      <div class="ul-field">
        <label for="ce-email">Email</label>
        <input id="ce-email" type="email" name="email" maxlength="255">
      </div>
      <div class="ul-field ul-field-full">
        <label for="ce-note">Note</label>
        <input id="ce-note" type="text" name="note" maxlength="500">
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Salva</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<!-- ========== Tabella ========== -->
<div class="ul-page">
  <?php if (empty($contatti)): ?>
  <div class="ul-empty">
    <div class="ul-empty-icon">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <p class="ul-empty-title">Nessun contatto ancora</p>
    <?php if (!$isRo): ?>
    <p class="ul-empty-sub">Aggiungi il primo con il pulsante in alto a destra.</p>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <div class="ul-table-wrap">
    <table class="ul-table">
      <thead>
        <tr>
          <th class="ul-th-ava" aria-hidden="true"></th>
          <th data-sort="text">Nome</th>
          <th data-sort="text">Ruolo</th>
          <th data-sort="text">Telefono</th>
          <th class="ul-td-email" data-sort="text">Email</th>
          <th>Note</th>
          <?php if (!$isRo): ?>
          <th class="ul-th-menu" aria-hidden="true"></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contatti as $c):
          $initial     = avatar_initials($c['nome']);
          $avatarStyle = avatar_style($c['nome']);
        ?>
        <tr class="ul-row">

          <td class="ul-td-ava">
            <div class="ul-ava" aria-hidden="true" style="<?= $avatarStyle ?>"><?= $h($initial) ?></div>
          </td>

          <td data-val="<?= $h(mb_strtolower($c['nome'], 'UTF-8')) ?>">
            <span class="ul-nome"><?= $h($c['nome']) ?></span>
          </td>

          <td data-val="<?= $h(mb_strtolower($c['ruolo'] ?? '', 'UTF-8')) ?>">
            <?= $c['ruolo'] ? $h($c['ruolo']) : '<span class="ul-muted">—</span>' ?>
          </td>

          <td data-val="<?= $h($c['telefono'] ?? '') ?>">
            <?php if ($c['telefono']): ?>
              <a href="tel:<?= $h($c['telefono']) ?>" class="ul-email-link"><?= $h($c['telefono']) ?></a>
            <?php else: ?>
              <span class="ul-muted">—</span>
            <?php endif; ?>
          </td>

          <td class="ul-td-email" data-val="<?= $h(mb_strtolower($c['email'] ?? '', 'UTF-8')) ?>">
            <?php if ($c['email']): ?>
              <a href="mailto:<?= $h($c['email']) ?>" class="ul-email-link"><?= $h($c['email']) ?></a>
            <?php else: ?>
              <span class="ul-muted">—</span>
            <?php endif; ?>
          </td>

          <td>
            <?= $c['note'] ? $h(mb_substr($c['note'], 0, 60)) . (mb_strlen($c['note']) > 60 ? '…' : '') : '<span class="ul-muted">—</span>' ?>
          </td>

          <?php if (!$isRo): ?>
          <td class="ul-td-menu">
            <div class="ul-action">
              <button type="button" class="ul-menu-btn" aria-label="Azioni per <?= $h($c['nome']) ?>">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="currentColor" aria-hidden="true">
                  <circle cx="7.5" cy="2.5" r="1.5"/>
                  <circle cx="7.5" cy="7.5" r="1.5"/>
                  <circle cx="7.5" cy="12.5" r="1.5"/>
                </svg>
              </button>
              <div class="ul-menu" role="menu">
                <button type="button" class="ul-menu-item" role="menuitem"
                        data-action="edit"
                        data-id="<?= (int)$c['id'] ?>"
                        data-nome="<?= $h($c['nome']) ?>"
                        data-ruolo="<?= $h($c['ruolo'] ?? '') ?>"
                        data-telefono="<?= $h($c['telefono'] ?? '') ?>"
                        data-email="<?= $h($c['email'] ?? '') ?>"
                        data-note="<?= $h($c['note'] ?? '') ?>">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 2.5a2.1 2.1 0 0 1 2.5 2.5L5 14H2v-3L11 2.5z"/></svg>
                  Modifica
                </button>
                <div class="ul-menu-sep" role="separator"></div>
                <form method="post" class="ul-menu-form"
                      data-name="<?= $h($c['nome']) ?>"
                      onsubmit="return confirm('Eliminare <?= $h($c['nome']) ?>?')">
                  <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
                  <input type="hidden" name="azione" value="elimina">
                  <input type="hidden" name="id"     value="<?= (int)$c['id'] ?>">
                  <button type="submit" role="menuitem" class="ul-menu-item ul-menu-danger">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="2 4 4 4 14 4"/><path d="M13 4l-.8 9.3a1.5 1.5 0 0 1-1.5 1.4H5.3a1.5 1.5 0 0 1-1.5-1.4L3 4"/><path d="M6.5 4V2.5h3V4"/></svg>
                    Elimina
                  </button>
                </form>
              </div>
            </div>
          </td>
          <?php endif; ?>

        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php endif; ?>
</div>

<script>
(function () {
  /* ---- Dropdown 3-punti (position:fixed per uscire da overflow:hidden) ---- */
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
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });

  /* ---- Azione modifica → popola dialog ---- */
  document.querySelectorAll('.ul-menu-item[data-action="edit"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeAll();
      document.getElementById('edit-id').value    = btn.dataset.id;
      document.getElementById('ce-nome').value    = btn.dataset.nome;
      document.getElementById('ce-ruolo').value   = btn.dataset.ruolo;
      document.getElementById('ce-tel').value     = btn.dataset.telefono;
      document.getElementById('ce-email').value   = btn.dataset.email;
      document.getElementById('ce-note').value    = btn.dataset.note;
      document.getElementById('dlg-cont-edit').showModal();
    });
  });

  /* ---- Chiudi dialog clic fuori ---- */
  document.querySelectorAll('.form-dialog').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  });

  /* ---- Ricerca live ---- */
  var srch = document.getElementById('tbl-search');
  if (srch) {
    srch.addEventListener('input', function () {
      var q = this.value.trim().toLowerCase();
      document.querySelectorAll('.ul-table tbody .ul-row').forEach(function (row) {
        row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  /* ---- Sort colonne ---- */
  var tbl = document.querySelector('.ul-table');
  if (!tbl) return;
  var ths = tbl.querySelectorAll('th[data-sort]');
  ths.forEach(function (th) {
    th.addEventListener('click', function () {
      var col = th.cellIndex;
      var dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
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
