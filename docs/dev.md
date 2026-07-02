# Documentazione sviluppo — GestHall Suite

Riferimento tecnico per sviluppatori che installano, personalizzano o estendono il sistema.

---

## Requisiti di sistema

| Componente | Minimo | Raccomandato |
|---|---|---|
| PHP | 8.0 | 8.2+ |
| MySQL | 8.0 | 8.0 / MariaDB 10.6+ |
| Estensioni PHP | `pdo_mysql`, `mbstring`, `json` | + `zip` (export XLSX), `gd` o `imagick` (foto profilo) |
| Web server | Apache 2.4+ con `mod_rewrite` | Apache; Nginx richiede config manuale |
| HTTPS | opzionale in sviluppo | **obbligatorio in produzione** (cookie sicuri, PWA) |

---

## Installazione da zero

1. **Copia i file sul server** nella directory pubblica (es. `/var/www/html/cassa/`).

2. **Crea il database MySQL** e un utente dedicato:
   ```sql
   CREATE DATABASE cassa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'cassa_user'@'localhost' IDENTIFIED BY 'password_sicura';
   GRANT ALL PRIVILEGES ON cassa.* TO 'cassa_user'@'localhost';
   ```

3. **Apri il setup wizard** nel browser: `https://tuodominio.it/cassa/install/setup.php`  
   Il wizard guida in 6 passi: credenziali DB → configurazione sala → creazione admin → dati iniziali → moduli → fine.

4. **Dopo il setup**, il file `install/config.php` viene creato automaticamente.  
   **Non versionarlo mai** (è già in `.gitignore`).

### Installazione manuale (senza wizard)

```bash
# 1. Importa lo schema
mysql -u cassa_user -p cassa < install/schema.sql

# 2. Crea config.php manualmente
cp install/config.example.php install/config.php
# Modifica le credenziali nel file
```

---

## Aggiornamento di un'installazione esistente

Non esistono file di migration. Il procedimento è:

1. Aggiorna i file PHP/CSS/JS (git pull o upload).
2. Leggi le note di `CHANGELOG.md` per la nuova versione.
3. Se la release aggiunge colonne o tabelle, il CHANGELOG riporta le query da eseguire manualmente:
   ```sql
   -- Esempio: aggiunta colonna email nella v1.4.0
   ALTER TABLE utenti ADD COLUMN email VARCHAR(255) NULL AFTER nome;
   ```
4. Le chiavi `impostazioni` nuove vengono create automaticamente al primo accesso alle pagine che le usano (con `INSERT IGNORE`).

---

## Configurazione Apache

Il `.htaccess` nella root dell'applicazione gestisce:
- Rewrite delle URL (non necessario per questa app — non usa URL friendly)
- Blocco accesso a `install/`, `includes/`, `docs/`
- Pagine di errore personalizzate 403 e 404

```apache
# Esempio .htaccess minimo per installazione in sottocartella
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /cassa/
</IfModule>

# Blocca accesso diretto alle directory sensibili
<Files "*.php">
  <RequireAll>
    Require all granted
  </RequireAll>
</Files>
Options -Indexes
```

### Nginx (configurazione equivalente)

```nginx
location /cassa/install/ { deny all; }
location /cassa/includes/ { deny all; }
location /cassa/docs/ { deny all; }
location /cassa/account/uploads/profili/ { deny all; }
```

---

## Configurazione email

Il sistema usa `mail()` nativo di PHP. Perché le email arrivino correttamente:

### 1. Imposta `mail_from` in Impostazioni → Sistema

```
noreply@tuodominio.it
```

Se lasciato vuoto, il sistema usa `noreply@cassasala.it` come fallback (alta probabilità di spam su domini non configurati).

### 2. Configura sendmail/Postfix sul server

```bash
# Ubuntu/Debian
sudo apt install postfix
# Scegli "Internet Site" e inserisci il dominio

# Test
echo "Test" | mail -s "Test" destinatario@esempio.it
```

### 3. Alternativa: relay SMTP esterno

Se il server non ha un MTA locale, usa un relay (Mailgun, SendGrid, SMTP Gmail):

```ini
; php.ini o .htaccess
SMTP = smtp.mailgun.org
smtp_port = 587
sendmail_from = noreply@tuodominio.it
```

O installa `msmtp` come wrapper sendmail per relay SMTP autenticato.

### Funzioni email disponibili (`includes/mail/mailer.php`)

Tutte le email passano da questo file. Non inviare mai email direttamente con `mail()` fuori da qui.

| Funzione | Trigger | Token |
|---|---|---|
| `mail_reset_password($pdo, $uid, $email, $sett, $cfg)` | Richiesta reset da login | 64-char hex, 1 ora |
| `mail_nuovo_account($pdo, $uid, $email, $nome, $sett, $cfg)` | Account creato senza password | 64-char hex, 24 ore |
| `mail_cambio_password($email, $nome, $ip, $sett, $cfg)` | Cambio password da profilo | nessuno (notifica) |
| `mail_chiusura_giornata($revs, $tot, $mailVers, $data, $nomeOp, $appUrl, $sett, $cfg)` | Chiusura giornata | nessuno (riepilogo) |

I template email usano automaticamente logo e colore brand da `impostazioni`. L'header HTML è generato da `_mail_header_html()` (privata — non chiamare direttamente).

---

## White-label: configurare una nuova istanza per un cliente

Guida completa per preparare una copia dell'app per un nuovo cliente.

### Step 1 — Fork o copia del repository

```bash
git clone git@github.com:matteo-nini/gestHall-suite.git gestsuite-cliente
cd nome-cliente-desk
```

### Step 2 — Deploy e setup wizard

Segui la sezione [Installazione da zero](#installazione-da-zero). Durante il wizard:
- **Nome sala**: nome del cliente (es. "Sala Giochi Rossi")
- **Admin**: crea un account `responsabile` per il gestore
- **Moduli**: abilita/disabilita in base alle esigenze (prestiti, documenti, etc.)

### Step 3 — Brand color e logo

In **Impostazioni → Aspetto**:

1. **Colore accent**: inserisci il colore brand del cliente in formato HEX (es. `#dc2626`). Il sistema deriva automaticamente le varianti per badge, hover e dark mode.
2. **Logo**: carica il logo della sala (PNG/SVG, max 2 MB). Viene usato in header, email e stampa guasto.
3. **Nome sala**: appare nel titolo del browser, nell'header login, nelle email.

### Step 4 — Configurazione sale e macchine

1. **Impostazioni → Turni**: configura numero turni (1-3), nomi e orari.
2. **Macchine**: aggiungi le VLT e AWP della sala con codice, tipo e fornitore.
3. **Fornitori**: configura i fornitori reali (NOVO, SNAI, etc. → nomi della sala).
4. **Impostazioni → Email**: imposta `mail_from` con l'email del dominio della sala.

### Step 5 — Operatori e revisori

1. Crea gli utenti operatori e assegna il ruolo `operatore`.
2. Crea almeno un `revisore` con email valida per ricevere le notifiche di chiusura giornata.
3. Se il revisore non accede mai all'app, basta l'email: riceverà il riepilogo versamento ad ogni chiusura.

### Step 6 — Test pre-consegna

- [ ] Login funziona per tutti i ruoli
- [ ] Cassa giornaliera: inserisci un turno di prova e chiudi la giornata
- [ ] Email revisore ricevuta alla chiusura giornata
- [ ] Reset password: richiedi un reset e verifica che il link arrivi
- [ ] PWA: installa sul telefono del gestore
- [ ] Dark mode: funziona correttamente con il brand color scelto

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
// Default: legge solo l'ultimo turno del giorno (issue Q-01 — sottostima report mensili)
riepilogo_giornata($pdo, '2026-06-01');

// Con opId: aggrega tutti i turni dell'operatore (corretto, usato nei report per operatore)
riepilogo_giornata($pdo, '2026-06-01', 3);
```

**Attenzione**: con `$opId = 0` la query usa `ORDER BY numero DESC LIMIT 1`, restituendo solo l'ultimo turno. Questo è corretto per la dashboard live, ma causa sottostima nei report mensili aggregati su giornate con 2 turni (issue #11 — fix pianificato).

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

Per resettare il tour: `localStorage.removeItem('gp_wizard_done')` oppure il pulsante "Rivedi guida popup" in `docs/onboarding.php`.

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
/
├── includes/           Codice condiviso caricato da ogni pagina
│   ├── auth.php        Sessioni, CSRF, rate limiting, guard functions
│   ├── lib.php         Logica di dominio: calcoli, helper, query aggregate
│   ├── db.php          Singleton PDO + config()
│   ├── nav.php         Sidebar HTML, topbar, dark mode, brand inject
│   ├── XlsxWriter.php  Writer XLSX nativo (ZIP + OpenXML)
│   └── mail/
│       └── mailer.php  Tutte le funzioni di invio email
│
├── account/            Pagine accessibili a tutti i ruoli autenticati
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php   Vista unificata per ruolo (responsabile/revisore/operatore)
│   ├── profilo.php
│   ├── responsabile_live.php  Endpoint JSON KPI (solo responsabile)
│   ├── reset_password.php
│   ├── reset_confirm.php
│   └── admin/          Pagine solo responsabile
│       ├── utenti.php
│       └── impostazioni.php
│
├── cassa/              Pagine di cassa (operatore + responsabile)
│   ├── giornaliero.php Il form principale della cassa giornaliera
│   ├── settimanale.php
│   ├── mensile.php
│   └── annuale.php
│
├── sala/               Moduli di sala (ticket, turni, documenti, prestiti)
│   ├── turni.php       Calendario turni mensile
│   ├── ticket.php      Ticket assistenza macchine
│   ├── prestiti.php    Prestiti e rientri
│   ├── documenti.php   Archivio documenti con cartelle e D&D
│   ├── contatti.php    Rubrica contatti sala
│   ├── macchine.php    Gestione macchine VLT/AWP e fornitori
│   ├── doc_view.php    Serve documenti con autenticazione (non accesso diretto)
│   └── print_guasto.php  Pagina stampa standalone (no nav, auto-print)
│
├── utils/              Utility e tool interni
│   ├── export.php      Export CSV mensile
│   ├── export_xlsx.php Export XLSX settimanale/mensile/annuale
│   └── onboarding.php  Guida interattiva e reset tour
│
├── assets/
│   ├── css/
│   │   ├── core.css    Design system: variabili, layout, componenti riusabili
│   │   ├── login.css
│   │   ├── tour.css    Spotlight e tooltip tour onboarding
│   │   └── *.css       Fogli per pagina specifica
│   └── js/
│       ├── sidebar.js  Toggle sidebar mobile
│       ├── tour.js     Sistema onboarding spotlight
│       ├── toast.js    Notifiche toast
│       └── *.js        Script per pagina specifica
│
├── install/
│   ├── schema.sql      Schema DB completo — unica fonte di verità
│   ├── setup.php       Wizard installazione (6 passi)
│   └── config.php      Credenziali DB — NON versionare
│
└── docs/               Documentazione (questo file è qui)
    ├── dev.md
    ├── features.md
    ├── issues.md
    ├── guida-operatori.md
    ├── guida-responsabili.md
    └── guida-revisori.md
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
