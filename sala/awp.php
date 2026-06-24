<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
require_not_revisore();
$cfg  = config();
$pdo  = db();
$REFILL_ROWS = (int)($cfg['refill_rows'] ?? 10);

$data = $_GET['data'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) $data = date('Y-m-d');
$g = ensure_giornata($pdo, $data);
$chiusa = ($g['stato'] === 'chiusa');
$readonly = $chiusa && !is_responsabile();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if ($readonly) { http_response_code(403); exit('Giornata chiusa.'); }
    $num = fn($v) => is_numeric($v) ? (float)$v : 0.0;
    $pdo->beginTransaction();
    foreach ([1, 2] as $n) {
        $t = ensure_turno($pdo, (int)$g['id'], $n);
        $tid = (int)$t['id'];
        $pdo->prepare('DELETE FROM refill_awp WHERE turno_id=?')->execute([$tid]);
        $ins = $pdo->prepare('INSERT INTO refill_awp (turno_id, n_macchina, euro, ora) VALUES (?,?,?,?)');
        foreach (($_POST['refill'][$n] ?? []) as $rf) {
            $euro = $num($rf['euro'] ?? 0);
            $nm   = trim((string)($rf['n_macchina'] ?? ''));
            $ora  = trim((string)($rf['ora'] ?? ''));
            if ($euro != 0.0 || $nm !== '') $ins->execute([$tid, $nm !== '' ? $nm : null, $euro, $ora !== '' ? $ora : null]);
        }
    }
    $pdo->commit();
    audit('salvataggio_awp', 'refill_awp', (int)$g['id'], $data);
    header("Location: awp.php?data=$data&ok=1"); exit;
}

// carica refill esistenti
$ref = [];
foreach ([1, 2] as $n) {
    $t = ensure_turno($pdo, (int)$g['id'], $n);
    $st = $pdo->prepare('SELECT * FROM refill_awp WHERE turno_id=? ORDER BY id'); $st->execute([(int)$t['id']]);
    $ref[$n] = $st->fetchAll();
}
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv = fn($v) => ($v == 0 ? '' : rtrim(rtrim(number_format((float)$v,2,'.',''),'0'),'.'));
$prev = date('Y-m-d', strtotime("$data -1 day"));
$next = date('Y-m-d', strtotime("$data +1 day"));
?>
<!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Refill AWP — <?= $h($data) ?></title><link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>"></head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>
<header class="topbar">
  <div><strong>Refill AWP</strong> · operazioni rare</div>
  <div class="nav">
    <a href="?data=<?= $prev ?>">◀</a>
    <input type="date" value="<?= $h($data) ?>" onchange="location='?data='+this.value">
    <a href="?data=<?= $next ?>">▶</a>
  </div>
  <div><span class="badge <?= $chiusa?'closed':'open' ?>"><?= $chiusa?'CHIUSA':'APERTA' ?></span></div>
</header>
<?php if (isset($_GET['ok'])): ?><div class="ok">Salvato.</div><?php endif; ?>
<?php if ($readonly): ?><div class="warn">Giornata chiusa: sola lettura.</div><?php endif; ?>
<div class="warn no-print">I refill AWP rientrano nel cassetto del giornaliero (riducono la cassa).</div>

<form method="post">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<div class="turni">
<?php foreach ([1=>'TURNO 1 (mattino)', 2=>'TURNO 2 (sera)'] as $n=>$titolo): ?>
  <section class="turno t<?= $n ?>" style="flex-basis:380px">
    <h2><?= $titolo ?></h2>
    <table class="grid">
      <tr><th>N. macchina AWP</th><th>Euro</th><th>Ora</th></tr>
      <?php for ($i=0; $i<$REFILL_ROWS; $i++): $r=$ref[$n][$i] ?? null; ?>
      <tr>
        <td><input type="text" name="refill[<?= $n ?>][<?= $i ?>][n_macchina]" value="<?= $h($r['n_macchina'] ?? '') ?>" <?= $readonly?'disabled':'' ?>></td>
        <td><input type="number" step="0.01" name="refill[<?= $n ?>][<?= $i ?>][euro]" value="<?= $h($nv($r['euro'] ?? 0)) ?>" <?= $readonly?'disabled':'' ?>></td>
        <td><input type="time" name="refill[<?= $n ?>][<?= $i ?>][ora]" value="<?= $h($r['ora'] ?? '') ?>" <?= $readonly?'disabled':'' ?>></td>
      </tr>
      <?php endfor; ?>
    </table>
  </section>
<?php endforeach; ?>
</div>
<?php if (!$readonly): ?><div class="actions"><button type="submit">Salva refill AWP</button></div><?php endif; ?>
</form>
<p class="hint" style="padding:0 16px 24px">Dopo il salvataggio, riapri il <a href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($data) ?>">giornaliero</a> per vedere il totale refill già conteggiato nel cassetto.</p>
</body></html>
