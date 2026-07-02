# Games Palace Desk

**Sistema di gestione cassa per sale giochi VLT/AWP**

Applicazione web completa per il controllo operativo quotidiano di una sala giochi: riconciliazione cassa, scassettamenti VLT, refill AWP, bet/win SNAI, ticket di assistenza, turni, prestiti e reportistica. PHP 8 + MySQL, zero framework, pronta per il white-label.

---

## Caratteristiche principali

### Cassa giornaliera
- **1–3 turni configurabili** (nomi e orari personalizzabili: Mattino · Sera · Notte) con schede separate — il salvataggio è sempre isolato al turno attivo
- **Swipe tra turni** su mobile con dot indicator; tap target 44 px per i tab; bottone Salva sticky in basso
- Conteggio banconote per taglio (5 € – 500 €) con totale automatico
- Scassettamenti VLT per macchina, raggruppati per fornitore configurabile (NOVO · INSPIRED · SPIELO o qualsiasi altro)
- Refill AWP con orario e importo per singola macchina
- Ticket vincita per fornitore
- **Alert giornata precedente aperta**: avviso automatico in cima alla pagina se ieri non è stata chiusa
- Formule di riconciliazione calcolate in tempo reale lato browser e confermate lato server
- Banner scostamento colorato (verde / giallo / rosso) con soglie configurabili
- **Auto-salvataggio locale**: il form si salva in `localStorage` ogni 500 ms — i dati sopravvivono a ricariche accidentali e vengono ripristinati automaticamente
- Chiusura e riapertura giornata con controllo ruolo

### Report e analisi
- **Settimanale** — dati Bet/Win SNAI con totali per fornitore, versamenti, bancomat e calcolo payout; badge +/−% a confronto con la settimana precedente
- **Mensile** — riepilogo per giorno; riga Δ% vs mese precedente in fondo ai totali; filtro per singolo operatore; tabella incasso VLT per macchina; stampa/PDF nativa
- **Annuale** — panoramica mese per mese con filtro operatore; link diretto al mensile
- **Export CSV** mensile e settimanale (separatore `;`, BOM UTF-8, numeri con virgola per Excel Italia)
- **Export Excel .xlsx** mensile — writer PHP nativo, zero dipendenze; include cassa giornaliera, Bet/Win SNAI e VLT per macchina con stili grassetto e formato valuta €
- **Dashboard responsabile**: KPI aggiornati ogni 30 secondi via polling JSON; badge live con pulse animation; grafici Chart.js — andamento incassi ultimi 30 giorni e ultimi 6 mesi; statistiche per operatore: turni compilati, scostamento medio, % turni corretti
- **Le mie performance** nella dashboard operatore: mini-grafico ultimi 30 turni, scostamento medio e % turni ok
- **Confronto mensile Δ%**: variazione percentuale vs mese precedente su incasso, ticket, bancomat e versamento

### Esperienza utente
- **Dark mode** — toggle luna/sole nella sidebar, persistenza `localStorage`, anti-FOUC; colori derivati con `color-mix()` in srgb per le varianti accent; compatibile con brand colore dinamico
- **Onboarding tour interattivo** — spotlight contest uale al primo accesso; steps diversi per giornaliero e dashboard; resettabile da Guida → "Rivedi guida popup"

### Sala
- **AWP** — registro refill con macchina, importo e orario
- **Turni** — calendario turni programmati per operatore con prezzi orari
- **Ticket assistenza** — apertura, chiusura con risoluzione e storico; i contatti dell'assistenza tecnica (numero, lock, password) compaiono automaticamente nel dialog; dopo ogni apertura compare un popup per stampare l'avviso guasto
- **Prestiti e rientri** — registro movimenti per persona con saldo corrente
- **Documenti** *(modulo opzionale)* — caricamento e distribuzione di PDF, Word, Excel, immagini. Solo il responsabile carica; tutti i ruoli visualizzano e scaricano. File serviti con autenticazione via `doc_view.php`

### Gestione macchine
- Parco VLT e AWP con tipo, fornitore, seriale, CIV e ordine di visualizzazione
- Storico guasti per macchina con conteggio ticket aperti/risolti
- Attivazione/disattivazione senza perdita dello storico

### Gestione utenti e ruoli

| Ruolo | Accesso |
|---|---|
| **Operatore** | Cassa giornaliera, AWP, turni, ticket, prestiti, documenti |
| **Responsabile** | Tutto + admin (macchine, fornitori, utenti, impostazioni, audit) + caricamento documenti |
| **Revisore** | Report in sola lettura (settimanale, mensile, annuale); calendario turni opzionale (vedi Permessi) |

Ogni utente può avere un **indirizzo email** configurato (da Profilo o da Impostazioni → Utenti). L'email abilita tre flussi automatici:
- **Reset password self-service**: l'utente inserisce il proprio username dalla pagina di login, riceve un link valido 1 ora e imposta la nuova password senza l'intervento del responsabile.
- **Nuovo account**: quando il responsabile crea un account senza password ma con email, viene inviato automaticamente un link di attivazione valido 24 ore. L'utente imposta la propria password al primo accesso.
- **Notifica cambio password**: dopo ogni cambio password eseguito dal profilo, l'utente riceve un'email di conferma con ora e IP. Se non è stato lui, può avvisare il responsabile.

### Impostazioni configurabili
- **Logo sala**: caricabile da Impostazioni, mostrato nella sidebar e nella pagina di login
- **Brand colori**: color picker con palette predefinita (24 colori) e anteprima live; il colore si propaga a sidebar, login, bottoni e badge. Varianti `--accent-weak` e `--accent-ink` derivate automaticamente
- Numero di turni (1–3) con nome e orari personalizzabili per turno e costo orario
- **Fornitori configurabili**: lista riordinabile, rinominabile, con toggle attivazione
- **Fuso orario**: selezionabile tra tutti i timezone IANA
- **Permessi unificati**: sezione unica per operatori (modifica calendario e turni), mobile (compilazione cassa e modifica turni da smartphone) e revisori (accesso opzionale al calendario turni in sola lettura)
- **Moduli opzionali**: Ticket assistenza · Prestiti e rientri · Documenti
- Dati assistenza tecnica (numero, lock, password)
- Email di sistema (`mail_from`): indirizzo mittente per tutte le email transazionali (reset, nuovo account, notifica cambio password, riepilogo versamento)
- Retention log audit (min 7 giorni)

### Sicurezza
- `password_hash` / `password_verify` per le credenziali
- CSRF token su ogni form POST
- Rate limiting login: blocco IP 15 minuti dopo 5 tentativi falliti
- Output sempre con `htmlspecialchars` — nessun XSS possibile
- Query esclusivamente con prepared statement PDO
- Documenti serviti via `doc_view.php` con verifica sessione (nessun accesso diretto alla cartella upload)
- Audit log completo: utente, IP, entità, dettaglio per ogni operazione significativa
- Sessioni con `cookie_httponly` e `cookie_samesite=Lax` (attivare `cookie_secure` in produzione HTTPS)
- **Reset password via email**: token `bin2hex(random_bytes(32))` con scadenza 1 ora, uso singolo; nessuna enumerazione utente nell'UI

### PWA e offline
- Progressive Web App installabile su desktop e mobile
- Manifest dinamico PHP, icona SVG adattiva
- Service worker: network-first per le pagine PHP, cache-first per gli asset statici

---

## Stack tecnico

| Componente | Tecnologia |
|---|---|
| Backend | PHP 8.1+ |
| Database | MySQL 8 / MariaDB 10.6+ |
| Query | PDO con prepared statement |
| Frontend | HTML / CSS / JS vanilla — zero framework |
| CSS | Custom properties + Grid/Flex |
| Grafici | Chart.js 4 (CDN) |
| Autenticazione | Sessioni PHP native |
| PWA | Web App Manifest + Service Worker |

---

## Setup

### Metodo raccomandato — Wizard web

Apri `install/setup.php` nel browser: il wizard guida in 6 passi (connessione DB, schema, account responsabile, nome sala e moduli, macchine, completato). Al termine **elimina la cartella `install/`**.

### Metodo alternativo — Riga di comando

```bash
mysql -u root -p -e "CREATE DATABASE cassa_sala CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p cassa_sala < install/schema.sql
```

Poi crea `install/config.php`:

```php
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'cassa_sala',
        'user'    => 'utente_db',
        'pass'    => 'password_db',
        'charset' => 'utf8mb4',
    ],
    'nome_sala'  => 'Nome Sala',
    'tolleranza' => 5,
];
```

`install/config.php` non va mai versionato.

### Deploy su hosting cPanel / SiteGround

1. Crea il database MySQL dal pannello e annota le credenziali
2. Carica i file via SFTP nella cartella pubblica
3. Apri `install/setup.php` dal browser e completa il wizard
4. Attiva HTTPS → imposta `'cookie_secure' => true` in `includes/auth.php`
5. **Elimina la cartella `install/`** dopo aver verificato l'accesso
6. Verifica che `sw.js` sia raggiungibile dalla root del dominio (necessario per la PWA)
7. Verifica che `account/uploads/` sia scrivibile dal web server
8. Verifica che `ZipArchive` sia abilitato in PHP (necessario per export Excel; standard in PHP 8+)

---

## Logica di cassa

### Formule per turno

```
cassetto      = contanti + refill_awp + differenze − ii_cassa − rientri
versamento    = scassettamenti_VLT − bancomat − ticket_pagati
totale_cassa  = cassetto + monete − versamento
scostamento   = totale_cassa − fondo_cassa         ← deve essere ≈ 0
```

Implementate in `includes/lib.php → calcola_turno()` (fonte di verità lato server) e replicate in `assets/js/giornaliero.js` per il ricalcolo live senza round-trip al server.

### Soglie scostamento

| Scostamento | Classe CSS | Significato |
|---|---|---|
| < 4 € | `ok` (verde) | Quadratura corretta |
| 4–5 € | `warn` (giallo) | Scostamento tollerabile |
| > 5 € | `bad` (rosso) | Da verificare |

---

## Permessi per operazione

| Operazione | Operatore | Responsabile | Revisore |
|---|:---:|:---:|:---:|
| Compila turno giornaliero | ✓ | ✓ | ✗ |
| Chiude giornata | ✓ | ✓ | ✗ |
| Riapre giornata | ✗ | ✓ | ✗ |
| Apre / chiude ticket assistenza | ✓ | ✓ | ✗ |
| Elimina ticket | ✗ | ✓ | ✗ |
| Registra prestito / rientro | ✓ | ✓ | ✗ |
| Visualizza documenti | ✓ | ✓ | ✗ |
| Carica / elimina documenti | ✗ | ✓ | ✗ |
| Salva dati Bet/Win settimanale | ✓ | ✓ | ✗ |
| Visualizza report | ✓ | ✓ | ✓ |
| Export CSV e Stampa PDF | ✓ | ✓ | ✓ |
| Export Excel .xlsx | ✓ | ✓ | ✓ |
| Gestione macchine | ✗ | ✓ | ✗ |
| Gestione utenti e password | ✗ | ✓ | ✗ |
| Impostazioni sala | ✗ | ✓ | ✗ |
| Audit log | ✗ | ✓ | ✗ |

---

## Struttura del progetto

```
games-palace-desk/
│
├── index.php                    Redirect → dashboard o login
│
├── account/
│   ├── login.php                Accesso con logo e brand colore sala + link reset password
│   ├── logout.php               Logout + distruzione sessione
│   ├── reset_password.php       Richiesta reset password (username → email link)
│   ├── reset_confirm.php        Conferma reset password (token → nuova password)
│   ├── dashboard.php            Dashboard operatore (avvio turni, performance 30gg)
│   ├── responsabile.php         Dashboard responsabile (KPI live, grafici, stats operatori)
│   ├── responsabile_live.php    Endpoint JSON per polling KPI ogni 30s
│   ├── profilo.php              Profilo utente + cambio password + foto
│   ├── uploads/
│   │   ├── sala/                Logo sala (caricato da Impostazioni)
│   │   └── documenti/           File modulo Documenti (UUID, accesso via doc_view.php)
│   └── admin/
│       ├── macchine.php         Parco macchine VLT/AWP (seriale, CIV, storico guasti)
│       ├── fornitori.php        Fornitori configurabili (add, rinomina, riordina, toggle)
│       ├── utenti.php           Gestione utenti e ruoli
│       ├── impostazioni.php     Impostazioni sala (brand, turni, moduli, assistenza, retention)
│       └── audit.php            Log operazioni con filtri e pulizia
│
├── cassa/
│   ├── giornaliero.php          Cassa giornaliera (1-3 turni, swipe mobile, auto-save)
│   ├── settimanale.php          Bet/Win SNAI settimanale + export CSV/stampa
│   ├── mensile.php              Riepilogo mensile (Δ%, filtro op, VLT per macchina, Excel)
│   └── annuale.php              Report annuale (filtro op) + export CSV/stampa
│
├── sala/
│   ├── awp.php                  Refill macchine AWP
│   ├── turni.php                Calendario turni programmati
│   ├── ticket.php               Ticket assistenza + dialog apertura/chiusura + stampa guasto
│   ├── prestiti.php             Registro prestiti e rientri per persona
│   ├── documenti.php            Modulo documenti (lista + upload)
│   ├── doc_view.php             Serve i documenti con autenticazione obbligatoria
│   └── print_guasto.php         Avviso fuori servizio stampabile (auto-print al caricamento)
│
├── utils/
│   ├── export.php               Export CSV aggregato
│   ├── export_xlsx.php          Export Excel .xlsx (cassa + Bet/Win + VLT per macchina)
│   └── onboarding.php           Guida operativa interattiva per ruolo
│
├── includes/
│   ├── auth.php                 Autenticazione, CSRF, rate limiting, ruoli
│   ├── lib.php                  Business logic: calcola_turno(), riepilogo_giornata(), helpers
│   ├── db.php                   Connessione PDO singleton + config()
│   ├── nav.php                  Sidebar dinamica + brand CSS + dark mode + tour
│   └── XlsxWriter.php           Writer XLSX nativo (ZIP + OpenXML, zero dipendenze)
│
├── assets/
│   ├── css/
│   │   ├── core.css             Design system: variabili, dark mode, layout base
│   │   ├── tour.css             Stili tour onboarding (spotlight + tooltip)
│   │   └── ...                  Fogli per componente/pagina
│   └── js/
│       ├── tour.js              Motore tour onboarding (spotlight, step, localStorage)
│       ├── giornaliero.js       Calcolo live + swipe + auto-save
│       └── ...                  sidebar.js, turni.js, toast.js, ob-banners.js, ...
│
├── install/
│   ├── setup.php                Wizard installazione (eliminare dopo il setup)
│   ├── schema.sql               Schema database completo (unica fonte di verità)
│   └── config.php               ⚠ Non versionare — credenziali DB
│
├── manifest.php                 Manifest PWA dinamico
├── sw.js                        Service worker
└── favicon.php / icon.php       Favicon e icone PWA dinamiche
```

---

## Note operative

- Il tab attivo (Mattino / Sera) nel giornaliero persiste in `localStorage` tra sessioni (`gp_tab`)
- L'auto-salvataggio locale del giornaliero si attiva dopo 500 ms di inattività; viene azzerato al submit
- Salvare un turno non tocca mai i dati dell'altro turno (campo `salva_turno` nel form)
- Le macchine disattivate non compaiono nel giornaliero ma rimangono nello storico e nei ticket
- Il banner dati assistenza nel dialog "Apri ticket" è visibile solo se almeno un campo è configurato in Impostazioni
- Il log audit include: utente, IP, entità modificata, dettaglio — un record per ogni scrittura significativa
- I documenti caricati vengono rinominati con UUID casuale e richiedono sempre autenticazione via `doc_view.php`
- Il brand accent aggiornato in Impostazioni ha effetto immediato su tutta l'interfaccia inclusa la pagina di login; l'anteprima live usa `document.documentElement.style.setProperty()` prima del salvataggio
- Dark mode: il tema si salva in `localStorage` (`gp-theme`); lo script anti-FOUC in `nav.php` applica `data-theme="dark"` prima del primo paint per evitare il flash bianco
- Tour onboarding: lo stato si salva in `localStorage` (`gp_wizard_done`); per farlo ripartire vai in Guida → "Rivedi guida popup"
- Export Excel: richiede l'estensione PHP `ZipArchive` (abilitata di default in PHP 8+, verificare su hosting condivisi)
- Dashboard live: il polling si avvia 30 s dopo il caricamento pagina e si ripete ogni 30 s — non genera richieste immediate all'apertura
