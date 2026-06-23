# Games Palace Desk

**Sistema di gestione cassa per sale giochi VLT/AWP**

Applicazione web completa per il controllo operativo quotidiano di una sala giochi: cassa, scassettamenti, bet/win SNAI, ticket di assistenza, turni e reportistica. Progettata per l'uso quotidiano da parte di operatori e responsabili, con interfaccia ottimizzata per desktop e mobile.

---

## Caratteristiche principali

### Cassa giornaliera
- Doppio turno (Mattino · Sera) con schede separate — il salvataggio è sempre isolato al turno attivo
- Conteggio banconote per taglio con calcolo automatico del totale contanti
- Scassettamenti VLT per macchina, raggruppati per fornitore (NOVO · INSPIRED · SPIELO)
- Refill AWP con orario e importo
- Ticket vincita per fornitore
- Formule di riconciliazione calcolate sia in tempo reale lato browser sia sul server
- Banner scostamento colorato (verde / giallo / rosso) configurabile per soglia
- **Auto-salvataggio locale**: il form si salva in `localStorage` ogni 500ms mentre scrivi — i dati sopravvivono a ricariche accidentali e vengono ripristinati automaticamente
- Chiusura e riapertura giornata con controllo ruolo

### Report e analisi
- **Settimanale** — dati Bet/Win SNAI per settimana con totali per fornitore, versamenti, bancomat e calcolo payout
- **Mensile** — riepilogo giornaliero con stampa/PDF nativa del browser
- **Annuale** — panoramica mese per mese con link diretto al mensile
- **Export CSV** mensile e settimanale (separatore `;`, BOM UTF-8 per Excel Italia)
- **Export Stampa/PDF** su tutti e tre i livelli: view HTML ottimizzata per la stampa con auto-trigger `window.print()`
- **Dashboard responsabile** con grafici Chart.js: andamento incassi ultimi 30 giorni (barre) e ultimi 6 mesi (linea)

### Sala
- **AWP** — registro refill con data, importo e macchina
- **Turni** — calendario turni programmati per operatore
- **Ticket assistenza** — apertura, chiusura con risoluzione e storico per macchina; i contatti dell'assistenza tecnica (numero, lock, password) compaiono automaticamente nel dialog di apertura
- **Prestiti e rientri** — registro movimenti per persona con saldo corrente

### Gestione macchine
- Parco macchine VLT e AWP con tipo, fornitore, ordine di visualizzazione
- **Seriale e CIV** per ogni macchina — editabili inline nella lista
- Storico guasti collassabile per macchina, con conteggio ticket aperti/risolti
- Attivazione/disattivazione senza perdita dello storico

### Gestione utenti e ruoli

| Ruolo | Accesso |
|---|---|
| **Operatore** | Cassa giornaliera, AWP, turni, ticket, prestiti |
| **Responsabile** | Tutto + admin (macchine, utenti, impostazioni, audit) |
| **Revisore** | Solo report in sola lettura: settimanale, mensile, annuale |

- Creazione utenti con descrizione esplicita di ciascun ruolo
- Reset password e attivazione/disattivazione da pannello admin
- Foto profilo caricabile
- Guida operativa (`onboarding.php`) con tab dedicati per operatore, responsabile e revisore

### Impostazioni configurabili
- Orari turni mattino/sera (per determinare il turno corrente nella dashboard)
- Costo orario per turno (visibile nei guadagni operatori)
- Permessi operatori per modifica turni programmati
- Moduli opzionali: Ticket assistenza · Prestiti e rientri
- **Dati assistenza tecnica**: numero di telefono, codice lock, password — mostrati agli operatori all'apertura di ogni ticket
- Retention log audit (minimo 7 giorni)

### Sicurezza
- Autenticazione con `password_hash` / `password_verify`
- CSRF token su ogni form POST
- **Rate limiting login**: blocco IP per 15 minuti dopo 5 tentativi falliti (tabella DB auto-creata)
- Tutti gli input utente passano per `htmlspecialchars` prima dell'output
- Query esclusivamente con prepared statement PDO
- Audit log completo con IP, utente, entità, dettaglio per ogni operazione significativa
- Sessioni con `cookie_httponly` e `cookie_samesite=Lax` (attivare `cookie_secure` in produzione HTTPS)

### PWA e offline
- **Progressive Web App** installabile su desktop e mobile (manifest dinamico PHP)
- Service worker con strategia network-first per le pagine PHP, cache-first per gli asset statici
- Icona SVG adattiva

---

## Stack tecnico

| Componente | Tecnologia |
|---|---|
| Backend | PHP 8.1+ |
| Database | MySQL 8 / MariaDB 10.6+ |
| ORM / Query | PDO con prepared statement |
| Frontend | HTML/CSS/JS vanilla — zero framework |
| CSS | Custom properties + layout Grid/Flex |
| Grafici | Chart.js 4 (CDN, caricamento opzionale) |
| Autenticazione | Sessioni PHP native |
| PWA | Web App Manifest + Service Worker |

---

## Setup

### 1. Database

Importa le migrazioni nell'ordine:

```bash
mysql -u root -p games_palace < sql/schema.sql
mysql -u root -p games_palace < sql/002_ticket_assistenza.sql
mysql -u root -p games_palace < sql/003_prestiti.sql
mysql -u root -p games_palace < sql/004_turni_programmati.sql
mysql -u root -p games_palace < sql/005_profilo_impostazioni.sql
mysql -u root -p games_palace < sql/006_moduli.sql
mysql -u root -p games_palace < sql/007_seriali_civ.sql
```

> Le migrazioni 004–007 aggiungono colonne con `IF NOT EXISTS`: sono sicure da eseguire più volte. La tabella `login_attempts` per il rate limiting viene creata automaticamente al primo accesso.

### 2. Configurazione

Rinomina `config.example.php` → `config.php` e compila:

```php
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'games_palace',
        'user'    => 'utente_db',
        'pass'    => 'password_db',
        'charset' => 'utf8mb4',
    ],
    'nome_sala'  => 'Games Palace',
    'tolleranza' => 5,   // € — soglia scostamento giornaliero (sopra = banner rosso)
];
```

`config.php` è escluso dal repository — non versionarlo mai.

### 3. Primo responsabile

Apri `install/setup.php` nel browser, crea il primo account responsabile, poi **elimina il file o la cartella `install/`**.

### 4. Parco macchine e operatori

Da `account/admin/macchine.php` inserisci tutte le VLT e AWP con fornitore, seriale e CIV. Da `account/admin/utenti.php` crea gli account operatori.

### 5. Impostazioni sala

Da `account/admin/impostazioni.php` configura:
- Orari turni mattino/sera
- Dati assistenza tecnica (numero, lock, password)
- Eventuali moduli opzionali da disabilitare

---

## Deploy su SiteGround (o hosting cPanel)

1. Crea il database MySQL dal pannello e annotare credenziali
2. In phpMyAdmin importa i file SQL nell'ordine indicato sopra
3. Carica i file via SFTP nella cartella pubblica
4. Compila `config.php` con i dati reali
5. Attiva HTTPS → imposta `'cookie_secure' => true` in `includes/auth.php`
6. Esegui `install/setup.php` dal browser, crea il responsabile, **elimina la cartella `install/`**
7. Verifica che `sw.js` sia raggiungibile dalla root del dominio (necessario per la PWA)

---

## Logica di cassa

### Formule per turno

```
cassetto      = contanti + refill_awp + differenze − ii_cassa − rientri
versamento    = scassettamenti_VLT − bancomat − ticket_pagati
totale_cassa  = cassetto + monete − versamento
scostamento   = totale_cassa − fondo_cassa         ← deve essere ≈ 0
```

Implementate in `includes/lib.php` → `calcola_turno()` (fonte di verità lato server) e replicate in JS nel giornaliero per il ricalcolo live senza round-trip al server.

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
| Compila turno giornaliero | ✓ (solo il proprio) | ✓ | ✗ |
| Chiude giornata | ✓ | ✓ | ✗ |
| Riapre giornata | ✗ | ✓ | ✗ |
| Apre/chiude ticket assistenza | ✓ | ✓ | ✗ |
| Elimina ticket | ✗ | ✓ | ✗ |
| Registra prestito/rientro | ✓ | ✓ | ✗ |
| Salva dati Bet/Win settimanale | ✓ | ✓ | ✗ |
| Visualizza report settimanale/mensile/annuale | ✓ | ✓ | ✓ |
| Export CSV e Stampa PDF | ✓ | ✓ | ✓ |
| Gestione macchine (seriale, CIV, attivazione) | ✗ | ✓ | ✗ |
| Gestione utenti e reset password | ✗ | ✓ | ✗ |
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
│   ├── login.php                Accesso con rate limiting
│   ├── logout.php               Logout + distruzione sessione
│   ├── dashboard.php            Dashboard operatore (KPI giorno + ultimi accessi)
│   ├── responsabile.php         Dashboard responsabile + grafici Chart.js
│   ├── profilo.php              Profilo utente + cambio password + foto
│   └── admin/
│       ├── macchine.php         Parco macchine VLT/AWP (seriale, CIV, storico guasti)
│       ├── utenti.php           Gestione utenti e ruoli
│       ├── impostazioni.php     Impostazioni sala (orari, assistenza, moduli, retention)
│       └── audit.php            Log operazioni con filtri e pulizia
│
├── cassa/
│   ├── giornaliero.php          Cassa giornaliera (doppio turno, auto-save)
│   ├── settimanale.php          Bet/Win SNAI settimanale + export CSV/stampa
│   ├── mensile.php              Riepilogo mensile + stampa/PDF
│   └── annuale.php              Report annuale + export CSV/stampa
│
├── sala/
│   ├── awp.php                  Refill macchine AWP
│   ├── turni.php                Calendario turni programmati
│   ├── ticket.php               Ticket assistenza tecnica
│   └── prestiti.php             Registro prestiti e rientri
│
├── utils/
│   ├── export.php               Export CSV mensile aggregato
│   └── onboarding.php           Guida operativa (tab per ruolo)
│
├── includes/
│   ├── auth.php                 Autenticazione, CSRF, rate limiting, ruoli
│   ├── lib.php                  Business logic, calcoli cassa, helpers
│   ├── db.php                   Connessione PDO singleton + config()
│   └── nav.php                  Sidebar di navigazione adattiva per ruolo
│
├── assets/
│   ├── css/                     Fogli di stile per componente
│   ├── js/                      Script per componente
│   └── img/                     Icone e immagini (gp-icon.svg per PWA)
│
├── sql/
│   ├── schema.sql               Schema principale
│   ├── 002_ticket_assistenza.sql
│   ├── 003_prestiti.sql
│   ├── 004_turni_programmati.sql
│   ├── 005_profilo_impostazioni.sql
│   ├── 006_moduli.sql
│   └── 007_seriali_civ.sql      Aggiunge colonne seriale e CIV alle macchine
│
├── manifest.php                 Manifest PWA dinamico (supporta sottocartelle)
├── sw.js                        Service worker (network-first PHP, cache-first asset)
└── config.php                   ⚠ Non versionare — credenziali DB e parametri sala
```

---

## Note operative

- Il tab attivo (Mattino / Sera) nel giornaliero persiste in `localStorage` tra sessioni
- L'auto-salvataggio locale del giornaliero si attiva dopo 500ms di inattività; viene azzerato al submit del form
- Salvare un turno non tocca mai il turno dell'altro operatore (campo `salva_turno` nel form)
- Le macchine disattivate non compaiono nel giornaliero ma rimangono nello storico scassettamenti e nei ticket
- Il banner dati assistenza nel dialog "Apri ticket" è visibile solo se almeno un campo è configurato in Impostazioni
- Il rate limiting login è IP-based: dopo 5 tentativi falliti in 15 minuti l'IP viene bloccato automaticamente
- Il log audit di ogni scrittura significativa include: utente, IP, entità modificata, dettaglio dell'operazione
