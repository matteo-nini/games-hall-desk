<?php
/**
 * SETUP WIZARD — Installazione iniziale.
 * Eliminare dal server dopo il completamento.
 */
session_start();

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

/* -------- helpers -------- */
function setup_cfg(): array {
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config.php';
        $c = file_exists($f)
            ? require $f
            : ['db' => ['host' => '127.0.0.1', 'name' => '', 'user' => '', 'pass' => '', 'charset' => 'utf8mb4'], 'nome_sala' => ''];
    }
    return $c;
}

function setup_connect(?string $dbname = null): PDO {
    $c   = setup_cfg()['db'];
    $dsn = "mysql:host={$c['host']};charset={$c['charset']}" . ($dbname ? ";dbname={$dbname}" : '');
    return new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function setup_pdo(): ?PDO {
    try { return setup_connect(setup_cfg()['db']['name']); }
    catch (Throwable) { return null; }
}

function setup_run_file(PDO $pdo, string $path): void {
    $sql  = file_get_contents($path);
    $sql  = preg_replace('/^--[^\n]*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql)), 'strlen') as $stmt) {
        $pdo->exec($stmt);
    }
}

$cfgFile = __DIR__ . '/config.php';

/* -------- guard: già configurato -------- */
$pdo = setup_pdo();
if ($pdo) {
    try {
        $nResp = (int)$pdo->query('SELECT COUNT(*) FROM utenti WHERE ruolo="responsabile"')->fetchColumn();
        if ($nResp > 0 && ($_GET['force'] ?? '') !== '1') {
            header('Location: ../account/login.php'); exit;
        }
    } catch (Throwable) { /* tabelle non ancora create */ }
}

$step = (int)($_SESSION['setup_step'] ?? 1);
$err  = $_SESSION['setup_err'] ?? '';
$msg  = $_SESSION['setup_ok']  ?? '';
unset($_SESSION['setup_err'], $_SESSION['setup_ok']);

/* Se config.php mancante o senza credenziali, torna al passo 1 */
if (!file_exists($cfgFile) || empty(setup_cfg()['db']['user'])) {
    if ($step > 1) { $_SESSION['setup_step'] = 1; $step = 1; }
}

/* ?back=1 — retrocede di un passo */
if (($_GET['back'] ?? '') === '1' && $step > 1) {
    $_SESSION['setup_step'] = $step - 1;
    header('Location: setup.php'); exit;
}

/* -------- POST handlers -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    /* Step 1 — connessione database */
    if ($act === 'dbconfig') {
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        if (!$dbHost || !$dbName || !$dbUser) {
            $_SESSION['setup_err'] = 'Host, nome database e utente sono obbligatori.';
        } else {
            try {
                $dsn = "mysql:host={$dbHost};charset=utf8mb4";
                new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $cfg = [
                    'db' => [
                        'host'    => $dbHost,
                        'name'    => $dbName,
                        'user'    => $dbUser,
                        'pass'    => $dbPass,
                        'charset' => 'utf8mb4',
                    ],
                    'nome_sala' => setup_cfg()['nome_sala'] ?? '',
                ];
                file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfg, true) . ";\n");
                $_SESSION['setup_step'] = 2;
                $_SESSION['setup_ok']   = 'Connessione verificata e configurazione salvata.';
            } catch (Throwable $e) {
                $_SESSION['setup_err'] = 'Connessione fallita: ' . $e->getMessage();
            }
        }
        header('Location: setup.php'); exit;
    }

    /* Step 2 — schema */
    if ($act === 'schema') {
        try {
            $c    = setup_cfg()['db'];
            $root = setup_connect();
            $root->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db   = setup_connect($c['name']);
            foreach (['schema','002_ticket_assistenza','003_prestiti',
                      '004_turni_programmati','005_profilo_impostazioni','006_moduli','007_seriali_civ'] as $f) {
                $path = __DIR__ . "/../sql/{$f}.sql";
                if (file_exists($path)) setup_run_file($db, $path);
            }
            $_SESSION['setup_step'] = 3;
            $_SESSION['setup_ok']   = 'Database creato e schema installato correttamente.';
        } catch (Throwable $e) {
            $_SESSION['setup_err'] = $e->getMessage();
        }
        header('Location: setup.php'); exit;
    }

    /* Step 3 — admin */
    if ($act === 'admin') {
        $db   = setup_pdo();
        $nome = trim($_POST['nome']     ?? '');
        $usr  = trim($_POST['username'] ?? '');
        $pwd  = $_POST['password'] ?? '';
        $pwd2 = $_POST['confirm']  ?? '';
        if (!$db)                  $_SESSION['setup_err'] = 'Database non raggiungibile.';
        elseif ($usr === '')       $_SESSION['setup_err'] = 'Username obbligatorio.';
        elseif (strlen($pwd) < 8)  $_SESSION['setup_err'] = 'Password minima 8 caratteri.';
        elseif ($pwd !== $pwd2)    $_SESSION['setup_err'] = 'Le password non coincidono.';
        else {
            try {
                $ex = $db->prepare('SELECT COUNT(*) FROM utenti WHERE username=?');
                $ex->execute([$usr]);
                if ($ex->fetchColumn()) {
                    $_SESSION['setup_err'] = 'Username già in uso.';
                } else {
                    $db->prepare('INSERT INTO utenti (username,password_hash,nome,ruolo) VALUES (?,?,?,?)')
                       ->execute([$usr, password_hash($pwd, PASSWORD_DEFAULT), $nome ?: null, 'responsabile']);
                    $_SESSION['setup_step'] = 4;
                    $_SESSION['setup_ok']   = "Utente responsabile «{$usr}» creato.";
                }
            } catch (Throwable $e) { $_SESSION['setup_err'] = $e->getMessage(); }
        }
        header('Location: setup.php'); exit;
    }

    /* Step 4 — sala & moduli */
    if ($act === 'sala') {
        $db   = setup_pdo();
        $nome = trim($_POST['nome_sala'] ?? '');
        $ass  = isset($_POST['modulo_assistenze']) ? '1' : '0';
        $pre  = isset($_POST['modulo_prestiti'])   ? '1' : '0';
        if (!$db) { $_SESSION['setup_err'] = 'Database non raggiungibile.'; }
        else {
            try {
                $ups = $db->prepare(
                    'INSERT INTO impostazioni (chiave,valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)'
                );
                $ups->execute(['modulo_assistenze', $ass]);
                $ups->execute(['modulo_prestiti',   $pre]);
                if ($nome !== '') {
                    $cfg = (function() use ($cfgFile) { return require $cfgFile; })();
                    $cfg['nome_sala'] = $nome;
                    file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfg, true) . ";\n");
                }
                $_SESSION['setup_step'] = 5;
                $_SESSION['setup_ok']   = 'Configurazione sala salvata.';
            } catch (Throwable $e) { $_SESSION['setup_err'] = $e->getMessage(); }
        }
        header('Location: setup.php'); exit;
    }

    /* Step 5 — macchine */
    if ($act === 'macchine') {
        $db   = setup_pdo();
        $skip = isset($_POST['salta']);
        if (!$db) { $_SESSION['setup_err'] = 'Database non raggiungibile.'; }
        elseif ($skip) {
            $_SESSION['setup_step'] = 6;
        } else {
            try {
                $db->exec('DELETE FROM macchine');
                $ins     = $db->prepare('INSERT INTO macchine (codice,tipo,fornitore,seriale,civ,ordine,attiva) VALUES (?,?,?,?,?,?,1)');
                $codici  = $_POST['codice']    ?? [];
                $tipi    = $_POST['tipo']      ?? [];
                $forns   = $_POST['fornitore'] ?? [];
                $seriali = $_POST['seriale']   ?? [];
                $civs    = $_POST['civ']        ?? [];
                $ord = 1;
                foreach ($codici as $i => $cod) {
                    if (($cod = trim($cod)) === '') continue;
                    $ser = mb_substr(trim($seriali[$i] ?? ''), 0, 100) ?: null;
                    $civ = mb_substr(trim($civs[$i]    ?? ''), 0, 100) ?: null;
                    $ins->execute([$cod, $tipi[$i] ?? 'VLT', $forns[$i] ?? 'NOVO', $ser, $civ, $ord++]);
                }
                $_SESSION['setup_step'] = 6;
                $_SESSION['setup_ok']   = 'Macchine salvate.';
            } catch (Throwable $e) { $_SESSION['setup_err'] = $e->getMessage(); }
        }
        header('Location: setup.php'); exit;
    }
}

/* Pre-carica macchine per step 5 */
$macchineExisting = [];
if ($step === 5 && ($db = setup_pdo())) {
    try { $macchineExisting = $db->query('SELECT codice,tipo,fornitore,seriale,civ FROM macchine ORDER BY ordine')->fetchAll(); }
    catch (Throwable) {}
}

$steps = ['Connessione DB', 'Schema DB', 'Utente admin', 'Sala e moduli', 'Macchine', 'Completato'];
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup</title>
<link rel="stylesheet" href="../assets/css/core.css">
<style>
body { margin: 0; display: flex; align-items: flex-start; justify-content: center; min-height: 100vh; background: var(--bg); padding: 40px 16px 60px }

.sw-wrap { width: 100%; max-width: 600px }

.sw-header { text-align: center; margin-bottom: 32px }
.sw-logo { font-size: 28px; font-weight: 800; color: var(--accent); letter-spacing: -1px }
.sw-sub { font-size: 13px; color: var(--muted); margin-top: 4px }

/* Step indicator */
.sw-stepper { display: flex; align-items: center; gap: 0; margin-bottom: 36px }
.sw-st { display: flex; flex-direction: column; align-items: center; flex: 1; gap: 6px }
.sw-st-dot {
  width: 30px; height: 30px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; border: 2px solid var(--border);
  background: var(--surface); color: var(--faint); transition: all .2s; position: relative; z-index: 1
}
.sw-st.done .sw-st-dot  { background: var(--accent); border-color: var(--accent); color: #fff }
.sw-st.active .sw-st-dot { border-color: var(--accent); color: var(--accent); background: var(--accent-weak) }
.sw-st-lbl { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: var(--faint); white-space: nowrap }
.sw-st.done .sw-st-lbl, .sw-st.active .sw-st-lbl { color: var(--accent) }
.sw-connector { flex: 1; height: 2px; background: var(--border); max-width: 40px; margin-top: -20px }
.sw-connector.done { background: var(--accent) }

/* Card */
.sw-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); padding: 28px 32px }
.sw-card h2 { font-size: 18px; font-weight: 700; color: var(--text); margin: 0 0 6px }
.sw-card .sw-desc { font-size: 13px; color: var(--muted); margin: 0 0 24px; line-height: 1.55 }

/* Alert */
.sw-err { background: var(--red-bg); color: var(--red-ink); border: 1px solid var(--red); border-radius: var(--rxs); padding: 10px 14px; font-size: 13px; margin-bottom: 18px }
.sw-ok  { background: var(--green-bg); color: var(--green-ink); border: 1px solid var(--green); border-radius: var(--rxs); padding: 10px 14px; font-size: 13px; margin-bottom: 18px }

/* Fields */
.sw-field { margin-bottom: 16px }
.sw-field label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--muted); margin-bottom: 5px }
.sw-field input[type=text], .sw-field input[type=password] {
  width: 100%; box-sizing: border-box;
  border: 1px solid var(--border); border-radius: var(--rxs); padding: 10px 12px;
  font-size: 14px; background: var(--bg); color: var(--text);
  transition: border-color .1s
}
.sw-field input:focus { outline: none; border-color: var(--accent) }

/* Checkboxes */
.sw-checks { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px }
.sw-check { display: flex; align-items: center; gap: 10px; font-size: 14px; cursor: pointer }
.sw-check input { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer }
.sw-check-lbl { color: var(--text); font-weight: 600 }
.sw-check-sub { display: block; font-size: 12px; color: var(--muted); font-weight: 400 }

/* Buttons */
.sw-btn { display: inline-flex; align-items: center; gap: 6px; background: var(--accent); color: #fff; border: none; border-radius: var(--rs); padding: 11px 22px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .1s; text-decoration: none }
.sw-btn:hover { background: var(--accent-ink) }
.sw-btn-ghost { background: none; border: 1px solid var(--border); color: var(--muted); font-weight: 600 }
.sw-btn-ghost:hover { background: var(--surface2); color: var(--text) }
.sw-actions { display: flex; align-items: center; gap: 10px; margin-top: 24px }

/* Config box */
.sw-cfgbox { background: var(--bg); border: 1px solid var(--border); border-radius: var(--rxs); padding: 14px 16px; font-size: 12px; font-family: monospace; color: var(--muted); margin-bottom: 20px; line-height: 1.7 }
.sw-cfgbox strong { color: var(--text) }
.sw-cfgbox a { color: var(--accent); font-family: inherit; font-size: inherit }

/* Machines table */
.sw-mach-table { width: 100%; border-collapse: collapse; margin-bottom: 10px }
.sw-mach-table th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--faint); text-align: left; padding: 0 6px 8px }
.sw-mach-table td { padding: 3px 4px }
.sw-mach-table input, .sw-mach-table select {
  width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 6px;
  padding: 6px 8px; font-size: 13px; background: var(--bg); color: var(--text)
}
.sw-mach-table select { cursor: pointer }
.sw-mach-del { background: none; border: none; color: var(--faint); font-size: 16px; cursor: pointer; padding: 4px 8px; border-radius: 4px; transition: color .1s, background .1s }
.sw-mach-del:hover { color: var(--red); background: var(--red-bg) }
.sw-add-row { font-size: 12px; font-weight: 700; color: var(--accent); background: none; border: 1px dashed var(--border2); border-radius: var(--rxs); padding: 7px 14px; cursor: pointer; transition: border-color .1s, background .1s; display: block; width: 100%; margin-bottom: 16px; text-align: center }
.sw-add-row:hover { border-color: var(--accent); background: var(--accent-weak) }
.sw-separator { display: flex; align-items: center; gap: 10px; margin: 18px 0; color: var(--faint); font-size: 12px }
.sw-separator::before, .sw-separator::after { content: ''; flex: 1; height: 1px; background: var(--border) }

/* Done */
.sw-done-icon { width: 64px; height: 64px; background: var(--green-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px }
.sw-done-icon svg { color: var(--green-ink) }
.sw-done h2 { text-align: center; font-size: 22px }
.sw-done .sw-desc { text-align: center }
.sw-done-warn { background: var(--amber-bg); color: var(--amber-ink); border: 1px solid var(--amber); border-radius: var(--rxs); padding: 12px 16px; font-size: 13px; margin: 20px 0; line-height: 1.55 }
.sw-done-warn strong { display: block; margin-bottom: 2px }
</style>
</head><body>

<div class="sw-wrap">

  <div class="sw-header">
    <div class="sw-logo">Configurazione</div>
    <div class="sw-sub">Procedura di installazione iniziale</div>
  </div>

  <!-- Step indicator -->
  <div class="sw-stepper" aria-label="Avanzamento setup">
    <?php foreach ($steps as $i => $label):
        $n      = $i + 1;
        $isDone = $n < $step;
        $isAct  = $n === $step;
        $cls    = $isDone ? 'done' : ($isAct ? 'active' : '');
    ?>
    <?php if ($i > 0): ?><div class="sw-connector <?= $isDone ? 'done' : '' ?>"></div><?php endif; ?>
    <div class="sw-st <?= $cls ?>">
      <div class="sw-st-dot">
        <?php if ($isDone): ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
        <?php else: ?>
          <?= $n ?>
        <?php endif; ?>
      </div>
      <div class="sw-st-lbl"><?= $h($label) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($err): ?><div class="sw-err"><?= $h($err) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="sw-ok"><?= $h($msg) ?></div><?php endif; ?>

  <!-- ============================================================
       STEP 1 — Connessione database
       ============================================================ -->
  <?php if ($step === 1): ?>
  <div class="sw-card">
    <h2>Connessione al database</h2>
    <p class="sw-desc">Inserisci i parametri MySQL. Le credenziali vengono salvate in <code>install/config.php</code> e utilizzate dall'applicazione.</p>
    <?php $dbCfg = setup_cfg()['db']; ?>
    <form method="post">
      <input type="hidden" name="act" value="dbconfig">
      <div class="sw-field">
        <label for="sw-dbhost">Host <span style="color:var(--red)">*</span></label>
        <input type="text" id="sw-dbhost" name="db_host"
               value="<?= $h($dbCfg['host'] ?: '127.0.0.1') ?>" placeholder="127.0.0.1" required>
      </div>
      <div class="sw-field">
        <label for="sw-dbname">Nome database <span style="color:var(--red)">*</span></label>
        <input type="text" id="sw-dbname" name="db_name"
               value="<?= $h($dbCfg['name']) ?>" placeholder="cassa_sala" required>
      </div>
      <div class="sw-field">
        <label for="sw-dbuser">Utente MySQL <span style="color:var(--red)">*</span></label>
        <input type="text" id="sw-dbuser" name="db_user"
               value="<?= $h($dbCfg['user']) ?>" placeholder="root" required autocomplete="username">
      </div>
      <div class="sw-field">
        <label for="sw-dbpass">Password MySQL</label>
        <input type="password" id="sw-dbpass" name="db_pass"
               placeholder="(lascia vuoto se assente)" autocomplete="current-password">
      </div>
      <div class="sw-actions">
        <button type="submit" class="sw-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
          Testa e continua
        </button>
      </div>
    </form>
  </div>

  <!-- ============================================================
       STEP 2 — Schema DB
       ============================================================ -->
  <?php elseif ($step === 2): ?>
  <div class="sw-card">
    <h2>Installazione database</h2>
    <p class="sw-desc">Il wizard creerà il database (se non esiste) ed eseguirà tutti i file di schema SQL.</p>

    <?php $c = setup_cfg()['db']; ?>
    <div class="sw-cfgbox">
      Host: <strong><?= $h($c['host']) ?></strong><br>
      Database: <strong><?= $h($c['name']) ?></strong><br>
      Utente: <strong><?= $h($c['user']) ?></strong><br>
      Password: <strong><?= $c['pass'] !== '' ? '••••••' : '(vuota)' ?></strong><br>
      <a href="?back=1" style="font-size:11px">Modifica connessione</a>
    </div>

    <?php
    $connOk = false;
    try { setup_connect(); $connOk = true; } catch (Throwable $e) { }
    ?>
    <?php if (!$connOk): ?>
    <div class="sw-err">Connessione al server MySQL fallita. <a href="?back=1">Controlla i parametri</a>.</div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="act" value="schema">
      <div class="sw-actions">
        <button type="submit" class="sw-btn" <?= !$connOk ? 'disabled' : '' ?>>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M21.17 8H12V2.83"/></svg>
          Installa schema
        </button>
        <?php if (!$connOk): ?><span style="font-size:12px;color:var(--faint)">Risolvi la connessione per continuare.</span><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ============================================================
       STEP 3 — Admin
       ============================================================ -->
  <?php elseif ($step === 3): ?>
  <div class="sw-card">
    <h2>Crea l'utente responsabile</h2>
    <p class="sw-desc">Questo account avrà accesso completo all'applicazione (admin). Potrai aggiungere altri utenti dopo il login.</p>
    <form method="post">
      <input type="hidden" name="act" value="admin">
      <div class="sw-field">
        <label for="sw-nome">Nome visualizzato</label>
        <input type="text" id="sw-nome" name="nome" placeholder="Es. Mario Rossi" autocomplete="name">
      </div>
      <div class="sw-field">
        <label for="sw-user">Username <span style="color:var(--red)">*</span></label>
        <input type="text" id="sw-user" name="username" required autocomplete="username">
      </div>
      <div class="sw-field">
        <label for="sw-pwd">Password <span style="color:var(--red)">*</span> <span style="font-size:11px;font-weight:400;text-transform:none;letter-spacing:0">(min. 8 caratteri)</span></label>
        <input type="password" id="sw-pwd" name="password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="sw-field">
        <label for="sw-pwd2">Conferma password <span style="color:var(--red)">*</span></label>
        <input type="password" id="sw-pwd2" name="confirm" required minlength="8" autocomplete="new-password">
      </div>
      <div class="sw-actions">
        <button type="submit" class="sw-btn">Crea account e continua</button>
      </div>
    </form>
  </div>

  <!-- ============================================================
       STEP 4 — Sala & moduli
       ============================================================ -->
  <?php elseif ($step === 4): ?>
  <div class="sw-card">
    <h2>Configurazione sala</h2>
    <p class="sw-desc">Inserisci il nome della sala (compare nell'intestazione) e scegli i moduli da attivare. Puoi modificarli in qualsiasi momento dalle Impostazioni.</p>
    <form method="post">
      <input type="hidden" name="act" value="sala">
      <div class="sw-field">
        <label for="sw-sala">Nome sala</label>
        <?php $curNome = setup_cfg()['nome_sala'] ?? ''; ?>
        <input type="text" id="sw-sala" name="nome_sala" value="<?= $h($curNome) ?>" placeholder="Es. Sala Giochi Roma">
      </div>

      <label style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin:20px 0 10px">Moduli attivi</label>
      <div class="sw-checks">
        <label class="sw-check">
          <input type="checkbox" name="modulo_assistenze" value="1" checked>
          <span>
            <span class="sw-check-lbl">Ticket assistenza</span>
            <span class="sw-check-sub">Gestione guasti e interventi tecnici sulle macchine</span>
          </span>
        </label>
        <label class="sw-check">
          <input type="checkbox" name="modulo_prestiti" value="1" checked>
          <span>
            <span class="sw-check-lbl">Prestiti</span>
            <span class="sw-check-sub">Tracciamento prestiti di denaro a clienti o collaboratori</span>
          </span>
        </label>
      </div>
      <div class="sw-actions">
        <button type="submit" class="sw-btn">Salva e continua</button>
      </div>
    </form>
  </div>

  <!-- ============================================================
       STEP 5 — Macchine
       ============================================================ -->
  <?php elseif ($step === 5): ?>
  <div class="sw-card">
    <h2>Parco macchine</h2>
    <p class="sw-desc">Inserisci le macchine VLT e AWP presenti in sala. Ogni macchina deve avere un codice univoco (es. NOVO 31, INSPIRED 106). Puoi aggiungere o rimuovere macchine in qualsiasi momento dalla sezione <strong>Macchine</strong> dopo il login.</p>

    <form method="post" id="frm-macchine">
      <input type="hidden" name="act" value="macchine">

      <table class="sw-mach-table" id="mach-table">
        <thead>
          <tr>
            <th style="width:28%">Codice</th>
            <th style="width:14%">Tipo</th>
            <th style="width:20%">Fornitore</th>
            <th style="width:18%">Seriale</th>
            <th style="width:13%">CIV</th>
            <th style="width:7%"></th>
          </tr>
        </thead>
        <tbody id="mach-body">
          <?php if ($macchineExisting): ?>
          <?php foreach ($macchineExisting as $m): ?>
          <tr class="mach-row">
            <td><input type="text" name="codice[]" value="<?= $h($m['codice']) ?>" placeholder="Es. NOVO 31" required></td>
            <td>
              <select name="tipo[]">
                <option value="VLT" <?= $m['tipo']==='VLT' ? 'selected':'' ?>>VLT</option>
                <option value="AWP" <?= $m['tipo']==='AWP' ? 'selected':'' ?>>AWP</option>
              </select>
            </td>
            <td>
              <select name="fornitore[]">
                <?php foreach (['NOVO','INSPIRED','SPIELO','ALTRO'] as $f): ?>
                <option value="<?= $f ?>" <?= $m['fornitore']===$f ? 'selected':'' ?>><?= $f ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="text" name="seriale[]" value="<?= $h($m['seriale'] ?? '') ?>" placeholder="SN…" maxlength="100"></td>
            <td><input type="text" name="civ[]" value="<?= $h($m['civ'] ?? '') ?>" placeholder="CIV…" maxlength="100"></td>
            <td><button type="button" class="sw-mach-del" title="Rimuovi">×</button></td>
          </tr>
          <?php endforeach; ?>
          <?php else: ?>
          <tr class="mach-row">
            <td><input type="text" name="codice[]" placeholder="Es. NOVO 31" required></td>
            <td><select name="tipo[]"><option value="VLT">VLT</option><option value="AWP">AWP</option></select></td>
            <td><select name="fornitore[]"><option value="NOVO">NOVO</option><option value="INSPIRED">INSPIRED</option><option value="SPIELO">SPIELO</option><option value="ALTRO">ALTRO</option></select></td>
            <td><input type="text" name="seriale[]" placeholder="SN…" maxlength="100"></td>
            <td><input type="text" name="civ[]" placeholder="CIV…" maxlength="100"></td>
            <td><button type="button" class="sw-mach-del" title="Rimuovi">×</button></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <button type="button" class="sw-add-row" id="add-mach">+ Aggiungi macchina</button>

      <div class="sw-actions">
        <button type="submit" class="sw-btn">Salva macchine e concludi</button>
        <button type="submit" class="sw-btn sw-btn-ghost" name="salta" value="1">Salta (usa le macchine attuali)</button>
      </div>
    </form>
  </div>

  <!-- ============================================================
       STEP 6 — Completato
       ============================================================ -->
  <?php elseif ($step >= 6): ?>
  <div class="sw-card sw-done">
    <div class="sw-done-icon">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
    </div>
    <h2>Setup completato!</h2>
    <p class="sw-desc">Il sistema è pronto all'uso. Accedi con le credenziali del responsabile appena creato.</p>

    <div class="sw-done-warn">
      <strong>⚠ Elimina setup.php dal server</strong>
      Questo file permette di reinstallare il database. Rimuovilo subito dopo aver verificato che l'accesso funziona.
    </div>

    <div class="sw-actions" style="justify-content:center">
      <a href="../account/login.php" class="sw-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
        Vai al login
      </a>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
(function () {
  var tbody = document.getElementById('mach-body');
  if (!tbody) return;

  function rowHtml() {
    return '<tr class="mach-row">'
      + '<td><input type="text" name="codice[]" placeholder="Es. NOVO 31" required></td>'
      + '<td><select name="tipo[]"><option value="VLT">VLT</option><option value="AWP">AWP</option></select></td>'
      + '<td><select name="fornitore[]"><option value="NOVO">NOVO</option><option value="INSPIRED">INSPIRED</option><option value="SPIELO">SPIELO</option><option value="ALTRO">ALTRO</option></select></td>'
      + '<td><input type="text" name="seriale[]" placeholder="SN…" maxlength="100"></td>'
      + '<td><input type="text" name="civ[]" placeholder="CIV…" maxlength="100"></td>'
      + '<td><button type="button" class="sw-mach-del" title="Rimuovi">&times;</button></td>'
      + '</tr>';
  }

  document.getElementById('add-mach')?.addEventListener('click', function () {
    tbody.insertAdjacentHTML('beforeend', rowHtml());
  });

  tbody.addEventListener('click', function (e) {
    if (e.target.closest('.sw-mach-del')) {
      var row = e.target.closest('.mach-row');
      if (tbody.querySelectorAll('.mach-row').length > 1) row.remove();
      else row.querySelector('input[name="codice[]"]').value = '';
    }
  });
}());
</script>
</body></html>
