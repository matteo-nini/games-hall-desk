<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

$oggi      = date('Y-m-d');
$r         = riepilogo_giornata($pdo, $oggi);
$nomiMesi  = nomi_mesi();

/* Stato giornata oggi */
$st = $pdo->prepare('SELECT stato FROM giornate WHERE data=?'); $st->execute([$oggi]);
$statoOggi = $st->fetchColumn() ?: null;

/* Mese corrente: giorni operativi + incasso */
$meseInizio = date('Y-m-01');
$meseFine   = date('Y-m-t');
$st = $pdo->prepare('
    SELECT COUNT(DISTINCT g.id) AS giorni, COALESCE(SUM(s.importo), 0) AS incasso
    FROM giornate g
    LEFT JOIN turni t ON t.giornata_id = g.id
    LEFT JOIN scassettamenti s ON s.turno_id = t.id
    WHERE g.data BETWEEN ? AND ?
');
$st->execute([$meseInizio, $meseFine]);
$mese = $st->fetch();

/* Ultime 10 giornate con incasso VLT */
$ultime = $pdo->query('
    SELECT g.data, g.stato, COALESCE(SUM(s.importo), 0) AS incasso
    FROM giornate g
    LEFT JOIN turni t ON t.giornata_id = g.id
    LEFT JOIN scassettamenti s ON s.turno_id = t.id
    GROUP BY g.id, g.data, g.stato
    ORDER BY g.data DESC
    LIMIT 10
')->fetchAll();
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div>
    <strong>Ciao, <?= $h($user['nome'] ?: $user['username']) ?></strong>
    <span class="topbar-sub"><?= (int)date('j') ?> <?= $h($nomiMesi[(int)date('n')]) ?> <?= date('Y') ?></span>
  </div>
</header>

<div class="dash-page">

  <!-- ===== Hero: giornata oggi ===== -->
  <div class="dash-hero">
    <div class="dash-hero-info">
      <span class="dash-hero-label">Giornata di oggi</span>
      <?php if ($statoOggi === 'chiusa'): ?>
        <span class="dash-hero-turno">Chiusa</span>
        <span class="dash-hero-stato muted-text">Incasso VLT: <?= eur($r['incasso_vlt']) ?></span>
      <?php elseif ($statoOggi === 'aperta'): ?>
        <span class="dash-hero-turno">In corso</span>
        <span class="dash-hero-stato ok-text">Incasso VLT: <?= eur($r['incasso_vlt']) ?></span>
      <?php else: ?>
        <span class="dash-hero-turno">Non iniziata</span>
        <span class="dash-hero-stato muted-text">Nessun dato ancora per oggi</span>
      <?php endif; ?>
    </div>
    <div class="dash-hero-actions">
      <a href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $oggi ?>" class="btn-dash-cassa">Apri cassa oggi &rarr;</a>
    </div>
  </div>

  <!-- ===== KPI mese corrente ===== -->
  <div class="calcrow dash-kpi">
    <div class="mini">
      <div class="l">Incasso VLT oggi</div>
      <div class="v"><?= eur($r['incasso_vlt']) ?></div>
    </div>
    <div class="mini">
      <div class="l">Versamento oggi</div>
      <div class="v"><?= eur($r['versamento']) ?></div>
    </div>
    <div class="mini">
      <div class="l">Incasso VLT mese</div>
      <div class="v"><?= eur((float)$mese['incasso']) ?></div>
    </div>
    <div class="mini">
      <div class="l">Giorni operativi mese</div>
      <div class="v"><?= (int)$mese['giorni'] ?></div>
    </div>
  </div>

  <div class="dash-grid">

    <!-- ===== Ultime giornate ===== -->
    <section class="dash-card">
      <h2 class="dash-card-title">Ultime giornate</h2>
      <?php if ($ultime): ?>
      <div class="recent-list">
        <?php foreach ($ultime as $g): ?>
        <a class="recent-row" href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($g['data']) ?>">
          <span class="recent-date"><?= $h(date('d/m/Y', strtotime($g['data']))) ?></span>
          <span class="badge <?= $g['stato'] === 'chiusa' ? 'closed' : 'open' ?>"><?= $g['stato'] === 'chiusa' ? 'Chiusa' : 'Aperta' ?></span>
          <span class="tp-earn tp-earn-preview"><?= eur((float)$g['incasso']) ?></span>
          <span class="recent-caret">›</span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="ticket-empty">Nessuna giornata registrata.</p>
      <?php endif; ?>
      <a href="<?= base_url('cassa/settimanale.php') ?>" class="dash-card-link">Riepilogo settimanale &rarr;</a>
    </section>

    <!-- ===== Accesso rapido ===== -->
    <section class="dash-card dash-quicklinks">
      <h2 class="dash-card-title">Accesso rapido</h2>
      <div class="dash-ql-grid">
        <a href="<?= base_url('cassa/settimanale.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
          <span>Settimanale</span>
        </a>
        <a href="<?= base_url('cassa/mensile.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 4-6"/></svg>
          <span>Mensile</span>
        </a>
        <a href="<?= base_url('cassa/annuale.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 16l2-2 3 2 3-3"/></svg>
          <span>Annuale</span>
        </a>
        <a href="<?= base_url('admin/utenti.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>Utenti</span>
        </a>
        <a href="<?= base_url('admin/macchine.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M7 8h2M7 11h5"/></svg>
          <span>Macchine</span>
        </a>
        <a href="<?= base_url('admin/impostazioni.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>
          <span>Impostazioni</span>
        </a>
      </div>
    </section>

  </div>
</div>
</body></html>
