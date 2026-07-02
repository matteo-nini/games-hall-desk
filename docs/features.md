# Games Palace Desk — Documentazione Funzionalità Complete

## 1. Overview

Games Palace Desk è un'applicazione web PHP+MySQL per la gestione della cassa giornaliera di una sala giochi con macchine VLT (Video Lottery Terminal) e AWP (Amusement With Prizes). Non usa framework: PHP 8+, PDO, HTML/CSS/JS vanilla. Progettata per essere white-label e rivendibile.

**Stack tecnico:** PHP 8+, MySQL 8 / MariaDB 10.6+, InnoDB, utf8mb4, PDO, HTML/CSS/JS vanilla, Chart.js (CDN), ZipArchive per export XLSX.

**Ruoli utente:** `operatore`, `responsabile`, `revisore`.

**File principali:**
- `includes/auth.php` — autenticazione, sessione, CSRF, rate limiting, setup guard
- `includes/lib.php` — logica di dominio, helper calcolo, utility
- `includes/db.php` — singleton PDO e config
- `includes/nav.php` — sidebar, tab bar mobile, stili globali
- `includes/mail/mailer.php` — invio email transazionali centralizzato

---

## 2. Autenticazione e Utenti

### 2.1 Login (`account/login.php`)

**Accesso:** pubblico, nessuna autenticazione richiesta. Se già autenticato, redirect automatico a `dashboard.php`.

**Campi form:**
- `username` — accetta username o indirizzo email
- `password`

**Logica di autenticazione (in `auth.php`):**
- Cerca l'utente per username nella tabella `utenti` con `attivo=1`
- Se il campo contiene `@`, tenta anche la ricerca per email (solo se univoca)
- Verifica la password con `password_verify()`
- In caso di successo: `session_regenerate_id(true)`, svuota i tentativi per quell'IP, redirect a `dashboard.php`
- In caso di fallimento: registra il tentativo fallito via `rate_limit_record()`

**Rate limiting:**
- Tabella `login_attempts` creata on-the-fly con `CREATE TABLE IF NOT EXISTS`
- Finestra: 15 minuti (900 secondi)
- Soglia: 5 tentativi
- Quando il limite è raggiunto, il form viene nascosto e viene mostrato un messaggio di blocco
- Pulizia stocastica (probabilità 1/30) dei tentativi più vecchi di 1 ora

**White label nel login:**
- Legge logo, brand accent e nome sala dalla tabella `impostazioni` se il DB è migrato
- Inietta blocco `:root{}` inline con variabili CSS brand se `brand_accent` è valido (`#rrggbb`)
- `meta theme-color` usa il brand accent o `#2563eb` come fallback
- Compatibilità DB: sonde `SELECT 1 FROM impostazioni / prezzi_turni` per rilevare installazioni parziali

**Setup guard (IIFE in `auth.php`):**
- Se non esiste nessun utente con ruolo `responsabile` nel DB, qualsiasi pagina (eccetto `setup.php` e `login.php`) viene reindirizzata a `install/setup.php`

### 2.2 Dashboard (`account/dashboard.php`)

Punto di atterraggio post-login per tutti i ruoli. Renderizza tre viste distinte nello stesso file in base al ruolo.

#### Vista Responsabile
- **KPI giornata odierna:** stato (aperta/chiusa), incasso VLT (via `riepilogo_giornata()`)
- **KPI mese:** giorni operativi, incasso VLT (query aggregata su giornate + scassettamenti)
- **Ultime 10 giornate:** stato e incasso VLT, lista cliccabile verso `giornaliero.php`
- **Versamenti in sospeso:** massimo 30 giornate chiuse senza conferma versamento
- **Versamenti recenti confermati:** massimo 30
- **Statistiche operatori ultimi 30 giorni:** scostamento medio, scostamento massimo, percentuale turni ok — calcolata in PHP con `calcola_turno()` su tutti i turni del periodo
- **Stipendi mese corrente:** da `turni_programmati` JOIN `prezzi_turni`; saltata silenziosamente se le tabelle non esistono (catch PDOException)
- **Grafico bar 30 giorni** e **grafico line 6 mesi** (Chart.js, dati iniettati come `GP_30D` / `GP_6M`)
- **Badge live:** polling ogni 30 secondi verso `account/responsabile_live.php`, aggiorna 4 KPI card senza reload pagina
- **Tour onboarding:** GP_Tour su 5 step

#### Vista Revisore
- **KPI mese:** conteggio giornate da confermare, totale confermato in euro, giorni confermati su giorni chiusi, percentuale copertura
- **Tabella "Da confermare":** massimo 60 giornate chiuse senza `versamento_confermato`; per ogni riga un form inline con bottone "Conferma ritiro" + JS confirm dialog
- **Tabella andamento mensile:** ultimi 6 mesi — giorni chiusi, confermati, copertura percentuale, totale euro
- **Storico versamenti confermati:** massimo 100 record — importo, chi ha confermato, data/ora, IP
- **Flash message** `?ok=confermato` dopo conferma riuscita

#### Vista Operatore
- **Rilevamento turno corrente:** basato sull'ora di sistema confrontata con `turno_mattino_inizio/fine` e `turno_sera_inizio/fine` da `impostazioni`; gestisce il caso del turno sera oltre mezzanotte (data effettiva = ieri)
- **Hero card turno:** chi è assegnato, stato (avviato/assegnato a me/assegnato ad altri/libero); bottone "Inizia turno" o "Vai alla cassa" in base a `iniziato_il`
- **Bottoni manuali:** se fuori orario, due bottoni Mattino/Sera
- **Card Stipendio mese:** guadagnato (turni già effettuati ≤ oggi), previsto (turni futuri), totale; riepilogo ultimi 3 mesi
- **Card Prossimi 6 turni**
- **Grid accesso rapido:** 6 link statici — Cassa, Turni, AWP, Assistenze, Prestiti, Profilo
- **Performance personali ultimi 30 giorni:** scostamento medio, percentuale turni ok, mini-grafico a barre (canvas-free, span con altezze proporzionali)
- **Versamenti in sospeso** (max 20) e **recenti confermati** (max 20), sola lettura

### 2.3 Profilo utente (`account/profilo.php`)

**Accesso:** qualsiasi ruolo autenticato (`require_login()`), nessuna restrizione di ruolo aggiuntiva.

**Campi modificabili:**
- Nome visualizzato
- Telefono
- Email
- Password (campo corrente + nuova password + conferma)
- Foto profilo (crop client-side via `profilo.js`, inviata come blob PNG 256×256 via XHR)

**Comportamenti:**
- Ogni modifica ai recapiti sincronizza automaticamente la voce nella tabella `contatti` tramite `sync_contact_utente()`
- Al cambio password, se l'utente ha un'email configurata, viene inviata una notifica via `mail_cambio_password()` (nessun link, solo info + IP)
- La foto profilo precedente viene eliminata dal filesystem al momento della sostituzione o rimozione
- Auto-migrazione silente di colonne DB (`telefono` in `utenti`, `utente_id` e `sistema` in `contatti`) via `ALTER TABLE ... ADD COLUMN` protetto da try/catch, eseguita ad ogni caricamento

### 2.4 Reset password

**Richiesta reset (`account/reset_password.php`):**
- Pagina pubblica, redirect a `dashboard.php` se già autenticato
- Campo: `username`
- Ricerca dell'utente per username, poi lookup email in `utenti`
- User enumeration protection: risposta identica sia se l'username non esiste sia se l'utente esiste con email configurata; errore esplicito solo se l'email mancante (trade-off UX documentato)
- Delega l'invio email a `mail_reset_password()` in `includes/mail/mailer.php`
- Token: 64 caratteri hex, valido 1 ora, salvato in `password_reset` con `scade_il = NOW() + 1 ora`

**Conferma reset (`account/reset_confirm.php`):**
- Pagina pubblica (redirect a dashboard se già loggato)
- Riceve il token via GET
- Valida il token: esiste, non scaduto (`scade_il`), non già usato (`usato=0`)
- Mostra form per nuova password
- Al POST: aggiorna `password_hash` in `utenti`, marca il token `usato=1`, logga l'operazione
- Supporta anche il flusso "nuovo account senza password" (stessa tabella, scadenza 24 ore)

### 2.5 Endpoint live responsabile (`account/responsabile_live.php`)

**Accesso:** esclusivo al ruolo `responsabile` (`require_responsabile()`).

**Tipo:** endpoint JSON-only, nessun HTML. `Content-Type: application/json`, `Cache-Control: no-store`.

**Dati restituiti:**
- `timestamp` Unix
- Stato giornata corrente (aperta/chiusa/null)
- Incasso VLT odierno
- Versamento netto odierno
- Incasso mensile da scassettamenti
- Numero di giorni aperti nel mese

**Query eseguite:**
1. `SELECT stato FROM giornate WHERE data=?` — stato giornata odierna
2. Query aggregata `giornate + turni + scassettamenti WHERE g.data BETWEEN ? AND ?` — conteggio giorni e somma scassettamenti del mese
3. `riepilogo_giornata($pdo, $oggi)` — incasso VLT e versamento netto

**Uso:** la dashboard responsabile fa polling ogni 30 secondi con `fetch()`.

---

## 3. Cassa Giornaliera

### 3.1 Form giornaliero (`cassa/giornaliero.php`)

Cuore operativo dell'applicazione. Gestisce la compilazione della cassa di una sala giochi con tab per turno.

**Accesso:** tutti i ruoli autenticati. Se `mobile_giornaliero='0'` su viewport ≤ 760 px, il form viene nascosto via CSS e viene mostrato un avviso.

**Navigazione per data:** frecce ◀▶ e date-picker; ogni data corrisponde a una riga in `giornate`.

**Alert preventivo:** se la giornata precedente è ancora aperta, viene mostrato un avviso.

**Struttura a tab per turno (fino a 3 turni configurabili):**

#### Sezione Contanti
- Griglia banconote: per ogni taglio valido [5, 10, 20, 50, 100, 200, 500] un campo numerico `pezzi`
- Il totale contanti viene calcolato live come somma di `taglio × pezzi`

#### Sezione Scassettamenti VLT
- Una riga per ogni macchina VLT attiva (tabella `macchine` con `tipo=VLT`, ordinata per `ordine`)
- Campo importo per macchina
- Subtotali per fornitore calcolati live
- Totale scassettamenti

#### Sezione Ticket pagati
- Un campo importo per ciascun fornitore attivo (da tabella `fornitori`)
- Totale ticket

#### Sezione Refill AWP
- Sola lettura nella schermata principale; i refill si inseriscono da `sala/awp.php`
- Mostra il totale refill del turno

#### Campi turno
- `fondo_cassa` — fondo cassa iniziale del turno
- `monete` — importo monete contate
- `bancomat` — importo prelevato con bancomat (riduce il versamento)
- `differenze` — differenze positive (es. prestiti usciti)
- `ii_cassa` — seconda cassa (riduce il cassetto)
- `rientri` — rientri di denaro (riducono il cassetto)
- `note` — note libere per il turno

#### Calcolo live (JS e server-side identici)

```
cassetto     = contanti + refill_awp + differenze - ii_cassa - rientri
versamento   = scassettamenti - bancomat - ticket
totale_cassa = cassetto + monete - versamento
scostamento  = totale_cassa - fondo_cassa
```

**Banner scostamento (statusbar colorata):**
- `< 4€` → verde (class `ok`)
- `4–5€` → giallo (class `warn`)
- `> 5€` → rosso (class `bad`)
- Soglia configurabile via `cfg['tolleranza']` (default 5€)

**Navigazione tab:**
- Tab attivo persistito in `localStorage` con chiave `gp_tab_<data>` (scoped per data, ogni nuova giornata parte dal turno 1)
- Swipe touch su `#frm`: soglia 55 px, ignora swipe verticali
- Dot indicator `.gp-swipe-dot`

**Azioni POST:**
- Salvataggio turno attivo (campo hidden `salva_turno` garantisce salvataggio solo del turno corrente)
- CSRF obbligatorio su ogni POST

**Chiusura giornata:**
- Bottone "Chiudi giornata" (disponibile a tutti i ruoli che possono modificare i turni)
- Al POST: aggiorna `giornate.stato = 'chiusa'`, imposta `chiusa_da` e `chiusa_il`
- Invia automaticamente email HTML di riepilogo a tutti i revisori attivi con email configurata
- Email include: scassettamenti per fornitore, bancomat, ticket, versamento netto calcolato come `SUM(scassettamenti) - SUM(bancomat) - SUM(ticket)`

**Riapertura giornata:**
- Solo responsabile (`is_responsabile()`)
- Ripristina `stato = 'aperta'`

**Conferma ritiro versamento (responsabile):**
- Bottone "Conferma ritiro" visibile al responsabile
- Handler `az === 'conferma_ritiro'`
- Inserisce riga in `versamenti_confermati` con importo dichiarato, IP, user agent, timestamp

**Badge conferma versamento:**
- Badge verde `gd-conf-badge` visibile a operatori e responsabili se la giornata ha una riga in `versamenti_confermati`

**Tour onboarding:** GP_Tour integrato.

**Permessi mobile:**
- `mobile_giornaliero='1'` — abilita la compilazione da mobile
- `mobile_turni_edit='1'` — abilita la modifica dei turni da mobile

### 3.2 Refill AWP (`sala/awp.php`)

**Accesso:** tutti i ruoli autenticati.

**Funzione:** registro dei refill (ricariche fisiche di denaro nelle macchine AWP). Ogni importo inserito riduce il cassetto nella formula del giornaliero.

**Navigazione:** frecce ◀▶ e date-picker per giorno.

**Struttura:** due sezioni — Turno 1 (mattino) e Turno 2 (sera). Ciascuna con una tabella di N righe configurabili (via `refill_rows` in `config()`, default 10). Ogni riga ha: numero macchina, importo euro, ora.

**Azioni POST:**
- Salvataggio refill (redirect a `?ok=1` dopo POST)

---

## 4. Turni

### 4.1 Calendario turni (`sala/turni.php`)

**Accesso:** operatori e responsabili. Revisori: accesso in sola lettura se `revisori_vedi_turni='1'`, altrimenti redirect 403.

**Struttura:** griglia mensile a mese scorrevole (parametri GET: `anno`, `mese`) con due slot per giorno (mattino/sera), basata sulla tabella `turni_programmati`.

**Colonna destra:**
- Turno corrente rilevato dall'ora di sistema (13:00–19:00 = mattino, 19:00–02:00 = sera)
- Riepilogo guadagnato/previsto per l'utente loggato
- Lista turni effettuati
- Lista prossimi turni dell'utente loggato

**Azioni Responsabile:**
- Assegnare/rimuovere qualsiasi operatore a qualsiasi slot via dialog HTML nativo
- Accesso a tutti i bottoni +/−

**Azioni Operatore:**
- Auto-assegnazione solo a se stesso se il permesso `operatori_modifica_turni='1'` è attivo
- Se il permesso è disattivo, nessun form di auto-assegnazione

**Azioni Revisore (con `revisori_vedi_turni='1'`):**
- Sola lettura: nessun bottone +/−, nessun form

**Export:**
- CSV via GET `?export=csv`
- Stampa HTML standalone via GET `?export=print`

**Prerequisiti:** le tabelle `turni_programmati`, `prezzi_turni` e la colonna `turni.iniziato_il` devono esistere; in caso contrario viene mostrato un banner "Setup incompleto" e nessuna query viene eseguita.

**Rilevamento turno corrente:** basato sull'ora del server.

---

## 5. Report

### 5.1 Settimanale Bet/Win (`cassa/settimanale.php`)

**Accesso:** tutti i ruoli autenticati. Revisori: sola lettura (senza form di salvataggio).

**Logica periodi:** il mese è suddiviso in 4 periodi fissi — 1-7, 8-15, 16-23, 24-fine. Parametri GET: `anno`, `mese`, `sett` (1–4).

**Dati per giorno del periodo:**
- Giocato/Pagato/Inserito per ciascun fornitore
- Bancomat
- Versamento
- Ricavo
- Margine

**Sezioni:**
- **Riepilogo totali** con delta percentuale rispetto alla settimana precedente
- **Verifica VLT:** addebito/sisal/assegni con calcolo live JS

**Navigazione:** frecce prev/next settimana con wraparound su mese/anno.

**Export:**
- CSV via GET `?export=csv`
- Stampa HTML via GET `?export=print`

**Importazione XLS/XLSX SISAL:**
- Bottone di upload file XLS/XLSX
- AJAX verso `cassa/parse_xls_betwin.php` per parsing lato server
- AJAX verso `cassa/save_betwin_multi.php` per salvataggio batch

**Flash message:** `?ok` mostra banner di successo.

#### Parser XLS (`cassa/parse_xls_betwin.php`)

Endpoint JSON-only. Analizza un file XLS/XLSX SISAL multi-giorno caricato via POST multipart. Estrae i totali Giocato/Pagato per fornitore per ogni data. Non scrive sul DB. Output: `{ ok: true, dates: { "YYYY-MM-DD": { "FORNITORE": { giocato: n, pagato: n } } } }`.

#### Salvataggio batch bet/win (`cassa/save_betwin_multi.php`)

Endpoint JSON-only. Salva in batch i dati bet/win su più date in un'unica transazione MySQL. I fornitori vengono validati tramite `get_fornitori($pdo)`; chiavi non riconosciute vengono ignorate silenziosamente. Risponde sempre con JSON `{"ok": true|false, ...}`. Rollback automatico in caso di eccezione.

### 5.2 Mensile (`cassa/mensile.php`)

**Accesso:** tutti i ruoli autenticati (nessun controllo di ruolo specifico, `require_login()` sufficiente).

**Tipo:** sola lettura — nessun POST handler.

**Parametri GET:** `anno` (default anno corrente), `mese` (1–12, default mese corrente), `op` (ID operatore, default 0 = tutti i turni).

**Logica di aggregazione:**
1. Ciclo su tutti i giorni del mese: per ogni data chiama `riepilogo_giornata($pdo, $data, $opFiltro)` e accumula `incasso_vlt`, `ticket`, `bancomat`, `versamento`
2. Ciclo identico sul mese precedente per calcolare delta percentuali (closure `$delta()` inline)
3. Query GROUP BY su `snai_betwin` per aggregare giocato/pagato per fornitore nell'intervallo del mese
4. Query LEFT JOIN `macchine/scassettamenti/turni/giornate` per incasso VLT per singola macchina attiva

**Nota critica:** senza filtro operatore, `riepilogo_giornata()` prende SOLO l'ultimo turno della giornata (ORDER BY numero DESC LIMIT 1), non la somma di tutti i turni.

**Sezioni HTML:**
- Tabella "Cassa per giorno" con riga delta vs mese precedente
- Tabella "Bet/Win SNAI per fornitore" + "Sintesi cassa" (margine = bancomat + versamento - ricavo SNAI)
- Tabella "Incasso VLT per macchina" (mostrata solo se ci sono macchine VLT attive)

**Export:** CSV e XLSX (link verso `utils/export.php` e `utils/export_xlsx.php`).

### 5.3 Annuale (`cassa/annuale.php`)

**Accesso:** tutti i ruoli autenticati (`require_login()`), nessun vincolo di ruolo specifico.

**Tipo:** sola lettura — nessun POST handler. Due "azioni" GET: navigazione anno/operatore e export CSV.

**Aggregati mensili (5 colonne per mese):**
- Incasso VLT da scassettamenti
- Ticket
- Bancomat
- Versamento cassa sera
- Giorni operativi

**Parametri GET:** `anno` (navigazione), `op` (filtro operatore), `export=csv`.

**Logica:** 5 query SELECT aggregate con GROUP BY MONTH(g.data), popola array indicizzati 1–12. Righe cliccabili verso `mensile.php`. Il calcolo del versamento mensile è inline via subquery correlate (non usa `riepilogo_giornata()`).

---

## 6. Sala

### 6.1 Ticket assistenza (`sala/ticket.php`)

**Accesso:** operatori e responsabili — revisori esclusi via `require_not_revisore()`. Se il modulo `modulo_assistenze='0'` in `impostazioni`, redirect a `cassa/giornaliero.php`.

**Funzione:** CRUD completo per segnalazioni di guasto su macchine VLT/AWP.

**Elenco ticket:**
- Filtro GET: tutti/aperto/risolto
- Ordinamento: aperti prima, poi per id DESC
- Colonne: data apertura, macchina, problema, ID ticket, stato (badge aperto/risolto), data chiusura, creato da

**Apertura nuovo ticket (dialog inline):**
- Campi: macchina (datalist autocompletamento da macchine attive), problema, ID ticket fornitore
- Mostra i parametri di contatto del tecnico (numero, lock, password) letti da `impostazioni`
- Dopo la creazione: se presente `?print_mac=<nome>`, viene aperta automaticamente una seconda dialog che propone la stampa dell'avviso

**Chiusura ticket:**
- Campi: risoluzione, data chiusura
- Aggiorna `stato = 'risolto'`

**Eliminazione ticket:**
- Solo responsabile

**Print guasto (`sala/print_guasto.php`):**
- Pagina standalone senza nav, CSS inline puro
- Riceve `?macchina=<nome>` via GET
- Mostra logo sala (da `impostazioni.logo_path`) o iniziali come fallback
- Nome sala, banner "Macchina fuori servizio", testo di scuse, data odierna
- Bottone "Stampa avviso" (nascosto in `@media print`)
- `window.print()` automatico dopo 700 ms al caricamento (disabilitabile con `?noprint=1`)
- Nessuna query SQL, nessun POST handler

### 6.2 Prestiti e rientri (`sala/prestiti.php`)

**Accesso:** operatori e responsabili — revisori esclusi via `require_not_revisore()`. Se il modulo `modulo_prestiti='0'`, redirect a `cassa/giornaliero.php`.

**Funzione:** gestione di prestiti a persone fisiche e relativi rientri, con riflesso automatico sui turni.

**Effetti sui turni:** ogni movimento viene automaticamente riflesso sul turno sera (turno 2) della giornata corrispondente:
- Prestiti incrementano `turni.differenze`
- Rientri incrementano `turni.rientri`

**Layout:**
- Topbar con totale "dare" complessivo (saldo aggregato di tutte le persone)
- Griglia di card: una per persona, mostra saldo corrente (positivo = deve restituire)
- Dialog "Aggiungi movimento": pre-aperto via `?nuovo` in GET
- Dialog "Aggiungi persona"
- Tabella movimenti con sort client-side per colonna e filtro per persona via `?p=<id>`

**Campi movimento:**
- Data
- Persona (select)
- Tipo: `prestito` o `rientro`
- Quantità (importo in euro)
- Note

**Campi persona:**
- Nome (UNIQUE)
- Saldo iniziale
- Note

**Azioni POST:**
- Aggiunta persona
- Aggiunta movimento
- Modifica movimento
- Eliminazione movimento (solo responsabile)

### 6.3 Documenti (`sala/documenti.php`)

**Accesso:** `require_login()`. Verifica `modulo_documenti='1'` in `impostazioni`. Tre livelli: revisore (sola lettura), operatore (upload e gestione propri documenti), responsabile (tutte le azioni incluse eliminazione e gestione cartelle).

**Funzione:** gestione documenti aziendali (PDF, immagini, Office) organizzati in cartelle.

**Estensioni consentite:** pdf, png, jpg, jpeg, webp, docx, xlsx, odt, ods. Dimensione max: 20 MB.

**Upload dir:** `account/uploads/documenti/`. Filename: UUID hex 16 byte + estensione.

**Struttura cartelle:**
- Documenti raggruppati per cartella (PHP-side grouping)
- Cartelle collassabili con sezioni `.doc-folder-section`
- Drag and drop HTML5 per spostare documenti tra cartelle (form sintetico via JS, usa `GP_CSRF`)
- Ordine cartelle e documenti gestito con `ordine` numerico

**Visualizzazione documento:**
- Nome, descrizione, icona tipo file
- Bottone "Apri" (visualizzazione inline) e "Stampa"
- Menu 3-dot: Scarica, Sposta cartella, Elimina

**Ricerca:** live client-side su nome, descrizione, meta.

**Dialog HTML nativi:** upload documento, gestione cartelle (aggiungi/rinomina/elimina cartella).

**Visualizzazione/download (`sala/doc_view.php`):**
- Endpoint sicuro — proxy autenticato per i file in `account/uploads/documenti/`
- Accesso diretto alla cartella non consentito
- Modalità inline (default, es. PDF nel browser) o download forzato (`?dl`)
- `Content-Type` letto dal DB (campo `mime`), non rilevato dal filesystem
- `X-Content-Type-Options: nosniff`
- Filename per `Content-Disposition` costruito da `nome` DB + estensione fisica, codificato RFC 5987 (UTF-8'')

### 6.4 Contatti (`sala/contatti.php`)

**Accesso:** `require_login()`. Operatori e responsabili: CRUD completo. Revisori: sola lettura.

**Funzione:** rubrica aziendale per tecnici, fornitori, personale esterno della sala.

**Campi contatto:**
- Nome
- Ruolo/categoria
- Telefono (link `tel:`)
- Email (link `mailto:`)
- Note

**Layout:** tabella con avatar deterministico (`avatar_initials` + `avatar_style`), colonna Note troncata a 60 caratteri in visualizzazione.

**Funzionalità:**
- Ordinamento client-side per colonna
- Ricerca live sul testo
- Menu 3-punti per riga (Modifica, Elimina)

**Dialog HTML nativi:** aggiungi contatto, modifica contatto.

**Campo ordine:** assegnato sequenzialmente (MAX(ordine)+1), non esposto in UI (nessun drag & drop).

**CSS condiviso:** `utenti.css` (condiviso con gestione utenti, non un foglio dedicato).

---

## 7. Impostazioni (`account/admin/impostazioni.php`)

**Accesso:** esclusivo al responsabile (`require_responsabile()`).

**Layout:** a colonna singola con sidenav sticky a 5 sezioni (Identità, Turni, Permessi, Moduli, Assistenza, Sistema), navigazione evidenziata via IntersectionObserver.

**Pattern:** `check_csrf()` unico all'inizio del blocco POST, poi dispatch su `$_POST['azione']`. Ogni azione termina con `header('Location: impostazioni.php?ok=1'); exit;`.

**Prerequisiti:** se `impostazioni` e `prezzi_turni` non esistono, mostra banner "Setup incompleto" e blocca tutti i POST.

**11 azioni POST distinte:**

### 7.1 Sezione Identità

**Azione `identita`:**
- Campo: nome sala (scritto sia in `impostazioni` sia in `install/config.php` via `var_export`)
- Campo: slogan/descrizione sala
- Campo: brand accent color (hex `#rrggbb`)
- Upload logo (file immagine)

**Azione `contatti`:**
- Telefono sala
- Email sala
- Sito web

### 7.2 Sezione Turni

**Azione `turni`:**
- Numero turni configurabili: 1, 2 o 3 (clamped con min/max)
- Per ogni turno (1, 2, 3): nome, ora inizio, ora fine
- Ogni scrittura via chiavi `turno_N_nome`, `turno_N_inizio`, `turno_N_fine` in `impostazioni`

**Azione `prezzi`:**
- Prezzo per turno mattino (in euro, da `prezzi_turni`)
- Prezzo per turno sera
- Usato per il calcolo stipendi degli operatori

### 7.3 Sezione Permessi

**Azione `permessi`** (5 chiavi in un unico submit):

| Chiave | Default | Descrizione |
|---|---|---|
| `operatori_modifica_turni` | `'1'` | Operatori possono aggiungere se stessi al calendario turni |
| `turno_edit_libero` | `'1'` | Operatori possono modificare qualsiasi turno giornaliero |
| `mobile_giornaliero` | `'0'` | Abilita compilazione cassa da mobile (≤ 760 px) |
| `mobile_turni_edit` | `'0'` | Abilita modifica turni da mobile |
| `revisori_vedi_turni` | `'0'` | Revisori possono vedere il calendario turni in sola lettura |

### 7.4 Sezione Moduli

**Azione `moduli`** — attiva/disattiva i moduli opzionali:

| Chiave | Modulo | Nav item |
|---|---|---|
| `modulo_assistenze` | Ticket assistenza | `sala/ticket.php` |
| `modulo_prestiti` | Prestiti e rientri | `sala/prestiti.php` |
| `modulo_documenti` | Documenti | `sala/documenti.php` |

### 7.5 Sezione Assistenza

**Azione `assistenza`:**
- Numero tecnico (telefono del tecnico di riferimento)
- Numero lock (PIN/lock macchine)
- Password tecnico

### 7.6 Sezione Sistema

**Azione `timezone`:**
- Fuso orario (scritto in `install/config.php`)

**Azione `retention`:**
- Giorni di retention per il log audit (default 90)
- Il valore viene usato da `account/admin/audit.php` per eliminare record vecchi

**Azione `email`:**
- `mail_from` — indirizzo mittente per le email transazionali (fallback `noreply@cassasala.it` se vuoto)

---

## 8. Admin

### 8.1 Macchine e Fornitori (`account/admin/macchine.php`)

**Accesso:** esclusivo al responsabile (`require_responsabile()`).

**Struttura:** due tab — "Macchine" e "Fornitori".

**Tab Macchine:**

Dati caricati con tre query separate: tutte le macchine (ordinate per tipo/ordine/codice), tutti i ticket di assistenza (per storico guasti per macchina), tutti i fornitori.

**Campi macchina:**
- Codice (UNIQUE)
- Tipo: VLT o AWP
- Fornitore
- Seriale
- CIV
- Ordine (posizione nella griglia)
- Attiva (flag boolean)

**Azioni:**
- Aggiunta macchina (dialog HTML nativo)
- Modifica macchina (dialog)
- Disattivazione macchina (confirm nativo JS)
- Riattivazione macchina

**Tab Fornitori:**

**Campi fornitore:**
- Nome
- Attiva (flag)
- Ordine

**Azioni:**
- Aggiunta fornitore
- Modifica fornitore
- Disattivazione fornitore
- Riordino via drag-and-drop (submit automatico al dragend)

**JS client-side:** switch tab, ricerca live filtrata per tab, drag-and-drop riordino fornitori, dialog con chiusura al clic esterno, confirm nativo su disattivazione macchina.

**Auto-migrazione silente:** aggiunge le colonne `seriale` e `civ` alla tabella `macchine` se non esistono (try/catch silenzioso — da non ripetere in produzione).

### 8.2 Gestione Utenti (`account/admin/utenti.php`)

**Accesso:** esclusivo al responsabile (`require_responsabile()`).

**Struttura:** tabella utenti con avatar deterministico (o foto profilo), 5 dialog HTML nativi aperti via JS.

**Dati caricati:** una sola SELECT carica tutti gli utenti in memoria; KPI e conteggi ricavati via `array_filter` in PHP (nessuna query JOIN o subquery).

**Azioni POST (6 distinte, tutte protette da CSRF):**

1. **Crea utente:**
   - Campi: username, nome, email, ruolo, password (opzionale)
   - Se email presente e password assente: invia email di attivazione account via `mail_nuovo_account()` con token valido 24 ore
   - Chiama `audit()`

2. **Modifica utente:**
   - Campi modificabili: nome, email, ruolo, username
   - Chiama `audit()`

3. **Cambia password:**
   - Nuova password impostata dal responsabile
   - Notifica email all'utente se ha email configurata
   - Chiama `audit()`

4. **Reset password via email:**
   - Genera token e invia email tramite `mail_reset_password()`
   - Chiama `audit()`

5. **Attiva/disattiva utente:**
   - Toggle flag `attivo`
   - Chiama `audit()`

6. **Elimina utente:**
   - Eliminazione definitiva
   - Chiama `audit()`

**UI:** ricerca live lato client, sort su colonne, avatar deterministico o foto profilo.

### 8.3 Log Audit (`account/admin/audit.php`)

**Accesso:** esclusivo al responsabile (`require_responsabile()`).

**Funzione:** visualizzazione paginata di tutte le operazioni registrate in `audit_log`.

**Colonne:** timestamp, utente, azione, entità, entità ID, dettaglio, IP.

**Colorazione visiva:** funzione locale `audit_cls()` che mappa il nome dell'azione a una classe CSS.

**Azioni:**
- **Filtro:** per utente, per tipo azione, per data
- **Paginazione:** navigazione tra pagine
- **Export CSV:** scarica l'intero log
- **Applica retention:** elimina i record più vecchi di N giorni (N configurabile in Impostazioni → Sistema → `retention_giorni`, default 90)

---

## 9. Email Automatiche (`includes/mail/mailer.php`)

File di libreria per l'invio centralizzato di email HTML transazionali. Non accetta POST, non ha UI propria. Va incluso via `require_once`. Dipende da `lib.php` (usa `base_url()` e `arrotonda_versamento()`).

**Caratteristiche comuni a tutte le email:**
- HTML inline (compatibilità client email)
- Header colorato con brand accent letto da `impostazioni`
- Footer con nome sala
- Invio tramite funzione nativa PHP `mail()`
- Mittente: `impostazioni.mail_from`; fallback `noreply@cassasala.it` se vuoto

**4 funzioni pubbliche:**

| Funzione | Trigger | Scadenza token |
|---|---|---|
| `mail_reset_password()` | Reset password self-service | 1 ora |
| `mail_nuovo_account()` | Account creato senza password dal responsabile | 24 ore |
| `mail_cambio_password()` | Cambio password da `profilo.php` | N/A (nessun link) |
| `mail_chiusura_giornata()` | Chiusura giornata da `giornaliero.php` | N/A |

**Contenuto email chiusura giornata:** riepilogo scassettamenti per fornitore, bancomat, ticket pagati, versamento netto calcolato come `SUM(scassettamenti) - SUM(bancomat) - SUM(ticket)`, nome operatore, data, link app.

**Destinatari email chiusura:** tutti i revisori attivi con email configurata.

**4 funzioni interne private (prefisso `_mail_*`):** costruzione header, footer, wrapper HTML, gestione logo/accent. Non vanno chiamate direttamente dall'esterno.

---

## 10. PWA e Mobile

### 10.1 Progressive Web App

**Service Worker:** registrato da `nav.php` per tutte le pagine autenticate.

**Output buffer globale in `auth.php`:** inietta automaticamente nel `<head>` di ogni risposta HTML:
- Favicon
- Manifest PWA (`manifest.json`)
- Meta tag mobile (`viewport`, `theme-color`, ecc.)

**Installabilità:** la PWA è installabile su mobile e desktop tramite il meccanismo standard del browser.

### 10.2 Sidebar e navigazione

**Sidebar (generata da `nav.php`, funzione `top_menu(array $user)`):**

Struttura:
- `sb-head` — logo sala (da `impostazioni`) + pulsante collapse sidebar
- `sb-nav` — gruppi di link di navigazione (filtrati per ruolo e moduli attivi)
- `sb-util` — link Guida (`docs/onboarding.php`)
- `sb-foot` — avatar utente (`avatar_initials` + `avatar_style`), toggle dark mode, logout

**Link navigazione per ruolo:**

*Tutti i ruoli autenticati:*
- Dashboard
- Giornaliero (`cassa/giornaliero.php`)
- Settimanale (`cassa/settimanale.php`)
- Mensile (`cassa/mensile.php`)
- Annuale (`cassa/annuale.php`)
- Turni (`sala/turni.php`)
- Contatti (`sala/contatti.php`)

*Moduli opzionali (se abilitati in `impostazioni`):*
- Ticket assistenza (`sala/ticket.php`) — se `modulo_assistenze='1'`
- Prestiti (`sala/prestiti.php`) — se `modulo_prestiti='1'`
- Documenti (`sala/documenti.php`) — se `modulo_documenti='1'`

*Solo responsabile:*
- Impostazioni
- Macchine
- Utenti
- Audit

**Tab bar mobile:** seconda `nav.mob-tab-bar` con 2–4 voci rapide; non mostrata ai revisori.

**Variabili JS globali iniettate da `nav.php`:** `GP_BASE`, `GP_ROLE`, `GP_SALA`.

### 10.3 Dark mode

- Toggle luna/sole nel footer della sidebar (`.sf-theme`)
- `localStorage` key: `gp-theme` — valori `'dark'` o `'light'`
- Anti-FOUC: script inline all'inizio di `top_menu()` che applica `data-theme` prima del primo paint
- CSS: blocco `html[data-theme="dark"]` in `core.css` ridefinisce tutte le variabili; `color-scheme: dark` per form controls nativi
- Accent-weak e accent-ink in dark mode: `color-mix(in srgb, var(--accent) 20%, #111827)` e `color-mix(in srgb, var(--accent) 55%, #e8edf5)`

---

## 11. White Label e Brand

### 11.1 Configurazione brand

Chiave `brand_accent` in `impostazioni`: hex `#rrggbb`. Se presente e valido:
- `nav.php` inietta blocco `:root { --accent: #hex; --accent-weak: rgb(...); --accent-ink: rgb(...) }` in ogni pagina
- `login.php` inietta lo stesso blocco
- `meta theme-color` usa il brand accent

**Derivazione variabili CSS (`brand_derive($hex)`):**
- `--accent` — colore originale
- `--accent-weak` — blend 85% bianco + 15% accent (badge, sfondi)
- `--accent-ink` — accent × 0.60 (hover, testo su weak)

**Logo sala:** upload in Impostazioni → Identità. Usato in sidebar, login, email, `print_guasto.php`. Fallback alle iniziali del nome sala.

**Nome sala:** configurato in Impostazioni → Identità, scritto sia in `impostazioni` sia in `install/config.php`.

### 11.2 Avatar utenti

- `avatar_initials(string $name): string` — prime due iniziali (nome + cognome) in maiuscolo UTF-8; una sola parola = una sola iniziale
- `avatar_style(string $name): string` — attributo `style` inline con gradiente deterministico dal nome (crc32 → HSL, angolo 135deg, delta hue = 40). Colori consistenti tra sessioni per lo stesso nome.

### 11.3 Tour onboarding (`assets/js/tour.js`)

Sistema spotlight contestuale. Si attiva al primo accesso (flag `gp_wizard_done` in `localStorage`).

**Struttura:** `GP_Tour.init([{ selector, title, body }, ...])` — selector CSS o `null` (tooltip centrato).

**Reset:** `localStorage.removeItem('gp_wizard_done')` o bottone in `docs/onboarding.php`.

**Integrazione:** dashboard responsabile (5 step), giornaliero e altre pagine.

---

## 12. Moduli Opzionali

I tre moduli opzionali si attivano/disattivano da Impostazioni → Moduli. La chiave in `impostazioni` è `'1'` se abilitato, `'0'` se no.

### 12.1 Ticket assistenza (`modulo_assistenze`)

**Nav item:** `sala/ticket.php` — compare solo se `modulo_assistenze='1'`.

Funzionalità: CRUD ticket guasto, datalist macchine, contatti tecnico, stampa avviso cartaceo. Descritto in dettaglio nella sezione 6.1.

### 12.2 Prestiti e rientri (`modulo_prestiti`)

**Nav item:** `sala/prestiti.php` — compare solo se `modulo_prestiti='1'`.

Funzionalità: gestione prestiti, rientri con effetto automatico sui campi turno (`differenze`, `rientri`). Descritto in dettaglio nella sezione 6.2.

### 12.3 Documenti (`modulo_documenti`)

**Nav item:** `sala/documenti.php` — compare solo se `modulo_documenti='1'`.

Funzionalità: gestione cartelle e file con upload, drag and drop, proxy di download autenticato. Descritto in dettaglio nella sezione 6.3.

---

## Appendice: Formule di calcolo (fonte di verità)

### Formule turno (`calcola_turno()` in `lib.php` — replica JS in `giornaliero.js`)

```
cassetto     = contanti + refill_awp + differenze - ii_cassa - rientri
totale       = cassetto + monete + bancomat + ticket
vers_vlt     = scassettamenti - bancomat - ticket
vers_cassa   = cassetto + monete - fondo_cassa
errore       = vers_vlt - vers_cassa
```

### Arrotondamento versamento (`arrotonda_versamento()`)

Arrotonda al multiplo di 5 più vicino con soglia custom a 2 euro (non 2,5 — comportamento diverso dall'arrotondamento matematico standard): se il resto è > 2 si arrotonda in su, altrimenti in giù.

### Calcolo versamento in email/conferma

```
versamento_netto = SUM(scassettamenti) - SUM(bancomat) - SUM(ticket)
```

Su tutti i turni della giornata (non usa `riepilogo_giornata()`).

---

## Appendice: Export dati

### Export CSV mensile (`utils/export.php`)

**Accesso:** qualsiasi utente autenticato.

**Output:** file CSV con BOM UTF-8, separatore `;`, decimali con virgola.

**Struttura CSV:**
1. Riepilogo giornaliero: incassi VLT, ticket, bancomat, versamento, scassettamenti per fornitore
2. Bet/Win SNAI per fornitore: giocato, pagato, ricavo netto, totale inserito

Registra export su `audit_log` con azione `export_csv`.

### Export XLSX mensile (`utils/export_xlsx.php`)

**Accesso:** qualsiasi utente autenticato.

**Output:** workbook XLSX con tre sezioni in un unico foglio ("Cassa {mese} {anno}"):
1. Cassa giornaliera giorno per giorno
2. Bet/Win SNAI aggregati per fornitore
3. Incasso VLT per singola macchina

**Writer:** `includes/XlsxWriter.php` — XLSX nativo (ZIP + OpenXML), richiede `ZipArchive` (default PHP 8+).

Registra export su `audit_log`.

### Export CSV turni (`sala/turni.php?export=csv`)

Export del calendario turni del mese.

### Export CSV audit (`account/admin/audit.php`)

Export completo del log audit.

---

## Appendice: Guida operativa (`docs/onboarding.php`)

**Accesso:** tutti i ruoli autenticati (`require_login()`).

**Tipo:** pagina statica di help center — nessun POST handler, nessuna query SQL.

**Struttura:** wizard multi-tab con sidebar a passi numerati. Contenuto hardcoded in PHP.

**Visibilità tab per ruolo:**
- "Operatori" e "Chiusura sala": operatori e responsabili
- "Responsabile": solo responsabili
- "Revisore": tutti i ruoli; tab di default per i revisori

**Unica interazione client:** bottone "Rivedi guida popup" — rimuove `gp_wizard_done` da localStorage e redirige a `giornaliero.php` per innescare il tour onboarding.