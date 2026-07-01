<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$msg  = '';

/* Auto-migrazione colonne seriale/civ */
try {
    $pdo->exec('ALTER TABLE macchine ADD COLUMN seriale VARCHAR(100) NULL AFTER fornitore');
    $pdo->exec('ALTER TABLE macchine ADD COLUMN civ VARCHAR(100) NULL AFTER seriale');
} catch (Throwable) {}

$fornitori = get_fornitori($pdo);

/* ====================================================================
   POST
   ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    /* ---- Macchine ---- */

    if ($az === 'add') {
        $codice  = trim($_POST['codice'] ?? '');
        $tipo    = ($_POST['tipo'] ?? 'VLT') === 'AWP' ? 'AWP' : 'VLT';
        $forn    = in_array($_POST['fornitore'] ?? '', array_merge($fornitori, ['ALTRO']), true) ? $_POST['fornitore'] : 'ALTRO';
        $ord     = (int)($_POST['ordine'] ?? 0);
        $seriale = mb_substr(trim($_POST['seriale'] ?? ''), 0, 100) ?: null;
        $civ     = mb_substr(trim($_POST['civ'] ?? ''), 0, 100) ?: null;
        if ($codice !== '') {
            try {
                $pdo->prepare('INSERT INTO macchine (codice,tipo,fornitore,seriale,civ,ordine) VALUES (?,?,?,?,?,?)')
                    ->execute([$codice, $tipo, $forn, $seriale, $civ, $ord]);
                audit('macchina_add', 'macchine', null, $codice);
                header('Location: macchine.php?ok=add'); exit;
            } catch (Throwable) {
                $msg = 'Errore: codice già esistente?';
            }
        }
    } elseif ($az === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE macchine SET attiva = 1-attiva WHERE id=?')->execute([$id]);
        audit('macchina_toggle', 'macchine', $id, null);
        header('Location: macchine.php?ok=toggle'); exit;
    } elseif ($az === 'edit') {
        $id      = (int)$_POST['id'];
        $forn    = in_array($_POST['fornitore'] ?? '', array_merge($fornitori, ['ALTRO']), true) ? $_POST['fornitore'] : 'ALTRO';
        $seriale = mb_substr(trim($_POST['seriale'] ?? ''), 0, 100) ?: null;
        $civ     = mb_substr(trim($_POST['civ'] ?? ''), 0, 100) ?: null;
        $pdo->prepare('UPDATE macchine SET codice=?, fornitore=?, seriale=?, civ=?, ordine=? WHERE id=?')
            ->execute([trim($_POST['codice'] ?? ''), $forn, $seriale, $civ, (int)($_POST['ordine'] ?? 0), $id]);
        audit('macchina_edit', 'macchine', $id, null);
        header('Location: macchine.php?ok=edit'); exit;
    }

    /* ---- Fornitori ---- */

    elseif ($az === 'forn_aggiungi') {
        $nome = mb_strtoupper(trim($_POST['nome'] ?? ''), 'UTF-8');
        if ($nome !== '' && mb_strlen($nome) <= 50) {
            $max = (int)($pdo->query('SELECT COALESCE(MAX(ordine),0) FROM fornitori')->fetchColumn());
            $pdo->prepare('INSERT IGNORE INTO fornitori (nome,ordine) VALUES (?,?)')->execute([$nome, $max + 1]);
            audit('fornitore_add', 'fornitori', null, $nome);
        }
        header('Location: macchine.php#fornitori'); exit;
    } elseif ($az === 'forn_toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE fornitori SET attiva = 1 - attiva WHERE id=?')->execute([$id]);
        audit('fornitore_toggle', 'fornitori', $id, null);
        header('Location: macchine.php#fornitori'); exit;
    } elseif ($az === 'forn_ordine') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $st  = $pdo->prepare('UPDATE fornitori SET ordine=? WHERE id=?');
        foreach ($ids as $pos => $fid) $st->execute([$pos + 1, $fid]);
        header('Location: macchine.php#fornitori'); exit;
    } elseif ($az === 'forn_rinomina') {
        $id   = (int)($_POST['id'] ?? 0);
        $nome = mb_strtoupper(trim($_POST['nome'] ?? ''), 'UTF-8');
        if ($id && $nome !== '' && mb_strlen($nome) <= 50) {
            $pdo->prepare('UPDATE fornitori SET nome=? WHERE id=?')->execute([$nome, $id]);
            audit('fornitore_rinomina', 'fornitori', $id, $nome);
        }
        header('Location: macchine.php#fornitori'); exit;
    } elseif ($az === 'forn_elimina') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT id FROM fornitori WHERE id=? AND attiva=0');
        $st->execute([$id]);
        if ($st->fetch()) {
            try {
                $pdo->prepare('DELETE FROM fornitori WHERE id=? AND attiva=0')->execute([$id]);
                audit('fornitore_elimina', 'fornitori', $id, null);
            } catch (Throwable) {}
        }
        header('Location: macchine.php#fornitori'); exit;
    }

    header('Location: macchine.php'); exit;
}

/* ====================================================================
   GET: dati
   ==================================================================== */
$macchine = $pdo->query('SELECT * FROM macchine ORDER BY tipo, ordine, codice')->fetchAll();
$vlt      = array_values(array_filter($macchine, fn($m) => $m['tipo'] === 'VLT'));
$awp      = array_values(array_filter($macchine, fn($m) => $m['tipo'] === 'AWP'));
$nVlt     = count(array_filter($vlt, fn($m) => (bool)$m['attiva']));
$nAwp     = count(array_filter($awp, fn($m) => (bool)$m['attiva']));

$allTickets = $pdo->query('SELECT macchina, problema, stato, data_apertura, data_chiusura FROM ticket_assistenza ORDER BY data_apertura DESC')->fetchAll();
$ticketsByMach = [];
foreach ($allTickets as $tk) { $ticketsByMach[$tk['macchina']][] = $tk; }

$listaFornitori = $pdo->query('SELECT * FROM fornitori ORDER BY ordine')->fetchAll();

$okMsg = match ($_GET['ok'] ?? '') {
    'add'    => 'Macchina aggiunta.',
    'toggle' => 'Stato aggiornato.',
    'edit'   => 'Modifiche salvate.',
    default  => ''
};
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Macchine · <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/utenti.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/macchine.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/impostazioni.css') ?>">
</head><body>
<?php require __DIR__ . '/../../includes/nav.php'; top_menu($user); ?>

<header class="topbar sh-top">
  <div class="mach-header-left">
    <strong>Macchine</strong>
    <?php if (count($vlt) || count($awp)): ?>
    <div class="mach-chips" id="mach-kpi-chips" style="margin-right:auto">
      <?php if (count($vlt)): ?>
      <span class="mach-chip"><span class="mach-type-sm mach-type-vlt">VLT</span><?= $nVlt ?> attive</span>
      <?php endif; ?>
      <?php if (count($awp)): ?>
      <span class="mach-chip"><span class="mach-type-sm mach-type-awp">AWP</span><?= $nAwp ?> attive</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <label class="topbar-search-wrap" id="mach-search-wrap">
    <svg class="topbar-search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="search" class="topbar-search" id="mach-search" placeholder="Cerca…" aria-label="Cerca">
  </label>
</header>

<nav class="mach-tab-bar" role="tablist" aria-label="Sezioni">
  <button class="mach-tab" data-tab="macchine"  role="tab">Macchine</button>
  <button class="mach-tab" data-tab="fornitori" role="tab">Fornitori</button>
</nav>

<?php if ($okMsg || $msg): ?>
<div class="<?= $msg ? 'warn' : 'ok' ?>" role="alert"><?= $h($msg ?: $okMsg) ?></div>
<?php endif; ?>

<!-- ================================================================
     TAB: MACCHINE
     ================================================================ -->
<div id="tab-macchine" class="mach-tab-pane">

  <div class="mach-page">
    <?php if (empty($macchine)): ?>
    <div class="ul-empty">
      <div class="ul-empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
      </div>
      <p class="ul-empty-title">Nessuna macchina ancora</p>
      <p class="ul-empty-sub">Aggiungi la prima macchina con il pulsante qui sotto.</p>
      <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-mach-add').showModal()" style="margin-top:12px">+ Aggiungi macchina</button>
    </div>
    <?php else: ?>

    <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
      <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-mach-add').showModal()">+ Aggiungi macchina</button>
    </div>

    <?php foreach ($macchine as $m):
      $mid = (int)$m['id']; ?>
    <form id="mef-<?= $mid ?>" method="post" hidden>
      <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="edit">
      <input type="hidden" name="id"     value="<?= $mid ?>">
    </form>
    <form id="mtf-<?= $mid ?>" method="post" hidden>
      <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="toggle">
      <input type="hidden" name="id"     value="<?= $mid ?>">
    </form>
    <?php endforeach; ?>

    <div class="mach-wrap">
      <div class="mach-head" aria-hidden="true">
        <span>Codice</span><span>Fornitore</span><span>Ord.</span><span>Seriale</span><span>CIV</span><span></span><span></span><span>Guasti</span>
      </div>

      <?php foreach ([['VLT', $vlt, $nVlt], ['AWP', $awp, $nAwp]] as [$tipo, $list, $n]):
        if (empty($list)) continue; ?>
      <div class="mach-group-row">
        <span class="mach-type-badge mach-type-<?= strtolower($tipo) ?>"><?= $tipo ?></span>
        <span class="mach-group-count"><?= $n ?> <?= $n === 1 ? 'attiva' : 'attive' ?> su <?= count($list) ?></span>
      </div>
      <?php foreach ($list as $m):
        $isActive = (bool)$m['attiva'];
        $mid      = (int)$m['id'];
        $mTk      = $ticketsByMach[$m['codice']] ?? [];
        $mTkCount = count($mTk);
        $mTkOpen  = count(array_filter($mTk, fn($t) => $t['stato'] === 'aperto')); ?>
      <div class="mach-row<?= $isActive ? '' : ' mach-row-off' ?>">
        <input class="mach-input" name="codice" form="mef-<?= $mid ?>"
               value="<?= $h($m['codice']) ?>" placeholder="Codice" required aria-label="Codice macchina">
        <select class="mach-select" name="fornitore" form="mef-<?= $mid ?>" aria-label="Fornitore">
          <?php foreach (array_merge($fornitori, ['ALTRO']) as $f): ?>
          <option <?= $f === $m['fornitore'] ? 'selected' : '' ?>><?= $h($f) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="number" class="mach-input mach-ord" name="ordine" form="mef-<?= $mid ?>"
               value="<?= (int)$m['ordine'] ?>" min="0" aria-label="Ordine">
        <input type="text" class="mach-input mach-mono" name="seriale" form="mef-<?= $mid ?>"
               value="<?= $h($m['seriale'] ?? '') ?>" placeholder="—" maxlength="100" aria-label="Seriale <?= $h($m['codice']) ?>">
        <input type="text" class="mach-input mach-mono" name="civ" form="mef-<?= $mid ?>"
               value="<?= $h($m['civ'] ?? '') ?>" placeholder="—" maxlength="100" aria-label="CIV <?= $h($m['codice']) ?>">
        <button type="submit" class="mach-btn mach-btn-save" form="mef-<?= $mid ?>" title="Salva modifiche">Salva</button>
        <button type="submit" class="mach-btn <?= $isActive ? 'mach-btn-off' : 'mach-btn-on' ?>"
                form="mtf-<?= $mid ?>"
                <?= $isActive ? 'data-confirm="Disattivare ' . $h($m['codice']) . '?"' : '' ?>>
          <?= $isActive ? 'Disattiva' : 'Attiva' ?>
        </button>
        <?php if ($mTkCount > 0): ?>
        <button type="button" class="mach-btn mach-tk-btn<?= $mTkOpen > 0 ? ' mach-tk-open' : '' ?>"
                onclick="var d=document.getElementById('mh-<?= $mid ?>');if(d)d.open=!d.open;"
                aria-label="Storico guasti <?= $h($m['codice']) ?>"><?= $mTkCount ?></button>
        <?php else: ?>
        <span class="mach-tk-empty" aria-hidden="true">—</span>
        <?php endif; ?>
      </div>
      <?php if ($mTkCount > 0): ?>
      <details class="mach-history" id="mh-<?= $mid ?>">
        <summary>
          Storico guasti · <?= $mTkCount ?> <?= $mTkCount === 1 ? 'guasto' : 'guasti' ?>
          <?php if ($mTkOpen > 0): ?><span class="badge open"><?= $mTkOpen ?> aper<?= $mTkOpen === 1 ? 'to' : 'ti' ?></span><?php endif; ?>
        </summary>
        <div class="mach-history-list">
          <?php foreach ($mTk as $tk): ?>
          <div class="mach-hist-row">
            <span class="mach-hist-date"><?= $h(date('d/m/Y', strtotime($tk['data_apertura']))) ?></span>
            <span class="mach-hist-prob"><?= $h(mb_substr($tk['problema'] ?? '', 0, 80)) ?></span>
            <span class="badge <?= $tk['stato'] === 'aperto' ? 'open' : 'closed' ?>"><?= $tk['stato'] === 'aperto' ? 'Aperto' : 'Risolto' ?></span>
            <span class="mach-hist-date"><?= $tk['data_chiusura'] ? $h(date('d/m/Y', strtotime($tk['data_chiusura']))) : '—' ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endif; ?>
      <?php endforeach; ?>
      <?php endforeach; ?>
    </div>

    <p class="mach-hint">Le macchine disattivate non compaiono nel giornaliero ma rimangono nello storico.</p>
    <?php endif; ?>
  </div>

</div><!-- /tab-macchine -->

<!-- ================================================================
     TAB: FORNITORI
     ================================================================ -->
<div id="tab-fornitori" class="mach-tab-pane">
  <div class="imp-page" style="padding-top:20px">

    <section class="imp-card" style="grid-column: 1 / -1">
      <div class="imp-card-head">
        <div class="imp-card-ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div style="flex:1">
          <h2 class="imp-card-title">Lista fornitori</h2>
          <p class="imp-card-desc">I fornitori configurati qui compaiono in Scassettamenti, Ticket vincite, Bet/Win e nei menu di selezione delle macchine. Disabilitando un fornitore lo si nasconde dai nuovi inserimenti — i dati storici rimangono intatti. Un fornitore disabilitato può essere eliminato solo se non ha dati storici collegati.</p>
        </div>
        <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-forn-add').showModal()">+ Aggiungi</button>
      </div>

      <?php if ($listaFornitori): ?>
      <ul class="forn-list" id="forn-list">
        <?php foreach ($listaFornitori as $f): ?>
        <li class="forn-row <?= $f['attiva'] ? '' : 'forn-off' ?>" data-id="<?= $f['id'] ?>">
          <span class="forn-handle" title="Trascina per riordinare" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
          </span>
          <form method="post" class="forn-rinomina-form">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="forn_rinomina">
            <input type="hidden" name="id"     value="<?= $f['id'] ?>">
            <input class="forn-name-input" type="text" name="nome" value="<?= $h($f['nome']) ?>" maxlength="50" aria-label="Nome fornitore">
            <button type="submit" class="ghost forn-btn-sm">Salva</button>
          </form>
          <form method="post" class="forn-toggle-form">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="forn_toggle">
            <input type="hidden" name="id"     value="<?= $f['id'] ?>">
            <button type="submit" class="ghost forn-btn-sm <?= $f['attiva'] ? '' : 'forn-toggle-on' ?>">
              <?= $f['attiva'] ? 'Disabilita' : 'Abilita' ?>
            </button>
          </form>
          <?php if (!$f['attiva']): ?>
          <form method="post" onsubmit="return confirm('Eliminare definitivamente <?= $h($f['nome']) ?>?\nQuesta azione è irreversibile. Se ci sono dati storici collegati l\'eliminazione verrà bloccata automaticamente.')">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="forn_elimina">
            <input type="hidden" name="id"     value="<?= $f['id'] ?>">
            <button type="submit" class="ghost forn-btn-sm forn-btn-danger">Elimina</button>
          </form>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <p class="ticket-empty">Nessun fornitore ancora.</p>
      <?php endif; ?>

      <form method="post" id="forn-ordine-form" style="display:none">
        <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="forn_ordine">
      </form>
    </section>

  </div>
</div><!-- /tab-fornitori -->

<!-- ================================================================
     DIALOG: Aggiungi macchina
     ================================================================ -->
<dialog id="dlg-mach-add" class="form-dialog">
  <div class="dlg-head">
    <strong>Aggiungi macchina</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="add">
    <div class="ul-form-grid">
      <div class="ul-field ul-field-full">
        <label for="add-cod">Codice macchina <span class="ul-req">*</span></label>
        <input id="add-cod" type="text" name="codice" required placeholder="es. NOVO 99" maxlength="40">
      </div>
      <div class="ul-field">
        <label for="add-tipo">Tipo</label>
        <select id="add-tipo" name="tipo">
          <option value="VLT">VLT</option>
          <option value="AWP">AWP</option>
        </select>
      </div>
      <div class="ul-field">
        <label for="add-forn">Fornitore</label>
        <select id="add-forn" name="fornitore">
          <?php foreach (array_merge($fornitori, ['ALTRO']) as $f): ?><option><?= $h($f) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="ul-field">
        <label for="add-ord">Ordine visualizzazione</label>
        <input id="add-ord" type="number" name="ordine" min="0" value="0">
      </div>
      <div class="ul-field">
        <label for="add-ser">Seriale</label>
        <input id="add-ser" type="text" name="seriale" maxlength="100" placeholder="es. SN123456">
      </div>
      <div class="ul-field">
        <label for="add-civ">CIV</label>
        <input id="add-civ" type="text" name="civ" maxlength="100" placeholder="es. CIV-ABC">
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Aggiungi</button>
    </div>
  </form>
</dialog>

<!-- ================================================================
     DIALOG: Aggiungi fornitore
     ================================================================ -->
<dialog id="dlg-forn-add" class="form-dialog">
  <div class="dlg-head">
    <div>
      <strong>Aggiungi fornitore</strong>
      <span class="dlg-head-sub">Il nome viene convertito automaticamente in maiuscolo.</span>
    </div>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="forn_aggiungi">
    <div class="field">
      <label for="forn-nome-dlg">Nome fornitore</label>
      <input id="forn-nome-dlg" type="text" name="nome" maxlength="50" placeholder="Es. GAMENET" required autofocus style="text-transform:uppercase;width:220px">
    </div>
    <div class="dlg-actions">
      <button type="button" class="ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Aggiungi</button>
    </div>
  </form>
</dialog>

<script>
(function () {
  /* ---- Tab switching ---- */
  var tabs   = document.querySelectorAll('.mach-tab');
  var panes  = document.querySelectorAll('.mach-tab-pane');
  var chips  = document.getElementById('mach-kpi-chips');

  function activateTab(name) {
    tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === name); });
    panes.forEach(function (p) { p.classList.toggle('active', p.id === 'tab-' + name); });
    if (chips) chips.style.display = name === 'macchine' ? '' : 'none';
    try { history.replaceState(null, '', '#' + name); } catch (e) {}
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      activateTab(t.dataset.tab);
      var inp = document.getElementById('mach-search');
      if (inp) { inp.value = ''; runSearch(''); }
    });
  });

  var hash = (location.hash || '').replace('#', '');
  activateTab(['macchine', 'fornitori'].includes(hash) ? hash : 'macchine');

  /* ---- Ricerca live (per tab) ---- */
  function runSearch(q) {
    var activeTab = (document.querySelector('.mach-tab.active') || {}).dataset;
    var tab = activeTab ? activeTab.tab : 'macchine';

    if (tab === 'macchine') {
      var groups = document.querySelectorAll('#tab-macchine .mach-group-row');
      document.querySelectorAll('#tab-macchine .mach-row').forEach(function (row) {
        var text = Array.from(row.querySelectorAll('input[name], select')).map(function (el) {
          return el.value;
        }).join(' ').toLowerCase();
        var show = !q || text.includes(q);
        row.style.display = show ? '' : 'none';
        var next = row.nextElementSibling;
        if (next && next.classList.contains('mach-history')) next.style.display = show ? '' : 'none';
      });
      groups.forEach(function (g) {
        var sib = g.nextElementSibling;
        var anyVis = false;
        while (sib && !sib.classList.contains('mach-group-row')) {
          if (sib.classList.contains('mach-row') && sib.style.display !== 'none') { anyVis = true; break; }
          sib = sib.nextElementSibling;
        }
        g.style.display = anyVis ? '' : 'none';
      });
    } else {
      document.querySelectorAll('#forn-list .forn-row').forEach(function (row) {
        var text = (row.querySelector('input[name="nome"]') || {}).value || '';
        row.style.display = !q || text.toLowerCase().includes(q) ? '' : 'none';
      });
    }
  }

  var machSrch = document.getElementById('mach-search');
  if (machSrch) {
    machSrch.addEventListener('input', function () { runSearch(this.value.trim().toLowerCase()); });
  }

  /* ---- Drag-to-reorder fornitori ---- */
  var list = document.getElementById('forn-list');
  if (list) {
    var dragged = null;
    list.querySelectorAll('.forn-handle').forEach(function (h) { h.closest('.forn-row').draggable = true; });
    list.addEventListener('dragstart', function (e) { dragged = e.target.closest('.forn-row'); dragged.style.opacity = '.4'; });
    list.addEventListener('dragend',   function ()  {
      if (dragged) dragged.style.opacity = '';
      dragged = null;
      var form = document.getElementById('forn-ordine-form');
      form.querySelectorAll('input[name="ids[]"]').forEach(function (i) { i.remove(); });
      list.querySelectorAll('.forn-row').forEach(function (row) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = row.dataset.id;
        form.appendChild(inp);
      });
      form.submit();
    });
    list.addEventListener('dragover', function (e) {
      e.preventDefault();
      var target = e.target.closest('.forn-row');
      if (target && target !== dragged) {
        var rect = target.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) list.insertBefore(dragged, target);
        else list.insertBefore(dragged, target.nextSibling);
      }
    });
  }

  /* ---- Conferma disattiva macchina ---- */
  document.querySelectorAll('[data-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  /* ---- Chiudi dialog clic fuori ---- */
  document.querySelectorAll('.form-dialog').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  });
})();
</script>
</body></html>
