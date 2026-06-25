# Documentazione sviluppo — Games Palace Desk

Riferimento tecnico per sviluppatori che lavorano sul codebase.

---

## Architettura

PHP 8+ + MySQL, zero framework. Ogni pagina è un file PHP autonomo che segue un pattern fisso. CSS/JS vanilla, CSS custom properties per il design system.

### Pattern di ogni pagina PHP

```php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lib.php';
$user = require_login();        // o require_responsabile()
$cfg  = config();
$pdo  = db();
$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

// 1. POST handler (con check_csrf() come prima istruzione)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    // ... gestione POST ...
    header('Location: stessa-pagina.php?ok=1'); exit;
}

// 2. Query GET
// 3. HTML output
```

### Regole ferme

- Mai concatenare parametri nelle query — sempre prepared statement
- Mai stampare input utente senza `$h()`
- Redirect dopo ogni POST (`header('Location: ...'); exit;`)
- `audit()` su ogni operazione di scrittura significativa
- `is_responsabile()` per le azioni admin; NON usarlo per la chiusura giornata (gli operatori possono chiudere)
- Ogni form POST deve avere `<input type="hidden" name="csrf" value="<?= csrf_token() ?>">`

---

## Helper sempre disponibili (`includes/lib.php`)

| Funzione | Firma | Scopo |
|---|---|---|
| `db()` | `(): PDO` | Singleton connessione PDO |
| `config()` | `(): array` | Singleton config da install/config.php |
| `get_settings()` | `(PDO): array` | Tutte le chiavi da `impostazioni` come array associativo |
| `setting()` | `(PDO, string, string): string` | Singola impostazione con default |
| `eur()` | `(float): string` | Formatta come €1.234,56 |
| `audit()` | `(string, ?string, ?int, ?string): void` | Log operazione nell'audit_log |
| `is_responsabile()` | `(): bool` | True se ruolo === 'responsabile' |
| `is_revisore()` | `(): bool` | True se ruolo === 'revisore' |
| `ensure_giornata()` | `(PDO, string): array` | Crea/recupera record giornata per data |
| `ensure_turno()` | `(PDO, int, int): array` | Crea/recupera record turno (n=1 mattino, n=2 sera) |
| `csrf_token()` | `(): string` | Genera/recupera token CSRF dalla sessione |
| `check_csrf()` | `(): void` | Valida token POST, esce con 400 se non valido |
| `brand_derive()` | `(string): array` | Ricava CSS vars da hex: `--accent`, `--accent-weak`, `--accent-ink` |
| `calcola_turno()` | `(array): array` | Riconciliazione server-side turno |
| `get_turns()` | `(array): array` | Turni configurati (indicizzati 1-3) da settings |
| `base_url()` | `(string): string` | URL base + path, calcolato dinamicamente |
| `asset_url()` | `(string): string` | URL asset con cache-busting (?v=filemtime) |
| `sums_turno()` | `(PDO, int): array` | Somme aggregate di un turno (contanti, refill, scass, ticket) |
| `riepilogo_giornata()` | `(PDO, string, int=0): array` | Riepilogo finanziario giornata; il terzo parametro filtra per `operatore_id` |
| `get_fornitori()` | `(PDO): array` | Fornitori attivi con fallback |
| `nomi_mesi()` | `(): array` | Array mesi 1-12 in italiano |
| `arrotonda_versamento()` | `(float): float` | Arrotonda a multiplo di 5 |

### Note su `riepilogo_giornata()`

```php
// Tutte le giornate (comportamento standard)
riepilogo_giornata($pdo, '2026-06-01');

// Solo i turni dell'operatore 3
riepilogo_giornata($pdo, '2026-06-01', 3);
```

Quando `$opId > 0`, la query rimuove il `LIMIT 1` e aggiunge `AND operatore_id = $opId`, restituendo la somma di tutti i turni di quell'operatore nel giorno.

---

## Database

Schema completo in `install/schema.sql` — unica fonte di verità. Non creare file di migration: per ogni nuova tabella o colonna, aggiorna `schema.sql` e detta la query manuale all'utente per le installazioni esistenti.

### Tabelle principali

```
giornate          (id, data, stato[aperta|chiusa], chiusa_da, chiusa_il)
turni             (id, giornata_id, numero[1|2], operatore_id, fondo_cassa,
                   monete, bancomat, differenze, ii_cassa, rientri, note)
contanti          (id, turno_id, taglio, pezzi)
scassettamenti    (id, turno_id, macchina_id, importo)
ticket            (id, turno_id, fornitore, importo)
refill_awp        (id, turno_id, n_macchina, euro, ora)
macchine          (id, codice, tipo[VLT|AWP], fornitore, ordine, attiva)
utenti            (id, username, password_hash, nome, ruolo, attivo, foto)
snai_betwin       (id, data, fornitore, giocato, pagato)
audit_log         (id, utente_id, azione, entita, entita_id, dettaglio, ip, creato_il)
ticket_assistenza (id, data_apertura, macchina, problema, id_ticket,
                   risoluzione, data_chiusura, stato[aperto|risolto], creato_da)
prestiti_persone  (id, nome, saldo_iniziale, note)
prestiti_movimenti(id, data, persona_id, tipo[prestito|rientro], quantita,
                   note, creato_da, creato_il)
documenti         (id, nome, descrizione, filename, mime, ordine, visibile,
                   caricato_da, caricato_il)
fornitori         (id, nome, ordine, attiva)
turni_programmati (id, data, numero, operatore_id, creato_da, creato_il)
prezzi_turni      (nome[mattino|sera|notte], prezzo, aggiornato_il)
impostazioni      (chiave PK, valore, aggiornato_il)
login_attempts    (id, ip, attempted_at)
```

### Chiavi `impostazioni` note

| Chiave | Default | Uso |
|---|---|---|
| `num_turni` | 2 | Numero turni giornalieri |
| `turno_N_nome/inizio/fine` | Mattino 13-19 | Config turno N (1-3) |
| `operatori_modifica_turni` | 1 | Permesso calendar edit |
| `turno_edit_libero` | 1 | Permesso edit turni altrui |
| `modulo_assistenze` | 1 | Toggle modulo ticket |
| `modulo_prestiti` | 1 | Toggle modulo prestiti |
| `modulo_documenti` | 1 | Toggle modulo documenti |
| `brand_accent` | null | Hex colore accent (#rrggbb) |
| `logo_path` | null | Filename logo in uploads/sala/ |
| `retention_giorni` | 90 | Giorni conservazione audit |

---

## CSS design system (`assets/css/core.css`)

### Variabili CSS (`:root`)

```css
--accent          /* colore primario (bottoni, link attivi) */
--accent-weak     /* accent a 85% bianco — sfondi badge */
--accent-ink      /* accent × 0.60 — hover, testo su weak */
--bg              /* sfondo pagina */
--surface         /* card, panel */
--surface2        /* surface alternativo */
--surface3        /* surface hover */
--border          /* bordo principale */
--border2         /* bordo secondario */
--text            /* testo principale */
--muted           /* testo secondario */
--faint           /* testo terzario */
--green / --green-bg / --green-bd / --green-ink
--amber / --amber-bg / --amber-border / --amber-ink
--red / --red-bg / --red-bd / --red-ink
--r               /* border-radius card */
--rs / --rxs      /* border-radius medium / small */
--rpill           /* border-radius pill */
--sh              /* box-shadow card */
--sh-popup        /* box-shadow modal / tooltip */
--sidebar-w       /* larghezza sidebar principale (220px) */
```

### Dark mode (`html[data-theme="dark"]`)

Il blocco `html[data-theme="dark"]` in `core.css` ridefinisce tutte le variabili di colore. La specificità `(0,1,1)` supera `:root` `(0,1,0)`, quindi overrida anche le variabili iniettate da `nav.php` (brand accent PHP).

Per le varianti accent in dark mode si usa `color-mix()`:
```css
--accent-weak: color-mix(in srgb, var(--accent) 20%, #111827);
--accent-ink:  color-mix(in srgb, var(--accent) 55%, #e8edf5);
```

Questo adatta automaticamente le varianti a qualsiasi colore accent impostato come brand.

**Toggle e persistenza**:
- `localStorage` key: `gp-theme` (`'dark'` o `'light'`)
- Anti-FOUC: script inline all'inizio di `top_menu()` in `nav.php` che applica `data-theme` prima del primo paint
- Bottone `.sf-theme` con icone `.th-moon` / `.th-sun` (mostrata solo in dark mode) nel footer sidebar

**Aggiungere dark mode a un nuovo foglio CSS**: usa `html[data-theme="dark"] .nuova-classe` invece di `@media (prefers-color-scheme: dark)` per coerenza con il sistema.

### Brand derive (`brand_derive(string $hex): array`)

```php
// Calcola le 3 varianti da un hex
$vars = brand_derive('#2563eb');
// ['--accent' => '#2563eb', '--accent-weak' => 'rgb(216,225,252)', '--accent-ink' => 'rgb(21,59,140)']
```

Formule:
- `accent-weak`: `rgb(255*.85 + c*.15)` per ogni canale (bianco al 85% + accent al 15%)
- `accent-ink`: `rgb(c * .60)` per ogni canale (accent scurito al 60%)

Iniettato in ogni pagina da `nav.php` con `<style>:root{...}</style>`. Specificità inferiore a `html[data-theme="dark"]` quindi il dark mode vince sempre.

### Classi riusabili principali

| Classe | Uso |
|---|---|
| `.menu` | Topbar sticky (z-index 20) |
| `.sidebar` | Sidebar fissa sinistra (z-index 40, width: --sidebar-w) |
| `.topbar` | Sub-header sticky sotto il menu |
| `.badge.open / .closed` | Badge stato verde/grigio |
| `.mini + .calcrow` | Card metriche in griglia |
| `.recent-list + .recent-row` | Lista cliccabile |
| `.ul-table / .pm-table` | Tabelle con header sticky e sort |
| `th[data-sort]` | Colonna sortabile (data-val sulle td) |
| `.form-dialog` | Dialog stile modale condiviso |
| `.dlg-head / .dlg-actions` | Header e footer del dialog |
| `.ghost` | Bottone outline |
| `.btnlink` | Link stilizzato come bottone primario |
| `.ok / .warn / .bad` | Banner status (verde/giallo/rosso) |
| `.sf-theme` | Bottone dark mode toggle nella sidebar footer |

---

## XlsxWriter — writer XLSX nativo (`includes/XlsxWriter.php`)

Writer senza dipendenze esterne che produce file `.xlsx` validi (ZIP + OpenXML). Richiede `ZipArchive` (default in PHP 8+).

```php
require_once __DIR__ . '/../includes/XlsxWriter.php';
$xlsx = new XlsxWriter('Nome foglio');

// Ogni cella è [valore, tipo, stile]
// tipo: 's' = stringa, 'n' = numero
// stile: 0=normale, 1=grassetto, 2=valuta€, 3=grassetto+valuta€
$xlsx->addRow([
    ['Intestazione', 's', 1],
    ['Importo', 's', 1],
]);
$xlsx->addRow([
    ['Riga dati', 's', 0],
    [1234.56, 'n', 2],
]);
$xlsx->output('file.xlsx'); // invia gli header HTTP e il file
```

Stili predefiniti:
- `0` — normale (Calibri 10)
- `1` — grassetto
- `2` — valuta `#,##0.00 €`
- `3` — grassetto + valuta

---

## Tour onboarding (`assets/js/tour.js`, `assets/css/tour.css`)

Sistema tour spotlight contestuale. Si attiva al primo accesso (flag `gp_wizard_done` in `localStorage`).

```js
// Definisci i passi per la pagina corrente
GP_Tour.init([
  { selector: '.tabs',     title: 'Titolo',     body: 'Descrizione HTML sicura.' },
  { selector: '.save-btn', title: 'Salva',       body: 'Clicca per salvare il turno.' },
  { selector: null,        title: 'Passo libero', body: 'Nessun elemento evidenziato.' },
]);
```

- `selector` — qualsiasi selettore CSS valido; `null` mostra il tooltip centrato senza spotlight
- `title` — testo puro (viene escaped)
- `body` — HTML (non escaped — usa testo sicuro o literal HTML)

Il tour usa opacity crossfade (nessuna animazione su proprietà layout). Il tooltip si posiziona automaticamente sopra o sotto l'elemento evidenziato in base allo spazio disponibile.

Per resettare il tour: `localStorage.removeItem('gp_wizard_done')` oppure il pulsante "Rivedi guida popup" in `utils/onboarding.php`.

---

## Dashboard live (`account/responsabile_live.php`)

Endpoint JSON autenticato (solo responsabile) che restituisce i KPI della giornata corrente e del mese:

```json
{
  "ts": 1750000000,
  "stato": "aperta",
  "incasso_vlt": 1234.56,
  "versamento": 890.00,
  "incasso_mese": 45678.90,
  "giorni_mese": 18
}
```

La dashboard (`account/responsabile.php`) fa polling ogni 30 secondi con `fetch()` e aggiorna i 4 KPI card tramite `document.getElementById()`. Il badge `.live-badge` mostra un'animazione pulse dopo ogni fetch riuscita.

---

## Moduli opzionali — pattern per aggiungerne uno

1. **schema.sql**: aggiungi la tabella e la chiave `impostazioni` con default `'1'`
2. **impostazioni.php**: aggiungi checkbox nel POST handler (`$az === 'moduli'`) e nel form HTML
3. **nav.php**: aggiungi la var `$navSett['modulo_xxx']` e il nav item condizionale con icona SVG
4. **CLAUDE.md**: aggiorna la tabella dei moduli

---

## Logica cassa (`includes/lib.php → calcola_turno()`)

```
cassetto     = contanti + refill_awp + differenze - ii_cassa - rientri
versamento   = scassettamenti - bancomat - ticket_pagati
totale_cassa = cassetto + monete - versamento
scostamento  = totale_cassa - fondo_cassa
```

Replicata in JS (`assets/js/giornaliero.js → recalcAll()`) per il calcolo live senza round-trip. `calcola_turno()` è la fonte di verità per il salvataggio.

---

## Autenticazione e sicurezza (`includes/auth.php`)

- Sessioni PHP con `HttpOnly`, `SameSite=Lax` (attivare `Secure` in HTTPS)
- CSRF: token in sessione, validato con `check_csrf()` su ogni POST
- Password: `password_hash(PASSWORD_DEFAULT)` + `password_verify()`
- Rate limiting: tabella `login_attempts` — 5 tentativi in 15 min = blocco IP
- Tutti i ruoli passano per `require_login()` o `require_responsabile()`

---

## Asset e cache-busting

```php
asset_url('assets/css/core.css')
// → https://esempio.it/assets/css/core.css?v=1748000000
```

`asset_url()` appende `?v=filemtime($path)`. Se il file cambia, il browser scarica la versione aggiornata.

---

## PWA

- `manifest.php`: manifest dinamico che legge nome sala, accent e logo da impostazioni
- `sw.js`: service worker statico. Strategy: network-first per `.php`, cache-first per `.css/.js/.png`
- `favicon.php` / `icon.php`: generano SVG/PNG dinamici con le iniziali della sala o il logo

---

## File upload

### Logo sala
- Dir: `account/uploads/sala/`
- Naming: `logo_{timestamp}_{hex4}.{ext}`
- Estensioni: jpg, jpeg, png, gif, webp, svg
- Max: 2 MB

### Documenti
- Dir: `account/uploads/documenti/`
- Naming: `bin2hex(random_bytes(16)).{ext}` (UUID)
- Estensioni: pdf, png, jpg, jpeg, webp, docx, xlsx, odt, ods
- Max: 20 MB
- Accesso: solo via `sala/doc_view.php?id=N` con sessione attiva

### Foto profilo
- Dir: `account/uploads/profili/`
- Naming: `profilo_{uid}_{hex4}.{ext}`
- Estensioni: jpg, jpeg, png, webp
- Max: 5 MB

---

## Convenzioni

- SQL: `snake_case`
- PHP locale: `camelCase`
- CSS classi: `kebab-case`
- Nessun commento se il codice è auto-esplicativo
- Un file CSS per componente/pagina (core.css + fogli specifici)
- JS: IIFE per evitare pollution dello scope globale; `DOMContentLoaded` per i dialog
- Nessun framework JS — vanilla puro

---

## Struttura directory

```
includes/       auth.php, lib.php, db.php, nav.php, XlsxWriter.php
account/        login, logout, dashboard, profilo, responsabile, responsabile_live + admin/
cassa/          giornaliero, settimanale, mensile, annuale
sala/           awp, turni, ticket, prestiti, documenti, doc_view, print_guasto
utils/          export, export_xlsx, onboarding
assets/css/     core.css + fogli per pagina + tour.css
assets/js/      sidebar.js, giornaliero.js, turni.js, toast.js, tour.js, ...
install/        schema.sql, setup.php, config.php (non versionare)
```

---

## Aggiornare la documentazione

Dopo ogni modifica significativa aggiornare:

| File | Quando |
|---|---|
| `README.md` | Ogni nuova feature, cambio struttura |
| `CLAUDE.md` | Nuovi helper, tabelle, moduli, convenzioni |
| `install/schema.sql` | Ogni nuova tabella o chiave `impostazioni` |
| `install/setup.php` | Se si aggiunge un modulo opzionale (step 4) |
| `docs/` | Se cambiano funzionalità rilevanti per utenti/sviluppatori |
