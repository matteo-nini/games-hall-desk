<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
require_not_revisore();
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$nv   = fn($v) => number_format((float)$v, 2, ',', '.');

/* =========================================================
   POST
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $az = $_POST['azione'] ?? '';

    if ($az === 'prezzi' && is_responsabile()) {
        $pm = is_numeric($_POST['prezzo_mattino'] ?? '') ? abs((float)$_POST['prezzo_mattino']) : null;
        $ps = is_numeric($_POST['prezzo_sera']   ?? '') ? abs((float)$_POST['prezzo_sera'])   : null;
        if ($pm !== null) $pdo->prepare('UPDATE prezzi_turni SET prezzo=? WHERE nome="mattino"')->execute([$pm]);
        if ($ps !== null) $pdo->prepare('UPDATE prezzi_turni SET prezzo=? WHERE nome="sera"')->execute([$ps]);
        audit('prezzi_turni_aggiornati', null, null, "mattino=$pm sera=$ps");
        header('Location: turni.php?ok=1'); exit;
    }

    /* Operatore: aggiunge se stesso a uno slot libero (se permesso abilitato) */
    if ($az === 'programma' && !is_responsabile()) {
        $perm = setting($pdo, 'operatori_modifica_turni', '1');
        if ($perm === '1') {
            $data = $_POST['data'] ?? '';
            $n    = (int)($_POST['numero'] ?? 0);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && in_array($n, [1,2])) {
                /* assegna solo se slot ancora libero */
                $check = $pdo->prepare('SELECT id FROM turni_programmati WHERE data=? AND numero=?');
                $check->execute([$data, $n]);
                if (!$check->fetch()) {
                    $pdo->prepare(
                        'INSERT INTO turni_programmati (data, numero, operatore_id, creato_da) VALUES (?,?,?,?)'
                    )->execute([$data, $n, $user['id'], $user['id']]);
                    audit('turno_self_assign', 'turni_programmati', null, "data=$data n=$n op={$user['id']}");
                }
            }
        }
        header('Location: turni.php?ok=1'); exit;
    }

    if ($az === 'programma' && is_responsabile()) {
        $data = $_POST['data'] ?? '';
        $n    = (int)($_POST['numero'] ?? 0);
        $oid  = (int)($_POST['operatore_id'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !in_array($n, [1,2]) || $oid <= 0) {
            header('Location: turni.php?err=1'); exit;
        }
        $pdo->prepare(
            'INSERT INTO turni_programmati (data, numero, operatore_id, creato_da)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE operatore_id=VALUES(operatore_id), creato_da=VALUES(creato_da), creato_il=NOW()'
        )->execute([$data, $n, $oid, $user['id']]);
        audit('turno_programmato', 'turni_programmati', null, "data=$data n=$n op=$oid");
        header('Location: turni.php?ok=1'); exit;
    }

    if ($az === 'rimuovi' && is_responsabile()) {
        $data = $_POST['data'] ?? '';
        $n    = (int)($_POST['numero'] ?? 0);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && in_array($n, [1,2])) {
            $pdo->prepare('DELETE FROM turni_programmati WHERE data=? AND numero=?')->execute([$data, $n]);
            audit('turno_rimosso', 'turni_programmati', null, "data=$data n=$n");
        }
        header('Location: turni.php?ok=1'); exit;
    }

    if ($az === 'inizia' && !is_responsabile()) {
        $n    = (int)($_POST['numero'] ?? 0);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !in_array($n, [1,2])) {
            header('Location: turni.php?err=1'); exit;
        }
        $g = ensure_giornata($pdo, $data);
        $t = ensure_turno($pdo, (int)$g['id'], $n);
        $pdo->prepare('UPDATE turni SET operatore_id=?, iniziato_il=NOW() WHERE id=?')
            ->execute([(int)$user['id'], (int)$t['id']]);
        audit('inizio_turno', 'turni', (int)$t['id'], "turno=$n data=$data");
        header('Location: turni.php?ok=1'); exit;
    }

    header('Location: turni.php'); exit;
}

/* =========================================================
   GET — parametri mese/anno
   ========================================================= */
$oggi = date('Y-m-d');
$anno = (int)($_GET['anno'] ?? date('Y'));
$mese = (int)($_GET['mese'] ?? date('n'));
if ($mese < 1)  { $mese = 12; $anno--; }
if ($mese > 12) { $mese = 1;  $anno++; }
$anno = max(2020, min(2040, $anno));

$prevMese = $mese === 1  ? ['anno' => $anno-1, 'mese' => 12] : ['anno' => $anno, 'mese' => $mese-1];
$nextMese = $mese === 12 ? ['anno' => $anno+1, 'mese' => 1]  : ['anno' => $anno, 'mese' => $mese+1];
$primoGiorno  = sprintf('%04d-%02d-01', $anno, $mese);
$ultimoGiorno = date('Y-m-t', strtotime($primoGiorno));
$giorniMese   = (int)date('t', strtotime($primoGiorno));
$nomiMesi     = nomi_mesi();
$sett         = get_settings($pdo);
$turns        = get_turns($sett);
$mobTurniEdit = ($sett['mobile_turni_edit'] ?? '0') === '1';

/* =========================================================
   GET — verifica migration (tutte e tre le dipendenze)
   ========================================================= */
$migrationOk = false;
try {
    $pdo->query('SELECT 1 FROM turni_programmati LIMIT 0');
    $pdo->query('SELECT 1 FROM prezzi_turni LIMIT 0');
    $pdo->query('SELECT iniziato_il FROM turni LIMIT 0');
    $migrationOk = true;
} catch (PDOException $e) {
    /* una o più tabelle/colonne mancanti */
}

/* =========================================================
   GET — dati (solo se migration ok)
   ========================================================= */
$uid              = (int)$user['id'];
$calendario       = [];
$operatori        = [];
$prezzoMattino    = 60.0;
$prezzoSera       = 70.0;
$miei_turni       = [];
$guadagnato       = 0.0;
$previsto         = 0.0;
$nCorrente        = null;
$turniOggi        = [];
$opPuoModificare  = true; /* permesso operatore di aggiungere se stesso */

if ($migrationOk) {

    /* Turni del mese visualizzato */
    $st = $pdo->prepare(
        'SELECT tp.data, tp.numero, tp.operatore_id,
                COALESCE(NULLIF(u.nome,""), u.username) AS nome
         FROM turni_programmati tp
         JOIN utenti u ON u.id = tp.operatore_id
         WHERE tp.data BETWEEN ? AND ?'
    );
    $st->execute([$primoGiorno, $ultimoGiorno]);
    foreach ($st as $r) $calendario[$r['data']][(int)$r['numero']] = $r;

    /* Operatori attivi (per form assegnazione responsabile) */
    $operatori = $pdo->query(
        'SELECT id, COALESCE(NULLIF(nome,""), username) AS nome
         FROM utenti WHERE attivo=1 ORDER BY nome'
    )->fetchAll();

    /* Prezzi correnti */
    foreach ($pdo->query('SELECT nome, prezzo FROM prezzi_turni') as $r) {
        if ($r['nome'] === 'mattino') $prezzoMattino = (float)$r['prezzo'];
        elseif ($r['nome'] === 'sera') $prezzoSera   = (float)$r['prezzo'];
    }

    /* I turni programmati dell'utente corrente (ultimi 3 mesi + futuri) */
    $st = $pdo->prepare(
        'SELECT tp.data, tp.numero, pt.prezzo
         FROM turni_programmati tp
         JOIN prezzi_turni pt ON pt.nome = CASE WHEN tp.numero=1 THEN "mattino" ELSE "sera" END
         WHERE tp.operatore_id = ?
           AND tp.data >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
         ORDER BY tp.data, tp.numero'
    );
    $st->execute([$uid]);
    $miei_turni = $st->fetchAll();

    foreach ($miei_turni as $mt) {
        if ($mt['data'] <= $oggi) $guadagnato += (float)$mt['prezzo'];
        else                      $previsto   += (float)$mt['prezzo'];
    }

    /* Turno corrente in base all'orario */
    $ora = (int)date('G');
    $nCorrente = ($ora >= 13 && $ora < 19) ? 1
               : (($ora >= 19 || $ora < 2) ? 2 : null);

    /* Assegnazioni di oggi (indipendenti dal mese visualizzato) */
    $st = $pdo->prepare(
        'SELECT tp.numero, tp.operatore_id,
                COALESCE(NULLIF(u.nome,""), u.username) AS nome
         FROM turni_programmati tp
         JOIN utenti u ON u.id = tp.operatore_id
         WHERE tp.data = ?'
    );
    $st->execute([$oggi]);
    foreach ($st as $r) $turniOggi[(int)$r['numero']] = $r;

    /* Permesso operatori da impostazioni */
    $opPuoModificare = setting($pdo, 'operatori_modifica_turni', '1') === '1';

    /* Operatori riepilogo (usato in CSV e print) */
    $stOp = $pdo->prepare(
        'SELECT COALESCE(NULLIF(u.nome,""), u.username) AS nome,
                SUM(CASE WHEN tp.numero=1 THEN 1 ELSE 0 END) AS n_mattino,
                SUM(CASE WHEN tp.numero=2 THEN 1 ELSE 0 END) AS n_sera
         FROM turni_programmati tp
         JOIN utenti u ON u.id = tp.operatore_id
         WHERE tp.data BETWEEN ? AND ?
         GROUP BY u.id, u.nome, u.username
         ORDER BY nome'
    );
    $stOp->execute([$primoGiorno, $ultimoGiorno]);
    $opStats = $stOp->fetchAll();

    /* CSV export mensile */
    if (($_GET['export'] ?? '') === 'csv') {
        $fname = "turni_{$anno}_{$mese}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        echo "\xEF\xBB\xBF";
        $st = $pdo->prepare(
            'SELECT tp.data, tp.numero, COALESCE(NULLIF(u.nome,""), u.username) AS nome
             FROM turni_programmati tp
             JOIN utenti u ON u.id = tp.operatore_id
             WHERE tp.data BETWEEN ? AND ?
             ORDER BY tp.data, tp.numero'
        );
        $st->execute([$primoGiorno, $ultimoGiorno]);
        $byDay = [];
        foreach ($st as $r) $byDay[$r['data']][(int)$r['numero']] = $r['nome'];
        $f = fopen('php://output', 'w');
        $csvHead = ['Data'];
        foreach ($turns as $n => $t) $csvHead[] = $t['nome'];
        fputcsv($f, $csvHead, ';');
        for ($g = 1; $g <= $giorniMese; $g++) {
            $dc = sprintf('%04d-%02d-%02d', $anno, $mese, $g);
            $row = [$dc];
            foreach (array_keys($turns) as $n) $row[] = $byDay[$dc][$n] ?? '';
            fputcsv($f, $row, ';');
        }
        fputcsv($f, [], ';');
        fputcsv($f, ['Riepilogo operatori'], ';');
        $n1 = $turns[1]['nome'] ?? 'Mattino'; $n2 = $turns[2]['nome'] ?? 'Sera';
        fputcsv($f, ['Operatore', 'Turni '.$n1, 'Turni '.$n2, 'Tot. '.$n1.' (€)', 'Tot. '.$n2.' (€)', 'Totale (€)'], ';');
        foreach ($opStats as $op) {
            $totM = (float)$op['n_mattino'] * $prezzoMattino;
            $totS = (float)$op['n_sera']    * $prezzoSera;
            fputcsv($f, [
                $op['nome'],
                (int)$op['n_mattino'],
                (int)$op['n_sera'],
                number_format($totM, 2, ',', '.'),
                number_format($totS, 2, ',', '.'),
                number_format($totM + $totS, 2, ',', '.'),
            ], ';');
        }
        fclose($f); exit;
    }

    /* Print export mensile */
    if (($_GET['export'] ?? '') === 'print') {
        $st = $pdo->prepare(
            'SELECT tp.data, tp.numero, COALESCE(NULLIF(u.nome,""), u.username) AS nome
             FROM turni_programmati tp
             JOIN utenti u ON u.id = tp.operatore_id
             WHERE tp.data BETWEEN ? AND ?
             ORDER BY tp.data, tp.numero'
        );
        $st->execute([$primoGiorno, $ultimoGiorno]);
        $byDay = [];
        foreach ($st as $r) $byDay[$r['data']][(int)$r['numero']] = $r['nome'];
        $h2 = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $titolo = $nomiMesi[$mese] . ' ' . $anno;
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="it"><head>';
        echo '<meta charset="utf-8"><title>Turni &mdash; ' . $h2($titolo) . '</title>';
        echo '<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,Arial,sans-serif;font-size:12px;color:#111;background:#fff;padding:20px}
h1{font-size:15px;font-weight:700;margin-bottom:16px}
.tp-export-wrap{display:flex;gap:32px;align-items:flex-start}
table{border-collapse:collapse;min-width:220px}
th,td{border:1px solid #c0c0c0;padding:5px 9px;text-align:left;white-space:nowrap}
th{background:#f0f3ff;font-weight:700;font-size:11px}
td.num{text-align:right}
.op-table th:first-child{min-width:120px}
.op-total td{font-weight:700;background:#f8f9ff;border-top:2px solid #7b93e0}
tr:nth-child(even) td{background:#fafbff}
.op-total td{background:#eef0fb}
@media print{body{padding:8px}@page{margin:12mm}}
</style></head><body>';
        echo '<h1>Turni &mdash; ' . $h2($titolo) . '</h1>';
        echo '<div class="tp-export-wrap">';

        /* Tabella giornaliera */
        echo '<table><thead><tr><th>Data</th>';
        foreach ($turns as $t) echo '<th>' . $h2($t['nome']) . '</th>';
        echo '</tr></thead><tbody>';
        for ($g = 1; $g <= $giorniMese; $g++) {
            $dc  = sprintf('%04d-%02d-%02d', $anno, $mese, $g);
            $dow = ['Lun','Mar','Mer','Gio','Ven','Sab','Dom'][(int)date('N', strtotime($dc)) - 1];
            echo '<tr><td>' . $h2(date('d/m', strtotime($dc))) . ' <span style="color:#666">' . $dow . '</span></td>';
            foreach (array_keys($turns) as $n) echo '<td>' . $h2($byDay[$dc][$n] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        /* Tabella operatori */
        $totAllM = 0.0; $totAllS = 0.0;
        $pn1 = $turns[1]['nome'] ?? 'Mattino'; $pn2 = $turns[2]['nome'] ?? 'Sera';
        echo '<table class="op-table"><thead><tr>';
        echo '<th>Operatore</th><th class="num">' . $h2($pn1) . '</th><th class="num">' . $h2($pn2) . '</th>';
        echo '<th class="num">Tot. ' . $h2($pn1) . '</th><th class="num">Tot. ' . $h2($pn2) . '</th><th class="num">Totale</th>';
        echo '</tr></thead><tbody>';
        foreach ($opStats as $op) {
            $totM    = (float)$op['n_mattino'] * $prezzoMattino;
            $totS    = (float)$op['n_sera']    * $prezzoSera;
            $totAllM += $totM; $totAllS += $totS;
            echo '<tr>';
            echo '<td>' . $h2($op['nome']) . '</td>';
            echo '<td class="num">' . (int)$op['n_mattino'] . '</td>';
            echo '<td class="num">' . (int)$op['n_sera'] . '</td>';
            echo '<td class="num">' . number_format($totM, 2, ',', '.') . ' &euro;</td>';
            echo '<td class="num">' . number_format($totS, 2, ',', '.') . ' &euro;</td>';
            echo '<td class="num">' . number_format($totM + $totS, 2, ',', '.') . ' &euro;</td>';
            echo '</tr>';
        }
        echo '<tr class="op-total">';
        echo '<td><strong>Totale</strong></td><td class="num"></td><td class="num"></td>';
        echo '<td class="num"><strong>' . number_format($totAllM, 2, ',', '.') . ' &euro;</strong></td>';
        echo '<td class="num"><strong>' . number_format($totAllS, 2, ',', '.') . ' &euro;</strong></td>';
        echo '<td class="num"><strong>' . number_format($totAllM + $totAllS, 2, ',', '.') . ' &euro;</strong></td>';
        echo '</tr>';
        echo '</tbody></table>';
        echo '</div>';
        echo '<script>window.onload=function(){window.print();}</script>';
        echo '</body></html>';
        exit;
    }

} /* end if migrationOk */

/* =========================================================
   HTML
   ========================================================= */
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Turni — <?= $h($nomiMesi[$mese]) ?> <?= $anno ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/turni.css') ?>">
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<header class="topbar">
  <strong>Turni operatori</strong>
  <div class="tp-month-nav">
    <a href="?anno=<?= $prevMese['anno'] ?>&mese=<?= $prevMese['mese'] ?>" class="tp-cal-arrow" aria-label="Mese precedente">&#9664;</a>
    <span class="tp-cal-title"><?= $h($nomiMesi[$mese]) ?> <?= $anno ?></span>
    <a href="?anno=<?= $nextMese['anno'] ?>&mese=<?= $nextMese['mese'] ?>" class="tp-cal-arrow" aria-label="Mese successivo">&#9654;</a>
  </div>
  <?php if ($migrationOk): ?>
  <div class="topbar-actions">
    <a href="?anno=<?= $anno ?>&mese=<?= $mese ?>&export=csv" class="topbar-action-btn">&#8595; CSV</a>
    <a href="?anno=<?= $anno ?>&mese=<?= $mese ?>&export=print" target="_blank" class="topbar-action-btn">&#128438; Stampa</a>
  </div>
  <?php endif; ?>
</header>

<?php if (isset($_GET['ok'])): ?><div class="ok">Salvato.</div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="warn">Compilare tutti i campi.</div><?php endif; ?>

<?php if (!$migrationOk): ?>
<div class="warn" style="margin:16px 24px;padding:14px 18px;border-radius:var(--r);font-size:13px;line-height:1.5">
  <strong>Setup incompleto.</strong> Eseguire <code>sql/004_turni_programmati.sql</code> sul database per attivare questa funzione.
</div>
<?php else: ?>

<div class="tp-layout">

<!-- ===== COLONNA CALENDARIO ===== -->
<section class="tp-cal-col">

  <div class="tp-cal-grid">
    <?php foreach (['Lun','Mar','Mer','Gio','Ven','Sab','Dom'] as $dow): ?>
    <div class="tp-cal-dow"><?= $dow ?></div>
    <?php endforeach; ?>

    <?php
    $offset = (int)date('N', strtotime($primoGiorno)) - 1;
    for ($i = 0; $i < $offset; $i++): ?>
      <div class="tp-cal-cell tp-cal-empty"></div>
    <?php endfor; ?>

    <?php for ($g = 1; $g <= $giorniMese; $g++):
        $dc      = sprintf('%04d-%02d-%02d', $anno, $mese, $g);
        $isToday = $dc === $oggi;
        $isPast  = $dc < $oggi;
        $slotM   = $calendario[$dc][1] ?? null;
        $slotS   = $calendario[$dc][2] ?? null;
    ?>
    <div class="tp-cal-cell <?= $isToday ? 'tp-oggi' : '' ?> <?= $isPast ? 'tp-passato' : '' ?>" data-date="<?= $h($dc) ?>">
      <div class="tp-cal-day">
        <?= $g ?>
        <?php if ($isToday): ?><span class="tp-oggi-badge">oggi</span><?php endif; ?>
      </div>

      <!-- Slot mattino -->
      <?php $canAddM = is_responsabile() || (!$slotM && $opPuoModificare);
            $tagM = mb_strtoupper(mb_substr($turns[1]['nome'] ?? 'M', 0, 1, 'UTF-8'), 'UTF-8'); ?>
      <div class="tp-slot <?= $slotM ? ($slotM['operatore_id'] == $uid ? 'tp-slot-mine' : 'tp-slot-other') : 'tp-slot-empty' ?>">
        <span class="tp-slot-tag"><?= $tagM ?></span>
        <?php if ($slotM): ?>
          <span class="tp-slot-name"><?= $h($slotM['nome']) ?></span>
          <?php if (is_responsabile()): ?>
          <form method="post" class="tp-del-form">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="rimuovi">
            <input type="hidden" name="data"   value="<?= $h($dc) ?>">
            <input type="hidden" name="numero" value="1">
            <button type="submit" class="tp-slot-del" title="Rimuovi">&times;</button>
          </form>
          <?php endif; ?>
        <?php elseif ($canAddM): ?>
          <?php if (is_responsabile()): ?>
          <button type="button" class="tp-slot-add"
                  data-data="<?= $h($dc) ?>" data-n="1"
                  data-label="<?= $h($turns[1]['nome'] ?? 'Turno 1') ?> <?= $g ?>/<?= $mese ?>">+</button>
          <?php else: ?>
          <form method="post" class="tp-del-form">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="programma">
            <input type="hidden" name="data"   value="<?= $h($dc) ?>">
            <input type="hidden" name="numero" value="1">
            <button type="submit" class="tp-slot-add" title="Aggiungiti">+</button>
          </form>
          <?php endif; ?>
        <?php else: ?>
          <span class="tp-slot-vuoto">—</span>
        <?php endif; ?>
      </div>

      <!-- Slot sera -->
      <?php $canAddS = is_responsabile() || (!$slotS && $opPuoModificare);
            $tagS = mb_strtoupper(mb_substr($turns[2]['nome'] ?? 'S', 0, 1, 'UTF-8'), 'UTF-8'); ?>
      <div class="tp-slot <?= $slotS ? ($slotS['operatore_id'] == $uid ? 'tp-slot-mine' : 'tp-slot-other') : 'tp-slot-empty' ?>">
        <span class="tp-slot-tag"><?= $tagS ?></span>
        <?php if ($slotS): ?>
          <span class="tp-slot-name"><?= $h($slotS['nome']) ?></span>
          <?php if (is_responsabile()): ?>
          <form method="post" class="tp-del-form">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="rimuovi">
            <input type="hidden" name="data"   value="<?= $h($dc) ?>">
            <input type="hidden" name="numero" value="2">
            <button type="submit" class="tp-slot-del" title="Rimuovi">&times;</button>
          </form>
          <?php endif; ?>
        <?php elseif ($canAddS): ?>
          <?php if (is_responsabile()): ?>
          <button type="button" class="tp-slot-add"
                  data-data="<?= $h($dc) ?>" data-n="2"
                  data-label="<?= $h($turns[2]['nome'] ?? 'Turno 2') ?> <?= $g ?>/<?= $mese ?>">+</button>
          <?php else: ?>
          <form method="post" class="tp-del-form">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="azione" value="programma">
            <input type="hidden" name="data"   value="<?= $h($dc) ?>">
            <input type="hidden" name="numero" value="2">
            <button type="submit" class="tp-slot-add" title="Aggiungiti">+</button>
          </form>
          <?php endif; ?>
        <?php else: ?>
          <span class="tp-slot-vuoto">—</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endfor; ?>
  </div>

  <div class="tp-legenda">
    <span class="tp-legenda-item tp-slot-mine">I miei turni</span>
    <span class="tp-legenda-item tp-slot-other">Altri operatori</span>
    <span class="tp-legenda-item tp-slot-empty">Non assegnato</span>
    <span class="tp-legenda-price"><?= $h($turns[1]['nome'] ?? 'Mattino') ?> <?= $h($nv($prezzoMattino)) ?> € · <?= $h($turns[2]['nome'] ?? 'Sera') ?> <?= $h($nv($prezzoSera)) ?> €</span>
  </div>

</section>

<!-- ===== COLONNA DESTRA ===== -->
<aside class="tp-right">

<?php
$labels = [];
foreach ($turns as $n => $t) $labels[$n] = $t['nome'];
$passati = array_values(array_filter($miei_turni, fn($t) => $t['data'] <= $oggi));
$futuri  = array_values(array_filter($miei_turni, fn($t) => $t['data'] >  $oggi));
?>

<!-- Turno corrente (operatori) -->
<?php if (!is_responsabile()):
    $assegnatoA = null;
    if ($nCorrente !== null && isset($turniOggi[$nCorrente])) {
        $assegnatoA = (int)$turniOggi[$nCorrente]['operatore_id'] === $uid
            ? 'me'
            : $turniOggi[$nCorrente]['nome'];
    }
    $labelTurno = null;
    if ($nCorrente !== null && isset($turns[$nCorrente])) {
        $tc = $turns[$nCorrente];
        $labelTurno = $tc['nome'];
        if (!empty($tc['inizio']) || !empty($tc['fine'])) {
            $labelTurno .= ' (' . ($tc['inizio'] ?? '') . ' – ' . ($tc['fine'] ?? '') . ')';
        }
    }
    $turnoGiornaliero = false;
    if ($nCorrente !== null) {
        $stTg = $pdo->prepare(
            'SELECT t.operatore_id, t.iniziato_il,
                    COALESCE(NULLIF(u.nome,""),u.username) AS nomeop
             FROM turni t
             JOIN giornate g ON g.id = t.giornata_id
             LEFT JOIN utenti u ON u.id = t.operatore_id
             WHERE g.data = ? AND t.numero = ?'
        );
        $stTg->execute([$oggi, $nCorrente]);
        $turnoGiornaliero = $stTg->fetch() ?: false;
    }
    $giaIniziato  = $turnoGiornaliero && !empty($turnoGiornaliero['iniziato_il']) && (int)$turnoGiornaliero['operatore_id'] === $uid;
    $altroInCorso = $turnoGiornaliero && !empty($turnoGiornaliero['iniziato_il']) && (int)($turnoGiornaliero['operatore_id'] ?? 0) !== $uid;
?>
<details class="tp-section" <?= $nCorrente !== null ? 'open' : '' ?>>
  <summary class="tp-section-head">Turno corrente</summary>
  <div class="tp-section-body">
    <?php if ($nCorrente !== null): ?>
    <div class="tp-inizia-inner">
      <div class="tp-inizia-info">
        <span class="tp-inizia-turno"><?= $h($labelTurno) ?></span>
        <?php if ($assegnatoA === 'me'): ?>
          <span class="tp-inizia-stato ok-text">Sei assegnato a questo turno</span>
        <?php elseif ($assegnatoA !== null): ?>
          <span class="tp-inizia-stato warn-text">Assegnato a <?= $h($assegnatoA) ?></span>
        <?php else: ?>
          <span class="tp-inizia-stato muted-text">Nessun operatore assegnato</span>
        <?php endif; ?>
      </div>
      <?php if ($giaIniziato): ?>
        <div class="tp-gia-in-corso">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
          Iniziato alle <?= $h(date('H:i', strtotime($turnoGiornaliero['iniziato_il']))) ?>
        </div>
      <?php else: ?>
        <button type="button" class="btn-inizia-turno" id="btn-inizia"
                data-n="<?= (int)$nCorrente ?>"
                data-label="<?= $h($labelTurno) ?>"
                data-assegnato="<?= $h($assegnatoA ?? 'nessuno') ?>"
                data-altro-nome="<?= $h($altroInCorso ? ($turnoGiornaliero['nomeop'] ?? '') : '') ?>"
                data-altro="<?= $altroInCorso ? '1' : '0' ?>">
          Inizia turno
        </button>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <?php
    $orariDesc = implode(' · ', array_map(function($t) {
        $s = $t['nome'];
        if (!empty($t['inizio']) || !empty($t['fine'])) $s .= ' ' . ($t['inizio'] ?? '') . '–' . ($t['fine'] ?? '');
        return $s;
    }, $turns));
    ?>
    <p class="tp-fuori-msg" style="margin:0">Fuori orario turni<br><span class="muted-text" style="font-size:11px"><?= $h($orariDesc) ?></span></p>
    <?php endif; ?>
  </div>
</details>
<dialog id="dlg-inizia" class="tp-dialog">
  <form method="post" id="frm-inizia">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="inizia">
    <input type="hidden" name="data"   value="<?= $h($oggi) ?>">
    <input type="hidden" name="numero" id="dlg-numero" value="">
    <div class="tp-dlg-header"><h2>Conferma inizio turno</h2></div>
    <div class="tp-dlg-body"  id="dlg-body"></div>
    <div class="tp-dlg-footer">
      <button type="button" id="dlg-cancel" class="ghost">Annulla</button>
      <button type="submit" class="btn-inizia-confirm">Conferma e inizia</button>
    </div>
  </form>
</dialog>
<?php endif; /* !is_responsabile */ ?>

<!-- Riepilogo € -->
<details class="tp-section" open>
  <summary class="tp-section-head">Riepilogo €</summary>
  <div class="tp-section-body">
    <div class="tp-summary-totals">
      <div class="tp-stot">
        <span class="tp-stot-lbl">Guadagnato</span>
        <span class="tp-stot-val"><?= $h($nv($guadagnato)) ?> €</span>
        <span class="tp-stot-sub">ultimi 3 mesi</span>
      </div>
      <div class="tp-stot">
        <span class="tp-stot-lbl">Previsto</span>
        <span class="tp-stot-val tp-stot-preview"><?= $h($nv($previsto)) ?> €</span>
        <span class="tp-stot-sub">turni futuri</span>
      </div>
    </div>
  </div>
</details>

<!-- Turni effettuati -->
<details class="tp-section">
  <summary class="tp-section-head">Turni effettuati</summary>
  <div class="tp-section-body">
    <?php if ($passati): ?>
    <div class="recent-list">
    <?php foreach (array_reverse($passati) as $mt):
        $n = (int)$mt['numero'];
        $tc = $turns[$n] ?? null;
        $orariT = $tc && (!empty($tc['inizio']) || !empty($tc['fine'])) ? ($tc['inizio'] ?? '') . '–' . ($tc['fine'] ?? '') : ''; ?>
      <div class="recent-row">
        <span class="recent-date"><?= $h(date('d/m', strtotime($mt['data']))) ?></span>
        <span class="tp-tipo-badge tp-tipo-<?= $n === 1 ? 'matt' : 'sera' ?>"><?= $h($labels[$n] ?? 'Turno '.$n) ?></span>
        <?php if ($orariT): ?><span class="muted-text" style="font-size:11px"><?= $h($orariT) ?></span><?php endif; ?>
        <span class="tp-earn"><?= $h($nv($mt['prezzo'])) ?> €</span>
      </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="ticket-empty" style="font-size:12px;margin:0">Nessun turno negli ultimi 3 mesi.</p>
    <?php endif; ?>
  </div>
</details>

<!-- Prossimi turni -->
<details class="tp-section" open>
  <summary class="tp-section-head">Prossimi turni</summary>
  <div class="tp-section-body">
    <?php if ($futuri): ?>
    <div class="recent-list">
    <?php foreach ($futuri as $mt):
        $n = (int)$mt['numero'];
        $tc = $turns[$n] ?? null;
        $orariT = $tc && (!empty($tc['inizio']) || !empty($tc['fine'])) ? ($tc['inizio'] ?? '') . '–' . ($tc['fine'] ?? '') : ''; ?>
      <div class="recent-row">
        <span class="recent-date"><?= $h(date('d/m', strtotime($mt['data']))) ?></span>
        <span class="tp-tipo-badge tp-tipo-<?= $n === 1 ? 'matt' : 'sera' ?>"><?= $h($labels[$n] ?? 'Turno '.$n) ?></span>
        <?php if ($orariT): ?><span class="muted-text" style="font-size:11px"><?= $h($orariT) ?></span><?php endif; ?>
        <span class="tp-earn tp-earn-preview"><?= $h($nv($mt['prezzo'])) ?> €</span>
      </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="ticket-empty" style="font-size:12px;margin:0">Nessun turno programmato.</p>
    <?php endif; ?>
  </div>
</details>

<!-- Dialog assegna (responsabile) + suo spazio destra -->
<?php if (is_responsabile()): ?>
<dialog id="dlg-assegna" class="tp-dialog">
  <form method="post" id="frm-assegna">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="azione" value="programma">
    <input type="hidden" name="data"   id="ass-data"   value="">
    <input type="hidden" name="numero" id="ass-numero" value="">
    <div class="tp-dlg-header"><h2 id="ass-title">Assegna turno</h2></div>
    <div class="tp-dlg-body">
      <div class="field">
        <label for="ass-op">Operatore</label>
        <select name="operatore_id" id="ass-op">
          <option value="">— seleziona —</option>
          <?php foreach ($operatori as $op): ?>
          <option value="<?= (int)$op['id'] ?>"><?= $h($op['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="tp-dlg-footer">
      <button type="button" id="ass-cancel" class="ghost">Annulla</button>
      <button type="submit">Assegna</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

</aside>

</div><!-- /.tp-layout -->

<script src="<?= asset_url('assets/js/turni.js') ?>"></script>

<script>
var TP_DATA = <?= json_encode([
    'cal'      => $calendario,
    'turns'    => $turns,
    'uid'      => $uid,
    'mobEdit'  => $mobTurniEdit,
    'isResp'   => is_responsabile(),
    'opPuoMod' => $opPuoModificare,
    'opList'   => $operatori,
    'today'    => $oggi,
], JSON_HEX_TAG | JSON_HEX_APOS) ?>;
var TP_CSRF = '<?= csrf_token() ?>';
</script>

<dialog class="tp-day-sheet" id="dlg-day-sheet" aria-modal="true">
  <div class="tds-handle" aria-hidden="true"></div>
  <div class="tds-header">
    <span class="tds-date-lbl" id="tds-date"></span>
    <button class="tds-close" id="tds-close" aria-label="Chiudi">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" width="16" height="16" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="tds-body" id="tds-body"></div>
</dialog>

<script>
(function () {
  var sheet = document.getElementById('dlg-day-sheet');
  if (!sheet || typeof TP_DATA === 'undefined') return;

  var GG = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
  var MM = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function fmtDate(d) {
    var dt = new Date(d + 'T00:00:00');
    return GG[dt.getDay()] + ', ' + dt.getDate() + ' ' + MM[dt.getMonth() + 1];
  }

  function buildBody(date) {
    var day  = TP_DATA.cal[date] || {};
    var keys = Object.keys(TP_DATA.turns).sort();
    var html = '';

    for (var i = 0; i < keys.length; i++) {
      var n     = keys[i];
      var turn  = TP_DATA.turns[n];
      var slot  = day[n];
      var nome  = turn.nome || ('Turno ' + n);
      var letter = nome.charAt(0).toUpperCase();
      var isMine = slot && String(slot.operatore_id) === String(TP_DATA.uid);
      var sc = slot ? (isMine ? 'tds-mine' : 'tds-other') : 'tds-empty';

      html += '<div class="tds-slot ' + sc + '">';
      html += '<div class="tds-slot-info">';
      html += '<span class="tds-chip">' + esc(letter) + '</span>';
      html += '<div class="tds-slot-text">';
      html += '<span class="tds-turn-lbl">' + esc(nome) + '</span>';
      if (slot) {
        html += '<span class="tds-op-nome">' + esc(slot.nome) + (isMine ? ' <span class="tds-tu">(tu)</span>' : '') + '</span>';
      } else {
        html += '<span class="tds-vuoto">Non assegnato</span>';
      }
      html += '</div></div>';

      if (TP_DATA.mobEdit) {
        html += '<div class="tds-actions">';
        if (slot) {
          if (TP_DATA.isResp) {
            html += '<form method="post" class="tds-form">'
              + '<input type="hidden" name="csrf"   value="' + esc(TP_CSRF) + '">'
              + '<input type="hidden" name="azione" value="rimuovi">'
              + '<input type="hidden" name="data"   value="' + esc(date) + '">'
              + '<input type="hidden" name="numero" value="' + esc(n) + '">'
              + '<button type="submit" class="tds-btn tds-rm">Rimuovi</button>'
              + '</form>';
          }
        } else {
          if (TP_DATA.isResp) {
            html += '<form method="post" class="tds-form tds-form-assign">'
              + '<input type="hidden" name="csrf"   value="' + esc(TP_CSRF) + '">'
              + '<input type="hidden" name="azione" value="programma">'
              + '<input type="hidden" name="data"   value="' + esc(date) + '">'
              + '<input type="hidden" name="numero" value="' + esc(n) + '">'
              + '<select name="operatore_id" class="tds-select">'
              + '<option value="">— operatore —</option>';
            for (var j = 0; j < TP_DATA.opList.length; j++) {
              var op = TP_DATA.opList[j];
              html += '<option value="' + esc(op.id) + '">' + esc(op.nome) + '</option>';
            }
            html += '</select>'
              + '<button type="submit" class="tds-btn tds-ok">Assegna</button>'
              + '</form>';
          } else if (TP_DATA.opPuoMod) {
            html += '<form method="post" class="tds-form">'
              + '<input type="hidden" name="csrf"   value="' + esc(TP_CSRF) + '">'
              + '<input type="hidden" name="azione" value="programma">'
              + '<input type="hidden" name="data"   value="' + esc(date) + '">'
              + '<input type="hidden" name="numero" value="' + esc(n) + '">'
              + '<button type="submit" class="tds-btn tds-ok">Aggiungiti</button>'
              + '</form>';
          }
        }
        html += '</div>';
      }

      html += '</div>';
    }
    return html;
  }

  function openSheet(date) {
    if (window.innerWidth > 760) return;
    document.getElementById('tds-date').textContent = fmtDate(date);
    document.getElementById('tds-body').innerHTML = buildBody(date);
    sheet.showModal();
  }

  document.querySelectorAll('.tp-cal-cell[data-date]').forEach(function (cell) {
    cell.addEventListener('click', function (e) {
      if (window.innerWidth > 760) return;
      openSheet(cell.dataset.date);
    });
  });

  document.getElementById('tds-close').addEventListener('click', function () { sheet.close(); });
  sheet.addEventListener('click', function (e) { if (e.target === sheet) sheet.close(); });
}());
</script>

<?php endif; /* migrationOk */ ?>
</body></html>
