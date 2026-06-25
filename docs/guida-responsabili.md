# Guida responsabili — Games Palace Desk

Guida completa per la gestione amministrativa della sala: configurazione, supervisione operativa, reportistica e controllo.

---

## Dashboard responsabile (`account/responsabile.php`)

Alla login arrivi direttamente alla dashboard responsabile. Trovi:
- **KPI del mese corrente**: giorni operativi, incasso VLT, ticket pagati, bancomat
- **Riepilogo odierno**: andamento della giornata in corso
- **Grafici**: incasso ultimi 30 giorni (barre) e ultimi 6 mesi (linea)
- **Statistiche operatori**: per ogni operatore — turni compilati, scostamento medio, % turni corretti negli ultimi 30 giorni
- **Ultime 10 giornate** con incasso: accesso rapido alle singole giornate

---

## Pannello admin

Dalla barra laterale, sotto la sezione **Admin**, trovi tutte le funzioni amministrative.

---

## Macchine (`account/admin/macchine.php`)

### Aggiungere una macchina
1. Compila il form in alto: codice (identificativo univoco es. VLT-01), tipo (VLT o AWP), fornitore, seriale, CIV, ordine
2. Premi **Aggiungi**
3. La macchina appare in lista e diventa disponibile nel giornaliero e nei ticket

### Modificare una macchina
- **Seriale e CIV** si modificano inline nella lista: clicca sul campo, modifica, il salvataggio è automatico
- **Ordine**: cambia il numero di ordine per riorganizzare la lista

### Disattivare / riattivare
Usa il toggle nella riga della macchina. Una macchina disattivata non compare nel giornaliero ma rimane nello storico degli scassettamenti e nei ticket.

### Storico guasti
Ogni macchina mostra il numero di ticket aperti e risolti. Clicca per espandere il dettaglio degli interventi.

---

## Fornitori (`account/admin/fornitori.php`)

### Aggiungere un fornitore
Inserisci il nome e premi **Aggiungi**. Il fornitore compare nei form di scassettamento e ticket.

### Riordinare
Trascina le righe nell'ordine desiderato — l'ordine si riflette nella lista del giornaliero.

### Disattivare
Usa il toggle. Un fornitore disattivato non compare nei form ma i dati storici rimangono intatti.

**Fornitori default**: NOVO · INSPIRED · SPIELO. Non si possono eliminare, solo disattivare.

---

## Utenti (`account/admin/utenti.php`)

### Creare un utente
1. Compila: username (univoco), nome, password (min 8 caratteri), ruolo
2. Premi **Aggiungi utente**
3. L'utente può accedere immediatamente

### Ruoli disponibili

| Ruolo | Cosa può fare |
|---|---|
| **Operatore** | Cassa, AWP, turni, ticket, prestiti, documenti |
| **Responsabile** | Tutto + admin panel |
| **Revisore** | Solo report in sola lettura |

### Gestire gli utenti esistenti
- **Cambia ruolo**: seleziona il ruolo dalla select nella riga dell'utente e salva
- **Reset password**: imposta una nuova password temporanea
- **Disabilita / riabilita**: un utente disabilitato non può accedere ma rimane nello storico audit

---

## Impostazioni (`account/admin/impostazioni.php`)

La sezione è divisa in sezioni navigabili dalla sidebar a sinistra.

### Identità
**Nome sala** — appare nell'header, nella PWA e nei documenti.

**Logo** — carica un file JPG, PNG, WebP o SVG (max 2 MB). Il logo sostituisce le iniziali nella sidebar e nella pagina di login. Usa il pulsante **Rimuovi** per tornare alle iniziali.

**Brand colori** — scegli il colore accent dell'interfaccia:
- Clicca su uno dei 24 swatches predefiniti o usa il color picker
- L'anteprima live mostra in tempo reale come apparirà la sidebar, i bottoni e i badge
- Premi **Salva colore** per applicare
- **Reset default** torna al blu (#3b5bdb)

### Turni
Configura 1, 2 o 3 turni giornalieri:
- **Numero di turni**: 1 (turno unico), 2 (mattino + sera), 3 (mattino + sera + notte)
- Per ogni turno: nome personalizzato, orario di inizio e fine
- **Costo per turno**: importo corrisposto all'operatore — visibile nelle statistiche della dashboard

### Operatori
- **Modifica calendario**: se disabilitato, solo il responsabile può programmare i turni
- **Modifica libera turni**: se abilitata, gli operatori possono compilare qualsiasi turno (non solo il proprio) — utile per inserimenti storici o correzioni

### Moduli
Attiva o disattiva le sezioni opzionali. Quando un modulo è disabilitato il link sparisce dalla sidebar — i dati rimangono intatti nel database.

| Modulo | Cosa include |
|---|---|
| Ticket assistenza | Apertura/chiusura guasti macchine |
| Prestiti e rientri | Movimenti di cassa extra per persone |
| Documenti | Upload e distribuzione file |

### Assistenza tecnica
Numero di telefono, codice lock e password dell'assistenza tecnica. Questi dati compaiono automaticamente nel dialog quando un operatore apre un ticket. Lascia vuoti i campi che non hai.

### Sistema
- **Fuso orario**: cambia se la sala opera in un fuso diverso dall'Italia
- **Retention log audit**: i log più vecchi del limite impostato possono essere eliminati dalla pagina Audit (minimo 7 giorni)

---

## Audit log (`account/admin/audit.php`)

Registro di tutte le operazioni significative: ogni scrittura nel database genera un record con utente, IP, entità modificata e dettaglio.

### Filtri
- Per utente
- Per tipo di azione (es. `turno_salvato`, `ticket_aperto`, `impostazioni_brand`)
- Per intervallo di date

### Pulizia log
Premi **Elimina log più vecchi di X giorni** per ridurre il volume (il limite è configurabile in Impostazioni → Sistema → Retention). L'operazione stessa viene registrata nell'audit.

---

## Supervisione operativa

### Cassa giornaliera
Hai le stesse funzionalità degli operatori più la possibilità di **riaprire una giornata chiusa** — usa questo solo in caso di errore, perché la riapertura viene loggata nell'audit.

### Chiusura di emergenza
Se un operatore non ha chiuso la giornata, puoi chiuderla tu dalla pagina giornaliero selezionando la data corrispondente.

### Report

**Settimanale** — dati Bet/Win per settimana. Puoi inserire i dati SNAI direttamente dalla tabella.

**Mensile** — riepilogo giornaliero per il mese. Usa **Stampa / PDF** per i report da conservare.

**Annuale** — panoramica mese per mese. Usa **Esporta CSV** per elaborazioni in Excel.

### Statistiche operatori
Nella dashboard responsabile vedi per ogni operatore:
- Turni compilati negli ultimi 30 giorni
- Scostamento medio (quanto si discosta la cassa compilata da quella attesa)
- % turni con scostamento nella soglia verde

---

## Documenti

Solo il responsabile può caricare e gestire i documenti.

### Caricare un documento
1. Vai su **Sala → Documenti**
2. Clicca **+ Carica documento**
3. Inserisci nome, descrizione (facoltativa) e seleziona il file
4. Premi **Carica**

Formati supportati: PDF, PNG, JPG, JPEG, WebP, DOCX, XLSX, ODT, ODS — max 20 MB.

### Gestire i documenti
- **Visibile / Nascosto**: i documenti nascosti non compaiono per gli operatori ma rimangono nel database
- **Elimina**: rimuove il file dal server e il record dal database
- **Riordina**: modifica il numero d'ordine per organizzare la lista

---

## Domande frequenti

**Un operatore ha sbagliato la cassa: come correggo?**
Vai su Giornaliero, seleziona la data, riapri la giornata (se già chiusa), modifica il turno sbagliato, salva e richiudi.

**Devo disabilitare un operatore che ha lasciato la sala.**
Vai in Utenti, trova l'operatore e clicca **Disabilita**. Non può più accedere ma i suoi turni rimangono nello storico.

**Voglio aggiungere un fornitore diverso da NOVO/INSPIRED/SPIELO.**
Vai in Admin → Fornitori, inserisci il nome nel form e clicca Aggiungi.

**Il log audit è diventato molto grande.**
Vai in Impostazioni → Sistema, imposta la retention e vai in Audit → Elimina log più vecchi di X giorni.

**Posso avere più responsabili?**
Sì, crea più utenti con ruolo responsabile. Tutti hanno gli stessi permessi.

**Come installo l'app sul telefono?**
Apri l'URL della sala nel browser mobile. Il browser mostra un banner o nel menu "Aggiungi alla schermata Home". L'app funziona come un'app nativa.
