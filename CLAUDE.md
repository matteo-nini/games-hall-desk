# CLAUDE.md — Guida per AI assistant

## Checklist operativa — aggiornare ad ogni sessione

Dopo ogni modifica significativa (nuova feature, cambio schema DB, nuovo modulo) aggiornare **sempre** questi file:

| File | Quando |
|---|---|
| `README.md` | Ogni nuova feature, modifica alla struttura progetto, aggiornamento permessi |
| `CLAUDE.md` | Nuovi helper, nuove tabelle, nuovi moduli, nuove convenzioni |
| `install/schema.sql` | Ogni nuova tabella o chiave `impostazioni` — **unica fonte di verità** per il DB |
| `install/setup.php` | Se si aggiunge un modulo opzionale (step 4: checkbox + POST handler) |
| `utils/onboarding.php` | Se si aggiunge una sezione/funzione che gli utenti devono conoscere |

**Regola SQL**: nessun file di migration nel progetto. Aggiornare solo `install/schema.sql`. Per le istallazioni esistenti, dettare la query all'utente.

**Regola branch**: ogni nuova feature o redesign significativo va su un branch dedicato (`feat/<nome>` o `fix/<nome>`), poi si mergea su `main` con PR o merge diretto dopo review. Non lavorare direttamente su `main` se non per hotfix urgenti o aggiornamenti doc.

---

## Panoramica progetto

**Games Palace Desk** è un'app PHP+MySQL per la gestione della cassa giornaliera di una sala giochi con macchine VLT e AWP. Nessun framework: PHP 8+, PDO, HTML/CSS/JS vanilla. Progettata per essere white-label e rivendibile.

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
- `brand_derive(string $hex): array` — ricava `--accent`, `--accent-weak`, `--accent-ink` da un hex colore
- `calcola_turno(array $t): array` — riconciliazione server-side di un turno (ritorna errore, cassetto, versamento, totale)
- `get_settings($pdo): array` — tutte le chiavi da tabella `impostazioni` come array associativo
- `riepilogo_giornata(PDO $pdo, string $data, int $opId = 0): array` — riepilogo finanziario di una giornata; passare `$opId > 0` per filtrare i turni di un singolo operatore
- `avatar_initials(string $name): string` — prime due iniziali (nome cognome) in maiuscolo; usare per l'avatar sidebar e operatori
- `avatar_style(string $name): string` — restituisce `style="background:linear-gradient(…)"` deterministico dal nome (crc32 → HSL); garantisce consistenza tra sessioni

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
documenti_cartelle(id, nome, ordine, creata_da, creata_il)
documenti         (id, nome, descrizione, filename, mime, ordine, visibile,
                   cartella_id, caricato_da, caricato_il)
```

## Moduli opzionali (tabella `impostazioni`)

I moduli si attivano/disattivano da Impostazioni → Moduli. La chiave in `impostazioni` è `'1'` se abilitato, `'0'` se no. Default `'1'` per tutti.

| chiave               | modulo                     | nav item           |
|----------------------|----------------------------|--------------------|
| `modulo_assistenze`  | Ticket assistenza          | sala/ticket.php    |
| `modulo_prestiti`    | Prestiti e rientri         | sala/prestiti.php  |
| `modulo_documenti`   | Documenti                  | sala/documenti.php |

In `nav.php` i moduli vengono letti via `$navSett = get_settings($pdo)` e il nav item compare solo se la chiave è `'1'`.

## White label / Brand colori

`brand_accent` in `impostazioni` contiene un hex `#rrggbb`. Se presente, `nav.php` inietta in ogni pagina:

```html
<style>:root{--accent:#hex;--accent-weak:rgb(...);--accent-ink:rgb(...)}</style>
```

Le varianti si derivano con `brand_derive($hex)`. L'accent-weak è il colore a 85% bianco + 15% accent (badge, sfondi); l'accent-ink è l'accent × 0.60 (hover, testo su weak). Tenere questa proporzione coerente in futuro (approccio C: full wizard con contrast checker).

**Priorità CSS**: il blocco `:root` iniettato da PHP ha specificità `(0,1,0)`. Il blocco dark mode `html[data-theme="dark"]` ha specificità `(0,1,1)` — vince sempre. Le varianti accent in dark mode usano `color-mix()` per adattarsi a qualsiasi brand accent dinamicamente.

## Dark mode

- Toggle luna/sole nel footer della sidebar (`.sf-theme` in `nav.php`)
- `localStorage` key: `gp-theme` — valori `'dark'` o `'light'`
- Anti-FOUC: script inline all'inizio di `top_menu()` in `nav.php` che applica `data-theme` prima del primo paint
- CSS: blocco `html[data-theme="dark"]` in `core.css` ridefinisce tutte le variabili; `color-scheme: dark` per form controls nativi
- Accent-weak e accent-ink in dark mode: `color-mix(in srgb, var(--accent) 20%, #111827)` e `color-mix(in srgb, var(--accent) 55%, #e8edf5)`

## Tour onboarding (`assets/js/tour.js`)

Sistema spotlight contestuale, si attiva al primo accesso (flag `gp_wizard_done` in `localStorage`).

```js
// Nella pagina che deve mostrare il tour:
document.addEventListener('DOMContentLoaded', function () {
  if (typeof GP_Tour === 'undefined') return;
  GP_Tour.init([
    { selector: '.elemento', title: 'Titolo', body: 'Testo descrittivo.' },
    { selector: null,         title: 'Fine',   body: 'Tour completato.' },
  ]);
});
```

- `selector` — qualsiasi CSS selector o `null` (tooltip centrato, no spotlight)
- Il tour non si mostra se `gp_wizard_done` è già in localStorage
- Reset: `localStorage.removeItem('gp_wizard_done')` o bottone in `utils/onboarding.php`

## Export Excel (`includes/XlsxWriter.php`, `utils/export_xlsx.php`)

Writer XLSX nativo (ZIP + OpenXML), richiede `ZipArchive` (default PHP 8+).

```php
require_once 'includes/XlsxWriter.php';
$x = new XlsxWriter('Foglio1');
$x->addRow([['Testo', 's', 1], [123.45, 'n', 2]]);
// stile: 0=normale, 1=grassetto, 2=valuta€, 3=grassetto+valuta€
$x->output('nome.xlsx'); // invia header HTTP e body
```

## Dashboard live (`account/responsabile_live.php`)

Endpoint JSON (solo responsabile) con KPI giornata + mese. Risponde con `Content-Type: application/json` e `Cache-Control: no-store`. La dashboard fa polling ogni 30 secondi con `fetch()` e aggiorna i KPI card.

## Documenti — file upload

- Upload dir: `account/uploads/documenti/` (creata automaticamente se mancante)
- Filename: UUID hex 16 byte + estensione (`bin2hex(random_bytes(16))`)
- Estensioni consentite: pdf, png, jpg, jpeg, webp, docx, xlsx, odt, ods
- Dimensione max: 20 MB
- I file vengono serviti esclusivamente via `sala/doc_view.php?id=N` (autenticazione obbligatoria, no accesso diretto alla cartella)
- Solo il responsabile può caricare ed eliminare; tutti i ruoli possono visualizzare/scaricare

## Print guasto

`sala/print_guasto.php` è una pagina standalone (nessun nav, solo CSS inline) che auto-stampa via `window.print()` al caricamento. Riceve `?macchina=<nome>` via GET. Il logo sala viene preso da `impostazioni.logo_path`; se assente usa le iniziali del nome sala. Aggiungere `?noprint=1` per disabilitare l'auto-stampa (utile per preview).

Viene proposta dalla dialog in `sala/ticket.php` dopo la creazione di un nuovo ticket (`?print_mac=<nome>`).

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

- `ACTIVE` = turno attivo (1=mattino, 2=sera), persistito in `localStorage` chiave `gp_tab_<data>` (scoped per data — ogni nuova giornata parte dal turno 1)
- `recalcAll()` → ricalcola tutti i turni → aggiorna `RES[n]`
- `updateActive()` → aggiorna il banner statusbar con i dati di `RES[ACTIVE]`
- `showTab(n)` → cambia tab attivo, aggiorna localStorage, il campo hidden `salva_turno` e i dot indicator `.gp-swipe-dot`
- Il campo `<input name="salva_turno">` garantisce che il POST salvi solo il turno attivo
- Swipe touch su `#frm`: `touchstart` + `touchend`, soglia 55 px, ignora swipe verticali

## CSS (core.css + fogli modulo)

Variabili CSS in `:root`: `--accent`, `--accent-weak`, `--accent-ink`, `--green`, `--amber`, `--red`, `--bg`, `--surface`, `--border`, `--sh`, `--sh-popup`, etc.

Il blocco `html[data-theme="dark"]` ridefinisce tutte le variabili di colore con specificità `(0,1,1)`.

Classi riusabili:
- `.mini` + `.calcrow` — card metriche in griglia
- `.badge.open` / `.badge.closed` — badge verde/grigio
- `.recent-list` + `.recent-row` — lista cliccabile
- `.ticket-new-wrap` + `.tnf-grid` — form collassabile con griglia campi
- `.ul-table` / `.pm-table` — tabelle con header sticky, avatar, sort su `th[data-sort]`
- `.ob-*` — classi onboarding
- `th[data-sort]` — intestazione cliccabile per sort client-side (JS IIFE, `data-val` sulle `<td>`)
- `.gp-tour-hl` / `.gp-tour-tip` — spotlight e tooltip del tour (in `tour.css`)
- `.sf-theme` — bottone dark mode toggle nella sidebar
- `.live-badge` / `.live-dot` — badge live con pulse animation (in `dashboard.css`)
- `.gp-swipe-hint` / `.gp-swipe-dot` — dot indicator swipe nel giornaliero
- `.doc-folder-section` / `.doc-folder-header` / `.doc-folder-body` — sezione cartella documenti collassabile con D&D
- `.doc-draggable[data-doc-id]` + `.doc-dropzone[data-folder-id]` — drag & drop HTML5 per spostare documenti tra cartelle
- `.doc-btn-apri` / `.doc-btn-stampa` — azioni dirette riga documento (in `documenti.css`)
- `.doc-menu-wrap` / `.doc-menu-btn` / `.doc-menu` / `.doc-menu-item` — menu 3-dot a dropdown per azioni secondarie (Scarica, Sposta, Elimina)
- `.sf-avatar` con `style="background:linear-gradient(…)"` inline — avatar sidebar con doppia iniziale + gradiente deterministico

## Convenzioni da rispettare

- Nessun commento se il codice è auto-esplicativo
- SQL con snake_case, PHP con camelCase per variabili locali
- Redirect dopo ogni POST (`header('Location: ...'); exit;`)
- `audit()` su ogni operazione di scrittura significativa
- Non usare `is_responsabile()` per chiudere la giornata (operatori possono chiudere); usarlo invece per riaprire, eliminare, gestire macchine/utenti
- I nuovi moduli opzionali vanno aggiunti a: tabella `impostazioni` (toggle), `nav.php` (var + nav item + icona SVG), `impostazioni.php` (checkbox + POST handler)
