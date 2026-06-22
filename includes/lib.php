<?php
// =====================================================================
//  Logica di dominio: riconciliazione cassa, costanti, audit.
//  Le stesse formule sono replicate in JS (giornaliero.php) per il
//  ricalcolo live; QUESTA versione lato server e' la fonte di verita'.
// =====================================================================

function fornitori(): array { return ['NOVO', 'INSPIRED', 'SPIELO']; }
function tagli(): array     { return [5, 10, 20, 50, 100, 200, 500]; }

function base_url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $appRoot    = realpath(dirname(__DIR__));
        $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
        $scriptUrl  = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        if ($appRoot && $scriptFile && str_starts_with($scriptFile, $appRoot)) {
            $relFile = str_replace('\\', '/', substr($scriptFile, strlen($appRoot)));
            $base    = substr($scriptUrl, 0, strlen($scriptUrl) - strlen($relFile));
            $base    = rtrim($base, '/') . '/';
        } else {
            $base = '/';
        }
    }
    return $base . ltrim($path, '/');
}

/**
 * Calcola la riconciliazione di un turno.
 * $t deve contenere: fondo_cassa, monete, bancomat, differenze, ii_cassa,
 * rientri, contanti (somma), refill (somma), scass (somma), ticket (somma).
 */
function calcola_turno(array $t): array {
    $g = fn($k) => (float)($t[$k] ?? 0);
    $contanti = $g('contanti');
    $refill   = $g('refill');
    $scass    = $g('scass');
    $ticket   = $g('ticket');

    $cassetto    = $contanti + $refill + $g('differenze') - $g('ii_cassa') - $g('rientri');
    $totale      = $cassetto + $g('monete') + $g('bancomat') + $ticket;
    $vers_vlt    = $scass - $g('bancomat') - $ticket;          // versamento (incasso VLT)
    $vers_cassa  = $cassetto + $g('monete') - $g('fondo_cassa'); // versamento (cassa)
    $errore      = $vers_vlt - $vers_cassa;                     // deve essere 0

    return compact('contanti','refill','scass','ticket',
                   'cassetto','totale','vers_vlt','vers_cassa','errore');
}

/** Totali di giornata = turno 1 + turno 2 (su un campo calcolato). */
function somma_giornata(array $r1, array $r2, string $campo): float {
    return (float)($r1[$campo] ?? 0) + (float)($r2[$campo] ?? 0);
}

function eur(float $v): string {
    return number_format($v, 2, ',', '.');
}

function audit(string $azione, ?string $entita = null, ?int $entita_id = null, ?string $dettaglio = null): void {
    $uid = $_SESSION['uid'] ?? null;
    $st = db()->prepare(
        'INSERT INTO audit_log (utente_id, azione, entita, entita_id, dettaglio, ip)
         VALUES (?,?,?,?,?,?)'
    );
    $st->execute([$uid, $azione, $entita, $entita_id, $dettaglio, $_SERVER['REMOTE_ADDR'] ?? null]);
}

// ---------------------------------------------------------------------
//  Dati derivati dai giornalieri (usati da settimanale, mensile, export)
// ---------------------------------------------------------------------

/** Somme di un turno: contanti, refill, scass (tot e per fornitore), ticket. */
function sums_turno(PDO $pdo, int $tid): array {
    $contanti = 0;
    $st = $pdo->prepare('SELECT taglio, pezzi FROM contanti WHERE turno_id=?'); $st->execute([$tid]);
    foreach ($st as $r) $contanti += (int)$r['taglio'] * (int)$r['pezzi'];

    $scass_forn = ['NOVO'=>0.0,'INSPIRED'=>0.0,'SPIELO'=>0.0]; $scass = 0.0;
    $st = $pdo->prepare('SELECT m.fornitore f, SUM(s.importo) imp
                         FROM scassettamenti s JOIN macchine m ON m.id=s.macchina_id
                         WHERE s.turno_id=? GROUP BY m.fornitore');
    $st->execute([$tid]);
    foreach ($st as $r) { $scass_forn[$r['f']] = (float)$r['imp']; $scass += (float)$r['imp']; }

    $st = $pdo->prepare('SELECT COALESCE(SUM(euro),0) v FROM refill_awp WHERE turno_id=?'); $st->execute([$tid]);
    $refill = (float)$st->fetch()['v'];
    $st = $pdo->prepare('SELECT COALESCE(SUM(importo),0) v FROM ticket WHERE turno_id=?'); $st->execute([$tid]);
    $ticket = (float)$st->fetch()['v'];

    return compact('contanti','refill','scass','ticket') + ['scass_forn'=>$scass_forn];
}

/** Riepilogo di giornata = somma dei due turni dei valori derivati. */
function riepilogo_giornata(PDO $pdo, string $data): array {
    $z = ['bancomat'=>0.0,'versamento'=>0.0,'ticket'=>0.0,'incasso_vlt'=>0.0,
          'scass'=>['NOVO'=>0.0,'INSPIRED'=>0.0,'SPIELO'=>0.0]];
    $g = $pdo->prepare('SELECT id FROM giornate WHERE data=?'); $g->execute([$data]); $g = $g->fetch();
    if (!$g) return $z;
    $ts = $pdo->prepare('SELECT * FROM turni WHERE giornata_id=?'); $ts->execute([$g['id']]);
    foreach ($ts as $t) {
        $s = sums_turno($pdo, (int)$t['id']);
        $c = calcola_turno([
            'fondo_cassa'=>$t['fondo_cassa'],'monete'=>$t['monete'],'bancomat'=>$t['bancomat'],
            'differenze'=>$t['differenze'],'ii_cassa'=>$t['ii_cassa'],'rientri'=>$t['rientri'],
            'contanti'=>$s['contanti'],'refill'=>$s['refill'],'scass'=>$s['scass'],'ticket'=>$s['ticket'],
        ]);
        $z['bancomat']   += (float)$t['bancomat'];
        $z['versamento'] += $c['vers_vlt'];
        $z['ticket']     += $s['ticket'];
        $z['incasso_vlt']+= $s['scass'];
        foreach (['NOVO','INSPIRED','SPIELO'] as $f) $z['scass'][$f] += $s['scass_forn'][$f];
    }
    return $z;
}

/** Bet/win SNAI di un giorno: ['NOVO'=>['giocato'=>..,'pagato'=>..], ...]. */
function betwin_giorno(PDO $pdo, string $data): array {
    $out = [];
    foreach (fornitori() as $f) $out[$f] = ['giocato'=>0.0,'pagato'=>0.0];
    $st = $pdo->prepare('SELECT fornitore, giocato, pagato FROM snai_betwin WHERE data=?'); $st->execute([$data]);
    foreach ($st as $r) $out[$r['fornitore']] = ['giocato'=>(float)$r['giocato'],'pagato'=>(float)$r['pagato']];
    return $out;
}

function nomi_mesi(): array {
    return [1=>'Gennaio',2=>'Febbraio',3=>'Marzo',4=>'Aprile',5=>'Maggio',6=>'Giugno',
            7=>'Luglio',8=>'Agosto',9=>'Settembre',10=>'Ottobre',11=>'Novembre',12=>'Dicembre'];
}

// ---------------------------------------------------------------------
//  Accesso giornata/turno (creano la riga se non esiste)
// ---------------------------------------------------------------------
function ensure_giornata(PDO $pdo, string $data): array {
    $st = $pdo->prepare('SELECT * FROM giornate WHERE data = ?');
    $st->execute([$data]); $g = $st->fetch();
    if (!$g) {
        $pdo->prepare('INSERT INTO giornate (data) VALUES (?)')->execute([$data]);
        $st->execute([$data]); $g = $st->fetch();
    }
    return $g;
}
function ensure_turno(PDO $pdo, int $giornata_id, int $n): array {
    $st = $pdo->prepare('SELECT * FROM turni WHERE giornata_id = ? AND numero = ?');
    $st->execute([$giornata_id, $n]); $t = $st->fetch();
    if (!$t) {
        $pdo->prepare('INSERT INTO turni (giornata_id, numero) VALUES (?,?)')->execute([$giornata_id, $n]);
        $st->execute([$giornata_id, $n]); $t = $st->fetch();
    }
    return $t;
}

function get_settings(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $rows  = $pdo->query('SELECT chiave, valore FROM impostazioni')->fetchAll();
        $cache = array_column($rows, 'valore', 'chiave');
    } catch (PDOException) {
        $cache = [];
    }
    return $cache;
}

function setting(PDO $pdo, string $key, string $default = ''): string {
    return get_settings($pdo)[$key] ?? $default;
}
