<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv   = fn($v) => number_format((float)$v, 2, '.', '');

$migrationOk = false;
try {
    $pdo->query('SELECT 1 FROM impostazioni LIMIT 0');
    $pdo->query('SELECT 1 FROM prezzi_turni LIMIT 0');
    $migrationOk = true;
} catch (PDOException) {}

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

    if ($az === 'assistenza') {
        $keys = ['assistenza_numero','assistenza_lock','assistenza_password'];
        $st   = $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)');
        foreach ($keys as $k) {
            $v = mb_substr(trim($_POST[$k] ?? ''), 0, 200);
            $st->execute([$k, $v]);
        }
        audit('impostazioni_assistenza', null, null, null);
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'sala') {
        $nuovoNome = mb_substr(trim($_POST['nome_sala'] ?? ''), 0, 100);
        if ($nuovoNome !== '') {
            $cfgFile = dirname(__DIR__, 2) . '/install/config.php';
            $cfgData = config();
            $cfgData['nome_sala'] = $nuovoNome;
            file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfgData, true) . ";\n");
            audit('impostazioni_sala', null, null, "nome_sala=$nuovoNome");
        }
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'retention') {
        $rd = max(7, min(3650, (int)($_POST['retention_giorni'] ?? 90)));
        $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)')
            ->execute(['retention_giorni', (string)$rd]);
        audit('impostazioni_retention', null, null, "retention_giorni=$rd");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    header('Location: impostazioni.php'); exit;
}

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
<title>Impostazioni · <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/impostazioni.css') ?>">
</head><body>
<?php require __DIR__ . '/../../includes/nav.php'; top_menu($user); ?>

<header class="topbar sh-top">
  <strong>Impostazioni</strong>
</header>

<?php if (isset($_GET['ok'])): ?>
<div class="ok" role="alert">Impostazioni salvate.</div>
<?php endif; ?>

<?php if (!$migrationOk): ?>
<div class="imp-page">
  <div class="warn">
    <strong>Setup incompleto.</strong> Eseguire <code>sql/004_turni_programmati.sql</code> e <code>sql/005_profilo_impostazioni.sql</code>.
  </div>
</div>
<?php else: ?>

<div class="imp-page">

  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 18h16M4 18V8l6-5 6 5v10M8 18v-5h4v5"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Nome sala</h2>
        <p class="imp-card-desc">Appare nell'intestazione, nel favicon, nella PWA e ovunque venga richiesto il nome della sala.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="sala">
      <div class="imp-price-row" style="grid-template-columns:1fr">
        <div class="imp-field">
          <label for="imp-sala">Nome</label>
          <input id="imp-sala" type="text" name="nome_sala" maxlength="100"
                 value="<?= $h($cfg['nome_sala'] ?? '') ?>" placeholder="Es. Sala Giochi Roma" required>
        </div>
      </div>
      <div class="imp-form-footer">
        <button type="submit">Salva nome</button>
      </div>
    </form>
  </section>

  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M10 6v4l2.5 2.5"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Orari turni</h2>
        <p class="imp-card-desc">Finestre orarie che determinano il turno corrente nella dashboard e nella pagina turni.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="orari">
      <div class="imp-orari-stack">
        <div class="imp-orari-row">
          <span class="imp-orari-lbl">Mattino</span>
          <div class="imp-time-pair">
            <div class="imp-field">
              <label for="ti-mi">Inizio</label>
              <input id="ti-mi" type="time" name="turno_mattino_inizio" value="<?= $h($sett['turno_mattino_inizio'] ?? '13:00') ?>" required>
            </div>
            <span class="imp-sep" aria-hidden="true">—</span>
            <div class="imp-field">
              <label for="ti-mf">Fine</label>
              <input id="ti-mf" type="time" name="turno_mattino_fine" value="<?= $h($sett['turno_mattino_fine'] ?? '19:00') ?>" required>
            </div>
          </div>
        </div>
        <div class="imp-orari-row">
          <span class="imp-orari-lbl">Sera</span>
          <div class="imp-time-pair">
            <div class="imp-field">
              <label for="ti-si">Inizio</label>
              <input id="ti-si" type="time" name="turno_sera_inizio" value="<?= $h($sett['turno_sera_inizio'] ?? '19:00') ?>" required>
            </div>
            <span class="imp-sep" aria-hidden="true">—</span>
            <div class="imp-field">
              <label for="ti-sf">Fine</label>
              <input id="ti-sf" type="time" name="turno_sera_fine" value="<?= $h($sett['turno_sera_fine'] ?? '01:00') ?>" required>
            </div>
          </div>
        </div>
      </div>
      <div class="imp-form-footer">
        <button type="submit">Salva orari</button>
      </div>
    </form>
  </section>

  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M13 7.5a3.5 3.5 0 1 0 0 5"/><path d="M6.5 9.5h5M6.5 11h5"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Costo turni</h2>
        <p class="imp-card-desc">Importo corrisposto per ogni turno effettuato. Visibile nel riepilogo guadagni degli operatori.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="prezzi">
      <div class="imp-price-row">
        <div class="imp-field">
          <label for="imp-pm">Mattino <span class="imp-unit">€</span></label>
          <input id="imp-pm" type="number" step="0.01" min="0" name="prezzo_mattino" value="<?= $h($nv($pm)) ?>">
        </div>
        <div class="imp-field">
          <label for="imp-ps">Sera <span class="imp-unit">€</span></label>
          <input id="imp-ps" type="number" step="0.01" min="0" name="prezzo_sera" value="<?= $h($nv($ps)) ?>">
        </div>
      </div>
      <div class="imp-form-footer">
        <button type="submit">Salva prezzi</button>
      </div>
    </form>
  </section>

  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="9" width="12" height="9" rx="1.5"/><path d="M7 9V7.5a3 3 0 0 1 6 0V9"/><circle cx="10" cy="14" r="1"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Permessi operatori</h2>
        <p class="imp-card-desc">Se abilitato, gli operatori possono modificare i turni programmati nel calendario.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="permessi">
      <label class="imp-opt">
        <input type="checkbox" name="operatori_modifica_turni" <?= ($sett['operatori_modifica_turni'] ?? '1') === '1' ? 'checked' : '' ?>>
        <span class="imp-opt-text">Gli operatori possono aggiungere e modificare i turni programmati</span>
      </label>
      <div class="imp-form-footer">
        <button type="submit">Salva permessi</button>
      </div>
    </form>
  </section>

  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Moduli aggiuntivi</h2>
        <p class="imp-card-desc">Attiva o disattiva le sezioni opzionali. Quando disabilitati i link non compaiono nella barra laterale.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="moduli">
      <div class="imp-opt-stack">
        <label class="imp-opt">
          <input type="checkbox" name="modulo_assistenze" <?= ($sett['modulo_assistenze'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span class="imp-opt-text">
            <strong>Ticket assistenza</strong>
            <span>Gestione manutenzione e segnalazioni su macchine</span>
          </span>
        </label>
        <label class="imp-opt">
          <input type="checkbox" name="modulo_prestiti" <?= ($sett['modulo_prestiti'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span class="imp-opt-text">
            <strong>Prestiti e rientri</strong>
            <span>Tracciamento movimenti di cassa extra per persone</span>
          </span>
        </label>
      </div>
      <div class="imp-form-footer">
        <button type="submit">Salva moduli</button>
      </div>
    </form>
  </section>


  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8 9a16 16 0 0 0 5 5l.72-.85a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 20.5 15l.42 1.92z"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Assistenza tecnica</h2>
        <p class="imp-card-desc">Numero di telefono, codice lock e password da mostrare agli operatori quando aprono un ticket di assistenza.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="assistenza">
      <div class="imp-price-row" style="grid-template-columns:1fr 1fr 1fr">
        <div class="imp-field">
          <label for="imp-atel">Numero assistenza</label>
          <input id="imp-atel" type="text" name="assistenza_numero" maxlength="200"
                 value="<?= $h($sett['assistenza_numero'] ?? '') ?>" placeholder="es. 800-123-456">
        </div>
        <div class="imp-field">
          <label for="imp-alock">N° Lock</label>
          <input id="imp-alock" type="text" name="assistenza_lock" maxlength="200"
                 value="<?= $h($sett['assistenza_lock'] ?? '') ?>" placeholder="es. 123456">
        </div>
        <div class="imp-field">
          <label for="imp-apwd">Password</label>
          <input id="imp-apwd" type="text" name="assistenza_password" maxlength="200"
                 value="<?= $h($sett['assistenza_password'] ?? '') ?>" placeholder="es. abc123">
        </div>
      </div>
      <div class="imp-form-footer">
        <button type="submit">Salva dati assistenza</button>
      </div>
    </form>
  </section>

  <section class="imp-card">
    <div class="imp-card-head">
      <div class="imp-card-ico" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/><line x1="12" y1="9" x2="12" y2="12"/><line x1="12" y1="15" x2="12.01" y2="15"/></svg>
      </div>
      <div>
        <h2 class="imp-card-title">Retention log audit</h2>
        <p class="imp-card-desc">I log più vecchi del limite impostato possono essere eliminati dalla pagina Audit. Minimo 7 giorni.</p>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="azione" value="retention">
      <div class="imp-price-row">
        <div class="imp-field">
          <label for="imp-ret">Mantieni log per <span class="imp-unit">giorni</span></label>
          <input id="imp-ret" type="number" min="7" max="3650" name="retention_giorni"
                 value="<?= $h($sett['retention_giorni'] ?? '90') ?>">
        </div>
      </div>
      <div class="imp-form-footer">
        <button type="submit">Salva politica</button>
      </div>
    </form>
  </section>

</div>
<?php endif; ?>
</body></html>
