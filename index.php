<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lib.php';
$user = require_login();
$cfg  = config();
$pdo  = db();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

$oggi = date('Y-m-d');
$r = riepilogo_giornata($pdo, $oggi);
$st = $pdo->prepare('SELECT stato FROM giornate WHERE data=?'); $st->execute([$oggi]);
$stato = $st->fetch()['stato'] ?? 'aperta';
$ultime = $pdo->query('SELECT g.data, g.stato FROM giornate g ORDER BY g.data DESC LIMIT 7')->fetchAll();
$oggi_fmt = date('d/m/Y');
?>
<!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($cfg['nome_sala']) ?></title><link rel="stylesheet" href="assets/css/core.css"></head><body>
<?php require __DIR__ . '/includes/nav.php'; top_menu($user); ?>

<div class="dash-hero">
  <div>
    <div class="dash-name"><?= $h($cfg['nome_sala']) ?></div>
    <div class="dash-dato"><?= $oggi_fmt ?> &nbsp;&middot;&nbsp; <span class="badge <?= $stato==='chiusa'?'closed':'open' ?>"><?= $stato==='chiusa'?'Chiusa':'Aperta' ?></span></div>
  </div>
  <a class="btnlink" href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $oggi ?>">Cassa di oggi &rarr;</a>
</div>

<div class="calcrow dash-kpi">
  <div class="mini">
    <div class="l">Incasso VLT</div>
    <div class="v"><?= eur($r['incasso_vlt']) ?></div>
  </div>
  <div class="mini">
    <div class="l">Versamento</div>
    <div class="v"><?= eur($r['versamento']) ?></div>
  </div>
  <div class="mini">
    <div class="l">Ticket pagati</div>
    <div class="v"><?= eur($r['ticket']) ?></div>
  </div>
  <div class="mini">
    <div class="l">Bancomat</div>
    <div class="v"><?= eur($r['bancomat']) ?></div>
  </div>
</div>

<div class="dash-section">
  <h3 class="dash-section-title">Ultime giornate</h3>
  <div class="recent-list">
    <?php foreach ($ultime as $g): ?>
    <a class="recent-row" href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($g['data']) ?>">
      <span class="recent-date"><?= $h($g['data']) ?></span>
      <span class="badge <?= $g['stato']==='chiusa'?'closed':'open' ?>"><?= $g['stato']==='chiusa'?'Chiusa':'Aperta' ?></span>
      <span class="recent-caret">&#8250;</span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
</body></html>
