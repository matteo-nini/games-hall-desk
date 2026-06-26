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

// Check tables
$tableOk   = false;
$foldersOk = false;
$colOk     = false;
try { $pdo->query('SELECT 1 FROM documenti LIMIT 0');          $tableOk   = true; } catch (PDOException) {}
try { $pdo->query('SELECT 1 FROM documenti_cartelle LIMIT 0'); $foldersOk = true; } catch (PDOException) {}
try { $pdo->query('SELECT cartella_id FROM documenti LIMIT 0'); $colOk    = true; } catch (PDOException) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableOk) {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    // ---- Cartelle (responsabile only) ----
    if ($foldersOk && is_responsabile()) {
        if ($az === 'crea_cartella') {
            $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 120);
            if ($nome !== '') {
                $pdo->prepare('INSERT INTO documenti_cartelle (nome, creata_da) VALUES (?,?)')
                    ->execute([$nome, $user['id']]);
                audit('cartella_creata', 'documenti_cartelle', (int)$pdo->lastInsertId(), $nome);
            }
            header('Location: documenti.php?ok=1'); exit;
        }

        if ($az === 'rinomina_cartella') {
            $id   = (int)($_POST['id'] ?? 0);
            $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 120);
            if ($id && $nome !== '') {
                $pdo->prepare('UPDATE documenti_cartelle SET nome=? WHERE id=?')->execute([$nome, $id]);
                audit('cartella_rinominata', 'documenti_cartelle', $id, $nome);
            }
            header('Location: documenti.php?ok=1'); exit;
        }

        if ($az === 'elimina_cartella') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                if ($colOk) {
                    $pdo->prepare('UPDATE documenti SET cartella_id=NULL WHERE cartella_id=?')->execute([$id]);
                }
                $pdo->prepare('DELETE FROM documenti_cartelle WHERE id=?')->execute([$id]);
                audit('cartella_eliminata', 'documenti_cartelle', $id, null);
            }
            header('Location: documenti.php?ok=1'); exit;
        }
    }

    // ---- Sposta documento (responsabile only) ----
    if ($az === 'sposta_doc' && is_responsabile() && $colOk) {
        $id       = (int)($_POST['id'] ?? 0);
        $cartella = (int)($_POST['cartella_id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE documenti SET cartella_id=? WHERE id=?')
                ->execute([$cartella ?: null, $id]);
            audit('documento_spostato', 'documenti', $id, "cartella:{$cartella}");
        }
        header('Location: documenti.php?ok=1'); exit;
    }

    // ---- Upload ----
    if ($az === 'upload' && is_responsabile()) {
        $file     = $_FILES['documento'] ?? null;
        $nome     = mb_substr(trim($_POST['nome'] ?? ''), 0, 120);
        $desc     = mb_substr(trim($_POST['descrizione'] ?? ''), 0, 255) ?: null;
        $cartella = $colOk ? ((int)($_POST['cartella_id'] ?? 0) ?: null) : null;

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
            'pdf'        => 'application/pdf',
            'png'        => 'image/png',
            'jpg','jpeg' => 'image/jpeg',
            'webp'       => 'image/webp',
            'docx'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'odt'        => 'application/vnd.oasis.opendocument.text',
            'ods'        => 'application/vnd.oasis.opendocument.spreadsheet',
            default      => 'application/octet-stream',
        };
        $pdo->prepare('INSERT INTO documenti (nome,descrizione,filename,mime,cartella_id,caricato_da) VALUES (?,?,?,?,?,?)')
            ->execute([$nome, $desc, $fname, $mime, $cartella, $user['id']]);
        audit('documento_aggiunto', 'documenti', (int)$pdo->lastInsertId(), $nome);
        header('Location: documenti.php?ok=1'); exit;
    }

    // ---- Elimina ----
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

// ---- GET: fetch data ----
$folders = [];
if ($foldersOk) {
    $folders = $pdo->query('SELECT * FROM documenti_cartelle ORDER BY ordine ASC, nome ASC')->fetchAll();
}

$docs = [];
if ($tableOk) {
    $colSel = $colOk ? 'd.cartella_id,' : '';
    $docs = $pdo->query("
        SELECT d.id, d.nome, d.descrizione, d.filename, d.mime, d.ordine, d.caricato_il,
               {$colSel}
               COALESCE(NULLIF(u.nome,''), u.username) AS autore
        FROM documenti d
        LEFT JOIN utenti u ON u.id = d.caricato_da
        WHERE d.visibile = 1
        ORDER BY d.ordine ASC, d.caricato_il DESC
    ")->fetchAll();
}

// Group docs by cartella_id
$byFolder = [0 => []]; // 0 = root/senza cartella
foreach ($folders as $f) $byFolder[(int)$f['id']] = [];
foreach ($docs as $doc) {
    $cid = $colOk ? (int)($doc['cartella_id'] ?? 0) : 0;
    if (!isset($byFolder[$cid])) $byFolder[$cid] = [];
    $byFolder[$cid][] = $doc;
}

function doc_icon(string $mime): string {
    if ($mime === 'application/pdf')    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/><polyline points="9 9 10 9"/></svg>';
    if (str_starts_with($mime, 'image/')) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}
function doc_icon_class(string $mime): string {
    if ($mime === 'application/pdf')    return 'doc-icon-pdf';
    if (str_starts_with($mime, 'image/')) return 'doc-icon-img';
    return 'doc-icon-file';
}

$totalDocs = count($docs);
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
    <?php if ($tableOk && $totalDocs): ?>
    <span class="topbar-sub"><?= $totalDocs ?> <?= $totalDocs === 1 ? 'documento' : 'documenti' ?></span>
    <?php endif; ?>
  </div>
  <?php if (is_responsabile() && $tableOk): ?>
  <div class="topbar-actions">
    <?php if ($foldersOk): ?>
    <button type="button" class="topbar-action-btn ghost" onclick="document.getElementById('dlg-crea-folder').showModal()">+ Cartella</button>
    <?php endif; ?>
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
    La tabella <code>documenti</code> non esiste. Eseguire la migrazione SQL.
  </div>

<?php elseif ($totalDocs === 0 && empty($folders)): ?>
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

<?php
// ---- Render helper ----
$renderDocItem = function(array $doc) use ($h, $folders, $foldersOk, $colOk): void {
    $ext      = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
    $icoClass = doc_icon_class($doc['mime']);
    $viewUrl  = base_url('sala/doc_view.php') . '?id=' . (int)$doc['id'];
    $resp     = is_responsabile();
    $canDrag  = $resp && $foldersOk && $colOk;
?>
  <div class="doc-item<?= $canDrag ? ' doc-draggable' : '' ?>"
       data-doc-id="<?= (int)$doc['id'] ?>"
       <?= $canDrag ? 'draggable="true"' : '' ?>>
    <div class="doc-drag-handle" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="7" r="1"/><circle cx="15" cy="7" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="17" r="1"/><circle cx="15" cy="17" r="1"/></svg>
    </div>
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
      <a href="<?= $h($viewUrl) ?>" target="_blank" rel="noopener" class="doc-btn doc-btn-apri">Apri</a>
      <button type="button" class="doc-btn doc-btn-stampa" data-url="<?= $h($viewUrl) ?>">Stampa</button>
      <div class="doc-menu-wrap">
        <button type="button" class="doc-menu-btn" aria-label="Altre azioni" aria-haspopup="true" aria-expanded="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
        </button>
        <div class="doc-menu" hidden role="menu">
          <a href="<?= $h($viewUrl) ?>&dl=1" class="doc-menu-item" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Scarica
          </a>
          <?php if ($resp && $foldersOk && $colOk && !empty($folders)): ?>
          <button type="button" class="doc-menu-item" role="menuitem"
                  onclick="openSposta(<?= (int)$doc['id'] ?>, <?= (int)($doc['cartella_id'] ?? 0) ?>)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/></svg>
            Sposta
          </button>
          <?php endif; ?>
          <?php if ($resp): ?>
          <form method="post" onsubmit="return confirm('Eliminare questo documento?')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="del">
            <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
            <button type="submit" class="doc-menu-item doc-menu-del" role="menuitem">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              Elimina
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php };

// ---- Render folder sections ----
$renderFolderSection = function(int $fid, string $fname, array $docsInFolder, bool $isRoot) use ($h, $renderDocItem, $foldersOk): void {
    $count  = count($docsInFolder);
    $bodyId = 'fb-' . $fid;
    $respOk = is_responsabile();
?>
<div class="doc-folder-section<?= $isRoot ? ' doc-folder-root' : '' ?>" id="fs-<?= $fid ?>">
  <div class="doc-folder-header<?= ($foldersOk && !$isRoot) ? ' doc-dropzone' : '' ?>"
       data-folder-id="<?= $fid ?>">
    <button type="button" class="doc-folder-toggle" aria-expanded="true" aria-controls="<?= $bodyId ?>">
      <?php if ($isRoot): ?>
      <svg class="doc-folder-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <?php else: ?>
      <svg class="doc-folder-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
      <?php endif; ?>
      <span class="doc-folder-name"><?= $h($fname) ?></span>
      <span class="doc-folder-count"><?= $count ?></span>
      <svg class="doc-folder-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <?php if ($respOk && !$isRoot): ?>
    <div class="doc-folder-actions">
      <button type="button" class="doc-folder-act-btn"
              title="Rinomina" aria-label="Rinomina cartella"
              onclick="openRinomina(<?= $fid ?>, '<?= addslashes($fname) ?>')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </button>
      <form method="post" onsubmit="return confirm('Eliminare la cartella? I documenti verranno spostati in Senza cartella.')">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="elimina_cartella">
        <input type="hidden" name="id" value="<?= $fid ?>">
        <button type="submit" class="doc-folder-act-btn doc-folder-del-btn" title="Elimina cartella" aria-label="Elimina cartella">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <div class="doc-folder-body doc-list doc-dropzone" id="<?= $bodyId ?>" data-folder-id="<?= $fid ?>">
    <?php if ($count === 0): ?>
    <div class="doc-folder-empty">
      <?= $isRoot ? 'Trascina qui i documenti senza cartella' : 'Cartella vuota — trascina qui un documento' ?>
    </div>
    <?php else: ?>
    <?php foreach ($docsInFolder as $doc): $renderDocItem($doc); endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php }; ?>

<div class="doc-folders" id="doc-folders">

<?php foreach ($folders as $f):
    $fid   = (int)$f['id'];
    $items = $byFolder[$fid] ?? [];
    $renderFolderSection($fid, $f['nome'], $items, false);
endforeach; ?>

<?php
// Root section only when there are docs without folder, or no folders at all
$rootDocs = $byFolder[0] ?? [];
if (!$foldersOk || !$colOk || !empty($rootDocs) || empty($folders)):
    $renderFolderSection(0, 'Senza cartella', $rootDocs, true);
endif;
?>

</div>

<?php endif; // end not-empty ?>

</div>

<?php if (is_responsabile() && $tableOk): ?>

<!-- Upload dialog -->
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
      <?php if ($foldersOk && !empty($folders)): ?>
      <div class="field tnf-full">
        <label for="doc-cartella">Cartella</label>
        <select id="doc-cartella" name="cartella_id">
          <option value="0">— Senza cartella —</option>
          <?php foreach ($folders as $f): ?>
          <option value="<?= (int)$f['id'] ?>"><?= $h($f['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
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

<?php if ($foldersOk): ?>
<!-- Crea cartella -->
<dialog id="dlg-crea-folder" class="form-dialog">
  <div class="dlg-head">
    <strong>Nuova cartella</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="crea_cartella">
    <div style="padding:16px 20px 0">
      <div class="field">
        <label for="cf-nome">Nome cartella *</label>
        <input id="cf-nome" type="text" name="nome" required maxlength="120" placeholder="es. Contratti">
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Crea</button>
    </div>
  </form>
</dialog>

<!-- Rinomina cartella -->
<dialog id="dlg-rinomina-folder" class="form-dialog">
  <div class="dlg-head">
    <strong>Rinomina cartella</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="rinomina_cartella">
    <input type="hidden" name="id" id="rn-folder-id">
    <div style="padding:16px 20px 0">
      <div class="field">
        <label for="rn-folder-nome">Nuovo nome *</label>
        <input id="rn-folder-nome" type="text" name="nome" required maxlength="120">
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Salva</button>
    </div>
  </form>
</dialog>

<!-- Sposta documento -->
<dialog id="dlg-sposta" class="form-dialog">
  <div class="dlg-head">
    <strong>Sposta in cartella</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="sposta_doc">
    <input type="hidden" name="id" id="sposta-doc-id">
    <div style="padding:16px 20px 0">
      <div class="field">
        <label for="sposta-cart">Cartella</label>
        <select id="sposta-cart" name="cartella_id">
          <option value="0">— Senza cartella —</option>
          <?php foreach ($folders as $f): ?>
          <option value="<?= (int)$f['id'] ?>"><?= $h($f['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Sposta</button>
    </div>
  </form>
</dialog>
<?php endif; // foldersOk ?>

<?php endif; // is_responsabile ?>

<script>var GP_CSRF='<?= addslashes(csrf_token()) ?>';</script>
<script>
(function () {
  // ---- Print ----
  document.querySelectorAll('.doc-btn-stampa').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.dataset.url;
      var w = window.open(url, '_blank', 'noopener');
      if (w) w.addEventListener('load', function () { try { w.print(); } catch (e) {} });
    });
  });

  // ---- 3-dot menu ----
  function closeAllMenus() {
    document.querySelectorAll('.doc-menu').forEach(function (m) {
      m.hidden = true;
      var btn = m.previousElementSibling;
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.doc-menu-btn');
    if (btn) {
      e.stopPropagation();
      var menu = btn.nextElementSibling;
      var wasHidden = menu.hidden;
      closeAllMenus();
      if (wasHidden) {
        menu.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
      }
      return;
    }
    if (!e.target.closest('.doc-menu')) closeAllMenus();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllMenus();
  });

  // ---- Folder collapse ----
  document.querySelectorAll('.doc-folder-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var expanded = btn.getAttribute('aria-expanded') !== 'false';
      btn.setAttribute('aria-expanded', String(!expanded));
      var body = document.getElementById(btn.getAttribute('aria-controls'));
      if (body) body.classList.toggle('doc-folder-collapsed', expanded);
    });
  });

  // ---- Drag & Drop ----
  var dragDocId = null;

  document.querySelectorAll('.doc-draggable').forEach(function (el) {
    el.addEventListener('dragstart', function (e) {
      dragDocId = el.dataset.docId;
      el.classList.add('doc-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', dragDocId);
    });
    el.addEventListener('dragend', function () {
      el.classList.remove('doc-dragging');
      document.querySelectorAll('.doc-dropzone').forEach(function (z) {
        z.classList.remove('doc-drag-over');
      });
      dragDocId = null;
    });
  });

  document.querySelectorAll('.doc-dropzone').forEach(function (zone) {
    zone.addEventListener('dragover', function (e) {
      if (!dragDocId) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      zone.classList.add('doc-drag-over');
    });
    zone.addEventListener('dragleave', function (e) {
      if (!zone.contains(e.relatedTarget)) zone.classList.remove('doc-drag-over');
    });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('doc-drag-over');
      if (!dragDocId) return;
      var fid = zone.dataset.folderId || '0';
      var form = document.createElement('form');
      form.method = 'post';
      form.innerHTML =
        '<input name="csrf" value="' + GP_CSRF + '">' +
        '<input name="azione" value="sposta_doc">' +
        '<input name="id" value="' + dragDocId + '">' +
        '<input name="cartella_id" value="' + fid + '">';
      document.body.appendChild(form);
      form.submit();
    });
  });

  // ---- Dialog helpers ----
  function openRinomina(id, nome) {
    document.getElementById('rn-folder-id').value = id;
    document.getElementById('rn-folder-nome').value = nome;
    document.getElementById('dlg-rinomina-folder').showModal();
  }
  function openSposta(docId, currentFolder) {
    closeAllMenus();
    document.getElementById('sposta-doc-id').value = docId;
    var sel = document.getElementById('sposta-cart');
    if (sel) sel.value = String(currentFolder || 0);
    document.getElementById('dlg-sposta').showModal();
  }
  window.openRinomina = openRinomina;
  window.openSposta   = openSposta;
})();
</script>
</body></html>
