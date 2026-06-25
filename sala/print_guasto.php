<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
require_login();
$pdo     = db();
$sett    = get_settings($pdo);
$cfg     = config();
$h       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$macchina = mb_substr(trim($_GET['macchina'] ?? ''), 0, 100);
$nomeSala = $sett['nome_sala'] ?? ($cfg['nome_sala'] ?? 'Sala');
$logoPath = $sett['logo_path'] ?? null;
$logoUrl  = $logoPath ? base_url('account/uploads/sala/' . rawurlencode($logoPath)) : null;
$sWords   = array_filter(array_slice(preg_split('/\s+/', trim($nomeSala)), 0, 2));
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1, 'UTF-8'), $sWords)), 'UTF-8') ?: 'CS';
$autoprint = !isset($_GET['noprint']);
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Avviso guasto<?= $macchina ? ' — ' . $h($macchina) : '' ?></title>
<style>
@page { margin: 2.5cm }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0 }
body {
  font-family: 'Helvetica Neue', Arial, sans-serif;
  background: #fff;
  color: #111;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 48px 32px;
  gap: 0
}
.pg-logo {
  max-width: 160px;
  max-height: 90px;
  object-fit: contain;
  margin-bottom: 20px
}
.pg-initials {
  width: 72px;
  height: 72px;
  border-radius: 16px;
  background: #3b5bdb;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  font-weight: 900;
  letter-spacing: -1px;
  margin-bottom: 20px
}
.pg-sala {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: #888;
  margin-bottom: 40px;
  text-align: center
}
.pg-strip {
  width: 100%;
  max-width: 500px;
  background: #111;
  color: #fff;
  text-align: center;
  padding: 18px 24px;
  border-radius: 12px;
  margin-bottom: 32px
}
.pg-strip-title {
  font-size: 22px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: .5px
}
.pg-machine {
  margin-top: 8px;
  font-size: 15px;
  font-weight: 600;
  opacity: .8
}
.pg-sorry {
  font-size: 17px;
  line-height: 1.65;
  text-align: center;
  color: #444;
  max-width: 440px;
  margin-bottom: 32px
}
.pg-date {
  font-size: 11px;
  color: #bbb;
  text-align: center;
  margin-bottom: 48px
}
.pg-btn {
  padding: 13px 36px;
  background: #3b5bdb;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  letter-spacing: .2px
}
@media print {
  .pg-btn { display: none }
  body { padding: 0; min-height: auto; justify-content: flex-start; padding-top: 1cm }
}
</style>
</head><body>

<?php if ($logoUrl): ?>
<img src="<?= $h($logoUrl) ?>" class="pg-logo" alt="<?= $h($nomeSala) ?>">
<?php else: ?>
<div class="pg-initials"><?= $h($initials) ?></div>
<?php endif; ?>

<div class="pg-sala"><?= $h($nomeSala) ?></div>

<div class="pg-strip">
  <div class="pg-strip-title">Macchina fuori servizio</div>
</div>

<p class="pg-sorry">
  Ci scusiamo per il disagio.<br>
  Stiamo lavorando per ripristinare il servizio<br>nel più breve tempo possibile.
</p>

<div class="pg-date">Data: <?= date('d/m/Y') ?></div>

<button class="pg-btn" onclick="window.print()">Stampa avviso</button>

<?php if ($autoprint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 700); });</script>
<?php endif; ?>
</body></html>
