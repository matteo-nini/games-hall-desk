# Guida revisori — GestHall Suite

Guida per chi ha il compito di verificare e confermare i versamenti giornalieri.

---

## Il ruolo revisore

Il revisore riceve le email di chiusura giornata, verifica i versamenti e li conferma nell'app. Ha accesso in sola lettura a tutti i report, ma **non può modificare dati operativi** (niente cassa, niente turni, niente ticket). È il ruolo ideale per commercialisti, supervisori esterni o titolari che vogliono solo monitorare i numeri e tracciare i versamenti.

---

## Accesso

Login con le credenziali fornite dal responsabile. Puoi accedere anche con il tuo indirizzo email al posto dello username.

### Password dimenticata?

Nella pagina di login clicca **"Password dimenticata?"**, inserisci il tuo username e ricevi il link di reset via email (valido 1 ora).

---

## Dashboard revisore

Alla login arrivi direttamente alla tua dashboard. Trovi quattro sezioni principali.

### KPI del mese corrente

In cima, quattro card con i numeri del mese in corso:

| KPI | Cosa misura |
|---|---|
| **Da confermare** | Giornate chiuse ancora senza conferma versamento |
| **Totale confermato** | Somma in € di tutti i versamenti già confermati |
| **Giorni coperti** | Numero di giornate con versamento confermato |
| **Copertura %** | Percentuale di giorni chiusi con almeno una conferma |

### Giornate da confermare

La tabella principale: tutte le giornate chiuse senza conferma, con la data e il versamento calcolato (scassettamenti − bancomat − ticket).

**Come confermare un versamento:**

1. Trova la riga della giornata nella tabella "Da confermare"
2. Verifica l'importo mostrato
3. Clicca il bottone **Conferma ritiro**
4. Conferma nella finestra di dialogo

La conferma registra: importo, chi ha confermato, data e ora, indirizzo IP. Appare come badge verde nella pagina giornaliero per operatori e responsabili.

> Una volta confermata, la giornata non compare più in questa tabella.

### Andamento mensile

Tabella degli ultimi 6 mesi con: giorni chiusi, giorni confermati, percentuale di copertura e totale versato in €. Utile per verificare se ci sono mesi con lacune nelle conferme.

### Storico versamenti confermati

Gli ultimi 100 versamenti confermati: importo, chi li ha confermati, data/ora e IP. Serve come prova di ricezione.

---

## Email di chiusura giornata

Ogni volta che un operatore chiude la giornata, ricevi automaticamente un'email riepilogativa con:

- Data della giornata e nome dell'operatore che ha chiuso
- Dettaglio scassettamenti per fornitore (VLT)
- Bancomat e ticket pagati
- **Versamento netto** (scassettamenti − bancomat − ticket)
- Link diretto alla app per confermare il ritiro

Puoi confermare il versamento sia dall'email (cliccando il link) sia direttamente dall'app.

---

## Report

Hai accesso completo a tutti i report in sola lettura.

### Settimanale

Dati Bet/Win SNAI per settimana. Naviga con i selettori Anno / Mese / Settimana. I badge +/−% mostrano il confronto con la settimana precedente. Usa **Esporta CSV** per elaborazioni in Excel.

### Mensile

Riepilogo giornaliero per il mese selezionato:

- Una riga per ogni giorno con incasso VLT, ticket, bancomat, versamento
- Riga **Δ%** in fondo: variazione percentuale rispetto al mese precedente (verde = miglioramento, rosso = calo)
- **Filtro operatore**: filtra per singolo operatore
- **Tabella VLT per macchina**: incasso per singola macchina con % sul totale
- **Esporta Excel** → file `.xlsx` con tre fogli: cassa giornaliera, Bet/Win SNAI, incasso VLT per macchina
- **Esporta CSV** → compatibile con Excel Italia
- **Stampa** → layout ottimizzato A4 orizzontale

### Annuale

Panoramica anno per mese. Clicca il nome di un mese per aprire il mensile corrispondente. Il filtro operatore selezionato si propaga al mensile. **Esporta CSV** per il riepilogo annuale.

---

## Calendario turni (se abilitato)

Se il responsabile ha attivato il permesso **"Revisori vedono i turni"**, puoi consultare il calendario turni mensile in sola lettura. Vedi chi è programmato per ogni turno ma non puoi modificare nulla.

---

## Profilo

In **Profilo** puoi modificare:

- Nome visualizzato
- Telefono
- Email (usata per le notifiche di chiusura giornata e per il reset password)
- Password (serve inserire quella attuale)
- Foto profilo (JPG o PNG, max 5 MB)

Mantieni l'email aggiornata: è l'unico modo in cui il sistema ti contatta alla chiusura di ogni giornata.

---

## Tema chiaro / scuro

In fondo alla barra laterale trovi il bottone **luna/sole** per passare al tema scuro. La preferenza viene ricordata tra sessioni.

---

## Cosa non puoi fare

| Operazione | Nota |
|---|---|
| Compilare la cassa giornaliera | Solo operatori e responsabili |
| Aprire o chiudere ticket | Solo operatori e responsabili |
| Modificare i dati Bet/Win | Solo responsabili |
| Gestire utenti, macchine, impostazioni | Solo responsabili |
| Caricare o eliminare documenti | Solo responsabili |

Se hai bisogno di accedere a sezioni aggiuntive, contatta il responsabile per un cambio di ruolo o per l'attivazione di permessi specifici.
