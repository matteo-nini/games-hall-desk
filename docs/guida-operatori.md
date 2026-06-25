# Guida operatori — Games Palace Desk

Guida passo-passo per chi compila la cassa, gestisce i turni e usa la sala giochi ogni giorno.

---

## Accesso e navigazione

### Login
Apri l'app nel browser (o dalla schermata home se installata come PWA). Inserisci username e password assegnati dal responsabile. Dopo 5 tentativi falliti consecutivi l'IP viene bloccato per 15 minuti: aspetta o contatta il responsabile.

### La barra laterale
A sinistra trovi la navigazione principale:
- **Cassa** → Giornaliero, Settimanale, Mensile, Annuale
- **Sala** → AWP, Turni, Ticket assistenza, Prestiti, Documenti (se abilitati)
- **Guida** → tutorial interattivo
- **Profilo** → cambia nome, password, foto

---

## Cassa giornaliera (`cassa/giornaliero.php`)

La pagina principale del lavoro quotidiano. Hai sempre a disposizione i tab dei turni configurati (Mattino, Sera o Notte).

### Flusso di compilazione turno

1. **Seleziona la data** — di default è oggi. Cambia se stai compilando un turno precedente.
2. **Scegli il tab del turno** — Mattino o Sera (o Notte se configurato).
3. **Contanti per taglio** — inserisci quanti pezzi hai per ogni taglio (5 €, 10 €, 20 €, 50 €, 100 €, 200 €, 500 €). Il totale si calcola automaticamente.
4. **Refill AWP** — se hai rifornito macchine AWP, inserisci importo e ora per ciascuna. Puoi farlo anche dalla sezione AWP dedicata.
5. **Scassettamenti VLT** — per ogni macchina VLT, inserisci l'importo incassato dal cassetto. Le macchine sono raggruppate per fornitore.
6. **Ticket vincite** — inserisci i ticket pagati per fornitore (NOVO, INSPIRED, SPIELO, …).
7. **Monete, Bancomat, Differenze** — campi aggiuntivi per completare la riconciliazione.
8. **II Cassa** e **Rientri** — fondi cassa aggiuntivi e contanti rientrati.
9. **Fondo cassa** — importo fisso configurato dal responsabile.

### Calcolo automatico
Mentre inserisci i dati, la pagina aggiorna in tempo reale:
- **Cassetto** = Contanti + Refill + Differenze − II Cassa − Rientri
- **Versamento** = Scassettamenti − Bancomat − Ticket
- **Totale cassa** = Cassetto + Monete − Versamento
- **Scostamento** = Totale − Fondo cassa

Il banner in cima cambia colore:
- Verde (< 4 €) → tutto ok
- Giallo (4–5 €) → leggero scostamento, verifica
- Rosso (> 5 €) → da verificare prima di chiudere

### Auto-salvataggio locale
La pagina salva automaticamente quello che stai scrivendo ogni mezzo secondo. Se la pagina si ricarica accidentalmente, i dati vengono ripristinati. **Attenzione**: questo salvataggio è locale al browser, non sostituisce il salvataggio ufficiale sul server.

### Salva turno
Premi **Salva turno** per inviare i dati al server. Il bottone salva solo il turno attivo — l'altro turno non viene toccato.

### Chiudi giornata
Quando entrambi i turni sono compilati, premi **Chiudi giornata**. La giornata passa allo stato "chiusa" e non è più modificabile (solo il responsabile può riaprirla).

### Alert giornata precedente aperta
Se ieri la giornata non è stata chiusa, compare un avviso in rosso in cima alla pagina. Chiudi prima quella giornata.

---

## Refill AWP (`sala/awp.php`)

Tieni traccia dei rifornimenti alle macchine AWP durante il turno.

- Seleziona la data e il turno
- Clicca **+ Aggiungi refill** e inserisci: macchina, importo in euro, ora
- Salva — i refill entrano automaticamente nel calcolo della cassa giornaliera per quel turno

---

## Turni programmati (`sala/turni.php`)

### Prenotazione turno
Se il responsabile ha abilitato la modifica del calendario, puoi prenotarti per un turno futuro:
1. Clicca sul giorno e turno che ti interessa
2. Se il posto è libero, clicca **Prendimi questo turno**
3. Il turno appare nel tuo calendario con il tuo nome

### Vedere i propri turni
Nella vista calendario vedi i tuoi turni evidenziati. I turni degli altri operatori sono visibili ma non modificabili (se il responsabile non ha abilitato la modifica libera).

---

## Ticket assistenza (`sala/ticket.php`)

Se il modulo è abilitato, gestisci le segnalazioni di guasti sulle macchine.

### Aprire un ticket
1. Clicca **+ Apri ticket** in alto a destra
2. Compila: data apertura, macchina, descrizione del problema (e ID ticket se hai già chiamato l'assistenza)
3. Se configurati, i contatti dell'assistenza (numero, lock, password) compaiono nel riquadro in alto al dialog
4. Premi **Apri ticket**
5. Compare una finestra che chiede se stampare l'avviso guasto per la macchina — se sì, si apre una pagina di stampa automatica

### Chiudere un ticket
Clicca **Chiudi ticket** sulla card del ticket aperto. Inserisci:
- Data chiusura
- ID ticket (se non era stato inserito all'apertura)
- Descrizione della risoluzione

Premi **Segna risolto**.

### Filtri
Usa i filtri in alto (Tutti / Aperti / Risolti) per trovare rapidamente i ticket.

---

## Prestiti e rientri (`sala/prestiti.php`)

Se il modulo è abilitato, tieni traccia dei prestiti a persone (clienti o colleghi).

### Aggiungere una persona
1. Clicca **+ Nuova persona**
2. Inserisci il nome e il saldo iniziale (se la persona ha già un debito)
3. Salva

### Registrare un movimento
1. Clicca sulla riga della persona
2. Scegli il tipo: **Prestito** (soldi dati) o **Rientro** (soldi ricevuti)
3. Inserisci importo, data, nota facoltativa
4. Salva

Il saldo corrente si aggiorna automaticamente.

---

## Documenti (`sala/documenti.php`)

Se il modulo è abilitato, qui trovi i documenti caricati dal responsabile (moduli, avvisi, istruzioni).

- Clicca su un documento per aprirlo o scaricarlo
- I file si aprono nel browser o vengono scaricati a seconda del tipo (PDF in-browser, Excel in download)
- Non puoi caricare documenti: questa operazione è riservata al responsabile

---

## Report

### Settimanale (`cassa/settimanale.php`)
Riepilogo della settimana con dati Bet/Win per fornitore, versamenti, bancomat e payout. I badge +/−% mostrano il confronto con la settimana precedente.

### Mensile (`cassa/mensile.php`)
Tutti i giorni del mese con riepilogo finanziario. Usa il pulsante **Stampa** per generare un PDF dal browser.

### Annuale (`cassa/annuale.php`)
Panoramica anno per mese. Clicca su un mese per aprire il mensile di quel mese.

### Export CSV
In settimanale e mensile trovi il pulsante **Esporta CSV** per scaricare i dati in formato compatibile con Excel (separatore `;`, codifica UTF-8 con BOM).

---

## Profilo (`account/profilo.php`)

- **Cambia nome** — il nome visualizzato nella sidebar e nei report
- **Cambia password** — inserisci la password attuale poi quella nuova (min 8 caratteri)
- **Foto profilo** — carica un'immagine JPG o PNG (max 5 MB). Se non carichi nessuna foto, le tue iniziali vengono generate automaticamente

---

## Guida interattiva (`utils/onboarding.php`)

La sezione **Guida** nella barra laterale contiene un tutorial interattivo con spiegazioni dettagliate su ogni sezione. Consulta la nel primo periodo di utilizzo.

---

## Domande frequenti

**Il browser si è chiuso: ho perso i dati della cassa?**
No, il salvataggio automatico locale li ha conservati. Riapri la pagina giornaliero sulla stessa data e troverai i dati ripristinati. Ricorda poi di premere **Salva turno**.

**Ho sbagliato a inserire uno scassettamento — posso correggerlo?**
Sì, finché la giornata è aperta puoi modificare qualsiasi campo e salvare di nuovo.

**La giornata è chiusa ma ho sbagliato qualcosa.**
Solo il responsabile può riaprire una giornata chiusa.

**Non vedo la sezione Ticket / Prestiti / Documenti.**
Il responsabile potrebbe averli disabilitati da Impostazioni → Moduli.

**Lo scostamento è rosso ma i dati sono giusti.**
Verifica di non aver dimenticato un refill AWP o un ticket vincita. Se sei sicuro che i dati siano corretti, segnalalo al responsabile.
