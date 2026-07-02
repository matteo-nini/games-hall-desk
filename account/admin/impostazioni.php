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
    /* Logo upload usa multipart — check_csrf() legge lo stesso campo hidden */
    check_csrf();
    $az = $_POST['azione'] ?? '';

    if ($az === 'logo') {
        $file = $_FILES['logo_file'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK && $file['size'] <= 2 * 1024 * 1024) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true)) {
                $dir = dirname(__DIR__, 2) . '/account/uploads/sala/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                    $old = $pdo->query("SELECT valore FROM impostazioni WHERE chiave='logo_path'")->fetchColumn();
                    if ($old && file_exists($dir . $old)) @unlink($dir . $old);
                    $pdo->prepare("INSERT INTO impostazioni (chiave,valore) VALUES ('logo_path',?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
                        ->execute([$fname]);
                    audit('impostazioni_logo', null, null, $fname);
                }
            }
        }
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'logo_del') {
        $old = $pdo->query("SELECT valore FROM impostazioni WHERE chiave='logo_path'")->fetchColumn();
        $dir = dirname(__DIR__, 2) . '/account/uploads/sala/';
        if ($old && file_exists($dir . $old)) @unlink($dir . $old);
        $pdo->exec("DELETE FROM impostazioni WHERE chiave='logo_path'");
        audit('impostazioni_logo_rimosso', null, null, null);
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'turni') {
        $n  = max(1, min(3, (int)($_POST['num_turni'] ?? 2)));
        $st = $pdo->prepare('INSERT INTO impostazioni (chiave,valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)');
        $st->execute(['num_turni', (string)$n]);
        for ($i = 1; $i <= 3; $i++) {
            $nome  = mb_substr(trim($_POST["turno_{$i}_nome"]   ?? ''), 0, 30) ?: ['Mattino','Sera','Notte'][$i-1];
            $inizio = trim($_POST["turno_{$i}_inizio"] ?? '');
            $fine   = trim($_POST["turno_{$i}_fine"]   ?? '');
            $st->execute(["turno_{$i}_nome",   $nome]);
            if (preg_match('/^\d{1,2}:\d{2}$/', $inizio)) $st->execute(["turno_{$i}_inizio", $inizio]);
            if (preg_match('/^\d{1,2}:\d{2}$/', $fine))   $st->execute(["turno_{$i}_fine",   $fine]);
        }
        /* Mantieni le chiavi legacy in sync per backward compat */
        $sett_new = get_settings($pdo);
        $st->execute(['turno_mattino_inizio', $sett_new['turno_1_inizio'] ?? '13:00']);
        $st->execute(['turno_mattino_fine',   $sett_new['turno_1_fine']   ?? '19:00']);
        $st->execute(['turno_sera_inizio',    $sett_new['turno_2_inizio'] ?? '19:00']);
        $st->execute(['turno_sera_fine',      $sett_new['turno_2_fine']   ?? '01:00']);
        /* Save prices if submitted with combined form */
        $ptNames = ['mattino', 'sera', 'notte'];
        $stP = $pdo->prepare('INSERT INTO prezzi_turni (nome, prezzo) VALUES (?,?) ON DUPLICATE KEY UPDATE prezzo=VALUES(prezzo)');
        for ($i = 1; $i <= $n; $i++) {
            $k = "prezzo_turno_$i";
            if (isset($_POST[$k]) && is_numeric($_POST[$k]))
                $stP->execute([$ptNames[$i-1], abs((float)$_POST[$k])]);
        }
        audit('impostazioni_turni', null, null, "num_turni=$n");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'timezone') {
        $allowed = DateTimeZone::listIdentifiers();
        $tz = trim($_POST['timezone'] ?? 'Europe/Rome');
        if (in_array($tz, $allowed, true)) {
            $cfgFile = dirname(__DIR__, 2) . '/install/config.php';
            $cfgData = config();
            $cfgData['timezone'] = $tz;
            file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfgData, true) . ";\n");
            audit('impostazioni_timezone', null, null, $tz);
        }
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
        $st = $pdo->prepare('INSERT INTO impostazioni (chiave,valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)');
        $v1 = isset($_POST['operatori_modifica_turni']) ? '1' : '0';
        $v2 = isset($_POST['turno_edit_libero'])        ? '1' : '0';
        $v3 = isset($_POST['mobile_giornaliero'])       ? '1' : '0';
        $v4 = isset($_POST['mobile_turni_edit'])        ? '1' : '0';
        $v5 = isset($_POST['revisori_vedi_turni'])      ? '1' : '0';
        $st->execute(['operatori_modifica_turni', $v1]);
        $st->execute(['turno_edit_libero',        $v2]);
        $st->execute(['mobile_giornaliero',       $v3]);
        $st->execute(['mobile_turni_edit',        $v4]);
        $st->execute(['revisori_vedi_turni',      $v5]);
        audit('impostazioni_permessi', null, null, "omt=$v1 tel=$v2 mg=$v3 mt=$v4 rvt=$v5");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'moduli') {
        $ma = isset($_POST['modulo_assistenze']) ? '1' : '0';
        $mp = isset($_POST['modulo_prestiti'])   ? '1' : '0';
        $md = isset($_POST['modulo_documenti'])  ? '1' : '0';
        $mc = isset($_POST['modulo_contatti'])   ? '1' : '0';
        $st = $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)');
        $st->execute(['modulo_assistenze', $ma]);
        $st->execute(['modulo_prestiti',   $mp]);
        $st->execute(['modulo_documenti',  $md]);
        $st->execute(['modulo_contatti',   $mc]);
        audit('impostazioni_moduli', null, null, "assistenze=$ma prestiti=$mp documenti=$md contatti=$mc");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'brand') {
        $hex = strtolower(trim($_POST['brand_accent'] ?? ''));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            $pdo->prepare('INSERT INTO impostazioni (chiave,valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)')
                ->execute(['brand_accent', $hex]);
            audit('impostazioni_brand', null, null, "accent=$hex");
        } else {
            $pdo->exec("DELETE FROM impostazioni WHERE chiave='brand_accent'");
            audit('impostazioni_brand_reset', null, null, null);
        }
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

    if ($az === 'sala_contatti') {
        $tel   = mb_substr(trim($_POST['tel_sala']   ?? ''), 0, 30);
        $email = mb_substr(trim($_POST['email_sala'] ?? ''), 0, 255);
        $sito  = mb_substr(trim($_POST['sito_web']   ?? ''), 0, 255);
        $save  = $pdo->prepare('INSERT INTO impostazioni (chiave,valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)');
        $save->execute(['tel_sala',   $tel]);
        $save->execute(['email_sala', $email]);
        $save->execute(['sito_web',   $sito]);
        audit('impostazioni_sala_contatti', null, null, null);
        try { $pdo->exec('ALTER TABLE contatti ADD COLUMN sistema TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable) {}
        try { $pdo->exec('ALTER TABLE contatti ADD COLUMN email VARCHAR(255) DEFAULT NULL'); } catch (Throwable) {}
        sync_contact_sala($pdo, $cfg['nome_sala'] ?? 'Sala', $tel, $email, $sito);
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'retention') {
        $rd = max(7, min(3650, (int)($_POST['retention_giorni'] ?? 90)));
        $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)')
            ->execute(['retention_giorni', (string)$rd]);
        audit('impostazioni_retention', null, null, "retention_giorni=$rd");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'email') {
        $mf = mb_substr(trim($_POST['mail_from'] ?? ''), 0, 200);
        if ($mf !== '' && !filter_var($mf, FILTER_VALIDATE_EMAIL)) {
            /* Ignora silenziosamente email malformata, non blocchiamo il redirect */
        } else {
            $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)')
                ->execute(['mail_from', $mf]);
            audit('impostazioni_email', null, null, "mail_from=$mf");
        }
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
$pm    = $prezzi['mattino'] ?? 60.0;
$ps    = $prezzi['sera']    ?? 70.0;
$pn    = $prezzi['notte']   ?? 60.0;
$turns = $migrationOk ? get_turns($sett) : [];
$numT  = (int)($sett['num_turni'] ?? 2);
$dn    = ['Mattino','Sera','Notte'];
$di    = ['13:00','19:00','01:00'];
$df    = ['19:00','01:00','09:00'];
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Impostazioni · <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/impostazioni.css') ?>">
</head><body>
<?php require __DIR__ . '/../../includes/nav.php'; top_menu($user); ?>
<?php
$swatchPalette = [
  '#2563eb'=>'Blu vivo',   '#3b5bdb'=>'Blu (default)','#1971c2'=>'Blu notte',  '#0284c7'=>'Azzurro',
  '#0891b2'=>'Ciano scuro','#0c8599'=>'Teal',
  '#16a34a'=>'Verde',      '#2f9e44'=>'Salvia',        '#099268'=>'Smeraldo',   '#5c940d'=>'Lime',
  '#ca8a04'=>'Ambra',      '#d97706'=>'Miele',
  '#ea580c'=>'Arancio',    '#d9480f'=>'Mattone',       '#dc2626'=>'Rosso',      '#e03131'=>'Fuoco',
  '#e64980'=>'Rosa',       '#c026d3'=>'Fucsia',
  '#7048e8'=>'Viola',      '#6741d9'=>'Indaco',        '#9c36b5'=>'Magenta',    '#7950f2'=>'Lavanda',
  '#334155'=>'Ardesia',    '#1e293b'=>'Inchiostro',
];
$curAccent = strtolower($sett['brand_accent'] ?? '#3b5bdb');
?>

<div class="imp-layout">

  <nav class="imp-sidenav" aria-label="Sezioni impostazioni">
    <a class="imp-snav-item active" href="#identita">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 18h16M4 18V8l6-5 6 5v10M8 18v-5h4v5"/></svg>
      Identità
    </a>
    <a class="imp-snav-item" href="#turni">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M10 6v4l2.5 2.5"/></svg>
      Turni
    </a>
    <div class="imp-snav-divider"></div>
    <a class="imp-snav-item" href="#permessi">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2L3 5.5v5c0 4.5 3 7.5 7 8 4-.5 7-3.5 7-8v-5L10 2z"/></svg>
      Permessi
    </a>
    <a class="imp-snav-item" href="#moduli">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/></svg>
      Moduli
    </a>
    <div class="imp-snav-divider"></div>
    <a class="imp-snav-item" href="#assistenza">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8 9a16 16 0 0 0 5 5l.72-.85a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 20.5 15l.42 1.92z"/></svg>
      Assistenza
    </a>
    <a class="imp-snav-item" href="#sistema">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="2.5"/><path d="M10 2v2M10 16v2M4.22 4.22l1.41 1.41M14.36 14.36l1.42 1.42M2 10h2M16 10h2M4.22 15.78l1.41-1.41M14.36 5.64l1.42-1.42"/></svg>
      Sistema
    </a>
  </nav>

  <div class="imp-body">
    <header class="topbar sh-top" style="padding-left:0; position:sticky; top:30px">
      <strong>Impostazioni</strong>
    </header>

    <?php if (isset($_GET['ok'])): ?>
    <div class="ok" role="alert">Impostazioni salvate.</div>
    <?php endif; ?>

    <?php if (!$migrationOk): ?>
    <div style="padding:24px">
      <div class="warn"><strong>Setup incompleto.</strong> Eseguire il setup iniziale dalla pagina di installazione.</div>
    </div>
    <?php else: ?>

    <!-- IDENTITÀ -->
    <div class="imp-section" id="identita">
      <div class="imp-section-head">
        <h2>Identità</h2>
        <p>Nome, logo e colori dell'interfaccia — come la sala appare in ogni pagina e nella PWA</p>
      </div>

      <div class="imp-settings-card">

        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Nome sala</h3>
            <p class="imp-srow-desc">Appare nell'header, nella PWA e nei documenti generati.</p>
          </div>
          <div class="imp-srow-ctrl">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="sala">
              <div class="imp-field">
                <label for="imp-sala">Nome</label>
                <input id="imp-sala" type="text" name="nome_sala" maxlength="100"
                       value="<?= $h($cfg['nome_sala'] ?? '') ?>" placeholder="Es. Sala Giochi Roma" required>
              </div>
              <div class="imp-form-footer">
                <button type="submit">Salva nome</button>
              </div>
            </form>
          </div>
        </div>

        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Logo</h3>
            <p class="imp-srow-desc">Sostituisce le iniziali nella sidebar. JPG, PNG, WebP, SVG — max 2 MB.</p>
          </div>
          <div class="imp-srow-ctrl">
            <?php $logoPath = $sett['logo_path'] ?? null; ?>
            <?php if ($logoPath): ?>
            <div class="imp-logo-preview">
              <img src="<?= asset_url('account/uploads/sala/' . $h($logoPath)) ?>" alt="Logo sala">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="azione" value="logo_del">
                <button type="submit" class="ghost btn-sm">Rimuovi</button>
              </form>
            </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="logo">
              <div class="imp-field">
                <label for="imp-logo"><?= $logoPath ? 'Sostituisci logo' : 'Carica logo' ?></label>
                <input id="imp-logo" type="file" name="logo_file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" required>
              </div>
              <div class="imp-form-footer">
                <button type="submit">Carica</button>
              </div>
            </form>
          </div>
        </div>

        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Contatti sala</h3>
            <p class="imp-srow-desc">Recapiti della sala — sincronizzati automaticamente nella rubrica Contatti.</p>
          </div>
          <div class="imp-srow-ctrl">
            <form method="post">
              <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="sala_contatti">
              <div class="imp-field">
                <label for="imp-tel">Telefono sala</label>
                <input id="imp-tel" type="tel" name="tel_sala" maxlength="30"
                       value="<?= $h($sett['tel_sala'] ?? '') ?>" placeholder="Es. 06 1234 5678">
              </div>
              <div class="imp-field" style="margin-top:8px">
                <label for="imp-email-sala">Email sala</label>
                <input id="imp-email-sala" type="email" name="email_sala" maxlength="255"
                       value="<?= $h($sett['email_sala'] ?? '') ?>" placeholder="info@...">
              </div>
              <div class="imp-field" style="margin-top:8px">
                <label for="imp-sito">Sito web</label>
                <input id="imp-sito" type="url" name="sito_web" maxlength="255"
                       value="<?= $h($sett['sito_web'] ?? '') ?>" placeholder="https://...">
              </div>
              <div class="imp-form-footer">
                <button type="submit">Salva</button>
              </div>
            </form>
          </div>
        </div>

      </div>

      <div class="imp-settings-card">
        <div class="imp-scard-head">
          <h3 class="imp-srow-title">Brand colori</h3>
          <p class="imp-srow-desc">Colore accent di bottoni, link attivi e badge. Lascia vuoto per tornare al blu predefinito.</p>
        </div>

        <div class="imp-brand-v2">
          <div class="imp-brand-v2-left">
            <div class="imp-color-row-v2">
              <input type="color" id="imp-accent" value="<?= $h($sett['brand_accent'] ?? '#3b5bdb') ?>">
              <div class="imp-color-info">
                <span class="imp-color-hex-v2" id="imp-accent-hex"><?= $h($sett['brand_accent'] ?? '#3b5bdb') ?></span>
                <span class="imp-color-label">Colore accent corrente</span>
              </div>
            </div>
            <p class="imp-sub-head" style="margin-bottom:12px">Palette predefinita</p>
            <div class="imp-swatches-v2" role="group" aria-label="Colori predefiniti">
              <?php foreach ($swatchPalette as $hex => $name): ?>
              <button type="button" class="imp-swatch-v2<?= strtolower($curAccent) === strtolower($hex) ? ' sel' : '' ?>"
                      data-color="<?= $h($hex) ?>" title="<?= $h($name) ?>"
                      style="background:<?= $h($hex) ?>"></button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="ibp-mockup-wrap">
            <span class="ibp-mockup-lbl">Anteprima live</span>
          <div class="ibp-mockup-v2" aria-hidden="true">
            <div class="ibp-mock-sb">
              <div class="ibp-mock-logo2" id="ibp-logo2"><?= $h(mb_strtoupper(mb_substr($cfg['nome_sala'] ?? 'GP', 0, 2))) ?></div>
              <div class="ibp-mock-ni ibp-on" id="ibp-ni-act">Giornaliero</div>
              <div class="ibp-mock-ni">Storico</div>
              <div class="ibp-mock-ni">Report</div>
              <div class="ibp-mock-ni">Impostazioni</div>
            </div>
            <div class="ibp-mock-main2">
              <div class="ibp-mock-bar">
                <span class="ibp-mock-barlbl">Cassa — Sera</span>
                <span class="ibp-mock-btn2" id="ibp-mock-btn2">Salva</span>
              </div>
              <div class="ibp-mock-content">
                <span class="ibp-mock-chip2" id="ibp-mock-chip2">Aperta</span>
                <div>
                  <div class="ibp-mock-num2" id="ibp-mock-num2">€ 1.234</div>
                  <div class="ibp-mock-numlbl">Cassetto</div>
                </div>
                <a class="ibp-mock-link2" id="ibp-mock-link2" href="#" onclick="return false">Vedi storico →</a>
              </div>
            </div>
          </div>
          </div><!-- /.ibp-mockup-wrap -->
        </div>

        <div class="imp-brand-footer">
          <form method="post" id="frm-brand">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="brand">
            <input type="hidden" name="brand_accent" id="imp-accent-val" value="<?= $h($sett['brand_accent'] ?? '') ?>">
            <button type="submit">Salva colore</button>
          </form>
          <?php if (!empty($sett['brand_accent'])): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="brand">
            <input type="hidden" name="brand_accent" value="">
            <button type="submit" class="ghost">Reset default</button>
          </form>
          <?php endif; ?>
        </div>
        <script>
        (function () {
          var inp   = document.getElementById('imp-accent');
          var hexEl = document.getElementById('imp-accent-hex');
          var val   = document.getElementById('imp-accent-val');
          function hexToRgb(h) {
            var m = /^#([0-9a-f]{6})$/i.exec(h);
            return m ? {r:parseInt(m[1].slice(0,2),16),g:parseInt(m[1].slice(2,4),16),b:parseInt(m[1].slice(4,6),16)} : null;
          }
          function applyAccent(color) {
            var rgb = hexToRgb(color); if (!rgb) return;
            var w = function(c){return Math.round(255*.85+c*.15);};
            var k = function(c){return Math.round(c*.60);};
            var weak = 'rgb('+w(rgb.r)+','+w(rgb.g)+','+w(rgb.b)+')';
            var ink  = 'rgb('+k(rgb.r)+','+k(rgb.g)+','+k(rgb.b)+')';
            document.documentElement.style.setProperty('--accent',      color);
            document.documentElement.style.setProperty('--accent-weak', weak);
            document.documentElement.style.setProperty('--accent-ink',  ink);
            hexEl.textContent  = color;
            hexEl.style.color  = color;
            val.value          = color;
            inp.value          = color;
            var btn  = document.getElementById('ibp-mock-btn2');
            var chip = document.getElementById('ibp-mock-chip2');
            var num  = document.getElementById('ibp-mock-num2');
            var lnk  = document.getElementById('ibp-mock-link2');
            var logo = document.getElementById('ibp-logo2');
            var ni   = document.getElementById('ibp-ni-act');
            if (btn)  btn.style.background  = color;
            if (chip) { chip.style.background = weak; chip.style.color = ink; }
            if (num)  num.style.color  = color;
            if (lnk)  lnk.style.color  = color;
            if (logo) logo.style.color  = color;
            if (ni)   { ni.style.color = color; ni.style.background = weak; ni.style.borderLeftColor = color; }
            document.querySelectorAll('.imp-swatch-v2').forEach(function(sw){
              sw.classList.toggle('sel', sw.dataset.color.toLowerCase() === color.toLowerCase());
            });
          }
          inp.addEventListener('input', function(){applyAccent(this.value);});
          document.querySelectorAll('.imp-swatch-v2').forEach(function(sw){
            sw.addEventListener('click', function(){applyAccent(this.dataset.color);});
          });
          applyAccent(inp.value);
        }());
        </script>
      </div>
    </div>

    <!-- TURNI -->
    <div class="imp-section" id="turni">
      <div class="imp-section-head">
        <h2>Turni</h2>
        <p>Numero, nomi, orari e costo dei turni giornalieri — si riflette sulla cassa, sul calendario e sui report</p>
      </div>
      <div class="imp-settings-card">
        <div class="imp-scard-head">
          <h3 class="imp-srow-title">Configurazione turni</h3>
          <p class="imp-srow-desc">Scegli quanti turni al giorno, personalizza i nomi, gli orari e il compenso per operatore.</p>
        </div>
        <div class="imp-scard-body">
        <form method="post" id="frm-turni">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="turni">
          <div class="imp-turni-layout">
            <div class="imp-turni-col">
              <p class="imp-sub-head">Configurazione</p>
              <div class="imp-field" style="margin-bottom:16px">
                <label for="num-turni">Numero di turni al giorno</label>
                <select id="num-turni" name="num_turni" onchange="aggiornaRighe(this.value)">
                  <option value="1" <?= $numT===1?'selected':'' ?>>1 — turno unico</option>
                  <option value="2" <?= $numT===2?'selected':'' ?>>2 — mattino e sera</option>
                  <option value="3" <?= $numT===3?'selected':'' ?>>3 — mattino, sera e notte</option>
                </select>
              </div>
              <div class="imp-orari-stack" id="turni-stack">
              <?php for ($i = 1; $i <= 3; $i++):
                $t   = $turns[$i] ?? ['nome'=>$dn[$i-1],'inizio'=>$di[$i-1],'fine'=>$df[$i-1]];
                $vis = $i <= $numT ? '' : ' style="display:none"';
              ?>
                <div class="imp-orari-row imp-turno-row" data-idx="<?= $i ?>"<?= $vis ?>>
                  <div class="imp-field" style="min-width:90px">
                    <label for="tn-<?= $i ?>">Nome turno <?= $i ?></label>
                    <input id="tn-<?= $i ?>" type="text" name="turno_<?= $i ?>_nome" value="<?= $h($t['nome']) ?>" maxlength="30" placeholder="<?= $h($dn[$i-1]) ?>">
                  </div>
                  <div class="imp-time-pair">
                    <div class="imp-field">
                      <label for="ti-<?= $i ?>i">Inizio</label>
                      <input id="ti-<?= $i ?>i" type="time" name="turno_<?= $i ?>_inizio" value="<?= $h($t['inizio']) ?>">
                    </div>
                    <span class="imp-sep" aria-hidden="true">—</span>
                    <div class="imp-field">
                      <label for="ti-<?= $i ?>f">Fine</label>
                      <input id="ti-<?= $i ?>f" type="time" name="turno_<?= $i ?>_fine" value="<?= $h($t['fine']) ?>">
                    </div>
                  </div>
                </div>
              <?php endfor; ?>
              </div>
            </div>
            <div class="imp-turni-col imp-turni-prezzi">
              <p class="imp-sub-head">Costo per turno <span class="imp-unit">€</span></p>
              <p class="imp-srow-desc" style="margin-bottom:12px">Importo corrisposto all'operatore per ogni turno effettuato.</p>
              <div class="imp-orari-stack">
              <?php for ($i = 1; $i <= 3; $i++):
                $ptKey  = ['mattino','sera','notte'][$i-1];
                $ptVal  = $prezzi[$ptKey] ?? ($i === 1 ? $pm : ($i === 2 ? $ps : $pn));
                $t      = $turns[$i] ?? ['nome' => $dn[$i-1]];
                $vis    = $i <= $numT ? '' : ' style="display:none"';
              ?>
                <div class="imp-field imp-prezzo-row" data-idx="<?= $i ?>"<?= $vis ?>>
                  <label for="imp-p<?= $i ?>"><?= $h($t['nome']) ?></label>
                  <input id="imp-p<?= $i ?>" type="number" step="0.01" min="0"
                         name="prezzo_turno_<?= $i ?>" value="<?= $h(number_format((float)$ptVal, 2, '.', '')) ?>">
                </div>
              <?php endfor; ?>
              </div>
            </div>
          </div>
          <div class="imp-form-footer">
            <button type="submit">Salva turni e prezzi</button>
          </div>
        </form>
        <script>
        function aggiornaRighe(n) {
          n = parseInt(n);
          document.querySelectorAll('.imp-turno-row, .imp-prezzo-row').forEach(function(r) {
            r.style.display = parseInt(r.dataset.idx) <= n ? '' : 'none';
          });
        }
        </script>
        </div><!-- /.imp-scard-body -->
      </div>
    </div>

    <!-- PERMESSI -->
    <div class="imp-section" id="permessi">
      <div class="imp-section-head">
        <h2>Permessi</h2>
        <p>Controllo delle azioni disponibili a operatori, revisori e da dispositivi mobili</p>
      </div>
      <div class="imp-settings-card">
        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Impostazioni accesso</h3>
            <p class="imp-srow-desc">Abilita o disabilita le azioni per ruolo. Le opzioni mobile valgono per schermi ≤ 760 px.</p>
          </div>
          <div class="imp-srow-ctrl">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="permessi">

              <p class="imp-sub-head">Operatori</p>
              <div class="imp-opt-stack">
                <label class="imp-opt">
                  <input type="checkbox" name="operatori_modifica_turni" <?= ($sett['operatori_modifica_turni'] ?? '1') === '1' ? 'checked' : '' ?>>
                  <span class="imp-opt-text">
                    <strong>Modifica calendario turni</strong>
                    <span>Gli operatori possono aggiungere se stessi ai turni programmati nel calendario</span>
                  </span>
                </label>
                <label class="imp-opt">
                  <input type="checkbox" name="turno_edit_libero" <?= ($sett['turno_edit_libero'] ?? '1') === '1' ? 'checked' : '' ?>>
                  <span class="imp-opt-text">
                    <strong>Modifica libera turni giornalieri</strong>
                    <span>Gli operatori possono modificare i dati di qualsiasi turno, non solo il proprio</span>
                  </span>
                </label>
              </div>

              <p class="imp-sub-head" style="margin-top:20px">Mobile <span class="imp-unit">(≤ 760 px)</span></p>
              <div class="imp-opt-stack">
                <label class="imp-opt">
                  <input type="checkbox" name="mobile_giornaliero" <?= ($sett['mobile_giornaliero'] ?? '0') === '1' ? 'checked' : '' ?>>
                  <span class="imp-opt-text">
                    <strong>Compilazione cassa da mobile</strong>
                    <span>Permette di inserire e salvare i dati del giornaliero da smartphone</span>
                  </span>
                </label>
                <label class="imp-opt">
                  <input type="checkbox" name="mobile_turni_edit" <?= ($sett['mobile_turni_edit'] ?? '0') === '1' ? 'checked' : '' ?>>
                  <span class="imp-opt-text">
                    <strong>Modifica turni da mobile</strong>
                    <span>Permette di assegnare e rimuovere turni dal calendario su smartphone</span>
                  </span>
                </label>
              </div>

              <p class="imp-sub-head" style="margin-top:20px">Revisori</p>
              <div class="imp-opt-stack">
                <label class="imp-opt">
                  <input type="checkbox" name="revisori_vedi_turni" <?= ($sett['revisori_vedi_turni'] ?? '0') === '1' ? 'checked' : '' ?>>
                  <span class="imp-opt-text">
                    <strong>Accesso al calendario turni</strong>
                    <span>I revisori possono visualizzare il calendario turni in sola lettura</span>
                  </span>
                </label>
              </div>

              <div class="imp-form-footer">
                <button type="submit">Salva permessi</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- MODULI -->
    <div class="imp-section" id="moduli">
      <div class="imp-section-head">
        <h2>Moduli aggiuntivi</h2>
        <p>Attiva o disattiva le sezioni opzionali — i link scompaiono dalla barra laterale quando disabilitati</p>
      </div>
      <div class="imp-settings-card">
        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Moduli attivi</h3>
            <p class="imp-srow-desc">I moduli disabilitati scompaiono dalla barra laterale senza cancellare i dati.</p>
          </div>
          <div class="imp-srow-ctrl">
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
                <label class="imp-opt">
                  <input type="checkbox" name="modulo_documenti" <?= ($sett['modulo_documenti'] ?? '1') === '1' ? 'checked' : '' ?>>
                  <span class="imp-opt-text">
                    <strong>Documenti</strong>
                    <span>Caricamento e stampa di documenti operativi (moduli, avvisi, istruzioni)</span>
                  </span>
                </label>
                <label class="imp-opt">
                  <input type="checkbox" name="modulo_contatti" <?= ($sett['modulo_contatti'] ?? '1') === '1' ? 'checked' : '' ?>>
                  <span class="imp-opt-text">
                    <strong>Contatti</strong>
                    <span>Rubrica interna con tecnici, fornitori e referenti della sala</span>
                  </span>
                </label>
              </div>
              <div class="imp-form-footer">
                <button type="submit">Salva moduli</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>


    <!-- ASSISTENZA -->
    <div class="imp-section" id="assistenza">
      <div class="imp-section-head">
        <h2>Assistenza tecnica</h2>
        <p>Recapiti mostrati agli operatori quando aprono un ticket di assistenza per una macchina</p>
      </div>
      <div class="imp-settings-card">
        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Contatti assistenza</h3>
            <p class="imp-srow-desc">Numero, codice lock e password mostrati nel dialog quando si apre un ticket.</p>
          </div>
          <div class="imp-srow-ctrl">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="assistenza">
              <div class="imp-price-row">
                <div class="imp-field">
                  <label for="imp-atel">Numero</label>
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
          </div>
        </div>
      </div>
    </div>

    <!-- SISTEMA -->
    <div class="imp-section" id="sistema">
      <div class="imp-section-head">
        <h2>Sistema</h2>
        <p>Fuso orario e politica di retention dei log di audit</p>
      </div>

      <div class="imp-settings-card">
        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Fuso orario</h3>
            <p class="imp-srow-desc">Usato per determinare il turno corrente e le date di chiusura. Effettivo alla sessione successiva.</p>
          </div>
          <div class="imp-srow-ctrl">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="timezone">
              <div class="imp-field">
                <label for="imp-tz">Timezone</label>
                <select id="imp-tz" name="timezone">
                  <?php
                  $curTz = config()['timezone'] ?? 'Europe/Rome';
                  $popular = ['Europe/Rome','Europe/London','Europe/Paris','Europe/Berlin','Europe/Madrid',
                              'America/New_York','America/Los_Angeles','America/Chicago','America/Sao_Paulo',
                              'Asia/Dubai','Asia/Tokyo','Australia/Sydney','Pacific/Auckland'];
                  $allTz   = DateTimeZone::listIdentifiers();
                  ?>
                  <optgroup label="Comuni">
                  <?php foreach ($popular as $tz): ?>
                    <option value="<?= $h($tz) ?>" <?= $tz === $curTz ? 'selected' : '' ?>><?= $h($tz) ?></option>
                  <?php endforeach; ?>
                  </optgroup>
                  <optgroup label="Tutti">
                  <?php foreach ($allTz as $tz): if (in_array($tz, $popular, true)) continue; ?>
                    <option value="<?= $h($tz) ?>" <?= $tz === $curTz ? 'selected' : '' ?>><?= $h($tz) ?></option>
                  <?php endforeach; ?>
                  </optgroup>
                </select>
              </div>
              <div class="imp-form-footer">
                <button type="submit">Salva fuso orario</button>
              </div>
            </form>
          </div>
        </div>

        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Retention log audit</h3>
            <p class="imp-srow-desc">I log più vecchi del limite possono essere eliminati dalla pagina Audit. Minimo 7 giorni.</p>
          </div>
          <div class="imp-srow-ctrl">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="retention">
              <div class="imp-field" style="max-width:160px">
                <label for="imp-ret">Mantieni per <span class="imp-unit">giorni</span></label>
                <input id="imp-ret" type="number" min="7" max="3650" name="retention_giorni"
                       value="<?= $h($sett['retention_giorni'] ?? '90') ?>">
              </div>
              <div class="imp-form-footer">
                <button type="submit">Salva politica</button>
              </div>
            </form>
          </div>
        </div>

        <div class="imp-srow">
          <div class="imp-srow-meta">
            <h3 class="imp-srow-title">Email di sistema</h3>
            <p class="imp-srow-desc">Indirizzo mittente per le email di reset password. Richiede che il server abbia PHP mail() configurato o un SMTP relay.</p>
          </div>
          <div class="imp-srow-ctrl">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="email">
              <div class="imp-field">
                <label for="imp-mailfrom">Indirizzo mittente</label>
                <input id="imp-mailfrom" type="email" name="mail_from" maxlength="200"
                       value="<?= $h($sett['mail_from'] ?? '') ?>" placeholder="es. noreply@miasala.it">
              </div>
              <div class="imp-form-footer">
                <button type="submit">Salva email</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var items    = document.querySelectorAll('.imp-snav-item[href^="#"]');
  var sections = document.querySelectorAll('.imp-section[id]');
  if (!('IntersectionObserver' in window)) return;
  var obs = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) {
        items.forEach(function (n) { n.classList.remove('active'); });
        var a = document.querySelector('.imp-snav-item[href="#' + e.target.id + '"]');
        if (a) a.classList.add('active');
      }
    });
  }, { rootMargin: '-10% 0px -72% 0px', threshold: 0 });
  sections.forEach(function (s) { obs.observe(s); });
}());
</script>
</body></html>
