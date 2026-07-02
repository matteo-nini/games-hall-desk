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

/**
 * Arrotonda il versamento al multiplo di 5 più vicino.
 * Soglia 2.0 anziché 2.5 (punto di mezzo matematico): scelta deliberata per
 * favorire l'arrotondamento verso il basso su importi vicini al multiplo inferiore,
 * riducendo i piccoli scostamenti sulle note di chiusura dei responsabili.
 */
function arrotonda_versamento(float $v): float {
    $v    = round($v, 2);
    $base = floor($v / 5) * 5;
    return ($v - $base) > 2.0 ? $base + 5.0 : $base;
}

/**
 * Restituisce l'URL assoluto dalla root dell'applicazione.
 * La logica ricava il prefisso di percorso confrontando la directory fisica dell'app
 * con il path dello script corrente — supporta installazioni in sottocartella
 * (es. /demo/gestsuite/) senza configurazione manuale.
 */
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

/** URL di un asset con cache-busting basato su mtime del file (?v=timestamp). */
function asset_url(string $path): string {
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    $v    = is_file($file) ? filemtime($file) : 0;
    return base_url($path) . ($v ? '?v=' . $v : '');
}

/**
 * Deriva le varianti CSS dell'accent color dal colore brand.
 * --accent-weak: 85% bianco + 15% accent (sfondo badge, highlight leggeri).
 * --accent-ink:  accent × 60% (hover, testo su sfondo weak — garantisce contrasto).
 * Queste proporzioni sono deliberate: cambiarle rompe il sistema colore in dark mode
 * (che usa color-mix() sulle stesse variabili in core.css).
 */
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
 * Riconciliazione di un singolo turno. Fonte di verità server-side;
 * le stesse formule esistono in JS (giornaliero.php) per il ricalcolo live.
 *
 * $t deve contenere: fondo_cassa, monete, bancomat, differenze, ii_cassa,
 * rientri, contanti (somma taglio×pezzi), refill (somma euro), scass (totale
 * scassettamenti), ticket (totale ticket vincita pagati).
 *
 * Formule:
 *   cassetto   = contanti + refill + differenze − 2ª_cassa − rientri
 *   vers_vlt   = scass − bancomat − ticket       (denaro da versare al gestore)
 *   vers_cassa = cassetto + monete − fondo_cassa  (quadratura cassa fisica)
 *   errore     = vers_vlt − vers_cassa            (deve essere 0 a fine giornata)
 *   totale     = cassetto + monete + bancomat + ticket  (bilancio complessivo del turno)
 *
 * Nota: `bancomat` e `ticket` compaiono sia in vers_vlt (uscite) sia in totale
 * (rientri contabili) — è corretto perché bilanciano i rispettivi movimenti di cassa.
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

/**
 * Riepilogo finanziario di una giornata — aggrega tutti i turni.
 *
 * Con $opId = 0 (default): somma tutti i turni del giorno.
 * Con $opId > 0: filtra per operatore (usato nei report per operatore e stipendi).
 */
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
        $ts = $pdo->prepare('SELECT * FROM turni WHERE giornata_id=? ORDER BY numero');
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

/**
 * Riepilogo finanziario di un mese intero — due query aggregate.
 * Rimpiazza N×riepilogo_giornata() nei report mensili (issue P-01).
 *
 * @return array{righe: array<int,array>, tot: array}
 *   righe: keyed 1–31, stessa struttura di riepilogo_giornata()
 *   tot:   ['incasso'=>float,'ticket'=>float,'bancomat'=>float,'versamento'=>float]
 */
function riepilogo_mese(PDO $pdo, string $primo, string $ultimo, int $opId = 0): array {
    $fornitori = get_fornitori($pdo);
    $zero = ['incasso_vlt'=>0.0,'bancomat'=>0.0,'versamento'=>0.0,'ticket'=>0.0,
             'scass' => array_fill_keys($fornitori, 0.0)];
    $tot  = ['incasso'=>0.0,'ticket'=>0.0,'bancomat'=>0.0,'versamento'=>0.0];

    $firstDay = (int)substr($primo, 8, 2);
    $lastDay  = (int)substr($ultimo, 8, 2);
    $righe = [];
    for ($d = $firstDay; $d <= $lastDay; $d++) $righe[$d] = $zero;

    $opFilter = $opId > 0 ? ' AND t.operatore_id = :op' : '';
    $params   = ['primo' => $primo, 'ultimo' => $ultimo];
    if ($opId > 0) $params['op'] = $opId;

    // Aggregazione principale: bancomat, incasso_vlt, ticket, versamento per giorno.
    // versamento = vers_cassa = cassetto + monete - fondo_cassa (stessa formula di calcola_turno()).
    $st = $pdo->prepare("
        SELECT DAY(g.data) AS giorno,
               COALESCE(SUM(t.bancomat), 0)  AS bancomat,
               COALESCE(SUM(s_agg.val), 0)   AS incasso_vlt,
               COALESCE(SUM(tk_agg.val), 0)  AS ticket,
               COALESCE(SUM(
                   COALESCE(c_agg.val,0) + COALESCE(r_agg.val,0)
                   + COALESCE(t.differenze,0) - COALESCE(t.ii_cassa,0)
                   - COALESCE(t.rientri,0)   + COALESCE(t.monete,0)
                   - COALESCE(t.fondo_cassa,0)
               ), 0) AS versamento
        FROM giornate g
        LEFT JOIN turni t ON t.giornata_id = g.id{$opFilter}
        LEFT JOIN (SELECT turno_id, SUM(taglio*pezzi) val FROM contanti        GROUP BY turno_id) c_agg  ON c_agg.turno_id  = t.id
        LEFT JOIN (SELECT turno_id, SUM(euro)          val FROM refill_awp     GROUP BY turno_id) r_agg  ON r_agg.turno_id  = t.id
        LEFT JOIN (SELECT turno_id, SUM(importo)       val FROM ticket         GROUP BY turno_id) tk_agg ON tk_agg.turno_id = t.id
        LEFT JOIN (SELECT turno_id, SUM(importo)       val FROM scassettamenti GROUP BY turno_id) s_agg  ON s_agg.turno_id  = t.id
        WHERE g.data BETWEEN :primo AND :ultimo
        GROUP BY g.data
        ORDER BY g.data
    ");
    $st->execute($params);
    foreach ($st as $row) {
        $d = (int)$row['giorno'];
        $righe[$d]['incasso_vlt'] = (float)$row['incasso_vlt'];
        $righe[$d]['bancomat']    = (float)$row['bancomat'];
        $righe[$d]['versamento']  = (float)$row['versamento'];
        $righe[$d]['ticket']      = (float)$row['ticket'];
        $tot['incasso']    += (float)$row['incasso_vlt'];
        $tot['ticket']     += (float)$row['ticket'];
        $tot['bancomat']   += (float)$row['bancomat'];
        $tot['versamento'] += (float)$row['versamento'];
    }

    // Seconda query: scassettamenti per fornitore per giorno.
    $st2 = $pdo->prepare("
        SELECT DAY(g.data) AS giorno, m.fornitore,
               COALESCE(SUM(s.importo), 0) AS tot
        FROM giornate g
        JOIN turni t ON t.giornata_id = g.id{$opFilter}
        JOIN scassettamenti s ON s.turno_id = t.id
        JOIN macchine m ON m.id = s.macchina_id
        WHERE g.data BETWEEN :primo AND :ultimo
        GROUP BY g.data, m.fornitore
    ");
    $st2->execute($params);
    foreach ($st2 as $row) {
        $d = (int)$row['giorno'];
        $f = $row['fornitore'];
        if (isset($righe[$d]['scass'][$f])) $righe[$d]['scass'][$f] = (float)$row['tot'];
    }

    return ['righe' => $righe, 'tot' => $tot];
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

/**
 * Tutte le impostazioni come array associativo chiave→valore.
 * Cache statica: stale se la tabella viene modificata nella stessa request (issue P-03).
 * In caso di DB irraggiungibile ritorna [] senza eccezione.
 */
/**
 * Legge tutte le chiavi dalla tabella impostazioni.
 * Passa $force=true dopo una scrittura per invalidare la cache statica
 * ed evitare dati stale nella stessa request (P-03).
 */
function get_settings(PDO $pdo, bool $force = false): array {
    static $cache = null;
    if ($cache !== null && !$force) return $cache;
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

/**
 * Gradiente HSL deterministico dal nome: crc32 → hue primario, +40° per il secondo.
 * Garantisce che lo stesso nome produca sempre lo stesso colore tra sessioni e pagine.
 */
function avatar_style(string $name): string {
    $h1 = abs(crc32($name)) % 360;
    $h2 = ($h1 + 40) % 360;
    return "background:linear-gradient(135deg,hsl({$h1},62%,46%),hsl({$h2},56%,36%))";
}

/* Sincronizza un contatto legato a un utente (utente_id).
   Cerca prima per utente_id, poi per telefono/email — evita duplicati se il contatto
   era già stato inserito manualmente con gli stessi recapiti. */
function sync_contact_utente(PDO $pdo, int $uid, string $nome, string $telefono, string $email, string $ruolo): void {
    try {
        $st = $pdo->prepare('SELECT id FROM contatti WHERE utente_id=? LIMIT 1');
        $st->execute([$uid]);
        $id = $st->fetchColumn();

        if (!$id) {
            $conds = []; $params = [];
            if ($telefono) { $conds[] = 'telefono=?'; $params[] = $telefono; }
            if ($email)    { $conds[] = 'email=?';    $params[] = $email; }
            if ($conds) {
                $st2 = $pdo->prepare('SELECT id FROM contatti WHERE (' . implode(' OR ', $conds) . ') ORDER BY id LIMIT 1');
                $st2->execute($params);
                $id = $st2->fetchColumn();
            }
        }

        if ($id) {
            $pdo->prepare('UPDATE contatti SET nome=?,telefono=?,email=?,ruolo=?,utente_id=? WHERE id=?')
                ->execute([$nome, $telefono ?: null, $email ?: null, $ruolo, $uid, $id]);
        } else {
            $max = (int)$pdo->query('SELECT COALESCE(MAX(ordine),0) FROM contatti')->fetchColumn();
            $pdo->prepare('INSERT INTO contatti (nome,ruolo,telefono,email,ordine,utente_id) VALUES (?,?,?,?,?,?)')
                ->execute([$nome, $ruolo, $telefono ?: null, $email ?: null, $max + 1, $uid]);
        }
    } catch (Throwable) {}
}

/* Sincronizza il contatto di sistema della sala (sistema=1).
   Cerca prima per sistema=1, poi per telefono/email — evita duplicati.
   Sull'update preserva il nome (potrebbe essere stato impostato manualmente o
   dal sync utente); il sito web va nel campo note. */
function sync_contact_sala(PDO $pdo, string $nome, string $telefono, string $email, string $sito): void {
    try {
        $note = $sito ? 'Sito: ' . $sito : null;

        $st = $pdo->query('SELECT id FROM contatti WHERE sistema=1 LIMIT 1');
        $id = $st->fetchColumn();

        if (!$id) {
            $conds = []; $params = [];
            if ($telefono) { $conds[] = 'telefono=?'; $params[] = $telefono; }
            if ($email)    { $conds[] = 'email=?';    $params[] = $email; }
            if ($conds) {
                $st2 = $pdo->prepare('SELECT id FROM contatti WHERE (' . implode(' OR ', $conds) . ') ORDER BY id LIMIT 1');
                $st2->execute($params);
                $id = $st2->fetchColumn();
            }
        }

        if ($id) {
            $pdo->prepare('UPDATE contatti SET telefono=?,email=?,note=?,sistema=1 WHERE id=?')
                ->execute([$telefono ?: null, $email ?: null, $note, $id]);
        } else {
            $max = (int)$pdo->query('SELECT COALESCE(MAX(ordine),0) FROM contatti')->fetchColumn();
            $pdo->prepare('INSERT INTO contatti (nome,ruolo,telefono,email,note,ordine,sistema) VALUES (?,?,?,?,?,?,1)')
                ->execute([$nome, 'Sala', $telefono ?: null, $email ?: null, $note, $max + 1]);
        }
    } catch (Throwable) {}
}
