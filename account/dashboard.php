<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv   = fn(float $v) => number_format($v, 2, ',', '.');
$uid  = (int)$user['id'];
$oggi = date('Y-m-d');

/* ====================================================================
   POST
   ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    /* Operatore: avvia turno → vai in cassa */
    if ($az === 'inizia' && !is_responsabile() && !is_revisore()) {
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

    /* Revisore + Responsabile: conferma ritiro versamento */
    if ($az === 'conferma_ritiro' && (is_revisore() || is_responsabile())) {
        $gid = (int)($_POST['giornata_id'] ?? 0);
        if ($gid > 0) {
            $stG = $pdo->prepare('SELECT id FROM giornate WHERE id=? AND stato="chiusa"');
            $stG->execute([$gid]);
            if ($stG->fetch()) {
                $stV = $pdo->prepare('
                    SELECT
                      COALESCE((SELECT SUM(s.importo) FROM scassettamenti s JOIN turni t ON t.id=s.turno_id WHERE t.giornata_id=?),0)
                      - COALESCE((SELECT SUM(t2.bancomat) FROM turni t2 WHERE t2.giornata_id=?),0)
                      - COALESCE((SELECT SUM(tk.importo) FROM ticket tk JOIN turni t3 ON t3.id=tk.turno_id WHERE t3.giornata_id=?),0)
                      AS versamento
                ');
                $stV->execute([$gid, $gid, $gid]);
                $importo = (float)$stV->fetchColumn();
                $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
                $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
                try {
                    $pdo->prepare('INSERT INTO versamenti_confermati (giornata_id,confermato_da,importo_dichiarato,ip,user_agent) VALUES (?,?,?,?,?)')
                        ->execute([$gid, (int)$user['id'], $importo, $ip, $ua]);
                    audit('versamento_confermato', 'giornate', $gid, "importo=$importo ip=$ip");
                    header('Location: dashboard.php?ok=confermato'); exit;
                } catch (\PDOException $e) {
                    // Duplicate key: giornata già confermata da qualcun altro
                    if ($e->getCode() === '23000') {
                        header('Location: dashboard.php?err=gia_confermato'); exit;
                    }
                    throw $e;
                }
            }
        }
        header('Location: dashboard.php'); exit;
    }

    header('Location: dashboard.php'); exit;
}
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — <?= $h($cfg['nome_sala'] ?? 'Cassa Sala') ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<?php if (is_revisore()): ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/revisore.css') ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/dashboard.css') ?>">
<?php endif; ?>
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<?php /* ================================================================
          RESPONSABILE
          ============================================================== */
if (is_responsabile()):
    $nomiMesi  = nomi_mesi();
    $riepilogo = riepilogo_giornata($pdo, $oggi);

    $st = $pdo->prepare('SELECT stato FROM giornate WHERE data=?');
    $st->execute([$oggi]);
    $statoOggi = $st->fetchColumn() ?: null;

    $st = $pdo->prepare('
        SELECT COUNT(DISTINCT g.id) AS giorni, COALESCE(SUM(s.importo), 0) AS incasso
        FROM giornate g LEFT JOIN turni t ON t.giornata_id=g.id LEFT JOIN scassettamenti s ON s.turno_id=t.id
        WHERE g.data BETWEEN ? AND ?
    ');
    $st->execute([date('Y-m-01'), date('Y-m-t')]);
    $mese = $st->fetch();

    $ultime = $pdo->query('
        SELECT g.data, g.stato, COALESCE(SUM(s.importo), 0) AS incasso
        FROM giornate g LEFT JOIN turni t ON t.giornata_id=g.id LEFT JOIN scassettamenti s ON s.turno_id=t.id
        GROUP BY g.id, g.data, g.stato ORDER BY g.data DESC LIMIT 10
    ')->fetchAll();

    $versAmmda = $pdo->query('
        SELECT g.id, g.data,
               COALESCE((SELECT SUM(s.importo) FROM scassettamenti s JOIN turni t ON t.id=s.turno_id WHERE t.giornata_id=g.id),0)
               - COALESCE((SELECT SUM(t2.bancomat) FROM turni t2 WHERE t2.giornata_id=g.id),0)
               - COALESCE((SELECT SUM(tk.importo) FROM ticket tk JOIN turni t3 ON t3.id=tk.turno_id WHERE t3.giornata_id=g.id),0)
               AS versamento
        FROM giornate g LEFT JOIN versamenti_confermati vc ON vc.giornata_id=g.id
        WHERE g.stato="chiusa" AND vc.id IS NULL ORDER BY g.data DESC LIMIT 30
    ')->fetchAll();

    $versConf = $pdo->query('
        SELECT g.data, vc.importo_dichiarato, vc.confermato_il,
               COALESCE(NULLIF(u.nome,""),u.username) AS nome_conf
        FROM versamenti_confermati vc
        JOIN giornate g ON g.id=vc.giornata_id JOIN utenti u ON u.id=vc.confermato_da
        ORDER BY g.data DESC LIMIT 30
    ')->fetchAll();

    $st = $pdo->prepare('
        SELECT g.data, COALESCE(SUM(s.importo),0) AS inc
        FROM giornate g LEFT JOIN turni t ON t.giornata_id=g.id LEFT JOIN scassettamenti s ON s.turno_id=t.id
        WHERE g.data BETWEEN ? AND ? GROUP BY g.data
    ');
    $st->execute([date('Y-m-d', strtotime('-29 days')), date('Y-m-d')]);
    $dayMap = [];
    foreach ($st as $row) $dayMap[$row['data']] = (float)$row['inc'];
    $chart30 = ['labels' => [], 'data' => []];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $chart30['labels'][] = date('d/m', strtotime($d));
        $chart30['data'][]   = $dayMap[$d] ?? 0;
    }

    $nomiMesiBr = ['', 'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];
    $st = $pdo->query("
        SELECT DATE_FORMAT(g.data,'%Y-%m') AS mese, COALESCE(SUM(s.importo),0) AS inc
        FROM giornate g LEFT JOIN turni t ON t.giornata_id=g.id LEFT JOIN scassettamenti s ON s.turno_id=t.id
        WHERE g.data >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY mese ORDER BY mese
    ");
    $mesiMap = [];
    foreach ($st as $row) $mesiMap[$row['mese']] = (float)$row['inc'];
    $chart6m = ['labels' => [], 'data' => []];
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-{$i} months"));
        [$y, $mo] = explode('-', $m);
        $chart6m['labels'][] = $nomiMesiBr[(int)$mo] . " '" . substr($y, 2);
        $chart6m['data'][]   = $mesiMap[$m] ?? 0;
    }

    // Derived table JOINs sostituiscono 4 subquery correlate per riga (issue P-02).
    $stOp = $pdo->prepare("
        SELECT t.id, t.operatore_id, t.fondo_cassa, t.monete, t.bancomat, t.differenze, t.ii_cassa, t.rientri,
               COALESCE(NULLIF(u.nome,''), u.username) AS op_nome,
               COALESCE(c_agg.val, 0)  AS contanti,
               COALESCE(r_agg.val, 0)  AS refill,
               COALESCE(s_agg.val, 0)  AS scass,
               COALESCE(tk_agg.val, 0) AS ticket
        FROM turni t
        JOIN giornate g ON g.id = t.giornata_id
        LEFT JOIN utenti u ON u.id = t.operatore_id
        LEFT JOIN (SELECT turno_id, SUM(taglio*pezzi) val FROM contanti       GROUP BY turno_id) c_agg  ON c_agg.turno_id  = t.id
        LEFT JOIN (SELECT turno_id, SUM(euro)          val FROM refill_awp    GROUP BY turno_id) r_agg  ON r_agg.turno_id  = t.id
        LEFT JOIN (SELECT turno_id, SUM(importo)       val FROM scassettamenti GROUP BY turno_id) s_agg ON s_agg.turno_id  = t.id
        LEFT JOIN (SELECT turno_id, SUM(importo)       val FROM ticket        GROUP BY turno_id) tk_agg ON tk_agg.turno_id = t.id
        WHERE g.data >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND t.operatore_id IS NOT NULL
    ");
    $stOp->execute();
    $opStats = [];
    foreach ($stOp as $row) {
        $calc = calcola_turno((array)$row);
        $oid  = (int)$row['operatore_id'];
        $scost = abs($calc['errore']);
        if (!isset($opStats[$oid])) $opStats[$oid] = ['nome' => $row['op_nome'], 'turni' => 0, 'scost_tot' => 0.0, 'scost_max' => 0.0, 'ok' => 0];
        $opStats[$oid]['turni']++;
        $opStats[$oid]['scost_tot'] += $scost;
        if ($scost > $opStats[$oid]['scost_max']) $opStats[$oid]['scost_max'] = $scost;
        if ($scost < 4) $opStats[$oid]['ok']++;
    }
    uasort($opStats, fn($a, $b) => $b['turni'] <=> $a['turni']);

    $salariMese = []; $salariMeseOk = false;
    try {
        $stSal = $pdo->prepare("
            SELECT tp.operatore_id, COALESCE(NULLIF(u.nome,''), u.username) AS nome,
                   SUM(CASE WHEN tp.data <= CURDATE() THEN pt.prezzo ELSE 0 END) AS guadagnato,
                   SUM(CASE WHEN tp.data  > CURDATE() THEN pt.prezzo ELSE 0 END) AS previsto,
                   COUNT(*) AS n_turni
            FROM turni_programmati tp JOIN utenti u ON u.id=tp.operatore_id
            JOIN prezzi_turni pt ON pt.nome = CASE WHEN tp.numero=1 THEN 'mattino' ELSE 'sera' END
            WHERE tp.data BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND LAST_DAY(CURDATE())
            GROUP BY tp.operatore_id, u.nome, u.username ORDER BY nome
        ");
        $stSal->execute();
        $salariMese   = $stSal->fetchAll();
        $salariMeseOk = true;
    } catch (PDOException) {}
?>

<header class="topbar">
  <div>
    <strong>Ciao, <?= $h($user['nome'] ?: $user['username']) ?></strong>
    <span class="topbar-sub"><?= (int)date('j') ?> <?= $h($nomiMesi[(int)date('n')]) ?> <?= date('Y') ?></span>
  </div>
  <div class="topbar-actions">
    <span class="live-badge" id="live-badge" title="Aggiornamento dati in tempo reale">
      <span class="live-dot" id="live-dot"></span>live
    </span>
  </div>
</header>

<div class="dash-page">
  <div class="dash-hero">
    <div class="dash-hero-info">
      <span class="dash-hero-label">Giornata di oggi</span>
      <?php if ($statoOggi === 'chiusa'): ?>
        <span class="dash-hero-turno">Chiusa</span>
        <span class="dash-hero-stato muted-text">Incasso VLT: <?= eur($riepilogo['incasso_vlt']) ?></span>
      <?php elseif ($statoOggi === 'aperta'): ?>
        <span class="dash-hero-turno">In corso</span>
        <span class="dash-hero-stato ok-text">Incasso VLT: <?= eur($riepilogo['incasso_vlt']) ?></span>
      <?php else: ?>
        <span class="dash-hero-turno">Non iniziata</span>
        <span class="dash-hero-stato muted-text">Nessun dato ancora per oggi</span>
      <?php endif; ?>
    </div>
    <div class="dash-hero-actions">
      <a href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $oggi ?>" class="btn-dash-cassa">Apri cassa oggi &rarr;</a>
    </div>
  </div>

  <div class="calcrow dash-kpi">
    <div class="mini"><div class="l">Incasso VLT oggi</div><div class="v" id="kpi-incasso-oggi"><?= eur($riepilogo['incasso_vlt']) ?></div></div>
    <div class="mini"><div class="l">Versamento oggi</div><div class="v" id="kpi-versamento-oggi"><?= eur($riepilogo['versamento']) ?></div></div>
    <div class="mini"><div class="l">Incasso VLT mese</div><div class="v" id="kpi-incasso-mese"><?= eur((float)$mese['incasso']) ?></div></div>
    <div class="mini"><div class="l">Giorni operativi mese</div><div class="v" id="kpi-giorni-mese"><?= (int)$mese['giorni'] ?></div></div>
  </div>

  <div class="resp-cols">
    <div class="resp-charts">
      <section class="dash-card">
        <h2 class="dash-card-title">Incasso VLT · ultimi 30 giorni</h2>
        <div class="dash-chart-wrap"><canvas id="chart-30d"></canvas></div>
      </section>
      <section class="dash-card">
        <h2 class="dash-card-title">Andamento mensile · ultimi 6 mesi</h2>
        <div class="dash-chart-wrap"><canvas id="chart-6m"></canvas></div>
      </section>
    </div>
    <section class="dash-card">
      <h2 class="dash-card-title">Ultime giornate</h2>
      <?php if ($ultime): ?>
      <div class="recent-list">
        <?php foreach ($ultime as $g): ?>
        <a class="recent-row" href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($g['data']) ?>">
          <span class="recent-date"><?= $h(date('d/m/Y', strtotime($g['data']))) ?></span>
          <span class="badge <?= $g['stato'] === 'chiusa' ? 'closed' : 'open' ?>"><?= $g['stato'] === 'chiusa' ? 'Chiusa' : 'Aperta' ?></span>
          <span class="tp-earn tp-earn-preview"><?= eur((float)$g['incasso']) ?></span>
          <span class="recent-caret">›</span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="ticket-empty">Nessuna giornata registrata.</p>
      <?php endif; ?>
      <a href="<?= base_url('cassa/settimanale.php') ?>" class="dash-card-link">Riepilogo settimanale &rarr;</a>
    </section>
  </div>

  <?php if (!empty($opStats)): ?>
  <section class="dash-card dash-op-stats">
    <h2 class="dash-card-title">Statistiche operatori · ultimi 30 giorni</h2>
    <div class="op-table-wrap">
      <table class="op-table">
        <thead>
          <tr><th class="op-th-ava" aria-hidden="true"></th><th>Operatore</th><th class="rt">Turni</th><th class="rt">Scost. medio</th><th class="rt">Scost. max</th><th class="rt">Turni ok</th></tr>
        </thead>
        <tbody>
          <?php foreach ($opStats as $op):
            $med = $op['turni'] > 0 ? $op['scost_tot'] / $op['turni'] : 0;
            $pct = $op['turni'] > 0 ? round($op['ok'] / $op['turni'] * 100) : 0;
            $cls = $med < 4 ? 'ok' : ($med <= 5 ? 'warn' : 'bad');
          ?>
          <tr>
            <td class="op-td-ava"><div class="op-ava" aria-hidden="true" style="<?= avatar_style($op['nome']) ?>"><?= $h(avatar_initials($op['nome'])) ?></div></td>
            <td class="op-nome"><?= $h($op['nome']) ?></td>
            <td class="rt"><?= $op['turni'] ?></td>
            <td class="rt"><span class="op-scost <?= $cls ?>"><?= eur($med) ?></span></td>
            <td class="rt muted-text"><?= eur($op['scost_max']) ?></td>
            <td class="rt">
              <span class="op-ok-bar" title="<?= $op['ok'] ?>/<?= $op['turni'] ?> turni con scostamento &lt; €4">
                <span class="op-ok-fill" style="width:<?= $pct ?>%"></span>
              </span>
              <span class="op-ok-pct"><?= $pct ?>%</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($salariMeseOk && !empty($salariMese)):
    $meseLabel = $nomiMesi[(int)date('n')] . ' ' . date('Y');
    $totGuad   = array_sum(array_column($salariMese, 'guadagnato'));
    $totPrev   = array_sum(array_column($salariMese, 'previsto'));
  ?>
  <section class="dash-card dash-op-stats" style="margin-top:14px">
    <h2 class="dash-card-title">Stipendi operatori — <?= $h($meseLabel) ?></h2>
    <div class="op-table-wrap">
      <table class="op-table">
        <thead>
          <tr><th class="op-th-ava" aria-hidden="true"></th><th>Operatore</th><th class="rt">Turni</th><th class="rt">Guadagnato</th><th class="rt">Previsto</th><th class="rt">Totale mese</th></tr>
        </thead>
        <tbody>
          <?php foreach ($salariMese as $sal):
            $guad = (float)$sal['guadagnato']; $prev = (float)$sal['previsto'];
          ?>
          <tr>
            <td class="op-td-ava"><div class="op-ava" aria-hidden="true" style="<?= avatar_style($sal['nome']) ?>"><?= $h(avatar_initials($sal['nome'])) ?></div></td>
            <td class="op-nome"><?= $h($sal['nome']) ?></td>
            <td class="rt"><?= (int)$sal['n_turni'] ?></td>
            <td class="rt"><?= eur($guad) ?></td>
            <td class="rt muted-text"><?= eur($prev) ?></td>
            <td class="rt"><strong><?= eur($guad + $prev) ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="op-sal-total">
            <td colspan="3" class="muted-text" style="font-size:11px;padding:10px 14px">Totale</td>
            <td class="rt"><?= eur($totGuad) ?></td>
            <td class="rt muted-text"><?= eur($totPrev) ?></td>
            <td class="rt"><strong><?= eur($totGuad + $totPrev) ?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <section class="dash-card" style="margin-top:14px">
    <h2 class="dash-card-title">
      Versamenti
      <?php if ($versAmmda): ?><span class="badge open" style="font-size:11px;margin-left:6px;vertical-align:middle"><?= count($versAmmda) ?> da ritirare</span><?php endif; ?>
    </h2>
    <?php if ($versAmmda): ?>
    <p class="dash-card-sub" style="margin:0 0 8px;font-size:12px;color:var(--red)">Giornate chiuse in attesa di conferma ritiro</p>
    <div class="recent-list" style="margin-bottom:16px">
      <?php foreach ($versAmmda as $vr): ?>
      <a class="recent-row" href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($vr['data']) ?>">
        <span class="recent-date"><?= $h(date('d/m/Y', strtotime($vr['data']))) ?></span>
        <span class="badge open">Da ritirare</span>
        <span class="tp-earn tp-earn-preview" style="color:var(--red)"><?= eur(arrotonda_versamento((float)$vr['versamento'])) ?></span>
        <span class="recent-caret">›</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="ticket-empty" style="color:var(--green)">Nessun versamento in sospeso.</p>
    <?php endif; ?>
    <?php if ($versConf): ?>
    <p class="dash-card-sub" style="margin:0 0 8px;font-size:12px;color:var(--muted)">Versamenti recenti confermati</p>
    <div class="recent-list">
      <?php foreach ($versConf as $vc): ?>
      <a class="recent-row" href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($vc['data']) ?>">
        <span class="recent-date"><?= $h(date('d/m/Y', strtotime($vc['data']))) ?></span>
        <span class="badge closed">Ritirato</span>
        <span class="tp-earn tp-earn-preview"><?= eur((float)$vc['importo_dichiarato']) ?></span>
        <span class="recent-caret">›</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</div>

<script>
var GP_30D=<?= json_encode($chart30, JSON_UNESCAPED_UNICODE) ?>;
var GP_6M=<?= json_encode($chart6m, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"
        integrity="sha384-e6nUZLBkQ86NJ6TVVKAeSaK8jWa3NhkYWZFomE39AvDbQWeie9PlQqM3pmYW5d1g"
        crossorigin="anonymous"
        onerror="document.querySelectorAll('.dash-chart-wrap').forEach(function(w){w.innerHTML='<p style=\'padding:8px;font-size:12px;color:#999\'>Grafici non disponibili</p>';})"></script>
<script>
if(typeof Chart!=='undefined')(function(){
  var cs=getComputedStyle(document.documentElement);
  var accent=(cs.getPropertyValue('--accent')||'').trim()||'#3b5bdb';
  var border=(cs.getPropertyValue('--border')||'').trim()||'#e4e8f0';
  var muted=(cs.getPropertyValue('--muted')||'').trim()||'#69748a';
  function hexRgba(h,a){
    var m=h.match(/^#([0-9a-f]{3,6})$/i);if(!m)return h;
    var hex=m[1].length===3?m[1].split('').map(function(c){return c+c;}).join(''):m[1];
    return 'rgba('+parseInt(hex.slice(0,2),16)+','+parseInt(hex.slice(2,4),16)+','+parseInt(hex.slice(4,6),16)+','+a+')';
  }
  Chart.defaults.font.family='inherit';Chart.defaults.font.size=11;
  Chart.defaults.color=muted;Chart.defaults.plugins.legend.display=false;
  var grid={color:border};
  var yAxis={grid:grid,beginAtZero:true,ticks:{callback:function(v){return '€ '+v.toLocaleString('it-IT');}}};
  var ttLabel=function(ctx){return '€ '+ctx.parsed.y.toLocaleString('it-IT',{minimumFractionDigits:2});};
  try{
    new Chart(document.getElementById('chart-30d'),{
      type:'bar',
      data:{labels:GP_30D.labels,datasets:[{data:GP_30D.data,backgroundColor:hexRgba(accent,.15),borderColor:accent,borderWidth:1,borderRadius:3}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:ttLabel}}},scales:{x:{grid:grid,ticks:{maxRotation:0,autoSkip:true,maxTicksLimit:10}},y:yAxis}}
    });
    new Chart(document.getElementById('chart-6m'),{
      type:'line',
      data:{labels:GP_6M.labels,datasets:[{data:GP_6M.data,borderColor:accent,backgroundColor:hexRgba(accent,.12),fill:true,tension:.3,pointBackgroundColor:accent,pointRadius:4,pointHoverRadius:6}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:ttLabel}}},scales:{x:{grid:grid},y:yAxis}}
    });
  }catch(e){console.error('Chart init:',e);}
})();
</script>
<script>
(function(){
  var INTERVAL=30000;
  var liveUrl='<?= base_url('account/responsabile_live.php') ?>';
  var dot=document.getElementById('live-dot');
  var fmt=function(n){return new Intl.NumberFormat('it-IT',{style:'currency',currency:'EUR'}).format(n);};
  function set(id,val){var el=document.getElementById(id);if(el)el.textContent=val;}
  function pulse(ok){if(!dot)return;dot.classList.remove('live-ok','live-err');void dot.offsetWidth;dot.classList.add(ok?'live-ok':'live-err');}
  function poll(){
    fetch(liveUrl,{cache:'no-store'})
      .then(function(r){return r.ok?r.json():Promise.reject(r.status);})
      .then(function(d){set('kpi-incasso-oggi',fmt(d.incasso_vlt));set('kpi-versamento-oggi',fmt(d.versamento));set('kpi-incasso-mese',fmt(d.incasso_mese));set('kpi-giorni-mese',d.giorni_mese);pulse(true);})
      .catch(function(){pulse(false);});
  }
  setTimeout(poll,INTERVAL);setInterval(poll,INTERVAL);
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof GP_Tour === 'undefined') return;
  GP_Tour.init([
    { selector: '.dash-hero',     title: 'Stato della giornata',       body: 'Qui vedi se la giornata è aperta o chiusa e l\'incasso VLT del giorno. Il link diretto porta alla cassa senza cercare la data.' },
    { selector: '.dash-kpi',      title: 'KPI giorno e mese',          body: 'Incasso, versamento e totale mese in tempo reale. Si aggiornano ogni 30 secondi in automatico — il badge live lampeggia ad ogni fetch.' },
    { selector: '#chart-30d',     title: 'Grafici incasso',            body: 'Le barre mostrano l\'incasso VLT degli ultimi 30 giorni; il grafico lineare l\'andamento mensile degli ultimi 6 mesi. Passa il cursore per i valori esatti.' },
    { selector: '.dash-op-stats', title: 'Performance operatori',      body: 'Turni compilati, scostamento medio e percentuale di turni in quadratura (scostamento &lt; 4 €) negli ultimi 30 giorni per ogni operatore.' },
    { selector: '.live-badge',    title: 'Aggiornamento live',         body: 'Il pallino verde lampeggia ad ogni refresh riuscito. Se diventa grigio, la connessione al server è interrotta.' },
  ]);
});
</script>

<?php /* ================================================================
          REVISORE
          ============================================================== */
elseif (is_revisore()):
    $nomiMesi = nomi_mesi();

    $pending = $pdo->query('
        SELECT g.id, g.data, g.chiusa_il,
               COALESCE(NULLIF(u.nome,""),u.username) AS chiusa_da_nome,
               COALESCE((SELECT SUM(s.importo) FROM scassettamenti s JOIN turni t ON t.id=s.turno_id WHERE t.giornata_id=g.id),0)
               - COALESCE((SELECT SUM(t2.bancomat) FROM turni t2 WHERE t2.giornata_id=g.id),0)
               - COALESCE((SELECT SUM(tk.importo) FROM ticket tk JOIN turni t3 ON t3.id=tk.turno_id WHERE t3.giornata_id=g.id),0)
               AS versamento
        FROM giornate g
        LEFT JOIN versamenti_confermati vc ON vc.giornata_id=g.id
        LEFT JOIN utenti u ON u.id=g.chiusa_da
        WHERE g.stato="chiusa" AND vc.id IS NULL
        ORDER BY g.data DESC LIMIT 60
    ')->fetchAll();

    $confermati = $pdo->query('
        SELECT g.data, vc.importo_dichiarato, vc.confermato_il, vc.ip,
               COALESCE(NULLIF(u.nome,""),u.username) AS nome_conf
        FROM versamenti_confermati vc
        JOIN giornate g ON g.id=vc.giornata_id JOIN utenti u ON u.id=vc.confermato_da
        ORDER BY g.data DESC LIMIT 100
    ')->fetchAll();

    $mensile = $pdo->query('
        SELECT DATE_FORMAT(g.data,"%Y-%m") AS mese,
               COUNT(g.id) AS n_giorni, COUNT(vc.id) AS n_conf,
               COALESCE(SUM(vc.importo_dichiarato),0) AS tot_conf
        FROM giornate g LEFT JOIN versamenti_confermati vc ON vc.giornata_id=g.id
        WHERE g.stato="chiusa" AND g.data >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY mese ORDER BY mese DESC
    ')->fetchAll();

    $meseCurr = date('Y-m');
    $kpiMese  = ['tot_conf' => 0.0, 'n_conf' => 0, 'n_giorni' => 0, 'n_pending' => count($pending)];
    foreach ($mensile as $m) {
        if ($m['mese'] === $meseCurr) {
            $kpiMese['tot_conf'] = (float)$m['tot_conf'];
            $kpiMese['n_conf']   = (int)$m['n_conf'];
            $kpiMese['n_giorni'] = (int)$m['n_giorni'];
            break;
        }
    }
    $fmtMese = fn(string $ym): string => ($nomiMesi[(int)explode('-', $ym)[1]] ?? '') . ' ' . explode('-', $ym)[0];
    $fmtData = fn(string $d): string => date('d/m/Y', strtotime($d));
    $fmtDT   = fn(string $d): string => date('d/m/Y H:i', strtotime($d));
?>

<?php if (isset($_GET['ok'])): ?>
<div class="ok rv-ok">Versamento confermato e registrato.</div>
<?php elseif (($_GET['err'] ?? '') === 'gia_confermato'): ?>
<div class="warn rv-ok">Questa giornata è già stata confermata da un altro utente.</div>
<?php endif; ?>

<main class="rv-main" id="main">
<section class="rv-kpi" aria-label="Riepilogo mese">
  <div class="rv-kpi-card rv-kpi-pending <?= $kpiMese['n_pending'] > 0 ? 'has-pending' : '' ?>">
    <span class="rv-kpi-num"><?= $kpiMese['n_pending'] ?></span>
    <span class="rv-kpi-lbl">Da confermare</span>
  </div>
  <div class="rv-kpi-card">
    <span class="rv-kpi-num"><?= $nv($kpiMese['tot_conf']) ?></span>
    <span class="rv-kpi-lbl">Confermato questo mese <span class="rv-kpi-sub">(€)</span></span>
  </div>
  <div class="rv-kpi-card">
    <span class="rv-kpi-num"><?= $kpiMese['n_conf'] ?> / <?= $kpiMese['n_giorni'] ?></span>
    <span class="rv-kpi-lbl">Giorni confermati questo mese</span>
  </div>
  <div class="rv-kpi-card">
    <span class="rv-kpi-num"><?= $kpiMese['n_giorni'] > 0 ? round($kpiMese['n_conf'] / $kpiMese['n_giorni'] * 100) : 0 ?>%</span>
    <span class="rv-kpi-lbl">Copertura mese corrente</span>
  </div>
</section>

<?php if (!empty($pending)): ?>
<section class="rv-section">
  <h2 class="rv-section-title rv-pending-title">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    Da confermare <span class="rv-count"><?= count($pending) ?></span>
  </h2>
  <div class="rv-table-wrap">
    <table class="rv-table rv-pending-table">
      <thead><tr><th>Data</th><th>Versamento</th><th>Chiusa da</th><th>Ora chiusura</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pending as $p): ?>
        <tr>
          <td class="rv-td-data"><strong><?= $fmtData($p['data']) ?></strong></td>
          <td class="rv-td-vers rv-vers-num">€ <?= $nv((float)$p['versamento']) ?></td>
          <td class="rv-td-op"><?= $h($p['chiusa_da_nome'] ?? '—') ?></td>
          <td class="rv-td-ora"><?= $p['chiusa_il'] ? date('H:i', strtotime($p['chiusa_il'])) : '—' ?></td>
          <td class="rv-td-action">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="azione" value="conferma_ritiro">
              <input type="hidden" name="giornata_id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="rv-btn-conf"
                      onclick="return confirm(<?= json_encode('Confermi il ritiro di € ' . $nv((float)$p['versamento']) . ' del ' . $fmtData($p['data']) . "?\n\nVerranno registrati IP, orario e identità account.") ?>)">
                Conferma ritiro
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php else: ?>
<section class="rv-section rv-all-conf">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
  Tutti i versamenti sono stati confermati.
</section>
<?php endif; ?>

<?php if (!empty($mensile)): ?>
<section class="rv-section">
  <h2 class="rv-section-title">Andamento mensile</h2>
  <div class="rv-table-wrap">
    <table class="rv-table">
      <thead><tr><th>Mese</th><th class="rv-num">Giorni chiusi</th><th class="rv-num">Confermati</th><th class="rv-num">Copertura</th><th class="rv-num">Tot. confermato</th></tr></thead>
      <tbody>
        <?php foreach ($mensile as $m):
          $cov    = $m['n_giorni'] > 0 ? round($m['n_conf'] / $m['n_giorni'] * 100) : 0;
          $covCls = $cov >= 90 ? 'rv-cov-ok' : ($cov >= 50 ? 'rv-cov-warn' : 'rv-cov-bad');
        ?>
        <tr>
          <td><strong><?= $fmtMese($m['mese']) ?></strong></td>
          <td class="rv-num"><?= (int)$m['n_giorni'] ?></td>
          <td class="rv-num"><?= (int)$m['n_conf'] ?></td>
          <td class="rv-num"><span class="rv-cov <?= $covCls ?>"><?= $cov ?>%</span></td>
          <td class="rv-num rv-vers-num">€ <?= $nv((float)$m['tot_conf']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($confermati)): ?>
<section class="rv-section">
  <h2 class="rv-section-title">Storico versamenti confermati</h2>
  <div class="rv-table-wrap">
    <table class="rv-table">
      <thead><tr><th>Data</th><th class="rv-num">Importo</th><th>Confermato da</th><th>Data conferma</th><th class="rv-td-ip">IP</th></tr></thead>
      <tbody>
        <?php foreach ($confermati as $c): ?>
        <tr>
          <td><?= $fmtData($c['data']) ?></td>
          <td class="rv-num rv-vers-num">€ <?= $nv((float)$c['importo_dichiarato']) ?></td>
          <td><?= $h($c['nome_conf']) ?></td>
          <td class="rv-td-dt"><?= $fmtDT($c['confermato_il']) ?></td>
          <td class="rv-td-ip rv-muted"><?= $h($c['ip'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php else: ?>
<section class="rv-section rv-empty">Nessun versamento ancora confermato.</section>
<?php endif; ?>
</main>

<?php /* ================================================================
          OPERATORE
          ============================================================== */
else:
    $nomiMesi       = nomi_mesi();
    $nomiGiorni     = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
    $nomiGiorniFull = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];

    $sett    = get_settings($pdo);
    $mInizio = $sett['turno_mattino_inizio'] ?? '13:00';
    $mFine   = $sett['turno_mattino_fine']   ?? '19:00';
    $sInizio = $sett['turno_sera_inizio']    ?? '19:00';
    $sFine   = $sett['turno_sera_fine']      ?? '01:00';

    $oraFloat = (int)date('G') + (int)date('i') / 60;
    [$mhI, $mmI] = array_map('intval', explode(':', $mInizio));
    [$mhF, $mmF] = array_map('intval', explode(':', $mFine));
    [$shI, $smI] = array_map('intval', explode(':', $sInizio));
    [$shF, $smF] = array_map('intval', explode(':', $sFine));
    $mStart = $mhI + $mmI / 60;
    $mEnd   = $mhF + $mmF / 60;
    $sStart = $shI + $smI / 60;
    $sEnd   = $shF + $smF / 60;
    $seraOltreMezzanotte = $sEnd < $sStart; // es. 19:00-01:00

    $nCorrente = null;
    if ($oraFloat >= $mStart && $oraFloat < $mEnd) {
        $nCorrente = 1;
    } elseif ($seraOltreMezzanotte) {
        if ($oraFloat >= $sStart || $oraFloat < $sEnd) $nCorrente = 2;
    } else {
        if ($oraFloat >= $sStart && $oraFloat < $sEnd) $nCorrente = 2;
    }

    // Se siamo dopo mezzanotte ancora nel turno di sera, la data effettiva del turno è ieri
    $dataCorrente = ($nCorrente === 2 && $seraOltreMezzanotte && $oraFloat < $sEnd)
        ? date('Y-m-d', strtotime('-1 day'))
        : $oggi;

    $assegnazioneOggi = null; $turnoGiornaliero = false; $giaIniziato = false;
    try {
        if ($nCorrente !== null) {
            $st = $pdo->prepare('SELECT tp.operatore_id, COALESCE(NULLIF(u.nome,""),u.username) AS nome FROM turni_programmati tp JOIN utenti u ON u.id=tp.operatore_id WHERE tp.data=? AND tp.numero=?');
            $st->execute([$dataCorrente, $nCorrente]);
            $assegnazioneOggi = $st->fetch() ?: null;
            $st2 = $pdo->prepare('SELECT t.operatore_id, t.iniziato_il FROM turni t JOIN giornate g ON g.id=t.giornata_id WHERE g.data=? AND t.numero=?');
            $st2->execute([$dataCorrente, $nCorrente]);
            $turnoGiornaliero = $st2->fetch() ?: false;
            $giaIniziato = $turnoGiornaliero && !empty($turnoGiornaliero['iniziato_il']) && (int)$turnoGiornaliero['operatore_id'] === $uid;
        }
    } catch (PDOException) {}

    $assegnatoAme   = $assegnazioneOggi !== null && (int)$assegnazioneOggi['operatore_id'] === $uid;
    $labelN         = [1 => 'Mattino', 2 => 'Sera'];
    $orarioN        = [1 => $mInizio . ' – ' . $mFine, 2 => $sInizio . ' – ' . $sFine];
    $labelTurnoOggi = $nCorrente !== null ? ($labelN[$nCorrente] . ' ' . $orarioN[$nCorrente]) : null;

    $guadagnato = 0.0; $previsto = 0.0; $miei_turni = [];
    try {
        $st = $pdo->prepare('SELECT tp.data, tp.numero, pt.prezzo FROM turni_programmati tp JOIN prezzi_turni pt ON pt.nome = CASE WHEN tp.numero=1 THEN "mattino" ELSE "sera" END WHERE tp.operatore_id=? AND tp.data >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) ORDER BY tp.data, tp.numero');
        $st->execute([$uid]);
        $miei_turni = $st->fetchAll();
        foreach ($miei_turni as $mt) {
            if ($mt['data'] <= $oggi) $guadagnato += (float)$mt['prezzo'];
            else                      $previsto   += (float)$mt['prezzo'];
        }
    } catch (PDOException) {}

    $prossimi = array_slice(array_values(array_filter($miei_turni, fn($t) => $t['data'] > $oggi)), 0, 6);
    $mese1    = date('Y-m-01');
    $mese2    = date('Y-m-t');
    $turniMese      = array_filter($miei_turni, fn($t) => $t['data'] >= $mese1 && $t['data'] <= $mese2);
    $guadagnatoMese = 0.0; $previstoMese = 0.0;
    foreach ($turniMese as $mt) {
        if ($mt['data'] <= $oggi) $guadagnatoMese += (float)$mt['prezzo'];
        else                      $previstoMese   += (float)$mt['prezzo'];
    }
    $totaleMese = $guadagnatoMese + $previstoMese;

    $miePerf = []; $scostMed = null; $pctOk = null; $nTurniPerf = 0; $clsPerf = '';
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
            $calc    = calcola_turno((array)$row);
            $miePerf[] = ['data' => $row['data'], 'errore' => abs($calc['errore'])];
        }
        $nTurniPerf = count($miePerf);
        if ($nTurniPerf > 0) {
            $nOkPerf  = count(array_filter($miePerf, fn($p) => $p['errore'] < 4));
            $scostMed = array_sum(array_column($miePerf, 'errore')) / $nTurniPerf;
            $pctOk    = (int)round($nOkPerf / $nTurniPerf * 100);
            $clsPerf  = $scostMed < 4 ? 'ok' : ($scostMed <= 5 ? 'warn' : 'bad');
        }
    } catch (PDOException) {}

    $versAmmdaOp = $pdo->query('
        SELECT g.id, g.data,
               COALESCE((SELECT SUM(s.importo) FROM scassettamenti s JOIN turni t ON t.id=s.turno_id WHERE t.giornata_id=g.id),0)
               - COALESCE((SELECT SUM(t2.bancomat) FROM turni t2 WHERE t2.giornata_id=g.id),0)
               - COALESCE((SELECT SUM(tk.importo) FROM ticket tk JOIN turni t3 ON t3.id=tk.turno_id WHERE t3.giornata_id=g.id),0)
               AS versamento
        FROM giornate g LEFT JOIN versamenti_confermati vc ON vc.giornata_id=g.id
        WHERE g.stato="chiusa" AND vc.id IS NULL ORDER BY g.data DESC LIMIT 20
    ')->fetchAll();

    $versConfOp = $pdo->query('
        SELECT g.data, vc.importo_dichiarato, vc.confermato_il,
               COALESCE(NULLIF(u.nome,""),u.username) AS nome_conf
        FROM versamenti_confermati vc
        JOIN giornate g ON g.id=vc.giornata_id JOIN utenti u ON u.id=vc.confermato_da
        ORDER BY g.data DESC LIMIT 20
    ')->fetchAll();
?>

<header class="topbar">
  <div>
    <strong>Ciao, <?= $h($user['nome'] ?: $user['username']) ?></strong>
    <span class="topbar-sub"><?= $h($nomiGiorniFull[(int)date('w', strtotime($oggi))]) ?>, <?= (int)date('j') ?> <?= $h($nomiMesi[(int)date('n')]) ?></span>
  </div>
</header>

<?php if (isset($_GET['ok'])): ?><div class="ok">Turno avviato. Buon lavoro!</div><?php endif; ?>

<div class="dash-page">
  <div class="dash-hero">
    <?php if ($nCorrente !== null): ?>
    <div class="dash-hero-info">
      <span class="dash-hero-label">Turno corrente</span>
      <span class="dash-hero-turno"><?= $h($labelTurnoOggi) ?></span>
      <?php if ($giaIniziato): ?>
        <span class="dash-hero-stato ok-text">Avviato alle <?= $h(date('H:i', strtotime((string)$turnoGiornaliero['iniziato_il']))) ?></span>
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
        <a href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($dataCorrente) ?>" class="btn-dash-cassa">Vai alla cassa &rarr;</a>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
          <input type="hidden" name="azione" value="inizia">
          <input type="hidden" name="data"   value="<?= $h($dataCorrente) ?>">
          <input type="hidden" name="numero" value="<?= (int)$nCorrente ?>">
          <button type="submit" class="btn-dash-inizia">Inizia turno &amp; vai alla cassa</button>
        </form>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="dash-hero-info">
      <span class="dash-hero-label">Avvio manuale</span>
      <span class="dash-hero-turno">Scegli turno da avviare</span>
      <span class="dash-hero-stato muted-text">M <?= $h($mInizio) ?>&ndash;<?= $h($mFine) ?> &nbsp;&middot;&nbsp; S <?= $h($sInizio) ?>&ndash;<?= $h($sFine) ?></span>
    </div>
    <div class="dash-hero-actions" style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ([1 => 'Mattino', 2 => 'Sera'] as $n => $lbl): ?>
      <form method="post">
        <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
        <input type="hidden" name="azione" value="inizia">
        <input type="hidden" name="data"   value="<?= $h($oggi) ?>">
        <input type="hidden" name="numero" value="<?= $n ?>">
        <button type="submit" class="btn-dash-cassa"><?= $lbl ?></button>
      </form>
      <?php endforeach; ?>
      <?php /* Il blocco avvio manuale usa sempre $oggi: è fuori orario turno, l'operatore sceglie esplicitamente */ ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="dash-grid">
    <section class="dash-card">
      <h2 class="dash-card-title">Stipendio — <?= $h($nomiMesi[(int)date('n')]) ?> <?= date('Y') ?></h2>
      <div class="dash-earn-row">
        <div class="dash-earn-item">
          <span class="dash-earn-lbl">Guadagnato</span>
          <span class="dash-earn-val"><?= $h($nv($guadagnatoMese)) ?> €</span>
          <span class="dash-earn-sub"><?= count(array_filter($turniMese, fn($t) => $t['data'] <= $oggi)) ?> turni effettuati</span>
        </div>
        <div class="dash-earn-item">
          <span class="dash-earn-lbl">Previsto</span>
          <span class="dash-earn-val dash-earn-muted"><?= $h($nv($previstoMese)) ?> €</span>
          <span class="dash-earn-sub"><?= count(array_filter($turniMese, fn($t) => $t['data'] > $oggi)) ?> turni futuri</span>
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

    <section class="dash-card">
      <h2 class="dash-card-title">Prossimi turni</h2>
      <?php if ($prossimi): ?>
      <div class="recent-list">
        <?php foreach ($prossimi as $pt): $n = (int)$pt['numero']; $d = strtotime($pt['data']); ?>
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

    <section class="dash-card dash-quicklinks">
      <h2 class="dash-card-title">Accesso rapido</h2>
      <div class="dash-ql-grid">
        <a href="<?= base_url('cassa/giornaliero.php') ?>" class="dash-ql-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Cassa</span></a>
        <a href="<?= base_url('sala/turni.php') ?>" class="dash-ql-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg><span>Turni</span></a>
        <a href="<?= base_url('sala/awp.php') ?>" class="dash-ql-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg><span>AWP</span></a>
        <a href="<?= base_url('sala/ticket.php') ?>" class="dash-ql-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg><span>Assistenze</span></a>
        <a href="<?= base_url('sala/prestiti.php') ?>" class="dash-ql-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M12 14h4M12 14l2-2M12 14l2 2"/></svg><span>Prestiti</span></a>
        <a href="<?= base_url('account/profilo.php') ?>" class="dash-ql-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profilo</span></a>
      </div>
    </section>
  </div>

  <?php if ($nTurniPerf > 0): ?>
  <section class="dash-card dash-perf-card">
    <h2 class="dash-card-title">Le mie performance · ultimi 30 gg</h2>
    <div class="dash-perf-row">
      <div class="dash-perf-metric">
        <span class="dash-perf-val dp-<?= $clsPerf ?>">€ <?= number_format((float)$scostMed, 2, ',', '.') ?></span>
        <span class="dash-perf-lbl">scostamento medio</span>
      </div>
      <div class="dash-perf-metric">
        <span class="dash-perf-val <?= (int)$pctOk >= 90 ? 'dp-ok' : ((int)$pctOk >= 70 ? 'dp-warn' : 'dp-bad') ?>"><?= (int)$pctOk ?>%</span>
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

  <?php if ($versAmmdaOp || $versConfOp): ?>
  <section class="dash-card" style="margin-top:14px">
    <h2 class="dash-card-title">
      Versamenti
      <?php if ($versAmmdaOp): ?><span class="badge open" style="font-size:11px;margin-left:6px;vertical-align:middle"><?= count($versAmmdaOp) ?> da ritirare</span><?php endif; ?>
    </h2>
    <?php if ($versAmmdaOp): ?>
    <p class="dash-card-sub" style="margin:0 0 8px;font-size:12px;color:var(--red)">Giornate chiuse in attesa di conferma ritiro</p>
    <div class="recent-list" style="margin-bottom:16px">
      <?php foreach ($versAmmdaOp as $vr): ?>
      <a class="recent-row" href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($vr['data']) ?>">
        <span class="recent-date"><?= $h(date('d/m/Y', strtotime($vr['data']))) ?></span>
        <span class="badge open">Da ritirare</span>
        <span class="tp-earn tp-earn-preview" style="color:var(--red)"><?= eur(arrotonda_versamento((float)$vr['versamento'])) ?></span>
        <span class="recent-caret">›</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="ticket-empty" style="color:var(--green)">Nessun versamento in sospeso.</p>
    <?php endif; ?>
    <?php if ($versConfOp): ?>
    <p class="dash-card-sub" style="margin:0 0 8px;font-size:12px;color:var(--muted)">Versamenti recenti confermati</p>
    <div class="recent-list">
      <?php foreach ($versConfOp as $vc): ?>
      <a class="recent-row" href="<?= base_url('cassa/giornaliero.php') ?>?data=<?= $h($vc['data']) ?>">
        <span class="recent-date"><?= $h(date('d/m/Y', strtotime($vc['data']))) ?></span>
        <span class="badge closed">Ritirato</span>
        <span class="tp-earn tp-earn-preview"><?= eur((float)$vc['importo_dichiarato']) ?></span>
        <span class="recent-caret">›</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>

<?php endif; /* fine blocchi ruolo */ ?>
</body></html>
