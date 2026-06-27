<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
if (is_revisore()) { header('Location: ' . base_url('cassa/settimanale.php')); exit; }
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv   = fn($v) => number_format((float)$v, 2, ',', '.');
$uid  = (int)$user['id'];

/* =========================================================
   POST: avvia turno dalla dashboard → poi va in cassa
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['azione'] ?? '') === 'inizia' && !is_responsabile()) {
        $n    = (int)($_POST['numero'] ?? 0);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && in_array($n, [1, 2])) {
            $g = ensure_giornata($pdo, $data);
            $t = ensure_turno($pdo, (int)$g['id'], $n);
            $pdo->prepare('UPDATE turni SET operatore_id=?, iniziato_il=NOW() WHERE id=?')
                ->execute([$uid, (int)$t['id']]);
            audit('inizio_turno', 'turni', (int)$t['id'], "turno=$n data=$data via=dashboard");
        }
        header('Location: ../cassa/giornaliero.php'); exit;
    }
    header('Location: dashboard.php'); exit;
}

/* =========================================================
   GET — dati
   ========================================================= */
$oggi    = date('Y-m-d');
$cfg     = config();
$sett    = get_settings($pdo);

/* Orari turni (da impostazioni o default) */
$mInizio = $sett['turno_mattino_inizio'] ?? '13:00';
$mFine   = $sett['turno_mattino_fine']   ?? '19:00';
$sInizio = $sett['turno_sera_inizio']    ?? '19:00';
$sFine   = $sett['turno_sera_fine']      ?? '01:00';

/* Turno corrente in base all'ora */
$ora       = (int)date('G');
$minuti    = (int)date('i');
$oraFloat  = $ora + $minuti / 60;

[$mhI, $mmI] = array_map('intval', explode(':', $mInizio));
[$mhF, $mmF] = array_map('intval', explode(':', $mFine));
[$shI, $smI] = array_map('intval', explode(':', $sInizio));

$mStart = $mhI + $mmI / 60;
$mEnd   = $mhF + $mmF / 60;
$sStart = $shI + $smI / 60;

$nCorrente = null;
if ($oraFloat >= $mStart && $oraFloat < $mEnd)              $nCorrente = 1;
elseif ($oraFloat >= $sStart || $oraFloat < 2)              $nCorrente = 2;

/* Assegnazione turno di oggi */
$assegnazioneOggi = null;
$turnoGiornaliero = false;
$giaIniziato      = false;

try {
    if ($nCorrente !== null) {
        $st = $pdo->prepare(
            'SELECT tp.operatore_id, COALESCE(NULLIF(u.nome,""),u.username) AS nome
             FROM turni_programmati tp JOIN utenti u ON u.id=tp.operatore_id
             WHERE tp.data=? AND tp.numero=?'
        );
        $st->execute([$oggi, $nCorrente]);
        $assegnazioneOggi = $st->fetch() ?: null;

        $st2 = $pdo->prepare(
            'SELECT t.operatore_id, t.iniziato_il
             FROM turni t JOIN giornate g ON g.id=t.giornata_id
             WHERE g.data=? AND t.numero=?'
        );
        $st2->execute([$oggi, $nCorrente]);
        $turnoGiornaliero = $st2->fetch() ?: false;
        $giaIniziato = $turnoGiornaliero && !empty($turnoGiornaliero['iniziato_il'])
                    && (int)$turnoGiornaliero['operatore_id'] === $uid;
    }
} catch (PDOException $e) { /* migration non eseguita */ }

/* Guadagni: ultimi 3 mesi + futuri */
$guadagnato = 0.0;
$previsto   = 0.0;
$miei_turni = [];
try {
    $st = $pdo->prepare(
        'SELECT tp.data, tp.numero, pt.prezzo
         FROM turni_programmati tp
         JOIN prezzi_turni pt ON pt.nome = CASE WHEN tp.numero=1 THEN "mattino" ELSE "sera" END
         WHERE tp.operatore_id=?
           AND tp.data >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
         ORDER BY tp.data, tp.numero'
    );
    $st->execute([$uid]);
    $miei_turni = $st->fetchAll();
    foreach ($miei_turni as $mt) {
        if ($mt['data'] <= $oggi) $guadagnato += (float)$mt['prezzo'];
        else                      $previsto   += (float)$mt['prezzo'];
    }
} catch (PDOException $e) { /* migration non eseguita */ }

/* Prossimi turni (max 6) */
$prossimi = array_values(array_filter($miei_turni, fn($t) => $t['data'] > $oggi));
$prossimi = array_slice($prossimi, 0, 6);

/* Turni questo mese (per conteggio e stipendio) */
$mese1 = date('Y-m-01');
$mese2 = date('Y-m-t');
$turniMese      = array_filter($miei_turni, fn($t) => $t['data'] >= $mese1 && $t['data'] <= $mese2);
$guadagnatoMese = 0.0;
$previstoMese   = 0.0;
foreach ($turniMese as $mt) {
    if ($mt['data'] <= $oggi) $guadagnatoMese += (float)$mt['prezzo'];
    else                      $previstoMese   += (float)$mt['prezzo'];
}
$totaleMese = $guadagnatoMese + $previstoMese;

/* Mie performance ultimi 30 giorni */
$miePerf    = [];
$scostMed   = null;
$pctOk      = null;
$nTurniPerf = 0;
$clsPerf    = '';
try {
    $stPerf = $pdo->prepare("
        SELECT t.fondo_cassa, t.monete, t.bancomat, t.differenze, t.ii_cassa, t.rientri, g.data,
               COALESCE((SELECT SUM(c.taglio*c.pezzi) FROM contanti c WHERE c.turno_id=t.id),0) AS contanti,
               COALESCE((SELECT SUM(r.euro) FROM refill_awp r WHERE r.turno_id=t.id),0) AS refill,
               COALESCE((SELECT SUM(s.importo) FROM scassettamenti s WHERE s.turno_id=t.id),0) AS scass,
               COALESCE((SELECT SUM(tk.importo) FROM ticket tk WHERE tk.turno_id=t.id),0) AS ticket
        FROM turni t JOIN giornate g ON g.id=t.giornata_id
        WHERE t.operatore_id=? AND g.data >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY g.data DESC, t.numero DESC
    ");
    $stPerf->execute([$uid]);
    foreach ($stPerf as $row) {
        $calc      = calcola_turno((array)$row);
        $miePerf[] = ['data' => $row['data'], 'errore' => abs($calc['errore'])];
    }
    $nTurniPerf = count($miePerf);
    if ($nTurniPerf > 0) {
        $nOkPerf  = count(array_filter($miePerf, fn($p) => $p['errore'] < 4));
        $scostMed = array_sum(array_column($miePerf, 'errore')) / $nTurniPerf;
        $pctOk    = (int)round($nOkPerf / $nTurniPerf * 100);
        $clsPerf  = $scostMed < 4 ? 'ok' : ($scostMed <= 5 ? 'warn' : 'bad');
    }
} catch (PDOException $e) {}

/* Label turno */
$labelN = [1 => 'Mattino', 2 => 'Sera'];
$orarioN = [
    1 => $mInizio . ' – ' . $mFine,
    2 => $sInizio . ' – ' . $sFine,
];
$nomiGiorni     = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
$nomiGiorniFull = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
$nomiMesi       = nomi_mesi();

$assegnatoAme = $assegnazioneOggi && (int)$assegnazioneOggi['operatore_id'] === $uid;
$labelTurnoOggi = $nCorrente ? ($labelN[$nCorrente] . ' ' . $orarioN[$nCorrente]) : null;
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/dashboard.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <div>
    <strong>Ciao, <?= $h($user['nome'] ?: $user['username']) ?></strong>
    <span class="topbar-sub"><?= $h($nomiGiorniFull[(int)date('w', strtotime($oggi))]) ?>, <?= (int)date('j') ?> <?= $h($nomiMesi[(int)date('n')]) ?></span>
  </div>
</header>

<?php if (isset($_GET['ok'])): ?><div class="ok">Turno avviato. Buon lavoro!</div><?php endif; ?>

<div class="dash-page">

  <!-- ===== HERO: turno corrente ===== -->
  <?php if (!is_responsabile()): ?>
  <div class="dash-hero">
    <?php if ($nCorrente !== null): ?>
      <div class="dash-hero-info">
        <span class="dash-hero-label">Turno corrente</span>
        <span class="dash-hero-turno"><?= $h($labelTurnoOggi) ?></span>
        <?php if ($giaIniziato): ?>
          <span class="dash-hero-stato ok-text">
            Avviato alle <?= $h(date('H:i', strtotime($turnoGiornaliero['iniziato_il']))) ?>
          </span>
        <?php elseif ($assegnatoAme): ?>
          <span class="dash-hero-stato ok-text">Sei assegnato a questo turno</span>
        <?php elseif ($assegnazioneOggi): ?>
          <span class="dash-hero-stato warn-text">Assegnato a <?= $h($assegnazioneOggi['nome']) ?></span>
        <?php else: ?>
          <span class="dash-hero-stato muted-text">Nessun operatore assegnato</span>
        <?php endif; ?>
      </div>
      <div class="dash-hero-actions">
        <?php if ($giaIniziato): ?>
          <a href="<?= base_url('cassa/giornaliero.php') ?>" class="btn-dash-cassa">Vai alla cassa &rarr;</a>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="inizia">
            <input type="hidden" name="data"   value="<?= $h($oggi) ?>">
            <input type="hidden" name="numero" value="<?= (int)$nCorrente ?>">
            <button type="submit" class="btn-dash-inizia">Inizia turno &amp; vai alla cassa</button>
          </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="dash-hero-info">
        <span class="dash-hero-label">Avvio manuale</span>
        <span class="dash-hero-turno">Scegli turno da avviare</span>
        <span class="dash-hero-stato muted-text">
          M <?= $h($mInizio) ?>&ndash;<?= $h($mFine) ?> &nbsp;&middot;&nbsp; S <?= $h($sInizio) ?>&ndash;<?= $h($sFine) ?>
        </span>
      </div>
      <div class="dash-hero-actions" style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="post">
          <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="inizia">
          <input type="hidden" name="data"   value="<?= $h($oggi) ?>">
          <input type="hidden" name="numero" value="1">
          <button type="submit" class="btn-dash-cassa">Mattino</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="inizia">
          <input type="hidden" name="data"   value="<?= $h($oggi) ?>">
          <input type="hidden" name="numero" value="2">
          <button type="submit" class="btn-dash-inizia">Sera</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="dash-grid">

    <!-- ===== Stipendio del mese ===== -->
    <section class="dash-card">
      <h2 class="dash-card-title">Stipendio — <?= $h($nomiMesi[(int)date('n')]) ?> <?= date('Y') ?></h2>
      <div class="dash-earn-row">
        <div class="dash-earn-item">
          <span class="dash-earn-lbl">Guadagnato</span>
          <span class="dash-earn-val"><?= $h($nv($guadagnatoMese)) ?> €</span>
          <span class="dash-earn-sub"><?= count(array_filter((array)$turniMese, fn($t) => $t['data'] <= $oggi)) ?> turni effettuati</span>
        </div>
        <div class="dash-earn-item">
          <span class="dash-earn-lbl">Previsto</span>
          <span class="dash-earn-val dash-earn-muted"><?= $h($nv($previstoMese)) ?> €</span>
          <span class="dash-earn-sub"><?= count(array_filter((array)$turniMese, fn($t) => $t['data'] > $oggi)) ?> turni futuri</span>
        </div>
        <div class="dash-earn-item">
          <span class="dash-earn-lbl">Totale mese</span>
          <span class="dash-earn-val dash-earn-count"><?= $h($nv($totaleMese)) ?> €</span>
          <span class="dash-earn-sub"><?= count($turniMese) ?> turni</span>
        </div>
      </div>
      <?php if ($guadagnato > 0 || $previsto > 0): ?>
      <p class="dash-earn-storico">Ultimi 3 mesi: <strong><?= $h($nv($guadagnato)) ?> €</strong> guadagnati · <strong><?= $h($nv($previsto)) ?> €</strong> in turni futuri</p>
      <?php endif; ?>
      <a href="<?= base_url('sala/turni.php') ?>" class="dash-card-link">Vedi calendario turni &rarr;</a>
    </section>

    <!-- ===== Prossimi turni ===== -->
    <section class="dash-card">
      <h2 class="dash-card-title">Prossimi turni</h2>
      <?php if ($prossimi): ?>
      <div class="recent-list">
        <?php foreach ($prossimi as $pt):
            $n = (int)$pt['numero'];
            $d = strtotime($pt['data']);
        ?>
        <div class="recent-row">
          <span class="recent-date"><?= $h(date('d/m', $d)) ?></span>
          <span class="dash-dow"><?= $nomiGiorni[(int)date('w', $d)] ?></span>
          <span class="tp-tipo-badge tp-tipo-<?= $n === 1 ? 'matt' : 'sera' ?>"><?= $labelN[$n] ?></span>
          <span class="tp-earn tp-earn-preview"><?= $h($nv($pt['prezzo'])) ?> €</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="ticket-empty">Nessun turno programmato.</p>
      <?php endif; ?>
      <a href="<?= base_url('sala/turni.php') ?>" class="dash-card-link">Turni &rarr;</a>
    </section>

    <!-- ===== Accesso rapido ===== -->
    <section class="dash-card dash-quicklinks">
      <h2 class="dash-card-title">Accesso rapido</h2>
      <div class="dash-ql-grid">
        <a href="<?= base_url('cassa/giornaliero.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          <span>Cassa</span>
        </a>
        <a href="<?= base_url('sala/turni.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
          <span>Turni</span>
        </a>
        <a href="<?= base_url('sala/awp.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
          <span>AWP</span>
        </a>
        <a href="<?= base_url('sala/ticket.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg>
          <span>Assistenze</span>
        </a>
        <a href="<?= base_url('sala/prestiti.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M12 14h4M12 14l2-2M12 14l2 2"/></svg>
          <span>Prestiti</span>
        </a>
        <a href="<?= base_url('account/profilo.php') ?>" class="dash-ql-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span>Profilo</span>
        </a>
      </div>
    </section>

  </div><!-- /.dash-grid -->

  <?php if ($nTurniPerf > 0): ?>
  <section class="dash-card dash-perf-card">
    <h2 class="dash-card-title">Le mie performance · ultimi 30 gg</h2>
    <div class="dash-perf-row">
      <div class="dash-perf-metric">
        <span class="dash-perf-val dp-<?= $clsPerf ?>">€ <?= number_format($scostMed, 2, ',', '.') ?></span>
        <span class="dash-perf-lbl">scostamento medio</span>
      </div>
      <div class="dash-perf-metric">
        <span class="dash-perf-val <?= $pctOk >= 90 ? 'dp-ok' : ($pctOk >= 70 ? 'dp-warn' : 'dp-bad') ?>"><?= $pctOk ?>%</span>
        <span class="dash-perf-lbl">turni ok (&lt; €4)</span>
      </div>
      <div class="dash-perf-metric">
        <span class="dash-perf-val dash-perf-n"><?= $nTurniPerf ?></span>
        <span class="dash-perf-lbl">turni registrati</span>
      </div>
    </div>
    <?php if ($nTurniPerf > 2): ?>
    <div class="dp-bars" aria-label="Grafico scostamenti per turno">
      <?php foreach (array_reverse($miePerf) as $p):
        $bc = $p['errore'] < 4 ? 'ok' : ($p['errore'] <= 5 ? 'warn' : 'bad');
        $bh = min(40, max(4, (int)round($p['errore'] * 5)));
      ?>
      <span class="dp-bar dp-bar-<?= $bc ?>" style="height:<?= $bh ?>px"
            title="<?= date('d/m', strtotime($p['data'])) ?> · €<?= number_format($p['errore'], 2, ',', '.') ?>"></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</div><!-- /.dash-page -->
</body></html>
