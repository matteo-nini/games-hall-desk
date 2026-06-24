<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_responsabile();
$pdo  = db();
$cfg  = config();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

$oggi      = date('Y-m-d');
$riepilogo = riepilogo_giornata($pdo, $oggi);
$nomiMesi  = nomi_mesi();

/* Stato giornata oggi */
$st = $pdo->prepare('SELECT stato FROM giornate WHERE data=?'); $st->execute([$oggi]);
$statoOggi = $st->fetchColumn() ?: null;

/* Mese corrente: giorni operativi + incasso */
$meseInizio = date('Y-m-01');
$meseFine   = date('Y-m-t');
$st = $pdo->prepare('
    SELECT COUNT(DISTINCT g.id) AS giorni, COALESCE(SUM(s.importo), 0) AS incasso
    FROM giornate g
    LEFT JOIN turni t ON t.giornata_id = g.id
    LEFT JOIN scassettamenti s ON s.turno_id = t.id
    WHERE g.data BETWEEN ? AND ?
');
$st->execute([$meseInizio, $meseFine]);
$mese = $st->fetch();

/* Ultime 10 giornate con incasso VLT */
$ultime = $pdo->query('
    SELECT g.data, g.stato, COALESCE(SUM(s.importo), 0) AS incasso
    FROM giornate g
    LEFT JOIN turni t ON t.giornata_id = g.id
    LEFT JOIN scassettamenti s ON s.turno_id = t.id
    GROUP BY g.id, g.data, g.stato
    ORDER BY g.data DESC
    LIMIT 10
')->fetchAll();

/* Charts: ultimi 30 giorni */
$chartStart = date('Y-m-d', strtotime('-29 days'));
$st = $pdo->prepare('
    SELECT g.data, COALESCE(SUM(s.importo),0) AS inc
    FROM giornate g
    LEFT JOIN turni t ON t.giornata_id=g.id
    LEFT JOIN scassettamenti s ON s.turno_id=t.id
    WHERE g.data BETWEEN ? AND ?
    GROUP BY g.data
');
$st->execute([$chartStart, date('Y-m-d')]);
$dayMap = [];
foreach ($st as $row) $dayMap[$row['data']] = (float)$row['inc'];
$chart30 = ['labels' => [], 'data' => []];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chart30['labels'][] = date('d/m', strtotime($d));
    $chart30['data'][]   = $dayMap[$d] ?? 0;
}

/* Charts: ultimi 6 mesi */
$nomiMesiBr = ['','Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
$st = $pdo->query("
    SELECT DATE_FORMAT(g.data,'%Y-%m') AS mese, COALESCE(SUM(s.importo),0) AS inc
    FROM giornate g
    LEFT JOIN turni t ON t.giornata_id=g.id
    LEFT JOIN scassettamenti s ON s.turno_id=t.id
    WHERE g.data >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mese ORDER BY mese
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
    <span class="topbar-sub"><?= (int)date('j') ?> <?= $h($nomiMesi[(int)date('n')]) ?> <?= date('Y') ?></span>
  </div>
</header>

<div class="dash-page">

  <!-- ===== Hero: giornata oggi ===== -->
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

  <!-- ===== KPI mese corrente ===== -->
  <div class="calcrow dash-kpi">
    <div class="mini">
      <div class="l">Incasso VLT oggi</div>
      <div class="v"><?= eur($riepilogo['incasso_vlt']) ?></div>
    </div>
    <div class="mini">
      <div class="l">Versamento oggi</div>
      <div class="v"><?= eur($riepilogo['versamento']) ?></div>
    </div>
    <div class="mini">
      <div class="l">Incasso VLT mese</div>
      <div class="v"><?= eur((float)$mese['incasso']) ?></div>
    </div>
    <div class="mini">
      <div class="l">Giorni operativi mese</div>
      <div class="v"><?= (int)$mese['giorni'] ?></div>
    </div>
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
</div>


<script>
var GP_30D=<?= json_encode($chart30, JSON_UNESCAPED_UNICODE) ?>;
var GP_6M=<?= json_encode($chart6m, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"
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
  Chart.defaults.font.family='inherit';
  Chart.defaults.font.size=11;
  Chart.defaults.color=muted;
  Chart.defaults.plugins.legend.display=false;
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
</body></html>
