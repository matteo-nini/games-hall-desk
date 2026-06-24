<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
require_not_revisore();
$cfg  = config();
$pdo  = db();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

if ((get_settings($pdo)['modulo_assistenze'] ?? '1') !== '1') {
    header('Location: ../cassa/giornaliero.php'); exit;
}

$macchine_list = $pdo->query('SELECT codice FROM macchine WHERE attiva=1 ORDER BY tipo,ordine')
                      ->fetchAll(PDO::FETCH_COLUMN);

$sett       = get_settings($pdo);
$aNumero    = $sett['assistenza_numero']   ?? '';
$aLock      = $sett['assistenza_lock']     ?? '';
$aPassword  = $sett['assistenza_password'] ?? '';
$hasAssInfo = $aNumero !== '' || $aLock !== '' || $aPassword !== '';

/* ---- POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    if ($az === 'nuovo') {
        $ap  = $_POST['data_apertura'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ap)) $ap = date('Y-m-d');
        $mac = mb_substr(trim($_POST['macchina'] ?? ''), 0, 50);
        $prb = mb_substr(trim($_POST['problema'] ?? ''), 0, 500);
        $idt = mb_substr(trim($_POST['id_ticket'] ?? ''), 0, 50) ?: null;
        if ($mac === '' || $prb === '') { header('Location: ticket.php?err=campi'); exit; }
        $pdo->prepare('INSERT INTO ticket_assistenza (data_apertura,macchina,problema,id_ticket,stato,creato_da) VALUES (?,?,?,?,"aperto",?)')
            ->execute([$ap, $mac, $prb, $idt, $user['id']]);
        audit('ticket_aperto','ticket_assistenza',(int)$pdo->lastInsertId(),"$mac – $prb");
        header('Location: ticket.php?ok=1'); exit;
    }

    if ($az === 'chiudi') {
        $id  = (int)($_POST['id'] ?? 0);
        $dc  = $_POST['data_chiusura'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dc)) $dc = date('Y-m-d');
        $ris = mb_substr(trim($_POST['risoluzione'] ?? ''), 0, 500) ?: null;
        $idt = mb_substr(trim($_POST['id_ticket'] ?? ''), 0, 50) ?: null;
        $pdo->prepare('UPDATE ticket_assistenza SET risoluzione=?,data_chiusura=?,id_ticket=COALESCE(id_ticket,?),stato="risolto" WHERE id=?')
            ->execute([$ris, $dc, $idt, $id]);
        audit('ticket_chiuso','ticket_assistenza',$id,$ris ?? '');
        header('Location: ticket.php?ok=1'); exit;
    }

    if ($az === 'elimina' && is_responsabile()) {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM ticket_assistenza WHERE id=?')->execute([$id]);
        audit('ticket_eliminato','ticket_assistenza',$id);
        header('Location: ticket.php?ok=1'); exit;
    }

    header('Location: ticket.php'); exit;
}

/* ---- GET ---- */
$filtro = in_array($_GET['filtro'] ?? '', ['aperto','risolto']) ? $_GET['filtro'] : 'tutti';
$where  = $filtro !== 'tutti' ? 'WHERE t.stato=?' : '';
$params = $filtro !== 'tutti' ? [$filtro] : [];
$st = $pdo->prepare("SELECT t.*, COALESCE(NULLIF(u.nome,''),u.username) AS op
                     FROM ticket_assistenza t LEFT JOIN utenti u ON u.id=t.creato_da
                     $where ORDER BY (t.stato='aperto') DESC, t.id DESC");
$st->execute($params);
$tickets = $st->fetchAll();
$n_aperti = (int)$pdo->query('SELECT COUNT(*) FROM ticket_assistenza WHERE stato="aperto"')->fetchColumn();
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ticket assistenza</title><link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/ticket.css') ?>"></head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div>
    <strong>Ticket assistenza</strong>
    <?php if ($n_aperti): ?><span class="badge-count"><?= $n_aperti ?> aperti</span><?php endif; ?>
  </div>
  <div class="filtri-bar">
    <a class="filtro-btn <?= $filtro==='tutti'?'active':'' ?>" href="?filtro=tutti">Tutti</a>
    <a class="filtro-btn <?= $filtro==='aperto'?'active':'' ?>" href="?filtro=aperto">Aperti</a>
    <a class="filtro-btn <?= $filtro==='risolto'?'active':'' ?>" href="?filtro=risolto">Risolti</a>
  </div>
  <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-ticket').showModal()">+ Apri ticket</button>
</header>

<?php if (isset($_GET['ok'])): ?><div class="ok">Salvato</div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="warn">Compilare tutti i campi obbligatori.</div><?php endif; ?>

<!-- Dialog nuovo ticket -->
<dialog id="dlg-ticket" class="form-dialog">
  <div class="dlg-head">
    <strong>Apri nuovo ticket</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <?php if ($hasAssInfo): ?>
  <div class="tk-ass-info">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8 9a16 16 0 0 0 5 5l.72-.85a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 20.5 15l.42 1.92z"/></svg>
    <span class="tk-ass-label">Contatti assistenza tecnica</span>
    <div class="tk-ass-fields">
      <?php if ($aNumero !== ''): ?><span class="tk-ass-item"><span class="tk-ass-key">Tel.</span> <strong><?= $h($aNumero) ?></strong></span><?php endif; ?>
      <?php if ($aLock !== ''): ?><span class="tk-ass-item"><span class="tk-ass-key">Lock</span> <strong><?= $h($aLock) ?></strong></span><?php endif; ?>
      <?php if ($aPassword !== ''): ?><span class="tk-ass-item"><span class="tk-ass-key">Password</span> <strong><?= $h($aPassword) ?></strong></span><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="nuovo">
    <div class="tnf-grid">
      <div class="field">
        <label>Data apertura</label>
        <input type="date" name="data_apertura" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="field">
        <label>Macchina *</label>
        <input type="text" name="macchina" list="mac-list" required placeholder="es. INSP 110">
        <datalist id="mac-list">
          <?php foreach ($macchine_list as $m): ?><option value="<?= $h($m) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div class="field tnf-full">
        <label>Problema *</label>
        <input type="text" name="problema" required placeholder="descrizione del guasto o anomalia" style="width:100%">
      </div>
      <div class="field">
        <label>ID Ticket (opz.)</label>
        <input type="text" name="id_ticket" placeholder="CAS-XXXXXXX">
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Apri ticket</button>
    </div>
  </form>
</dialog>
<script>
  <?php if (isset($_GET['nuovo'])): ?>document.getElementById('dlg-ticket').showModal();<?php endif; ?>
</script>

<!-- Lista ticket -->
<div class="ticket-list">
  <?php foreach ($tickets as $t): ?>
  <div class="ticket-card tc-<?= $h($t['stato']) ?>">
    <div class="tc-head">
      <span class="tc-mach"><?= $h($t['macchina']) ?></span>
      <span class="badge <?= $t['stato']==='aperto'?'open':'closed' ?>"><?= $t['stato']==='aperto'?'APERTO':'RISOLTO' ?></span>
    </div>
    <div class="tc-prob"><?= $h($t['problema']) ?></div>
    <div class="tc-meta">
      <span>Apertura: <?= $h(date('d/m/Y', strtotime($t['data_apertura']))) ?></span>
      <?php if ($t['id_ticket']): ?><span>&middot; <?= $h($t['id_ticket']) ?></span><?php endif; ?>
      <?php if ($t['op']): ?><span>&middot; <?= $h($t['op']) ?></span><?php endif; ?>
    </div>
    <?php if ($t['stato'] === 'risolto'): ?>
    <div class="tc-ris">&#10003; <?= $h($t['risoluzione'] ?? '—') ?><?php if ($t['data_chiusura']): ?> &middot; <span class="tc-dch">chiuso <?= $h(date('d/m/Y', strtotime($t['data_chiusura']))) ?></span><?php endif; ?></div>
    <?php else: ?>
    <details class="tc-chiudi">
      <summary>Chiudi ticket</summary>
      <form method="post" class="tc-chiudi-form">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="chiudi">
        <input type="hidden" name="id" value="<?= $h($t['id']) ?>">
        <div class="field"><label>Data chiusura</label><input type="date" name="data_chiusura" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label>ID Ticket (se mancante)</label><input type="text" name="id_ticket" placeholder="CAS-XXXXXXX"></div>
        <div class="field tnf-full"><label>Risoluzione</label><input type="text" name="risoluzione" placeholder="es. Intervento remoto / Mandano tecnico" style="width:100%"></div>
        <button type="submit">Segna risolto</button>
      </form>
    </details>
    <?php endif; ?>
    <?php if (is_responsabile()): ?>
    <form method="post" class="tc-del" onsubmit="return confirm('Eliminare questo ticket?')">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="elimina">
      <input type="hidden" name="id" value="<?= $h($t['id']) ?>">
      <button type="submit" class="ghost tc-del-btn">Elimina</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (empty($tickets)): ?>
  <p class="ticket-empty">Nessun ticket trovato per il filtro selezionato.</p>
  <?php endif; ?>
</div>
</body></html>
