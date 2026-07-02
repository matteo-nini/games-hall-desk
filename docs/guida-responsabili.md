# Guida responsabili — Games Palace Desk

Guida completa per la gestione amministrativa della sala: configurazione, supervisione operativa, reportistica e controllo.

---

## Dashboard responsabile

Alla login arrivi direttamente alla dashboard. Trovi:

- **KPI del giorno e del mese** — incasso VLT, versamento, incasso mese, giorni operativi — aggiornati **automaticamente ogni 30 secondi** senza ricaricare la pagina
- **Badge live** — pallino verde che lampeggia ad ogni aggiornamento (diventa rosso se il server non risponde)
- **Grafici** — incasso ultimi 30 giorni (barre) e ultimi 6 mesi (linea)
- **Statistiche operatori** — turni compilati, scostamento medio, % turni nella soglia verde (ultimi 30 giorni)
- **Stipendi del mese** — importo maturato per ogni operatore in base ai turni programmati
- **Ultime 10 giornate** — accesso rapido con stato e incasso
- **Versamenti in sospeso** — giornate chiuse non ancora confermate dal revisore (max 30)
- **Versamenti recenti confermati** — storico delle ultime conferme (max 30)

---

## Gestione utenti

### Creare un utente

In **Admin → Utenti** compila il form e scegli una delle due modalità:

**Con password** (accesso immediato):
- Inserisci username, nome, ruolo, password (min. 8 caratteri)
- L'operatore può accedere subito con le credenziali fornite

**Con email, senza password** (link di attivazione):
- Inserisci username, nome, ruolo ed email — lascia la password vuota
- Il sistema invia automaticamente un'email all'indirizzo indicato con un **link di attivazione valido 24 ore**
- Al primo click sul link, l'utente imposta la propria password
- Utile per non dover comunicare password temporanee

### Ruoli disponibili

| Ruolo | Cosa può fare |
|---|---|
| **Operatore** | Cassa, AWP, turni, ticket, prestiti, documenti |
| **Responsabile** | Tutto + pannello admin |
| **Revisore** | Dashboard versamenti + report in sola lettura |

### Gestire gli utenti esistenti

- **Cambia ruolo** — seleziona dalla dropdown nella riga e il cambio è immediato
- **Reset password** — imposta una nuova password direttamente; se l'utente ha un'email, riceve una notifica con il cambio avvenuto e l'IP della modifica
- **Disabilita / Riabilita** — l'utente disabilitato non può accedere ma i suoi turni restano nello storico

---

## Macchine e fornitori

In **Admin → Macchine** gestisci in un'unica sezione macchine VLT/AWP e fornitori.

### Macchine

- **Aggiungi**: codice (es. VLT-01), tipo (VLT/AWP), fornitore, seriale, CIV, ordine di visualizzazione
- **Seriale e CIV**: modificabili inline — clicca sul campo, modifica e salva in automatico
- **Disattiva**: la macchina scompare dal giornaliero ma rimane nei ticket e negli scassettamenti storici
- **Storico guasti**: ogni macchina mostra il numero di ticket aperti/risolti

### Fornitori

- **Aggiungi**: inserisci il nome del fornitore reale della sala
- **Disattiva**: scompare dai form ma i dati storici rimangono
- I fornitori determinano la colonna di scassettamento nel giornaliero e le righe nei report Bet/Win

---

## Rubrica contatti

In **Sala → Contatti** trovi un elenco di tutti i numeri utili della sala: tecnici, fornitori, operatori, commercialisti.

- **Aggiungi contatto**: nome, telefono, email, ruolo (es. "Tecnico SNAI")
- I profili degli operatori si sincronizzano automaticamente se hanno un numero di telefono nel profilo
- **Ricerca rapida**: digita nella barra di ricerca per filtrare la lista in tempo reale

---

## Impostazioni

La sezione è divisa in tab navigabili.

### Identità sala

- **Nome sala** — appare nell'header, nella PWA, nei documenti e nelle email
- **Logo** — JPG, PNG, WebP o SVG (max 2 MB); sostituisce le iniziali nella sidebar e nella pagina di login
- **Sito web** e **telefono** della sala — visibili nella rubrica contatti

### Brand colori

Clicca uno dei 24 swatches predefiniti o usa il color picker per scegliere il colore accent dell'interfaccia. L'anteprima live mostra sidebar, bottoni e badge. Premi **Salva colore** per applicare. **Reset default** torna al blu originale.

Il sistema deriva automaticamente le varianti per badge, hover e dark mode — non è necessario impostare altri colori.

### Turni

- **Numero di turni**: 1, 2 o 3
- Per ogni turno: nome personalizzato, orario inizio e fine
- **Costo per turno**: importo corrisposto all'operatore — usato nel calcolo stipendi della dashboard

### Permessi

| Permesso | Cosa controlla |
|---|---|
| **Modifica calendario** | Gli operatori possono aggiungere se stessi al calendario turni |
| **Modifica libera turni** | Gli operatori possono modificare qualsiasi turno (non solo il proprio) |
| **Cassa da mobile** | Abilita il form cassa su viewport ≤ 760 px |
| **Modifica turni da mobile** | Abilita la modifica calendario turni su mobile |
| **Revisori vedono i turni** | I revisori possono vedere il calendario in sola lettura |

### Moduli

Attiva o disattiva le sezioni opzionali. Quando un modulo è disabilitato il link sparisce dalla sidebar — i dati nel database rimangono intatti.

| Modulo | Include |
|---|---|
| Ticket assistenza | Apertura/chiusura guasti macchine, stampa avviso |
| Prestiti e rientri | Movimenti di cassa extra per persone |
| Documenti | Upload, cartelle, drag & drop, distribuzione file |

### Email

- **Email mittente** (`mail_from`): l'indirizzo che appare come mittente nelle email di sistema (es. `noreply@tuasala.it`). Se vuoto, usa un fallback generico con alta probabilità di spam.

### Assistenza tecnica

Numero di telefono, codice lock e password dell'assistenza tecnica. Questi dati compaiono automaticamente nel dialog quando un operatore apre un ticket.

### Sistema

- **Fuso orario**: cambia solo se la sala opera in un fuso diverso dall'Italia
- **Retention log audit**: i log più vecchi del limite si possono eliminare dalla pagina Audit

---

## Supervisione cassa

Hai le stesse funzionalità degli operatori, più:

- **Riapri giornata chiusa**: solo in caso di errore (la riapertura viene loggata nell'audit)
- **Chiusura di emergenza**: se un operatore non ha chiuso, puoi farlo tu selezionando la data dalla pagina giornaliero
- **Conferma versamento**: clicca **Conferma ritiro** nella pagina giornaliero per registrare che il versamento è stato ritirato. La conferma viene tracciata con importo, data, orario e IP.

---

## Report

### Settimanale

Dati Bet/Win SNAI per settimana. Puoi inserire i dati direttamente dalla tabella. Supporta l'**importazione XLS/XLSX** scaricato dal portale SISAL: usa il pulsante **Importa XLS/XLSX** nella pagina settimanale.

### Mensile

Riepilogo giornaliero per il mese. Funzionalità:

- **Riga Δ%** in fondo — variazione percentuale vs mese precedente
- **Filtro operatore** — seleziona un operatore per vedere solo i suoi turni
- **Tabella VLT per macchina** — incasso per macchina con % sul totale
- **Esporta Excel** → `.xlsx` con tre fogli: cassa giornaliera, Bet/Win SNAI, incasso VLT per macchina
- **Esporta CSV** → compatibile con Excel Italia (separatore `;`)

### Annuale

Panoramica mese per mese. Il filtro operatore si propaga ai link mensili. **Esporta CSV** per elaborazioni in Excel.

---

## Audit log

In **Admin → Audit** trovi il registro completo di tutte le operazioni significative con utente, IP, azione e dettaglio.

### Filtri disponibili
- Per utente
- Per tipo azione (es. `turno_salvato`, `giornata_chiusa`, `impostazioni_brand`)
- Per intervallo di date

### Pulizia
Premi **Elimina log più vecchi di X giorni**. Il limite è configurabile in Impostazioni → Sistema → Retention (minimo 7 giorni). L'eliminazione stessa viene loggata.

---

## Documenti

Solo il responsabile (di default) può caricare e gestire i documenti. Gli operatori possono visualizzare e scaricare.

### Caricare un documento
1. Vai su **Sala → Documenti**
2. Clicca **+ Carica documento**
3. Inserisci nome, descrizione (facoltativa) e seleziona il file (max 20 MB: PDF, PNG, JPG, WebP, DOCX, XLSX, ODT, ODS)
4. Premi **Carica**

### Organizzare i documenti
- **Cartelle**: crea cartelle con **+ Nuova cartella**; i documenti si spostano tra cartelle con drag & drop
- **Visibilità**: nascondi un documento agli operatori senza eliminarlo
- **Riordina**: modifica il numero d'ordine di cartelle e documenti

---

## Domande frequenti

**Un operatore ha sbagliato la cassa: come correggo?**
Vai su Giornaliero, seleziona la data, riapri la giornata (se già chiusa), modifica il turno sbagliato, salva e richiudi.

**Devo disabilitare un operatore che ha lasciato la sala.**
Vai in Admin → Utenti, trova l'operatore e clicca **Disabilita**. Non può più accedere ma i suoi turni rimangono nello storico.

**Il revisore non riceve le email di chiusura giornata.**
Verifica che il revisore abbia un'email configurata in Admin → Utenti. Verifica anche che `mail_from` sia impostato in Impostazioni → Email e che il server abbia un mail transport configurato (Postfix o relay SMTP).

**Voglio aggiungere un fornitore diverso da quelli di default.**
Vai in Admin → Macchine, sezione Fornitori, inserisci il nome e clicca Aggiungi.

**Come vedo l'incasso di un singolo operatore nel mese?**
Vai in Mensile, seleziona l'operatore nel dropdown in alto. Il filtro si propaga anche all'annuale.

**La dashboard non si aggiorna in automatico.**
I KPI si aggiornano ogni 30 secondi. Se il badge live diventa rosso c'è un problema di connessione — ricarica la pagina.

**Posso avere più responsabili?**
Sì. Tutti i responsabili hanno gli stessi permessi.

**Come installo l'app sul telefono?**
Apri l'URL nel browser mobile → menu → "Aggiungi alla schermata Home". Su iPhone usa Safari e il pulsante Condividi.
