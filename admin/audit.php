<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

$sett    = get_settings($pdo);
$retDays = max(1, (int)($sett['retention_giorni'] ?? 90));
$retDate = date('Y-m-d', strtotime("-{$retDays} days"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['azione'] ?? '') === 'retention') {
        $del = $pdo->prepare('DELETE FROM audit_log WHERE DATE(creato_il) < ?');
        $del->execute([$retDate]);
        $n = $del->rowCount();
        audit('retention_log', 'audit_log', null, "eliminati $n log prima di $retDate");
        header('Location: audit.php?ok=retention'); exit;
    }
    header('Location: audit.php'); exit;
}

$stOld = $pdo->prepare('SELECT COUNT(*) FROM audit_log WHERE DATE(creato_il) < ?');
$stOld->execute([$retDate]);
$nOld = (int)$stOld->fetchColumn();

$stTotal = $pdo->query('SELECT COUNT(*) FROM audit_log');
$nTotal  = (int)$stTotal->fetchColumn();

$per    = 100;
$page   = max(1, (int)($_GET['p'] ?? 1));
$pages  = max(1, (int)ceil($nTotal / $per));
$page   = min($page, $pages);
$offset = ($page - 1) * $per;

if (($_GET['export'] ?? '') === 'csv') {
    $rows = $pdo->query(
        'SELECT a.*, u.username FROM audit_log a
         LEFT JOIN utenti u ON u.id=a.utente_id
         ORDER BY a.id DESC'
    )->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Data/ora','Utente','Azione','Entità','ID','Dettaglio','IP'], ';');
    foreach ($rows as $r) {
        fputcsv($f, [
            $r['creato_il'], $r['username'] ?? '', $r['azione'],
            $r['entita'] ?? '', $r['entita_id'] ?? '',
            $r['dettaglio'] ?? '', $r['ip'] ?? ''
        ], ';');
    }
    fclose($f); exit;
}

$stRows = $pdo->prepare(
    'SELECT a.*, u.username FROM audit_log a
     LEFT JOIN utenti u ON u.id=a.utente_id
     ORDER BY a.id DESC LIMIT ? OFFSET ?'
);
$stRows->bindValue(1, $per, PDO::PARAM_INT);
$stRows->bindValue(2, $offset, PDO::PARAM_INT);
$stRows->execute();
$rows = $stRows->fetchAll();

function audit_cls(string $az): string {
    if (str_starts_with($az, 'login'))    return 'aud-login';
    if (str_contains($az, 'chiusura') || str_contains($az, 'riapertura')) return 'aud-close';
    if (str_starts_with($az, 'macchina')) return 'aud-macchina';
    if (str_starts_with($az, 'utente'))   return 'aud-utente';
    if (str_starts_with($az, 'impostaz')) return 'aud-imp';
    if ($az === 'retention_log')          return 'aud-ret';
    return 'aud-default';
}

$base = base_url();
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Audit log</title>
<link rel="stylesheet" href="<?= $base ?>assets/css/core.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/audit.css">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar sh-top">
  <strong>Audit log</strong>
  <span style="font-weight:400;color:var(--muted)"><?= number_format($nTotal) ?> operazioni totali</span>
  <a href="audit.php?export=csv" class="topbar-action-btn btnlink">
    <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17h14M10 3v10M6 9l4 4 4-4"/></svg>
    Esporta CSV
  </a>
</header>

<?php if (isset($_GET['ok'])): ?>
<div class="ok" role="alert"><?= $_GET['ok'] === 'retention' ? "Retention applicata: $nOld record eliminati." : 'Operazione completata.' ?></div>
<?php endif; ?>

<div class="audit-page">

  <div class="audit-ret">
    <div class="audit-ret-info">
      <span class="audit-ret-label">Politica retention: <?= $retDays ?> giorni · log prima del <?= $retDate ?></span>
      <?php if ($nOld > 0): ?>
        <span class="audit-ret-count"><?= number_format($nOld) ?> record da eliminare · <a href="<?= $base ?>admin/impostazioni.php">Modifica politica</a></span>
      <?php else: ?>
        <span class="audit-ret-ok">Nessun record da eliminare · <a href="<?= $base ?>admin/impostazioni.php">Modifica politica</a></span>
      <?php endif; ?>
    </div>
    <?php if ($nOld > 0): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="retention">
      <button type="submit" class="audit-ret-btn"
        onclick="return confirm('Eliminare <?= number_format($nOld) ?> record di log prima del <?= $retDate ?>?\nOperazione irreversibile.')">
        Applica retention
      </button>
    </form>
    <?php endif; ?>
  </div>

  <div class="audit-wrap">
    <table class="audit-table">
      <thead>
        <tr>
          <th>Data/ora</th>
          <th>Utente</th>
          <th>Azione</th>
          <th>Entità</th>
          <th>Dettaglio</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="audit-ts"><?= $h(substr($r['creato_il'], 0, 16)) ?></td>
          <td><?= $h($r['username'] ?? '—') ?></td>
          <td><span class="audit-action <?= audit_cls($r['azione']) ?>"><?= $h($r['azione']) ?></span></td>
          <td><?= $h($r['entita'] ?? '—') ?><?= $r['entita_id'] ? ' <span style="color:var(--faint)">#' . (int)$r['entita_id'] . '</span>' : '' ?></td>
          <td class="audit-detail"><?= $h($r['dettaglio'] ?? '') ?></td>
          <td class="audit-ip"><?= $h($r['ip'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--faint);padding:32px">Nessuna operazione registrata.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
    <div class="audit-pager">
      <span>Pagina <?= $page ?> di <?= $pages ?></span>
      <div class="audit-pager-links">
        <?php if ($page > 1): ?><a href="?p=<?= $page-1 ?>" class="audit-pager-link">←</a><?php endif; ?>
        <?php
        $from = max(1, $page - 2);
        $to   = min($pages, $page + 2);
        for ($i = $from; $i <= $to; $i++):
        ?><a href="?p=<?= $i ?>" class="audit-pager-link<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if ($page < $pages): ?><a href="?p=<?= $page+1 ?>" class="audit-pager-link">→</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>
</body></html>
