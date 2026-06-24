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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

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
}

$fornitori = get_fornitori($pdo);
$macchine = $pdo->query('SELECT * FROM macchine ORDER BY tipo, ordine, codice')->fetchAll();
$vlt      = array_values(array_filter($macchine, fn($m) => $m['tipo'] === 'VLT'));
$awp      = array_values(array_filter($macchine, fn($m) => $m['tipo'] === 'AWP'));
$nVlt     = count(array_filter($vlt, fn($m) => (bool)$m['attiva']));
$nAwp     = count(array_filter($awp, fn($m) => (bool)$m['attiva']));

$allTickets = $pdo->query('SELECT macchina, problema, stato, data_apertura, data_chiusura FROM ticket_assistenza ORDER BY data_apertura DESC')->fetchAll();
$ticketsByMach = [];
foreach ($allTickets as $tk) { $ticketsByMach[$tk['macchina']][] = $tk; }

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
</head><body>
<?php require __DIR__ . '/../../includes/nav.php'; top_menu($user); ?>

<header class="topbar sh-top">
  <div class="mach-header-left">
    <strong>Macchine</strong>
    <?php if (count($vlt) || count($awp)): ?>
    <div class="mach-chips">
      <?php if (count($vlt)): ?>
      <span class="mach-chip"><span class="mach-type-sm mach-type-vlt">VLT</span><?= $nVlt ?> attive</span>
      <?php endif; ?>
      <?php if (count($awp)): ?>
      <span class="mach-chip"><span class="mach-type-sm mach-type-awp">AWP</span><?= $nAwp ?> attive</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-mach-add').showModal()">
    <svg width="11" height="11" viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5.5 1v9M1 5.5h9"/></svg>
    Aggiungi macchina
  </button>
</header>

<?php if ($okMsg || $msg): ?>
<div class="<?= $msg ? 'warn' : 'ok' ?>" role="alert"><?= $h($msg ?: $okMsg) ?></div>
<?php endif; ?>

<dialog id="dlg-mach-add" class="form-dialog">
  <div class="dlg-head">
    <strong>Aggiungi macchina</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
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

<div class="mach-page">
<?php if (empty($macchine)): ?>
  <div class="ul-empty">
    <div class="ul-empty-icon">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
    </div>
    <p class="ul-empty-title">Nessuna macchina ancora</p>
    <p class="ul-empty-sub">Aggiungi la prima macchina con il pulsante in alto a destra.</p>
  </div>
<?php else: ?>

  <?php
  /* Render hidden forms outside the grid so the form= attribute approach works cleanly */
  foreach ($macchine as $m):
    $mid = (int)$m['id']; ?>
  <form id="mef-<?= $mid ?>" method="post" hidden>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="edit">
    <input type="hidden" name="id" value="<?= $mid ?>">
  </form>
  <form id="mtf-<?= $mid ?>" method="post" hidden>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="toggle">
    <input type="hidden" name="id" value="<?= $mid ?>">
  </form>
  <?php endforeach; ?>

  <div class="mach-wrap">
    <div class="mach-head" aria-hidden="true">
      <span>Codice</span>
      <span>Fornitore</span>
      <span>Ord.</span>
      <span>Seriale</span>
      <span>CIV</span>
      <span></span>
      <span></span>
      <span>Guasti</span>
    </div>

    <?php foreach ([['VLT', $vlt, $nVlt], ['AWP', $awp, $nAwp]] as [$tipo, $list, $n]):
      if (empty($list)) continue; ?>

    <div class="mach-group-row">
      <span class="mach-type-badge mach-type-<?= strtolower($tipo) ?>"><?= $tipo ?></span>
      <span class="mach-group-count"><?= $n ?> <?= $n === 1 ? 'attiva' : 'attive' ?> su <?= count($list) ?></span>
    </div>

    <?php foreach ($list as $m):
      $isActive = (bool)$m['attiva'];
      $mid = (int)$m['id'];
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
              aria-label="Storico guasti <?= $h($m['codice']) ?>">
        <?= $mTkCount ?>
      </button>
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

<script>
document.querySelectorAll('[data-confirm]').forEach(function(btn) {
  btn.addEventListener('click', function(e) {
    if (!confirm(btn.dataset.confirm)) e.preventDefault();
  });
});
</script>
</body></html>
