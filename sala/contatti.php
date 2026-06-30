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

    } elseif ($az === 'ordine') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $st  = $pdo->prepare('UPDATE contatti SET ordine=? WHERE id=?');
        foreach ($ids as $pos => $cid) $st->execute([$pos + 1, $cid]);
        header('Location: contatti.php'); exit;
    }

    header('Location: contatti.php'); exit;
}

/* ====================================================================
   GET: dati
   ==================================================================== */
$contatti = $pdo->query('SELECT * FROM contatti ORDER BY ordine, nome')->fetchAll();
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contatti · <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/impostazioni.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar sh-top">
  <strong>Contatti</strong>
  <?php if (!$isRo): ?>
  <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-cont-add').showModal()">+ Aggiungi</button>
  <?php endif; ?>
</header>

<div class="imp-page" style="padding-top:20px">

  <section class="imp-card" style="grid-column: 1 / -1">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Rubrica contatti</h2>
        <p class="imp-card-desc">Fornitori interni, tecnici e referenti utili per la sala: numeri, email e note sempre a portata di mano.</p>
      </div>
    </div>

    <?php if (empty($contatti)): ?>
    <p class="ticket-empty">Nessun contatto ancora.<?= $isRo ? '' : ' Aggiungine uno con il pulsante in alto.' ?></p>
    <?php else: ?>
    <ul class="cont-list" id="cont-list">
      <?php foreach ($contatti as $c): ?>
      <li class="cont-row" data-id="<?= $c['id'] ?>">
        <?php if (!$isRo): ?>
        <span class="cont-handle" title="Trascina per riordinare" aria-hidden="true" style="cursor:grab;color:var(--muted)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
        </span>
        <?php else: ?>
        <span style="width:14px;display:block"></span>
        <?php endif; ?>

        <?php if ($isRo): ?>
        <div class="cont-fields">
          <span class="cont-input cont-input-nome" style="background:none;border-color:transparent;pointer-events:none"><?= $h($c['nome']) ?></span>
          <?php if ($c['ruolo']):    ?><span class="cont-input cont-input-ruolo" style="background:none;border-color:transparent;color:var(--muted);pointer-events:none"><?= $h($c['ruolo']) ?></span><?php endif; ?>
          <?php if ($c['telefono']): ?><span class="cont-input cont-input-tel"   style="background:none;border-color:transparent;pointer-events:none"><?= $h($c['telefono']) ?></span><?php endif; ?>
          <?php if ($c['email']):    ?><span class="cont-input cont-input-email" style="background:none;border-color:transparent;pointer-events:none"><?= $h($c['email']) ?></span><?php endif; ?>
          <?php if ($c['note']):     ?><span class="cont-input cont-input-note"  style="background:none;border-color:transparent;color:var(--muted);pointer-events:none"><?= $h($c['note']) ?></span><?php endif; ?>
        </div>
        <?php else: ?>
        <form method="post" class="cont-fields">
          <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="modifica">
          <input type="hidden" name="id"     value="<?= $c['id'] ?>">
          <input class="cont-input cont-input-nome"  type="text" name="nome"     value="<?= $h($c['nome'])     ?>" maxlength="100" required aria-label="Nome">
          <input class="cont-input cont-input-ruolo" type="text" name="ruolo"    value="<?= $h($c['ruolo']    ?? '') ?>" maxlength="100" placeholder="Ruolo / categoria" aria-label="Ruolo">
          <input class="cont-input cont-input-tel"   type="tel"  name="telefono" value="<?= $h($c['telefono'] ?? '') ?>" maxlength="30"  placeholder="Telefono" aria-label="Telefono">
          <input class="cont-input cont-input-email" type="email" name="email"   value="<?= $h($c['email']    ?? '') ?>" maxlength="255" placeholder="Email" aria-label="Email">
          <input class="cont-input cont-input-note"  type="text" name="note"     value="<?= $h($c['note']     ?? '') ?>" maxlength="500" placeholder="Note" aria-label="Note">
          <button type="submit" class="ghost forn-btn-sm" aria-label="Salva <?= $h($c['nome']) ?>">Salva</button>
        </form>
        <?php endif; ?>

        <div class="cont-actions">
          <?php if ($c['telefono']): ?>
          <a href="tel:<?= $h($c['telefono']) ?>" class="icon-link" title="Chiama <?= $h($c['nome']) ?>" aria-label="Chiama">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.71 12 19.79 19.79 0 0 1 1.65 3.38 2 2 0 0 1 3.62 1.17h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.8a16 16 0 0 0 6.29 6.29l.9-.9a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </a>
          <?php endif; ?>
          <?php if ($c['email']): ?>
          <a href="mailto:<?= $h($c['email']) ?>" class="icon-link" title="Scrivi a <?= $h($c['nome']) ?>" aria-label="Email">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!$isRo): ?>
          <form method="post" onsubmit="return confirm('Eliminare <?= $h($c['nome']) ?>?')">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="elimina">
            <input type="hidden" name="id"     value="<?= $c['id'] ?>">
            <button type="submit" class="icon-link" style="color:var(--red)" title="Elimina" aria-label="Elimina <?= $h($c['nome']) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!$isRo): ?>
    <form method="post" id="cont-ordine-form" style="display:none">
      <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="ordine">
    </form>
    <?php endif; ?>
    <?php endif; ?>

  </section>

</div>

<?php if (!$isRo): ?>
<!-- Dialog: Aggiungi contatto -->
<dialog id="dlg-cont-add" class="form-dialog">
  <div class="dlg-head">
    <strong>Aggiungi contatto</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="aggiungi">
    <div class="field">
      <label for="cn-nome">Nome <span class="ul-req">*</span></label>
      <input id="cn-nome" type="text" name="nome" maxlength="100" required autofocus placeholder="Es. Mario Rossi">
    </div>
    <div class="field">
      <label for="cn-ruolo">Ruolo / categoria</label>
      <input id="cn-ruolo" type="text" name="ruolo" maxlength="100" placeholder="Es. Tecnico HVAC, Bar, Elettricista…">
    </div>
    <div class="field">
      <label for="cn-tel">Telefono</label>
      <input id="cn-tel" type="tel" name="telefono" maxlength="30" placeholder="+39 333 000 0000">
    </div>
    <div class="field">
      <label for="cn-email">Email</label>
      <input id="cn-email" type="email" name="email" maxlength="255" placeholder="mario@esempio.it">
    </div>
    <div class="field">
      <label for="cn-note">Note</label>
      <input id="cn-note" type="text" name="note" maxlength="500" placeholder="Informazioni aggiuntive">
    </div>
    <div class="dlg-actions">
      <button type="button" class="ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Aggiungi</button>
    </div>
  </form>
</dialog>

<script>
(function () {
  var list = document.getElementById('cont-list');
  if (!list) return;
  var dragged = null;
  list.querySelectorAll('.cont-handle').forEach(function (h) { h.closest('.cont-row').draggable = true; });
  list.addEventListener('dragstart', function (e) { dragged = e.target.closest('.cont-row'); dragged.style.opacity = '.4'; });
  list.addEventListener('dragend',   function ()  {
    if (dragged) dragged.style.opacity = '';
    dragged = null;
    var form = document.getElementById('cont-ordine-form');
    if (!form) return;
    form.querySelectorAll('input[name="ids[]"]').forEach(function (i) { i.remove(); });
    list.querySelectorAll('.cont-row').forEach(function (row) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = row.dataset.id;
      form.appendChild(inp);
    });
    form.submit();
  });
  list.addEventListener('dragover', function (e) {
    e.preventDefault();
    var target = e.target.closest('.cont-row');
    if (target && target !== dragged) {
      var rect = target.getBoundingClientRect();
      if (e.clientY < rect.top + rect.height / 2) list.insertBefore(dragged, target);
      else list.insertBefore(dragged, target.nextSibling);
    }
  });
  document.getElementById('dlg-cont-add').addEventListener('click', function (e) {
    if (e.target === this) this.close();
  });
})();
</script>
<?php endif; ?>

</body></html>
