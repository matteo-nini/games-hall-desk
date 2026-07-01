<?php
// =====================================================================
//  Logica di dominio: riconciliazione cassa, costanti, audit.
//  Le stesse formule sono replicate in JS (giornaliero.php) per il
//  ricalcolo live; QUESTA versione lato server e' la fonte di verita'.
// =====================================================================

function tagli(): array { return [5, 10, 20, 50, 100, 200, 500]; }

/**
 * Fornitori attivi, letti dalla tabella DB con fallback ai 3 fornitori
 * predefiniti. In questo modo le installazioni senza la tabella fornitori
 * (o senza la migration) continuano a funzionare.
 */
function get_fornitori(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $rows  = $pdo->query('SELECT nome FROM fornitori WHERE attiva=1 ORDER BY ordine')->fetchAll(PDO::FETCH_COLUMN);
        $cache = $rows ?: ['NOVO', 'INSPIRED', 'SPIELO'];
    } catch (Throwable) {
        $cache = ['NOVO', 'INSPIRED', 'SPIELO'];
    }
    return $cache;
}

/**
 * Turni configurati. Legge num_turni e turno_N_* dalle impostazioni.
 * Backward compat: se i nuovi tasti non esistono usa turno_mattino_* e turno_sera_*.
 * Restituisce array indicizzato per numero turno (da 1 a N).
 */
function get_turns(array $sett): array {
    $n = max(1, min(3, (int)($sett['num_turni'] ?? 2)));
    $compat = [
        1 => ['inizio' => 'turno_mattino_inizio', 'fine' => 'turno_mattino_fine'],
        2 => ['inizio' => 'turno_sera_inizio',    'fine' => 'turno_sera_fine'],
    ];
    $defaults_nome  = [1 => 'Mattino', 2 => 'Sera', 3 => 'Notte'];
    $defaults_inizio = [1 => '13:00',  2 => '19:00', 3 => '01:00'];
    $defaults_fine   = [1 => '19:00',  2 => '01:00', 3 => '09:00'];
    $turns = [];
    for ($i = 1; $i <= $n; $i++) {
        $ik = "turno_{$i}_inizio";
        $fk = "turno_{$i}_fine";
        $turns[$i] = [
            'numero' => $i,
            'nome'   => $sett["turno_{$i}_nome"] ?? $defaults_nome[$i],
            'inizio' => $sett[$ik] ?? ($sett[$compat[$i]['inizio'] ?? ''] ?? $defaults_inizio[$i]),
            'fine'   => $sett[$fk] ?? ($sett[$compat[$i]['fine']   ?? ''] ?? $defaults_fine[$i]),
        ];
    }
    return $turns;
}

/** Arrotonda il versamento al multiplo di 5 più vicino: su se resto > 2, giù altrimenti. */
function arrotonda_versamento(float $v): float {
    $v    = round($v, 2);
    $base = floor($v / 5) * 5;
    return ($v - $base) > 2.0 ? $base + 5.0 : $base;
}

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

function asset_url(string $path): string {
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    $v    = is_file($file) ? filemtime($file) : 0;
    return base_url($path) . ($v ? '?v=' . $v : '');
}

function brand_derive(string $hex): array {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) return [];
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    return [
        '--accent'      => $hex,
        '--accent-weak' => 'rgb('.round(255*.85+$r*.15).','.round(255*.85+$g*.15).','.round(255*.85+$b*.15).')',
        '--accent-ink'  => 'rgb('.round($r*.60).','.round($g*.60).','.round($b*.60).')',
    ];
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
    $vers_vlt    = $scass - $g('bancomat') - $ticket;
    $vers_cassa  = $cassetto + $g('monete') - $g('fondo_cassa');
    $errore      = $vers_vlt - $vers_cassa;

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
    $fornitori = get_fornitori($pdo);
    $contanti  = 0;
    $st = $pdo->prepare('SELECT taglio, pezzi FROM contanti WHERE turno_id=?'); $st->execute([$tid]);
    foreach ($st as $r) $contanti += (int)$r['taglio'] * (int)$r['pezzi'];

    $scass_forn = array_fill_keys($fornitori, 0.0);
    $scass = 0.0;
    $st = $pdo->prepare('SELECT m.fornitore f, SUM(s.importo) imp
                         FROM scassettamenti s JOIN macchine m ON m.id=s.macchina_id
                         WHERE s.turno_id=? GROUP BY m.fornitore');
    $st->execute([$tid]);
    foreach ($st as $r) {
        $scass_forn[$r['f']] = (float)$r['imp'];
        $scass += (float)$r['imp'];
    }

    $st = $pdo->prepare('SELECT COALESCE(SUM(euro),0) v FROM refill_awp WHERE turno_id=?'); $st->execute([$tid]);
    $refill = (float)$st->fetch()['v'];
    $st = $pdo->prepare('SELECT COALESCE(SUM(importo),0) v FROM ticket WHERE turno_id=?'); $st->execute([$tid]);
    $ticket = (float)$st->fetch()['v'];

    return compact('contanti','refill','scass','ticket') + ['scass_forn' => $scass_forn];
}

/** Riepilogo finanziario di giornata: usa l'ultimo turno disponibile (massimo numero). */
function riepilogo_giornata(PDO $pdo, string $data, int $opId = 0): array {
    $fornitori = get_fornitori($pdo);
    $z = ['bancomat'=>0.0,'versamento'=>0.0,'ticket'=>0.0,'incasso_vlt'=>0.0,
          'scass'   => array_fill_keys($fornitori, 0.0)];
    $g = $pdo->prepare('SELECT id FROM giornate WHERE data=?'); $g->execute([$data]); $g = $g->fetch();
    if (!$g) return $z;
    if ($opId > 0) {
        $ts = $pdo->prepare('SELECT * FROM turni WHERE giornata_id=? AND operatore_id=? ORDER BY numero');
        $ts->execute([$g['id'], $opId]);
    } else {
        /* Usa solo l'ultimo turno del giorno per il riepilogo finanziario */
        $ts = $pdo->prepare('SELECT * FROM turni WHERE giornata_id=? ORDER BY numero DESC LIMIT 1');
        $ts->execute([$g['id']]);
    }
    foreach ($ts as $t) {
        $s = sums_turno($pdo, (int)$t['id']);
        $c = calcola_turno([
            'fondo_cassa'=>$t['fondo_cassa'],'monete'=>$t['monete'],'bancomat'=>$t['bancomat'],
            'differenze'=>$t['differenze'],'ii_cassa'=>$t['ii_cassa'],'rientri'=>$t['rientri'],
            'contanti'=>$s['contanti'],'refill'=>$s['refill'],'scass'=>$s['scass'],'ticket'=>$s['ticket'],
        ]);
        $z['bancomat']    += (float)$t['bancomat'];
        $z['versamento']  += $c['vers_cassa'];
        $z['ticket']      += $s['ticket'];
        $z['incasso_vlt'] += $s['scass'];
        foreach ($fornitori as $f) $z['scass'][$f] += ($s['scass_forn'][$f] ?? 0.0);
    }
    return $z;
}

/** Bet/win per un giorno: ['NOVO'=>['giocato'=>..,'pagato'=>..], ...]. */
function betwin_giorno(PDO $pdo, string $data): array {
    $fornitori = get_fornitori($pdo);
    $out = [];
    foreach ($fornitori as $f) $out[$f] = ['giocato'=>0.0,'pagato'=>0.0];
    $st = $pdo->prepare('SELECT fornitore, giocato, pagato FROM snai_betwin WHERE data=?'); $st->execute([$data]);
    foreach ($st as $r) {
        if (isset($out[$r['fornitore']])) {
            $out[$r['fornitore']] = ['giocato'=>(float)$r['giocato'],'pagato'=>(float)$r['pagato']];
        }
    }
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

function avatar_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    return mb_strtoupper(
        mb_substr($parts[0], 0, 1, 'UTF-8') .
        (isset($parts[1]) ? mb_substr($parts[1], 0, 1, 'UTF-8') : ''),
        'UTF-8'
    );
}

function avatar_style(string $name): string {
    $h1 = abs(crc32($name)) % 360;
    $h2 = ($h1 + 40) % 360;
    return "background:linear-gradient(135deg,hsl({$h1},62%,46%),hsl({$h2},56%,36%))";
}

/* Sincronizza un contatto legato a un utente (utente_id).
   Aggiorna nome/telefono/email/ruolo; non tocca le note manuali. */
function sync_contact_utente(PDO $pdo, int $uid, string $nome, string $telefono, string $email, string $ruolo): void {
    try {
        $st = $pdo->prepare('SELECT id FROM contatti WHERE utente_id=? LIMIT 1');
        $st->execute([$uid]);
        $existing = $st->fetchColumn();
        if ($existing) {
            $pdo->prepare('UPDATE contatti SET nome=?,telefono=?,email=?,ruolo=? WHERE id=?')
                ->execute([$nome, $telefono ?: null, $email ?: null, $ruolo, $existing]);
        } else {
            $max = (int)$pdo->query('SELECT COALESCE(MAX(ordine),0) FROM contatti')->fetchColumn();
            $pdo->prepare('INSERT INTO contatti (nome,ruolo,telefono,email,ordine,utente_id) VALUES (?,?,?,?,?,?)')
                ->execute([$nome, $ruolo, $telefono ?: null, $email ?: null, $max + 1, $uid]);
        }
    } catch (Throwable) {}
}

/* Sincronizza il contatto di sistema della sala (sistema=1).
   Aggiorna nome/telefono; salva il sito web nel campo note. */
function sync_contact_sala(PDO $pdo, string $nome, string $telefono, string $sito): void {
    try {
        $note = $sito ? 'Sito: ' . $sito : null;
        $st   = $pdo->query('SELECT id FROM contatti WHERE sistema=1 LIMIT 1');
        $existing = $st->fetchColumn();
        if ($existing) {
            $pdo->prepare('UPDATE contatti SET nome=?,telefono=?,note=? WHERE id=?')
                ->execute([$nome, $telefono ?: null, $note, $existing]);
        } else {
            $max = (int)$pdo->query('SELECT COALESCE(MAX(ordine),0) FROM contatti')->fetchColumn();
            $pdo->prepare('INSERT INTO contatti (nome,ruolo,telefono,note,ordine,sistema) VALUES (?,?,?,?,?,1)')
                ->execute([$nome, 'Sala', $telefono ?: null, $note, $max + 1]);
        }
    } catch (Throwable) {}
}
