<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lib.php';
$user = require_login();
require_responsabile();
$pdo = db();
$h   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

/* ---- POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

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

    header('Location: fornitori.php'); exit;
}

$lista = $pdo->query('SELECT * FROM fornitori ORDER BY ordine')->fetchAll();
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fornitori</title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/impostazioni.css') ?>">
</head><body>
<?php require __DIR__ . '/../../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div><strong>Fornitori</strong></div>
</header>

<div class="imp-wrap">

  <div class="imp-section">
    <h2 class="imp-section-title">Lista fornitori</h2>
    <p class="imp-section-desc">I fornitori configurati qui compaiono nelle sezioni Scassettamenti, Ticket vincite e Bet/Win SNAI. Disabilitando un fornitore lo si nasconde dai nuovi inserimenti (i dati storici rimangono).</p>

    <ul class="forn-list" id="forn-list">
    <?php foreach ($lista as $f): ?>
      <li class="forn-row <?= $f['attiva'] ? '' : 'forn-off' ?>" data-id="<?= $f['id'] ?>">
        <span class="forn-handle" title="Trascina per riordinare" aria-hidden="true">⠿</span>
        <form method="post" class="forn-rinomina-form">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="rinomina">
          <input type="hidden" name="id" value="<?= $f['id'] ?>">
          <input class="forn-name-input" type="text" name="nome" value="<?= $h($f['nome']) ?>" maxlength="50" aria-label="Nome fornitore">
          <button type="submit" class="ghost" style="font-size:11px;padding:3px 8px">Salva</button>
        </form>
        <form method="post" class="forn-toggle-form">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="toggle">
          <input type="hidden" name="id" value="<?= $f['id'] ?>">
          <button type="submit" class="ghost" style="font-size:11px;padding:3px 8px">
            <?= $f['attiva'] ? 'Disabilita' : 'Abilita' ?>
          </button>
        </form>
      </li>
    <?php endforeach; ?>
    </ul>

    <form method="post" id="forn-ordine-form" style="display:none">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="ordine">
    </form>
  </div>

  <div class="imp-section">
    <h2 class="imp-section-title">Aggiungi fornitore</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="aggiungi">
      <div class="imp-field-row">
        <input type="text" name="nome" placeholder="Es. GAMENET" maxlength="50" required style="text-transform:uppercase">
        <button type="submit">Aggiungi</button>
      </div>
    </form>
  </div>

</div>

<style>
.forn-list { list-style:none; padding:0; margin:0 0 8px; display:flex; flex-direction:column; gap:6px }
.forn-row  { display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:var(--rxs); padding:8px 12px }
.forn-off  { opacity:.5 }
.forn-handle { cursor:grab; color:var(--faint); font-size:16px; user-select:none }
.forn-rinomina-form { display:flex; align-items:center; gap:6px; flex:1 }
.forn-name-input { border:1px solid var(--border); border-radius:var(--rxs); padding:5px 8px; font-size:13px; font-weight:600; background:var(--bg); color:var(--text); width:120px; text-transform:uppercase }
.forn-toggle-form { margin-left:auto }
.imp-field-row { display:flex; gap:8px; align-items:center }
.imp-field-row input { flex:1; max-width:220px }
</style>

<script>
(function(){
  var list = document.getElementById('forn-list');
  if (!list) return;
  var dragged = null;

  list.querySelectorAll('.forn-handle').forEach(function(h) {
    h.closest('.forn-row').draggable = true;
  });

  list.addEventListener('dragstart', function(e) {
    dragged = e.target.closest('.forn-row');
    dragged.style.opacity = '.4';
  });
  list.addEventListener('dragend', function() {
    if (dragged) dragged.style.opacity = '';
    dragged = null;
    saveOrder();
  });
  list.addEventListener('dragover', function(e) {
    e.preventDefault();
    var target = e.target.closest('.forn-row');
    if (target && target !== dragged) {
      var rect = target.getBoundingClientRect();
      if (e.clientY < rect.top + rect.height / 2) list.insertBefore(dragged, target);
      else list.insertBefore(dragged, target.nextSibling);
    }
  });

  function saveOrder() {
    var form = document.getElementById('forn-ordine-form');
    form.querySelectorAll('input[name="ids[]"]').forEach(function(i){i.remove();});
    list.querySelectorAll('.forn-row').forEach(function(row) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = row.dataset.id;
      form.appendChild(inp);
    });
    form.submit();
  }
})();
</script>
</body></html>
