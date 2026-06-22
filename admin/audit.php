<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

$rows = $pdo->query(
    'SELECT a.*, u.username FROM audit_log a LEFT JOIN utenti u ON u.id=a.utente_id
     ORDER BY a.id DESC LIMIT 300'
)->fetchAll();
?>
<!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Audit log</title><link rel="stylesheet" href="<?= base_url('assets/css/core.css') ?>"></head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>
<header class="topbar"><div><strong>Audit log</strong> · ultime 300 operazioni</div></header>
<div class="riepilogo" style="max-width:900px">
  <table class="grid">
    <tr><th>Data/ora</th><th>Utente</th><th>Azione</th><th>Entità</th><th>Dettaglio</th><th>IP</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= $h($r['creato_il']) ?></td>
      <td><?= $h($r['username'] ?? '—') ?></td>
      <td><?= $h($r['azione']) ?></td>
      <td><?= $h($r['entita']) ?><?= $r['entita_id']?(' #'.(int)$r['entita_id']):'' ?></td>
      <td><?= $h($r['dettaglio']) ?></td>
      <td><?= $h($r['ip']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
</body></html>
