<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
if (!is_revisore() && !is_responsabile()) render_403('Accesso riservato ai revisori.');
$pdo = db();
$cfg = config();
$h   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv  = fn(float $v) => number_format($v, 2, ',', '.');

/* ---- POST: conferma ritiro versamento ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['azione'] ?? '') === 'conferma_ritiro') {
        $gid = (int)($_POST['giornata_id'] ?? 0);
        if ($gid > 0) {
            $stG = $pdo->prepare('SELECT id, data FROM giornate WHERE id=? AND stato="chiusa"');
            $stG->execute([$gid]);
            $gg = $stG->fetch();
            if ($gg) {
                $stV = $pdo->prepare('
                    SELECT
                      COALESCE((SELECT SUM(s.importo) FROM scassettamenti s JOIN turni t ON t.id=s.turno_id WHERE t.giornata_id=?),0)
                      - COALESCE((SELECT SUM(t2.bancomat) FROM turni t2 WHERE t2.giornata_id=?),0)
                      - COALESCE((SELECT SUM(tk.importo) FROM ticket tk JOIN turni t3 ON t3.id=tk.turno_id WHERE t3.giornata_id=?),0)
                      AS versamento
                ');
                $stV->execute([$gid,$gid,$gid]);
                $importo = (float)$stV->fetchColumn();
                try {
                    $pdo->prepare('INSERT INTO versamenti_confermati (giornata_id,confermato_da,importo_dichiarato,ip,user_agent) VALUES (?,?,?,?,?)')
                        ->execute([$gid,(int)$user['id'],$importo,$_SERVER['REMOTE_ADDR']??'',mb_substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);
                    audit('versamento_confermato','giornate',$gid,"importo=$importo ip=".($_SERVER['REMOTE_ADDR']??''));
                } catch (Throwable) {}
            }
        }
        header('Location: revisore.php?ok=confermato'); exit;
    }
}

/* ---- Giornate da confermare ---- */
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
    ORDER BY g.data DESC
    LIMIT 60
')->fetchAll();

/* ---- Storico confermati (ultimi 100) ---- */
$confermati = $pdo->query('
    SELECT g.data, vc.importo_dichiarato, vc.confermato_il, vc.ip,
           COALESCE(NULLIF(u.nome,""),u.username) AS nome_conf
    FROM versamenti_confermati vc
    JOIN giornate g ON g.id=vc.giornata_id
    JOIN utenti u ON u.id=vc.confermato_da
    ORDER BY g.data DESC
    LIMIT 100
')->fetchAll();

/* ---- Andamento mensile ultimi 6 mesi ---- */
$mensile = $pdo->query('
    SELECT DATE_FORMAT(g.data,"%Y-%m") AS mese,
           COUNT(g.id)  AS n_giorni,
           COUNT(vc.id) AS n_conf,
           COALESCE(SUM(vc.importo_dichiarato),0) AS tot_conf
    FROM giornate g
    LEFT JOIN versamenti_confermati vc ON vc.giornata_id=g.id
    WHERE g.stato="chiusa" AND g.data >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mese
    ORDER BY mese DESC
')->fetchAll();

/* ---- KPI mese corrente ---- */
$meseCurr = date('Y-m');
$kpiMese = ['tot_conf'=>0.0,'n_conf'=>0,'n_giorni'=>0,'n_pending'=>count($pending)];
foreach ($mensile as $m) {
    if ($m['mese'] === $meseCurr) {
        $kpiMese['tot_conf']  = (float)$m['tot_conf'];
        $kpiMese['n_conf']    = (int)$m['n_conf'];
        $kpiMese['n_giorni']  = (int)$m['n_giorni'];
        break;
    }
}

$nomiMesi = nomi_mesi();
$fmtMese = function(string $ym) use ($nomiMesi): string {
    [$y,$m] = explode('-', $ym);
    return ($nomiMesi[(int)$m] ?? $m) . ' ' . $y;
};
$fmtData = fn(string $d) => date('d/m/Y', strtotime($d));
$fmtDT   = fn(string $d) => date('d/m/Y H:i', strtotime($d));

top_menu('Dashboard revisore');
?>
<link rel="stylesheet" href="<?= asset_url('assets/css/revisore.css') ?>">
</head><body>
<?php nav('revisore.php'); ?>
<main class="rv-main" id="main">

<?php if (isset($_GET['ok'])): ?>
<div class="ok rv-ok">Versamento confermato e registrato.</div>
<?php endif; ?>

<!-- KPI -->
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

<!-- Da confermare -->
<?php if (!empty($pending)): ?>
<section class="rv-section">
  <h2 class="rv-section-title rv-pending-title">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    Da confermare
    <span class="rv-count"><?= count($pending) ?></span>
  </h2>
  <div class="rv-table-wrap">
    <table class="rv-table rv-pending-table">
      <thead>
        <tr>
          <th>Data</th>
          <th>Versamento</th>
          <th>Chiusa da</th>
          <th>Ora chiusura</th>
          <th></th>
        </tr>
      </thead>
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
                      onclick="return confirm('Confermi il ritiro di € <?= $nv((float)$p['versamento']) ?> del <?= $fmtData($p['data']) ?>?\n\nVerranno registrati IP, orario e identità account.')">
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

<!-- Andamento mensile -->
<?php if (!empty($mensile)): ?>
<section class="rv-section">
  <h2 class="rv-section-title">Andamento mensile</h2>
  <div class="rv-table-wrap">
    <table class="rv-table">
      <thead>
        <tr>
          <th>Mese</th>
          <th class="rv-num">Giorni chiusi</th>
          <th class="rv-num">Confermati</th>
          <th class="rv-num">Copertura</th>
          <th class="rv-num">Tot. confermato</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($mensile as $m):
          $cov = $m['n_giorni'] > 0 ? round($m['n_conf'] / $m['n_giorni'] * 100) : 0;
          $covClass = $cov >= 90 ? 'rv-cov-ok' : ($cov >= 50 ? 'rv-cov-warn' : 'rv-cov-bad');
        ?>
        <tr>
          <td><strong><?= $fmtMese($m['mese']) ?></strong></td>
          <td class="rv-num"><?= (int)$m['n_giorni'] ?></td>
          <td class="rv-num"><?= (int)$m['n_conf'] ?></td>
          <td class="rv-num"><span class="rv-cov <?= $covClass ?>"><?= $cov ?>%</span></td>
          <td class="rv-num rv-vers-num">€ <?= $nv((float)$m['tot_conf']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- Storico confermati -->
<?php if (!empty($confermati)): ?>
<section class="rv-section">
  <h2 class="rv-section-title">Storico versamenti confermati</h2>
  <div class="rv-table-wrap">
    <table class="rv-table">
      <thead>
        <tr>
          <th>Data</th>
          <th class="rv-num">Importo</th>
          <th>Confermato da</th>
          <th>Data conferma</th>
          <th class="rv-td-ip">IP</th>
        </tr>
      </thead>
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
<section class="rv-section rv-empty">
  Nessun versamento ancora confermato.
</section>
<?php endif; ?>

</main>
<?php foot_scripts(); ?>
</body></html>
