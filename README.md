# Games Palace Desk

Sistema di gestione cassa giornaliera per sala giochi VLT/AWP.

## Funzionalità

| Sezione | File | Accesso |
|---------|------|---------|
| Dashboard | `index.php` | Tutti |
| Cassa giornaliera | `giornaliero.php` | Tutti |
| Settimanale SNAI | `settimanale.php` | Tutti |
| Riepilogo mensile | `mensile.php` | Tutti |
| Refill AWP | `awp.php` | Tutti |
| Ticket assistenza | `ticket.php` | Tutti |
| Prestiti e rientri | `prestiti.php` | Tutti |
| Parco macchine | `macchine.php` | Responsabile |
| Gestione utenti | `utenti.php` | Responsabile |
| Audit log | `audit.php` | Responsabile |
| Export CSV | `export.php` | Responsabile |
| Guida operativa | `onboarding.php` | Tutti |

## Stack tecnico

- **Backend**: PHP 8+ con PDO
- **Database**: MySQL / MariaDB
- **Frontend**: HTML/CSS/JS vanilla (nessun framework)
- **Auth**: sessioni PHP, CSRF token, `password_hash`
- **Ruoli**: `operatore` · `responsabile`

## Setup iniziale

### 1. Database

Crea il database e importa gli schema nell'ordine:

```bash
mysql -u root -p games_palace < sql/001_schema.sql
mysql -u root -p games_palace < sql/002_ticket_assistenza.sql
mysql -u root -p games_palace < sql/003_prestiti.sql
```

### 2. Configurazione

Copia e compila `config.php`:

```php
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'games_palace',
        'user'    => 'root',
        'pass'    => 'password',
        'charset' => 'utf8mb4',
    ],
    'nome_sala'  => 'Games Palace',
    'tolleranza' => 5,   // € scostamento massimo accettabile (usato per il mini-card Scostamento)
];
```

### 3. Primo utente

Apri `setup.php` nel browser, crea il primo responsabile, poi **elimina il file**.

### 4. Parco macchine e utenti

Da `macchine.php` inserisci le VLT/AWP. Da `utenti.php` crea gli operatori.

## Struttura file

```
games-palace-desk/
├── index.php            Dashboard (KPI giornalieri + ultime giornate)
├── giornaliero.php      Cassa giornaliera (turno mattino / sera)
├── settimanale.php      Dati bet/win SNAI settimanali
├── mensile.php          Report mensile con stampa/PDF ed export CSV
├── awp.php              Refill macchine AWP
├── ticket.php           Ticket di assistenza tecnica per macchine
├── prestiti.php         Registro prestiti e rientri clienti/dipendenti
├── onboarding.php       Guida operativa per nuovi utenti
├── macchine.php         Gestione parco macchine VLT/AWP (responsabile)
├── utenti.php           Gestione utenti e password (responsabile)
├── audit.php            Log operazioni (responsabile)
├── export.php           Export CSV mensile (responsabile)
├── login.php            Pagina di accesso
├── logout.php           Logout + distruzione sessione
├── setup.php            Setup primo utente — eliminare dopo uso
├── auth.php             Auth: sessione, CSRF, ruoli, password_verify
├── lib.php              Business logic: calcoli cassa, helpers DB, audit
├── db.php               Connessione PDO + config()
├── config.php           Credenziali DB e parametri sala (non versionare)
├── nav.php              Menu di navigazione (funzione top_menu)
├── styles.css           CSS unico per tutto il progetto
└── sql/
    ├── 001_schema.sql             Schema principale (tabelle base)
    ├── 002_ticket_assistenza.sql  Tabella ticket_assistenza
    └── 003_prestiti.sql           Tabelle prestiti_persone + prestiti_movimenti
```

## Logica di cassa

### Formule (per turno)

```
cassetto      = contanti + refill_awp + differenze - ii_cassa - rientri
versamento    = scassettamenti_VLT - bancomat - ticket_pagati
totale_cassa  = cassetto + monete - versamento
scostamento   = totale_cassa - fondo_cassa   ← deve essere ≈ 0
```

### Soglie scostamento (banner colorato)

| Scostamento | Colore | Significato |
|-------------|--------|-------------|
| < 4 € | Verde | Ottimo |
| 4–5 € | Giallo | Tollerabile |
| > 5 € | Rosso | Da verificare |

## Ruoli e permessi

| Azione | Operatore | Responsabile |
|--------|-----------|--------------|
| Compila turno | Solo il proprio | Entrambi |
| Salva giornata | Solo turno attivo | Entrambi i turni |
| Chiude giornata | ✓ | ✓ |
| Riapre giornata | ✗ | ✓ |
| Apre/chiude ticket | ✓ | ✓ |
| Registra prestito/rientro | ✓ | ✓ |
| Aggiunge persona prestiti | ✗ | ✓ |
| Gestione macchine/utenti | ✗ | ✓ |
| Audit log | ✗ | ✓ |

## Sicurezza

- HTTPS obbligatorio in produzione (imposta `cookie_secure => true` in `auth.php`)
- Query con prepared statement su tutta la base di codice
- CSRF token su ogni form POST
- Audit log di tutte le operazioni significative (salvataggio, chiusura, apertura ticket, movimenti prestiti)
- Backup regolare del DB (su SiteGround usa i backup gestiti)

## Deploy su SiteGround

1. Crea database MySQL dal pannello e annota credenziali
2. In phpMyAdmin importa i file SQL nell'ordine
3. Carica i file via SFTP
4. Aggiorna `config.php` con i dati reali
5. Attiva HTTPS + imposta `cookie_secure => true` in `auth.php`
6. Esegui `setup.php`, crea responsabile, **elimina il file**

## Note operative

- Il tab attivo (Mattino / Sera) sopravvive ai salvataggi grazie a `localStorage`
- Salvare un turno non tocca il turno dell'altro operatore
- La guida per i nuovi utenti è disponibile all'indirizzo `onboarding.php`
