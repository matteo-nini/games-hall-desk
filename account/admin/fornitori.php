<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lib.php';
$user = require_login();
require_responsabile();
$pdo = db();
$h   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

/* ====================================================================
   POST
   ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    /* --- Fornitori --- */

    if ($az === 'aggiungi') {
        $nome = mb_strtoupper(trim($_POST['nome'] ?? ''), 'UTF-8');
        if ($nome !== '' && mb_strlen($nome) <= 50) {
            $max = (int)($pdo->query('SELECT COALESCE(MAX(ordine),0) FROM fornitori')->fetchColumn());
            $pdo->prepare('INSERT IGNORE INTO fornitori (nome,ordine) VALUES (?,?)')->execute([$nome, $max + 1]);
            audit('fornitore_add', 'fornitori', null, $nome);
        }
        header('Location: fornitori.php'); exit;
    }

    if ($az === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE fornitori SET attiva = 1 - attiva WHERE id=?')->execute([$id]);
        audit('fornitore_toggle', 'fornitori', $id, null);
        header('Location: fornitori.php'); exit;
    }

    if ($az === 'ordine') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $st  = $pdo->prepare('UPDATE fornitori SET ordine=? WHERE id=?');
        foreach ($ids as $pos => $fid) $st->execute([$pos + 1, $fid]);
        header('Location: fornitori.php'); exit;
    }

    if ($az === 'rinomina') {
        $id   = (int)($_POST['id'] ?? 0);
        $nome = mb_strtoupper(trim($_POST['nome'] ?? ''), 'UTF-8');
        if ($id && $nome !== '' && mb_strlen($nome) <= 50) {
            $pdo->prepare('UPDATE fornitori SET nome=? WHERE id=?')->execute([$nome, $id]);
            audit('fornitore_rinomina', 'fornitori', $id, $nome);
        }
        header('Location: fornitori.php'); exit;
    }

    if ($az === 'elimina_fornitore') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT id FROM fornitori WHERE id=? AND attiva=0');
        $st->execute([$id]);
        if ($st->fetch()) {
            try {
                $pdo->prepare('DELETE FROM fornitori WHERE id=? AND attiva=0')->execute([$id]);
                audit('fornitore_elimina', 'fornitori', $id, null);
            } catch (Throwable) {
                // vincolo FK: impossibile eliminare, i dati storici referenziano questo fornitore
            }
        }
        header('Location: fornitori.php'); exit;
    }

    /* --- Contatti --- */

    if ($az === 'aggiungi_contatto') {
        $nome     = trim($_POST['nome']     ?? '');
        $ruolo    = trim($_POST['ruolo']    ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $note     = trim($_POST['note']     ?? '');
        if ($nome !== '' && mb_strlen($nome) <= 100) {
            $max = (int)($pdo->query('SELECT COALESCE(MAX(ordine),0) FROM contatti')->fetchColumn());
            $pdo->prepare('INSERT INTO contatti (nome,ruolo,telefono,email,note,ordine,creato_da) VALUES (?,?,?,?,?,?,?)')
                ->execute([$nome, $ruolo ?: null, $telefono ?: null, $email ?: null, $note ?: null, $max + 1, (int)$user['id']]);
            audit('contatto_add', 'contatti', null, $nome);
        }
        header('Location: fornitori.php#contatti'); exit;
    }

    if ($az === 'modifica_contatto') {
        $id       = (int)($_POST['id']       ?? 0);
        $nome     = trim($_POST['nome']      ?? '');
        $ruolo    = trim($_POST['ruolo']     ?? '');
        $telefono = trim($_POST['telefono']  ?? '');
        $email    = trim($_POST['email']     ?? '');
        $note     = trim($_POST['note']      ?? '');
        if ($id && $nome !== '' && mb_strlen($nome) <= 100) {
            $pdo->prepare('UPDATE contatti SET nome=?,ruolo=?,telefono=?,email=?,note=? WHERE id=?')
                ->execute([$nome, $ruolo ?: null, $telefono ?: null, $email ?: null, $note ?: null, $id]);
            audit('contatto_modifica', 'contatti', $id, $nome);
        }
        header('Location: fornitori.php#contatti'); exit;
    }

    if ($az === 'elimina_contatto') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM contatti WHERE id=?')->execute([$id]);
            audit('contatto_elimina', 'contatti', $id, null);
        }
        header('Location: fornitori.php#contatti'); exit;
    }

    if ($az === 'ordine_contatti') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $st  = $pdo->prepare('UPDATE contatti SET ordine=? WHERE id=?');
        foreach ($ids as $pos => $cid) $st->execute([$pos + 1, $cid]);
        header('Location: fornitori.php#contatti'); exit;
    }

    header('Location: fornitori.php'); exit;
}

$lista    = $pdo->query('SELECT * FROM fornitori ORDER BY ordine')->fetchAll();
$contatti = [];
try {
    $contatti = $pdo->query('SELECT * FROM contatti ORDER BY ordine')->fetchAll();
} catch (PDOException) {}
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fornitori &amp; Contatti</title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/impostazioni.css') ?>">
</head><body>
<?php require __DIR__ . '/../../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div><strong>Fornitori &amp; Contatti</strong></div>
</header>

<div class="imp-page">

  <!-- ================================================================
       FORNITORI
       ================================================================ -->
  <section class="imp-card" style="grid-column: 1 / -1">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Lista fornitori</h2>
        <p class="imp-card-desc">I fornitori configurati qui compaiono in Scassettamenti, Ticket vincite e Bet/Win SNAI. Disabilitando un fornitore lo si nasconde dai nuovi inserimenti — i dati storici rimangono intatti. Trascina per riordinare. Un fornitore disabilitato può essere eliminato solo se non ha dati storici collegati.</p>
      </div>
    </div>

    <?php if ($lista): ?>
    <ul class="forn-list" id="forn-list">
      <?php foreach ($lista as $f): ?>
      <li class="forn-row <?= $f['attiva'] ? '' : 'forn-off' ?>" data-id="<?= $f['id'] ?>">
        <span class="forn-handle" title="Trascina per riordinare" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
        </span>
        <form method="post" class="forn-rinomina-form">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="rinomina">
          <input type="hidden" name="id" value="<?= $f['id'] ?>">
          <input class="forn-name-input" type="text" name="nome" value="<?= $h($f['nome']) ?>" maxlength="50" aria-label="Nome fornitore">
          <button type="submit" class="ghost forn-btn-sm">Salva</button>
        </form>
        <form method="post" class="forn-toggle-form">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="toggle">
          <input type="hidden" name="id" value="<?= $f['id'] ?>">
          <button type="submit" class="ghost forn-btn-sm <?= $f['attiva'] ? '' : 'forn-toggle-on' ?>">
            <?= $f['attiva'] ? 'Disabilita' : 'Abilita' ?>
          </button>
        </form>
        <?php if (!$f['attiva']): ?>
        <form method="post" onsubmit="return confirm('Eliminare definitivamente <?= $h($f['nome']) ?>?\nQuesta azione è irreversibile. Se ci sono dati storici collegati l\'eliminazione verrà bloccata automaticamente.')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="elimina_fornitore">
          <input type="hidden" name="id" value="<?= $f['id'] ?>">
          <button type="submit" class="ghost forn-btn-sm forn-btn-danger">Elimina</button>
        </form>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="ticket-empty">Nessun fornitore ancora. Aggiungine uno qui sotto.</p>
    <?php endif; ?>

    <form method="post" id="forn-ordine-form" style="display:none">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="ordine">
    </form>
  </section>

  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Aggiungi fornitore</h2>
        <p class="imp-card-desc">Il nome viene convertito automaticamente in maiuscolo.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="aggiungi">
      <div class="forn-add-row">
        <input type="text" name="nome" placeholder="Es. GAMENET" maxlength="50" required style="text-transform:uppercase">
        <button type="submit">Aggiungi</button>
      </div>
    </form>
  </section>

  <!-- ================================================================
       CONTATTI
       ================================================================ -->
  <section class="imp-card" id="contatti" style="grid-column: 1 / -1">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.4a2 2 0 0 1 1.99-2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6.13 6.13l.96-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Rubrica contatti</h2>
        <p class="imp-card-desc">Contatti dei fornitori, tecnici e referenti della sala. Trascina per riordinare. Telefono ed email diventano link cliccabili.</p>
      </div>
    </div>

    <?php if ($contatti): ?>
    <ul class="cont-list" id="cont-list">
      <?php foreach ($contatti as $c): ?>
      <li class="cont-row" data-id="<?= $c['id'] ?>">
        <span class="forn-handle" title="Trascina per riordinare" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
        </span>
        <form method="post" class="cont-fields">
          <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="modifica_contatto">
          <input type="hidden" name="id"     value="<?= $c['id'] ?>">
          <input class="cont-input cont-input-nome"  type="text"  name="nome"     value="<?= $h($c['nome']) ?>"     maxlength="100" placeholder="Nome"       aria-label="Nome"     required>
          <input class="cont-input cont-input-ruolo" type="text"  name="ruolo"    value="<?= $h($c['ruolo'] ?? '') ?>"  maxlength="100" placeholder="Ruolo"      aria-label="Ruolo">
          <input class="cont-input cont-input-tel"   type="tel"   name="telefono" value="<?= $h($c['telefono'] ?? '') ?>" maxlength="30"  placeholder="Telefono"   aria-label="Telefono">
          <input class="cont-input cont-input-email" type="email" name="email"    value="<?= $h($c['email'] ?? '') ?>"    maxlength="255" placeholder="Email"      aria-label="Email">
          <input class="cont-input cont-input-note"  type="text"  name="note"     value="<?= $h($c['note'] ?? '') ?>"     maxlength="500" placeholder="Note"       aria-label="Note">
          <button type="submit" class="ghost forn-btn-sm">Salva</button>
        </form>
        <div class="cont-actions">
          <?php if ($c['telefono']): ?><a href="tel:<?= $h($c['telefono']) ?>" class="ghost forn-btn-sm" title="Chiama <?= $h($c['telefono']) ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.4a2 2 0 0 1 1.99-2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6.13 6.13l.96-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </a><?php endif; ?>
          <?php if ($c['email']): ?><a href="mailto:<?= $h($c['email']) ?>" class="ghost forn-btn-sm" title="Scrivi a <?= $h($c['email']) ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </a><?php endif; ?>
          <form method="post" onsubmit="return confirm('Eliminare il contatto <?= $h(addslashes($c['nome'])) ?>?')">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="elimina_contatto">
            <input type="hidden" name="id"     value="<?= $c['id'] ?>">
            <button type="submit" class="ghost forn-btn-sm forn-btn-danger" title="Elimina contatto">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
          </form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="ticket-empty">Nessun contatto ancora. Aggiungine uno qui sotto.</p>
    <?php endif; ?>

    <form method="post" id="cont-ordine-form" style="display:none">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="ordine_contatti">
    </form>
  </section>

  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Aggiungi contatto</h2>
        <p class="imp-card-desc">Aggiungi un contatto alla rubrica. Solo il nome è obbligatorio.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="aggiungi_contatto">
      <div class="cont-add-grid">
        <label>Nome *<input type="text"  name="nome"     maxlength="100" placeholder="Mario Rossi"        required></label>
        <label>Ruolo<input  type="text"  name="ruolo"    maxlength="100" placeholder="Tecnico referente"></label>
        <label>Telefono<input type="tel" name="telefono" maxlength="30"  placeholder="+39 333 1234567"></label>
        <label>Email<input  type="email" name="email"    maxlength="255" placeholder="mario@esempio.it"></label>
        <label>Note<input   type="text"  name="note"     maxlength="500" placeholder="Es. NOVO Lombardia"></label>
        <div class="cont-add-actions"><button type="submit">Aggiungi</button></div>
      </div>
    </form>
  </section>

</div>

<script>
(function () {
  function makeDraggable(listId, ordineFormId) {
    var list = document.getElementById(listId);
    if (!list) return;
    var dragged = null;

    list.querySelectorAll('.forn-handle').forEach(function (h) {
      h.closest('[data-id]').draggable = true;
    });

    list.addEventListener('dragstart', function (e) {
      dragged = e.target.closest('[data-id]');
      if (dragged) dragged.style.opacity = '.4';
    });
    list.addEventListener('dragend', function () {
      if (dragged) dragged.style.opacity = '';
      dragged = null;
      saveOrder(list, ordineFormId);
    });
    list.addEventListener('dragover', function (e) {
      e.preventDefault();
      var target = e.target.closest('[data-id]');
      if (target && target !== dragged) {
        var rect = target.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) list.insertBefore(dragged, target);
        else list.insertBefore(dragged, target.nextSibling);
      }
    });
  }

  function saveOrder(list, formId) {
    var form = document.getElementById(formId);
    if (!form) return;
    form.querySelectorAll('input[name="ids[]"]').forEach(function (i) { i.remove(); });
    list.querySelectorAll('[data-id]').forEach(function (row) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = row.dataset.id;
      form.appendChild(inp);
    });
    form.submit();
  }

  makeDraggable('forn-list',  'forn-ordine-form');
  makeDraggable('cont-list', 'cont-ordine-form');
})();
</script>
</body></html>
