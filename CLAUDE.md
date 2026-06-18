# CLAUDE.md — Guida per AI assistant

## Panoramica progetto

**Games Palace Desk** è un'app PHP+MySQL per la gestione della cassa giornaliera di una sala giochi con macchine VLT e AWP. Nessun framework: PHP 8+, PDO, HTML/CSS/JS vanilla.

## Pattern architetturali

### Ogni pagina PHP segue questo schema

```php
require_once __DIR__ . '/auth.php';    // sempre primo
require_once __DIR__ . '/lib.php';
$user = require_login();               // guard: redirect a login se non autenticato
$cfg  = config();                      // configurazione sala
$pdo  = db();                          // connessione PDO (singleton)

// 1. POST handler (con check_csrf())
// 2. Query GET
// 3. HTML output
```

### Helper sempre disponibili

- `eur(float $v): string` — formatta come €1.234,56
- `audit(string $azione, ?string $entita, ?int $id, ?string $dettaglio)` — log operazioni
- `is_responsabile(): bool` — true se ruolo === 'responsabile'
- `ensure_giornata($pdo, $data): array` — crea/recupera record giornata
- `ensure_turno($pdo, $gid, $n): array` — crea/recupera record turno (n=1 mattino, n=2 sera)
- `csrf_token(): string` — genera/recupera token CSRF dalla sessione
- `check_csrf(): void` — valida token POST, esce con 400 se non valido

### Sicurezza

- Ogni form POST deve avere `<input type="hidden" name="csrf" value="<?= csrf_token() ?>">`
- `check_csrf()` va chiamato come prima cosa nel blocco POST
- Mai stampare input utente senza `htmlspecialchars()` — usa la closure `$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES)`
- Mai concatenare parametri nelle query — sempre prepared statement

## Database schema (tabelle principali)

```
giornate          (id, data, stato[aperta|chiusa], chiusa_da, chiusa_il)
turni             (id, giornata_id, numero[1|2], operatore_id, fondo_cassa, monete,
                   bancomat, differenze, ii_cassa, rientri, note)
contanti          (id, turno_id, taglio, pezzi)
scassettamenti    (id, turno_id, macchina_id, importo)
ticket            (id, turno_id, fornitore, importo)
refill_awp        (id, turno_id, n_macchina, euro, ora)
macchine          (id, codice, tipo[VLT|AWP], fornitore, ordine, attiva)
utenti            (id, username, password_hash, nome, ruolo[operatore|responsabile], attivo)
snai_betwin       (id, data, fornitore, giocato, pagato)
audit_log         (id, utente_id, azione, entita, entita_id, dettaglio, ip, creato_il)
ticket_assistenza (id, data_apertura, macchina, problema, id_ticket, risoluzione,
                   data_chiusura, stato[aperto|risolto], creato_da, creato_il)
prestiti_persone  (id, nome, saldo_iniziale, note)
prestiti_movimenti(id, data, persona_id, tipo[prestito|rientro], quantita, note,
                   creato_da, creato_il)
```

## Logica cassa

Formule per turno (calcolate in JS live e sul server in `calcola_turno()`):

```
cassetto     = contanti + refill_awp + differenze - ii_cassa - rientri
versamento   = scassettamenti - bancomat - ticket_pagati
totale_cassa = cassetto + monete - versamento
scostamento  = totale_cassa - fondo_cassa
```

Soglie scostamento per il banner colorato (giornaliero.php):
- `< 4€` → verde (class `ok`)
- `4–5€` → giallo (class `warn`)
- `> 5€` → rosso (class `bad`)

## Frontend (JS in giornaliero.php)

- `ACTIVE` = turno attivo (1=mattino, 2=sera), persistito in `localStorage` chiave `gp_tab`
- `recalcAll()` → ricalcola tutti i turni → aggiorna `RES[n]`
- `updateActive()` → aggiorna il banner statusbar con i dati di `RES[ACTIVE]`
- `showTab(n)` → cambia tab attivo, aggiorna localStorage e il campo hidden `salva_turno`
- Il campo `<input name="salva_turno">` garantisce che il POST salvi solo il turno attivo

## CSS (styles.css)

Variabili CSS in `:root`: `--accent` blu, `--green`, `--amber`, `--red`, `--bg`, `--surface`, `--border`, etc.

Classi riusabili:
- `.mini` + `.calcrow` — card metriche in griglia
- `.badge.open` / `.badge.closed` — badge verde/grigio
- `.recent-list` + `.recent-row` — lista cliccabile (usata in dashboard e prestiti)
- `.ticket-new-wrap` + `.tnf-grid` — form collassabile con griglia campi (usato in ticket e prestiti)
- `.ob-*` — classi onboarding
- `.stickyhead` — header sticky (topbar + tabbar + statusbar insieme)

## Convenzioni da rispettare

- Nessun commento se il codice è auto-esplicativo
- SQL con snake_case, PHP con camelCase per variabili locali
- Redirect dopo ogni POST (`header('Location: ...'); exit;`)
- `audit()` su ogni operazione di scrittura significativa
- Non usare `is_responsabile()` per chiudere la giornata (operatori possono chiudere); usarlo invece per riaprire, eliminare, gestire macchine/utenti
