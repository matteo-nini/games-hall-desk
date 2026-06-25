<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$pdo  = db();
$cfg  = config();
$sett = get_settings($pdo);
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

if (($sett['modulo_documenti'] ?? '1') !== '1') {
    header('Location: ../cassa/giornaliero.php'); exit;
}

$UPLOAD_DIR  = dirname(__DIR__) . '/account/uploads/documenti/';
$ALLOWED_EXT = ['pdf','png','jpg','jpeg','webp','docx','xlsx','odt','ods'];

// Check table exists
$tableOk = false;
try { $pdo->query('SELECT 1 FROM documenti LIMIT 0'); $tableOk = true; } catch (PDOException) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableOk) {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    if ($az === 'upload' && is_responsabile()) {
        $file = $_FILES['documento'] ?? null;
        $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 120);
        $desc = mb_substr(trim($_POST['descrizione'] ?? ''), 0, 255) ?: null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 20 * 1024 * 1024 || $nome === '') {
            header('Location: documenti.php?err=upload'); exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT, true)) {
            header('Location: documenti.php?err=tipo'); exit;
        }
        if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);

        $fname = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $UPLOAD_DIR . $fname)) {
            header('Location: documenti.php?err=upload'); exit;
        }
        $mime = match($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'odt'  => 'application/vnd.oasis.opendocument.text',
            'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
            default => 'application/octet-stream',
        };
        $pdo->prepare('INSERT INTO documenti (nome,descrizione,filename,mime,caricato_da) VALUES (?,?,?,?,?)')
            ->execute([$nome, $desc, $fname, $mime, $user['id']]);
        audit('documento_aggiunto', 'documenti', (int)$pdo->lastInsertId(), $nome);
        header('Location: documenti.php?ok=1'); exit;
    }

    if ($az === 'del' && is_responsabile()) {
        $id  = (int)($_POST['id'] ?? 0);
        $row = $pdo->prepare('SELECT filename FROM documenti WHERE id=?');
        $row->execute([$id]);
        $doc = $row->fetch();
        if ($doc) {
            $fp = $UPLOAD_DIR . $doc['filename'];
            if (file_exists($fp)) @unlink($fp);
            $pdo->prepare('DELETE FROM documenti WHERE id=?')->execute([$id]);
            audit('documento_eliminato', 'documenti', $id, null);
        }
        header('Location: documenti.php?ok=1'); exit;
    }

    header('Location: documenti.php'); exit;
}

$docs = [];
if ($tableOk) {
    $docs = $pdo->query('
        SELECT d.*, COALESCE(NULLIF(u.nome,""), u.username) AS autore
        FROM documenti d
        LEFT JOIN utenti u ON u.id = d.caricato_da
        WHERE d.visibile = 1
        ORDER BY d.ordine ASC, d.caricato_il DESC
    ')->fetchAll();
}

function doc_icon(string $mime): string {
    if ($mime === 'application/pdf') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/><polyline points="9 9 10 9"/></svg>';
    if (str_starts_with($mime, 'image/')) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}

function doc_icon_class(string $mime): string {
    if ($mime === 'application/pdf') return 'doc-icon-pdf';
    if (str_starts_with($mime, 'image/')) return 'doc-icon-img';
    return 'doc-icon-file';
}
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Documenti · <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/documenti.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div>
    <strong>Documenti</strong>
    <?php if ($tableOk && count($docs)): ?>
    <span class="topbar-sub"><?= count($docs) ?> <?= count($docs) === 1 ? 'documento' : 'documenti' ?></span>
    <?php endif; ?>
  </div>
  <?php if (is_responsabile() && $tableOk): ?>
  <div class="topbar-actions">
    <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-upload').showModal()">+ Carica</button>
  </div>
  <?php endif; ?>
</header>

<?php if (isset($_GET['ok'])): ?>
<div class="ok" role="alert">Operazione completata.</div>
<?php elseif (isset($_GET['err'])): ?>
<div class="warn" role="alert">
  <?php if ($_GET['err'] === 'tipo'): ?>Tipo file non consentito. Usa PDF, immagini, Word o Excel.
  <?php else: ?>Errore durante il caricamento del file.<?php endif; ?>
</div>
<?php endif; ?>

<div class="doc-page">

<?php if (!$tableOk): ?>
  <div class="warn">
    Eseguire <code>sql/documenti_migration.sql</code> per attivare questo modulo.
  </div>

<?php elseif (empty($docs)): ?>
  <div class="doc-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
    <p class="doc-empty-title">Nessun documento</p>
    <p class="doc-empty-sub">
      <?php if (is_responsabile()): ?>
        Carica il primo documento con il pulsante in alto a destra.
      <?php else: ?>
        I documenti caricati dal responsabile appariranno qui.
      <?php endif; ?>
    </p>
  </div>

<?php else: ?>
  <div class="doc-list">
    <?php foreach ($docs as $doc):
      $ext      = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
      $icoClass = doc_icon_class($doc['mime']);
      $viewUrl  = base_url('sala/doc_view.php') . '?id=' . $doc['id'];
    ?>
    <div class="doc-item">
      <div class="doc-icon <?= $icoClass ?>" aria-hidden="true">
        <?= doc_icon($doc['mime']) ?>
      </div>
      <div class="doc-body">
        <div class="doc-name"><?= $h($doc['nome']) ?></div>
        <?php if ($doc['descrizione']): ?>
        <div class="doc-desc"><?= $h($doc['descrizione']) ?></div>
        <?php endif; ?>
        <div class="doc-meta">
          <?= mb_strtoupper($ext) ?>
          &middot; <?= $h(date('d/m/Y', strtotime($doc['caricato_il']))) ?>
          <?php if ($doc['autore']): ?>&middot; <?= $h($doc['autore']) ?><?php endif; ?>
        </div>
      </div>
      <div class="doc-actions">
        <a href="<?= $h($viewUrl) ?>" target="_blank" rel="noopener" class="doc-open-btn">Apri</a>
        <a href="<?= $h($viewUrl) ?>&dl=1" class="doc-del-btn" title="Scarica">&#8595;</a>
        <?php if (is_responsabile()): ?>
        <form method="post" onsubmit="return confirm('Eliminare questo documento?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="del">
          <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
          <button type="submit" class="doc-del-btn">Elimina</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</div>

<?php if (is_responsabile() && $tableOk): ?>
<dialog id="dlg-upload" class="form-dialog">
  <div class="dlg-head">
    <strong>Carica documento</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="upload">
    <div class="tnf-grid" style="padding:16px 20px 0">
      <div class="field tnf-full">
        <label for="doc-nome">Nome *</label>
        <input id="doc-nome" type="text" name="nome" required maxlength="120" placeholder="es. Modulo antiriciclaggio">
      </div>
      <div class="field tnf-full">
        <label for="doc-desc">Descrizione <span class="doc-dlg-file-hint">(opzionale)</span></label>
        <input id="doc-desc" type="text" name="descrizione" maxlength="255" placeholder="Breve descrizione">
      </div>
      <div class="field tnf-full">
        <label for="doc-file">File * <span class="doc-dlg-file-hint">PDF, immagini, Word, Excel · max 20 MB</span></label>
        <input id="doc-file" type="file" name="documento" required
               accept=".pdf,.png,.jpg,.jpeg,.webp,.docx,.xlsx,.odt,.ods">
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Carica</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
</body></html>
