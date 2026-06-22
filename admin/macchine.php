<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['azione'] ?? '';
    if ($a === 'add') {
        $codice = trim($_POST['codice'] ?? '');
        $tipo   = ($_POST['tipo'] ?? 'VLT') === 'AWP' ? 'AWP' : 'VLT';
        $forn   = in_array($_POST['fornitore'] ?? '', ['NOVO','INSPIRED','SPIELO','ALTRO'], true) ? $_POST['fornitore'] : 'ALTRO';
        $ord    = (int)($_POST['ordine'] ?? 0);
        if ($codice !== '') {
            try {
                $pdo->prepare('INSERT INTO macchine (codice,tipo,fornitore,ordine) VALUES (?,?,?,?)')
                    ->execute([$codice,$tipo,$forn,$ord]);
                audit('macchina_add','macchine',null,$codice);
                $msg = 'Macchina aggiunta.';
            } catch (Throwable $e) { $msg = 'Errore (codice gia esistente?).'; }
        }
    } elseif ($a === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE macchine SET attiva = 1-attiva WHERE id=?')->execute([$id]);
        audit('macchina_toggle','macchine',$id);
    } elseif ($a === 'edit') {
        $id = (int)$_POST['id'];
        $forn = in_array($_POST['fornitore'] ?? '', ['NOVO','INSPIRED','SPIELO','ALTRO'], true) ? $_POST['fornitore'] : 'ALTRO';
        $pdo->prepare('UPDATE macchine SET codice=?, fornitore=?, ordine=? WHERE id=?')
            ->execute([trim($_POST['codice']), $forn, (int)$_POST['ordine'], $id]);
        audit('macchina_edit','macchine',$id);
        $msg = 'Salvato.';
    }
}
$macchine = $pdo->query('SELECT * FROM macchine ORDER BY tipo, ordine, codice')->fetchAll();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Macchine</title><link rel="stylesheet" href="<?= base_url('assets/css/core.css') ?>"></head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>
<header class="topbar"><div><strong>Parco macchine</strong></div></header>
<?php if ($msg): ?><div class="ok"><?= $h($msg) ?></div><?php endif; ?>

<div class="riepilogo" style="max-width:780px">
  <h3>Aggiungi macchina</h3>
  <form method="post" class="inline">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="azione" value="add">
    <input name="codice" placeholder="Codice (es. NOVO 99)" required>
    <select name="tipo"><option>VLT</option><option>AWP</option></select>
    <select name="fornitore"><option>NOVO</option><option>INSPIRED</option><option>SPIELO</option><option>ALTRO</option></select>
    <input type="number" name="ordine" placeholder="Ord." style="width:70px">
    <button>Aggiungi</button>
  </form>

  <h3 style="margin-top:18px">Elenco</h3>
  <div class="rowhead"><span>Codice</span><span>Tipo</span><span>Fornitore</span><span>Ord.</span><span>Stato</span><span></span></div>
  <?php foreach ($macchine as $m): ?>
  <div class="editrow">
    <form method="post" class="rowform">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="edit"><input type="hidden" name="id" value="<?= $m['id'] ?>">
      <input name="codice" value="<?= $h($m['codice']) ?>">
      <span class="tipo"><?= $h($m['tipo']) ?></span>
      <select name="fornitore">
        <?php foreach (['NOVO','INSPIRED','SPIELO','ALTRO'] as $f): ?>
        <option <?= $f===$m['fornitore']?'selected':'' ?>><?= $f ?></option><?php endforeach; ?>
      </select>
      <input type="number" name="ordine" value="<?= (int)$m['ordine'] ?>" class="ord">
      <span class="stato"><?= $m['attiva'] ? 'attiva' : '<span style="color:#999">disattiva</span>' ?></span>
      <button title="Salva modifiche">Salva</button>
    </form>
    <form method="post" class="rowtoggle">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="toggle"><input type="hidden" name="id" value="<?= $m['id'] ?>">
      <button><?= $m['attiva']?'Disattiva':'Attiva' ?></button>
    </form>
  </div>
  <?php endforeach; ?>
  <p class="hint">Le macchine disattivate non compaiono nel giornaliero ma restano nello storico.</p>
</div>
</body></html>
