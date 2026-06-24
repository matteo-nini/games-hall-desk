<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
require_not_revisore();
$cfg  = config();
$pdo  = db();
$h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv = fn($v) => number_format((float)$v, 2, ',', '.');

if ((get_settings($pdo)['modulo_prestiti'] ?? '1') !== '1') {
    header('Location: ../cassa/giornaliero.php'); exit;
}

/* ---- POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    /* Aggiungi movimento */
    if ($az === 'movimento') {
        $data      = $_POST['data'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) $data = date('Y-m-d');
        $pid       = (int)($_POST['persona_id'] ?? 0);
        $tipo      = in_array($_POST['tipo'] ?? '', ['prestito','rientro']) ? $_POST['tipo'] : null;
        $quantita  = is_numeric($_POST['quantita'] ?? '') ? abs((float)$_POST['quantita']) : 0;
        $nota      = mb_substr(trim($_POST['note'] ?? ''), 0, 255) ?: null;
        if (!$pid || !$tipo || $quantita <= 0) { header('Location: prestiti.php?err=campi'); exit; }
        $pdo->prepare('INSERT INTO prestiti_movimenti (data,persona_id,tipo,quantita,note,creato_da) VALUES (?,?,?,?,?,?)')
            ->execute([$data, $pid, $tipo, $quantita, $nota, $user['id']]);
        $mid = (int)$pdo->lastInsertId();
        audit('prestito_movimento','prestiti_movimenti',$mid,"$tipo €$quantita");
        // Rifletti sul turno giornaliero: prestito → differenze, rientro → rientri (turno sera)
        $g = ensure_giornata($pdo, $data);
        $t = ensure_turno($pdo, (int)$g['id'], 2);
        if ($tipo === 'prestito') {
            $pdo->prepare('UPDATE turni SET differenze = differenze + ? WHERE id=?')->execute([$quantita, (int)$t['id']]);
        } else {
            $pdo->prepare('UPDATE turni SET rientri = rientri + ? WHERE id=?')->execute([$quantita, (int)$t['id']]);
        }
        audit('prestito_sync_giornaliero','turni',(int)$t['id'],"$tipo $quantita su $data");
        header('Location: prestiti.php?ok=1'); exit;
    }

    /* Aggiungi persona */
    if ($az === 'persona') {
        $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 100);
        $si   = is_numeric($_POST['saldo_iniziale'] ?? '') ? (float)$_POST['saldo_iniziale'] : 0.0;
        $nota = mb_substr(trim($_POST['note'] ?? ''), 0, 255) ?: null;
        if ($nome === '') { header('Location: prestiti.php?err=campi'); exit; }
        $pdo->prepare('INSERT INTO prestiti_persone (nome,saldo_iniziale,note) VALUES (?,?,?)')
            ->execute([$nome, $si, $nota]);
        audit('prestito_persona_aggiunta','prestiti_persone',(int)$pdo->lastInsertId(),$nome);
        header('Location: prestiti.php?ok=1'); exit;
    }

    /* Elimina movimento (solo responsabile) */
    if ($az === 'del_movimento' && is_responsabile()) {
        $id = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare('SELECT * FROM prestiti_movimenti WHERE id=?');
        $s->execute([$id]);
        $mov = $s->fetch();
        $pdo->prepare('DELETE FROM prestiti_movimenti WHERE id=?')->execute([$id]);
        audit('prestito_movimento_eliminato','prestiti_movimenti',$id);
        if ($mov) {
            $g = ensure_giornata($pdo, $mov['data']);
            $t = ensure_turno($pdo, (int)$g['id'], 2);
            if ($mov['tipo'] === 'prestito') {
                $pdo->prepare('UPDATE turni SET differenze = differenze - ? WHERE id=?')->execute([(float)$mov['quantita'], (int)$t['id']]);
            } else {
                $pdo->prepare('UPDATE turni SET rientri = rientri - ? WHERE id=?')->execute([(float)$mov['quantita'], (int)$t['id']]);
            }
            audit('prestito_sync_undo_giornaliero','turni',(int)$t['id'],"{$mov['tipo']} -{$mov['quantita']} su {$mov['data']}");
        }
        header('Location: prestiti.php?ok=1'); exit;
    }

    header('Location: prestiti.php'); exit;
}

/* ---- GET ---- */
$filtro_pid = (int)($_GET['p'] ?? 0);

/* Saldi per persona */
$persone = $pdo->query(
    'SELECT p.id, p.nome, p.saldo_iniziale, p.note,
            COALESCE(SUM(CASE WHEN m.tipo="prestito" THEN m.quantita ELSE 0 END),0) AS tot_prestiti,
            COALESCE(SUM(CASE WHEN m.tipo="rientro"  THEN m.quantita ELSE 0 END),0) AS tot_rientri,
            p.saldo_iniziale
            + COALESCE(SUM(CASE WHEN m.tipo="prestito" THEN m.quantita ELSE 0 END),0)
            - COALESCE(SUM(CASE WHEN m.tipo="rientro"  THEN m.quantita ELSE 0 END),0) AS dare
     FROM prestiti_persone p
     LEFT JOIN prestiti_movimenti m ON m.persona_id=p.id
     GROUP BY p.id ORDER BY p.nome'
)->fetchAll();

/* Movimenti */
$st = $filtro_pid
    ? $pdo->prepare('SELECT m.*,p.nome AS pnome FROM prestiti_movimenti m JOIN prestiti_persone p ON p.id=m.persona_id WHERE m.persona_id=? ORDER BY m.data DESC,m.id DESC LIMIT 200')
    : $pdo->prepare('SELECT m.*,p.nome AS pnome FROM prestiti_movimenti m JOIN prestiti_persone p ON p.id=m.persona_id ORDER BY m.data DESC,m.id DESC LIMIT 200');
$filtro_pid ? $st->execute([$filtro_pid]) : $st->execute([]);
$movimenti = $st->fetchAll();

/* Totale complessivo dare */
$tot_dare = array_sum(array_column($persone, 'dare'));
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prestiti e rientri</title><link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/prestiti.css') ?>"></head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div><strong>Prestiti e rientri</strong> <span class="topbar-sub">Totale dare: <strong><?= $nv($tot_dare) ?> €</strong></span></div>
  <div style="display:flex;gap:8px">
    <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-movimento').showModal()">+ Movimento</button>
    <button type="button" class="topbar-action-btn" onclick="document.getElementById('dlg-persona').showModal()">+ Persona</button>
  </div>
</header>

<?php if (isset($_GET['ok'])): ?><div class="ok">Salvato</div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="warn">Compilare tutti i campi obbligatori.</div><?php endif; ?>

<!-- Riepilogo saldi -->
<div class="calcrow prest-saldi" style="margin:14px 24px">
  <?php foreach ($persone as $p): ?>
  <a class="mini prest-card <?= $p['dare']>0?'pcard-dare':($p['dare']<0?'pcard-neg':'pcard-zero') ?>" href="?p=<?= $p['id'] ?>">
    <div class="l"><?= $h($p['nome']) ?></div>
    <div class="v"><?= $nv($p['dare']) ?> €</div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Dialog: aggiungi movimento -->
<dialog id="dlg-movimento" class="form-dialog">
  <div class="dlg-head">
    <strong>Aggiungi movimento</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="movimento">
    <div class="tnf-grid">
      <div class="field"><label>Data</label><input type="date" name="data" value="<?= date('Y-m-d') ?>" required></div>
      <div class="field">
        <label>Persona *</label>
        <select name="persona_id" required>
          <option value="">— scegli —</option>
          <?php foreach ($persone as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $filtro_pid===$p['id']?'selected':'' ?>><?= $h($p['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Tipo *</label>
        <select name="tipo" required>
          <option value="">— scegli —</option>
          <option value="prestito">Prestito</option>
          <option value="rientro">Rientro</option>
        </select>
      </div>
      <div class="field"><label>Importo (€) *</label><input type="number" step="0.01" min="0.01" name="quantita" required></div>
      <div class="field tnf-full"><label>Note</label><input type="text" name="note" placeholder="annotazioni opzionali" style="width:100%"></div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Salva movimento</button>
    </div>
  </form>
</dialog>

<!-- Dialog: aggiungi persona -->
<dialog id="dlg-persona" class="form-dialog">
  <div class="dlg-head">
    <strong>Aggiungi persona</strong>
    <button type="button" class="dlg-close" onclick="this.closest('dialog').close()" aria-label="Chiudi">&times;</button>
  </div>
  <form method="post" class="ticket-new-form">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="persona">
    <div class="tnf-grid">
      <div class="field"><label>Nome *</label><input type="text" name="nome" required placeholder="nome o soprannome"></div>
      <div class="field"><label>Saldo iniziale (€)</label><input type="number" step="0.01" name="saldo_iniziale" value="0" placeholder="0"></div>
      <div class="field tnf-full"><label>Note</label><input type="text" name="note" style="width:100%" placeholder="opzionale"></div>
    </div>
    <div class="dlg-actions">
      <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Annulla</button>
      <button type="submit">Aggiungi persona</button>
    </div>
  </form>
</dialog>
<script>
  <?php if (isset($_GET['nuovo'])): ?>document.getElementById('dlg-movimento').showModal();<?php endif; ?>
</script>

<!-- Lista movimenti -->
<div style="margin: 20px 24px 0">
  <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px">
    <h3 style="margin:0; font-size:13px; text-transform:uppercase; letter-spacing:.4px; color:var(--muted)">
      Movimenti<?php if ($filtro_pid): $fp=array_filter($persone,fn($x)=>$x['id']===$filtro_pid); $fp=reset($fp); echo $fp?' — '.$h($fp['nome']):''; endif; ?>
    </h3>
    <?php if ($filtro_pid): ?><a href="prestiti.php" style="font-size:12px; color:var(--muted)">&times; Mostra tutti</a><?php endif; ?>
  </div>
  <div class="recent-list">
    <?php foreach ($movimenti as $m): ?>
    <div class="recent-row prest-row">
      <span class="recent-date prest-data"><?= $h($m['data']) ?></span>
      <span class="prest-persona"><?= $h($m['pnome']) ?></span>
      <span class="badge prest-tipo <?= $m['tipo']==='prestito'?'ptype-out':'ptype-in' ?>"><?= strtoupper($m['tipo']) ?></span>
      <span class="prest-qta"><?= $nv($m['quantita']) ?> €</span>
      <?php if ($m['note']): ?><span class="prest-note"><?= $h($m['note']) ?></span><?php endif; ?>
      <?php if (is_responsabile()): ?>
      <form method="post" onsubmit="return confirm('Eliminare?')" style="margin-left:auto">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="del_movimento">
        <input type="hidden" name="id" value="<?= $h($m['id']) ?>">
        <button type="submit" class="ghost" style="font-size:11px; padding:3px 8px">&#10005;</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($movimenti)): ?>
    <p class="ticket-empty">Nessun movimento registrato.</p>
    <?php endif; ?>
  </div>
</div>
</body></html>
