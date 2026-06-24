<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$pdo  = db();

$anno = (int)($_GET['anno'] ?? date('Y'));
$mese = (int)($_GET['mese'] ?? date('n'));
if ($mese < 1 || $mese > 12) $mese = (int)date('n');
$ngiorni = (int)date('t', mktime(0,0,0,$mese,1,$anno));

audit('export_csv', 'mensile', null, sprintf('%04d-%02d', $anno, $mese));

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=cassa_{$anno}_{$mese}.csv");
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM per Excel
$sep = ';';
$w = function(array $r) use ($out, $sep) {
    // numeri con virgola decimale per Excel IT
    $r = array_map(fn($v) => is_float($v) ? number_format($v,2,',','') : $v, $r);
    fputcsv($out, $r, $sep);
};

$w(["Cassa per giorno - $anno/$mese"]);
$w(['Giorno','Incasso VLT','Ticket','Bancomat','Versamento','Scass NOVO','Scass INSPIRED','Scass SPIELO']);
$tot = [0.0,0.0,0.0,0.0,0.0,0.0,0.0];
for ($d=1;$d<=$ngiorni;$d++){
    $r = riepilogo_giornata($pdo, sprintf('%04d-%02d-%02d',$anno,$mese,$d));
    $vals = [(float)$r['incasso_vlt'],(float)$r['ticket'],(float)$r['bancomat'],(float)$r['versamento'],
             (float)$r['scass']['NOVO'],(float)$r['scass']['INSPIRED'],(float)$r['scass']['SPIELO']];
    foreach ($vals as $i=>$v) $tot[$i]+=$v;
    $w(array_merge([$d], $vals));
}
$w(array_merge(['TOTALE'], $tot));
$w([]);

$w(["Bet/Win SNAI per fornitore"]);
$w(['Fornitore','Giocato','Pagato','Ricavo','Inserito']);
$primo = sprintf('%04d-%02d-01',$anno,$mese);
$ultimo= sprintf('%04d-%02d-%02d',$anno,$mese,$ngiorni);
$fornitori = get_fornitori($pdo);
$bw = array_fill_keys($fornitori, ['g'=>0.0,'p'=>0.0]);
$st = $pdo->prepare('SELECT fornitore,SUM(giocato) g,SUM(pagato) p FROM snai_betwin WHERE data BETWEEN ? AND ? GROUP BY fornitore');
$st->execute([$primo,$ultimo]);
foreach ($st as $row) if (isset($bw[$row['fornitore']])) $bw[$row['fornitore']]=['g'=>(float)$row['g'],'p'=>(float)$row['p']];
$ins = array_fill_keys($fornitori, 0.0);
for ($d=1;$d<=$ngiorni;$d++){ $r=riepilogo_giornata($pdo,sprintf('%04d-%02d-%02d',$anno,$mese,$d)); foreach($fornitori as $f) $ins[$f]+=$r['scass'][$f]??0; }
foreach ($fornitori as $f){
    $w([$f,(float)$bw[$f]['g'],(float)$bw[$f]['p'],(float)($bw[$f]['g']-$bw[$f]['p']),(float)$ins[$f]]);
}
fclose($out);
