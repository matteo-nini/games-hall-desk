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
        $st->execute(['operatori_modifica_turni', $v1]);
        $st->execute(['turno_edit_libero',        $v2]);
        audit('impostazioni_permessi', null, null, "omt=$v1 tel=$v2");
        header('Location: impostazioni.php?ok=1'); exit;
    }

    if ($az === 'moduli') {
        $ma = isset($_POST['modulo_assistenze']) ? '1' : '0';
        $mp = isset($_POST['modulo_prestiti'])   ? '1' : '0';
        $md = isset($_POST['modulo_documenti'])  ? '1' : '0';
        $st = $pdo->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)');
        $st->execute(['modulo_assistenze', $ma]);
        $st->execute(['modulo_prestiti',   $mp]);
        $st->execute(['modulo_documenti',  $md]);
        audit('impostazioni_moduli', null, null, "assistenze=$ma prestiti=$mp documenti=$md");
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

<header class="topbar sh-top">
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
    <a class="imp-snav-item" href="#operatori">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="9" width="12" height="9" rx="1.5"/><path d="M7 9V7.5a3 3 0 0 1 6 0V9"/></svg>
      Operatori
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

    <!-- IDENTITÀ -->
    <div class="imp-section" id="identita">
      <div class="imp-section-head">
        <h2>Identità</h2>
        <p>Nome, logo e colori dell'interfaccia — come la sala appare in ogni pagina e nella PWA</p>
      </div>

      <div class="imp-identita-row">
        <div class="imp-card">
          <div class="imp-card-head">
            <div class="imp-card-ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 18h16M4 18V8l6-5 6 5v10M8 18v-5h4v5"/></svg>
            </div>
            <div>
              <h2 class="imp-card-title">Nome sala</h2>
              <p class="imp-card-desc">Appare nell'header, nella PWA e nei documenti generati.</p>
            </div>
          </div>
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

        <div class="imp-card">
          <div class="imp-card-head">
            <div class="imp-card-ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="16" height="13" rx="1.5"/><path d="M2 8l5 4 4-3 7 5"/></svg>
            </div>
            <div>
              <h2 class="imp-card-title">Logo</h2>
              <p class="imp-card-desc">Sostituisce le iniziali nella sidebar. JPG, PNG, WebP, SVG — max 2 MB.</p>
            </div>
          </div>
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

      <div class="imp-card" style="margin-top:16px">
        <div class="imp-card-head">
          <div class="imp-card-ico" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="3"/><path d="M5.3 5.3l1.4 1.4M13.3 13.3l1.4 1.4M3 10h2M15 10h2M5.3 14.7l1.4-1.4M13.3 6.7l1.4-1.4M10 3v2M10 15v2"/></svg>
          </div>
          <div>
            <h2 class="imp-card-title">Brand colori</h2>
            <p class="imp-card-desc">Colore accent di bottoni, link attivi e badge. Lascia vuoto per tornare al blu predefinito.</p>
          </div>
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
      <div class="imp-card">
        <div class="imp-card-head">
          <div class="imp-card-ico" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M10 6v4l2.5 2.5"/></svg>
          </div>
          <div>
            <h2 class="imp-card-title">Configurazione turni</h2>
            <p class="imp-card-desc">Scegli quanti turni al giorno, personalizza i nomi, gli orari e il compenso per operatore.</p>
          </div>
        </div>
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
              <p class="imp-card-desc" style="margin-bottom:12px">Importo corrisposto all'operatore per ogni turno effettuato.</p>
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
      </div>
    </div>

    <!-- OPERATORI -->
    <div class="imp-section" id="operatori">
      <div class="imp-section-head">
        <h2>Permessi operatori</h2>
        <p>Controllo delle azioni disponibili agli operatori sul calendario e sui turni giornalieri</p>
      </div>
      <div class="imp-card">
        <div class="imp-card-head">
          <div class="imp-card-ico" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="9" width="12" height="9" rx="1.5"/><path d="M7 9V7.5a3 3 0 0 1 6 0V9"/><circle cx="10" cy="14" r="1"/></svg>
          </div>
          <div>
            <h2 class="imp-card-title">Permessi</h2>
            <p class="imp-card-desc">Abilita o disabilita le azioni degli operatori sul calendario e sui dati di cassa.</p>
          </div>
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="permessi">
          <div class="imp-opt-stack">
            <label class="imp-opt">
              <input type="checkbox" name="operatori_modifica_turni" <?= ($sett['operatori_modifica_turni'] ?? '1') === '1' ? 'checked' : '' ?>>
              <span class="imp-opt-text">
                <strong>Modifica calendario</strong>
                <span>Gli operatori possono aggiungere e modificare i turni programmati nel calendario</span>
              </span>
            </label>
            <label class="imp-opt">
              <input type="checkbox" name="turno_edit_libero" <?= ($sett['turno_edit_libero'] ?? '1') === '1' ? 'checked' : '' ?>>
              <span class="imp-opt-text">
                <strong>Modifica libera turni</strong>
                <span>Gli operatori possono modificare i dati di qualsiasi turno giornaliero, non solo il proprio — utile per correzioni e inserimento storico</span>
              </span>
            </label>
          </div>
          <div class="imp-form-footer">
            <button type="submit">Salva permessi</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODULI -->
    <div class="imp-section" id="moduli">
      <div class="imp-section-head">
        <h2>Moduli aggiuntivi</h2>
        <p>Attiva o disattiva le sezioni opzionali — i link scompaiono dalla barra laterale quando disabilitati</p>
      </div>
      <div class="imp-card">
        <div class="imp-card-head">
          <div class="imp-card-ico" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/></svg>
          </div>
          <div>
            <h2 class="imp-card-title">Moduli</h2>
            <p class="imp-card-desc">Abilita solo le funzionalità necessarie alla sala per mantenere l'interfaccia pulita.</p>
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
            <label class="imp-opt">
              <input type="checkbox" name="modulo_documenti" <?= ($sett['modulo_documenti'] ?? '1') === '1' ? 'checked' : '' ?>>
              <span class="imp-opt-text">
                <strong>Documenti</strong>
                <span>Caricamento e stampa di documenti operativi (moduli, avvisi, istruzioni)</span>
              </span>
            </label>
          </div>
          <div class="imp-form-footer">
            <button type="submit">Salva moduli</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ASSISTENZA -->
    <div class="imp-section" id="assistenza">
      <div class="imp-section-head">
        <h2>Assistenza tecnica</h2>
        <p>Recapiti mostrati agli operatori quando aprono un ticket di assistenza per una macchina</p>
      </div>
      <div class="imp-card">
        <div class="imp-card-head">
          <div class="imp-card-ico" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8 9a16 16 0 0 0 5 5l.72-.85a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 20.5 15l.42 1.92z"/></svg>
          </div>
          <div>
            <h2 class="imp-card-title">Contatti assistenza</h2>
            <p class="imp-card-desc">Numero di telefono, codice lock e password da mostrare nel dialog del ticket.</p>
          </div>
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="assistenza">
          <div class="imp-price-row">
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
      </div>
    </div>

    <!-- SISTEMA -->
    <div class="imp-section" id="sistema">
      <div class="imp-section-head">
        <h2>Sistema</h2>
        <p>Fuso orario e politica di retention dei log di audit</p>
      </div>

      <div class="imp-card">
        <div class="imp-card-head">
          <div class="imp-card-ico" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M5 10l3 3 7-7"/></svg>
          </div>
          <div>
            <h2 class="imp-card-title">Fuso orario</h2>
            <p class="imp-card-desc">Usato per determinare il turno corrente e le date di chiusura. Un cambio diventa effettivo alla sessione successiva.</p>
          </div>
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="timezone">
          <div class="imp-field">
            <label for="imp-tz">Fuso orario</label>
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

      <div class="imp-card" style="margin-top:16px">
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
      </div>
    </div>

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

<?php endif; ?>
</body></html>
