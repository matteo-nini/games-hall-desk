<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib.php';
$user = require_responsabile();
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv   = fn($v) => number_format((float)$v, 2, ',', '.');

/* Verifica migration */
$migrationOk = false;
try {
    $pdo->query('SELECT 1 FROM impostazioni LIMIT 0');
    $pdo->query('SELECT 1 FROM prezzi_turni LIMIT 0');
    $migrationOk = true;
} catch (PDOException $e) {}

/* =========================================================
   POST
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $migrationOk) {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    if ($az === 'orari') {
        $keys = ['turno_mattino_inizio','turno_mattino_fine','turno_sera_inizio','turno_sera_fine'];
        $st   = $pdo->prepare('UPDATE impostazioni SET valore=? WHERE chiave=?');
        foreach ($keys as $k) {
            $v = trim($_POST[$k] ?? '');
            if (preg_match('/^\d{1,2}:\d{2}$/', $v)) $st->execute([$v, $k]);
        }
        audit('impostazioni_orari', null, null, json_encode(array_intersect_key($_POST, array_flip($keys))));
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'prezzi') {
        $pm = is_numeric($_POST['prezzo_mattino'] ?? '') ? abs((float)$_POST['prezzo_mattino']) : null;
        $ps = is_numeric($_POST['prezzo_sera']   ?? '') ? abs((float)$_POST['prezzo_sera'])   : null;
        if ($pm !== null) $pdo->prepare('UPDATE prezzi_turni SET prezzo=? WHERE nome="mattino"')->execute([$pm]);
        if ($ps !== null) $pdo->prepare('UPDATE prezzi_turni SET prezzo=? WHERE nome="sera"')->execute([$ps]);
        audit('impostazioni_prezzi', null, null, "mattino=$pm sera=$ps");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'permessi') {
        $v = isset($_POST['operatori_modifica_turni']) ? '1' : '0';
        $pdo->prepare('UPDATE impostazioni SET valore=? WHERE chiave="operatori_modifica_turni"')->execute([$v]);
        audit('impostazioni_permessi', null, null, "operatori_modifica_turni=$v");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'moduli') {
        $ma = isset($_POST['modulo_assistenze']) ? '1' : '0';
        $mp = isset($_POST['modulo_prestiti'])   ? '1' : '0';
        $st = $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)');
        $st->execute(['modulo_assistenze', $ma]);
        $st->execute(['modulo_prestiti',   $mp]);
        audit('impostazioni_moduli', null, null, "assistenze=$ma prestiti=$mp");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    header('Location: impostazioni.php'); exit;
}

/* =========================================================
   GET
   ========================================================= */
$sett   = $migrationOk ? get_settings($pdo) : [];
$prezzi = [];
if ($migrationOk) {
    foreach ($pdo->query('SELECT nome, prezzo FROM prezzi_turni') as $r)
        $prezzi[$r['nome']] = (float)$r['prezzo'];
}
$pm = $prezzi['mattino'] ?? 60.0;
$ps = $prezzi['sera']    ?? 70.0;
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Impostazioni</title>
<link rel="stylesheet" href="styles.css">
</head><body>
<?php require __DIR__ . '/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div><strong>Impostazioni</strong></div>
</header>

<?php if (isset($_GET['ok'])): ?><div class="ok">Impostazioni salvate.</div><?php endif; ?>

<?php if (!$migrationOk): ?>
<div class="warn" style="margin:16px 24px;padding:14px 18px;border-radius:var(--r);font-size:13px">
  <strong>Setup incompleto.</strong> Eseguire <code>sql/004_turni_programmati.sql</code> e <code>sql/005_profilo_impostazioni.sql</code>.
</div>
<?php else: ?>

<div class="imp-page">

  <!-- ===== Orari turni ===== -->
  <section class="imp-card">
    <h2 class="imp-card-title">Orari turni</h2>
    <p class="imp-card-desc">Definisce le finestre orarie che determinano il turno corrente nella dashboard e nella pagina turni.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="orari">
      <div class="imp-field-group">
        <div class="imp-field-label">Mattino</div>
        <div class="imp-field-row">
          <div class="field">
            <label>Inizio</label>
            <input type="time" name="turno_mattino_inizio" value="<?= $h($sett['turno_mattino_inizio'] ?? '13:00') ?>" required>
          </div>
          <span class="imp-sep">–</span>
          <div class="field">
            <label>Fine</label>
            <input type="time" name="turno_mattino_fine" value="<?= $h($sett['turno_mattino_fine'] ?? '19:00') ?>" required>
          </div>
        </div>
      </div>
      <div class="imp-field-group">
        <div class="imp-field-label">Sera</div>
        <div class="imp-field-row">
          <div class="field">
            <label>Inizio</label>
            <input type="time" name="turno_sera_inizio" value="<?= $h($sett['turno_sera_inizio'] ?? '19:00') ?>" required>
          </div>
          <span class="imp-sep">–</span>
          <div class="field">
            <label>Fine</label>
            <input type="time" name="turno_sera_fine" value="<?= $h($sett['turno_sera_fine'] ?? '01:00') ?>" required>
          </div>
        </div>
      </div>
      <button type="submit">Salva orari</button>
    </form>
  </section>

  <!-- ===== Costo turni ===== -->
  <section class="imp-card">
    <h2 class="imp-card-title">Costo turni</h2>
    <p class="imp-card-desc">Importo corrisposto per ogni turno effettuato. Visibile nel riepilogo guadagni degli operatori.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="prezzi">
      <div class="imp-field-row">
        <div class="field">
          <label>Mattino (€)</label>
          <input type="number" step="0.01" min="0" name="prezzo_mattino" value="<?= $h($nv($pm)) ?>" style="width:110px">
        </div>
        <div class="field">
          <label>Sera (€)</label>
          <input type="number" step="0.01" min="0" name="prezzo_sera" value="<?= $h($nv($ps)) ?>" style="width:110px">
        </div>
      </div>
      <button type="submit">Salva prezzi</button>
    </form>
  </section>

  <!-- ===== Permessi operatori ===== -->
  <section class="imp-card">
    <h2 class="imp-card-title">Permessi operatori</h2>
    <p class="imp-card-desc">Se abilitato, gli operatori possono aggiungere se stessi agli slot liberi del calendario turni.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="permessi">
      <label class="imp-toggle-label">
        <input type="checkbox" name="operatori_modifica_turni" <?= ($sett['operatori_modifica_turni'] ?? '1') === '1' ? 'checked' : '' ?>>
        Gli operatori possono modificare i turni programmati
      </label>
      <div style="margin-top:12px">
        <button type="submit">Salva permessi</button>
      </div>
    </form>
  </section>

  <!-- ===== Moduli ===== -->
  <section class="imp-card">
    <h2 class="imp-card-title">Moduli aggiuntivi</h2>
    <p class="imp-card-desc">Attiva o disattiva le sezioni opzionali dell'applicazione. Quando disabilitati, i link non vengono mostrati nella barra laterale.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="moduli">
      <div style="display:flex;flex-direction:column;gap:10px">
        <label class="imp-toggle-label">
          <input type="checkbox" name="modulo_assistenze" <?= ($sett['modulo_assistenze'] ?? '1') === '1' ? 'checked' : '' ?>>
          Ticket assistenza — gestione manutenzione macchine
        </label>
        <label class="imp-toggle-label">
          <input type="checkbox" name="modulo_prestiti" <?= ($sett['modulo_prestiti'] ?? '1') === '1' ? 'checked' : '' ?>>
          Prestiti e rientri — tracciamento movimenti di cassa extra
        </label>
      </div>
      <div style="margin-top:14px">
        <button type="submit">Salva moduli</button>
      </div>
    </form>
  </section>

</div>

<?php endif; ?>
</body></html>
