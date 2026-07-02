# Guida operatori — Games Palace Desk

Guida passo per passo per l'uso quotidiano del sistema di cassa.

---

## Accesso

Apri l'URL della sala nel browser. Inserisci il tuo **username** (o indirizzo email, se configurato) e la password.

Se hai installato la PWA sul telefono, il login funziona allo stesso modo.

**Al primo accesso** compare una guida interattiva: un tour passo per passo che evidenzia i componenti principali. Puoi saltarla in qualsiasi momento. Per riavviarla vai su **Guida** → "Rivedi guida popup".

### Password dimenticata?

Nella pagina di login clicca **"Password dimenticata?"**, inserisci il tuo username e ricevi il link di reset via email (valido 1 ora). Se non hai un'email configurata, chiedi al responsabile di impostarne una o di resettare la password manualmente.

---

## Dashboard operatore

Alla login arrivi alla tua dashboard personale. Trovi:

- **Card turno corrente**: se sei programmato per il turno di adesso, trovi il tasto diretto per aprire la cassa
- **Stipendio del mese**: guadagnato, previsto e totale — basato sui turni programmati
- **Prossimi 6 turni** programmati
- **Le tue performance negli ultimi 30 giorni**: scostamento medio e % turni nella soglia verde
- **Accesso rapido**: link diretti a Cassa, Turni, AWP, Assistenze, Prestiti, Profilo

---

## Cassa giornaliera

### Aprire la giornata

La cassa si apre automaticamente sulla giornata odierna. Se ieri è rimasta aperta, compare un avviso giallo in cima alla pagina — chiudi prima la giornata precedente.

Per consultare o correggere una giornata passata usa il selettore data in alto (solo se il responsabile ha abilitato la modifica libera dei turni).

### Navigare tra turni

La pagina ha 1, 2 o 3 tab in base alla configurazione della sala (es. **Mattino · Sera**).

- Clicca sul tab per cambiare turno
- **Su mobile**: scorri con uno swipe destra/sinistra — i pallini sotto indicano il turno attivo
- Ogni nuova giornata parte sempre dal primo turno

### Cosa inserire

| Campo | Significato |
|---|---|
| **Fondo cassa** | Importo in cassa all'inizio del turno |
| **Contanti** | Conta le banconote per taglio — il totale si calcola da solo |
| **Monete** | Totale monete in cassa (importo unico) |
| **Bancomat** | Incasso POS del turno |
| **Ticket pagati** | Totale ticket vincita per fornitore |
| **2ª cassa** | Eventuale seconda cassa del turno |
| **Rientri** | Denaro che rientra in cassa (es. da prestiti) |
| **Differenze** | Aggiustamenti manuali (positivo o negativo) |

### Scassettamenti VLT

Per ogni macchina VLT inserisci l'importo prelevato dalla cassetta. Lascia a zero le macchine non scassettate nel turno.

### Refill AWP

Ogni refill è denaro che **esce dalla cassa** e va nella macchina AWP. Inserisci numero macchina, importo e ora. Puoi aggiungere più refill con il pulsante **+**.

### La barra di stato

In cima vedi in tempo reale:

| Valore | Formula |
|---|---|
| **Cassetto** | contanti + refill + differenze − 2ª cassa − rientri |
| **Versamento** | scassettamenti − bancomat − ticket |
| **Scostamento** | cassetto + monete − fondo cassa |

Il banner cambia colore in base allo scostamento:
- 🟢 **Verde** — meno di 4 € (quadratura corretta)
- 🟡 **Giallo** — 4–5 € (tollerabile, controlla i conteggi)
- 🔴 **Rosso** — più di 5 € (verifica tutto prima di chiudere)

### Note del turno

Campo libero per annotazioni: eventi particolari, anomalie, messaggi per il turno successivo.

### Salvare

Clicca **Salva turno**. Il sistema salva **solo il turno che stai visualizzando**, senza toccare gli altri.

**Auto-salvataggio locale**: ogni 500 ms il form si salva automaticamente nel browser. Se la pagina si ricarica accidentalmente, i dati vengono ripristinati.

### Guida chiusura sala

Nel tab **Guida chiusura** trovi la procedura passo per passo (numerata) per chiudere correttamente la giornata: dall'ultimo refill AWP al versamento finale. Usala come checklist prima di cliccare "Chiudi giornata".

### Chiudere la giornata

Dopo aver compilato e salvato tutti i turni, clicca **Chiudi giornata**. Una giornata chiusa **non può essere modificata** dagli operatori (solo il responsabile può riaprirla).

---

## Calendario turni

Nel calendario mensile vedi chi è programmato per ogni turno. Se il responsabile ha abilitato la modifica, puoi segnare la tua disponibilità cliccando sul giorno e aggiungendoti al turno desiderato.

---

## Ticket assistenza

Se una macchina ha un guasto:

1. Vai su **Sala → Assistenze** e clicca **+ Nuovo ticket**
2. Seleziona la macchina, descrivi il problema e inserisci il codice ticket del fornitore (se disponibile)
3. Clicca **Apri ticket**

Dopo l'apertura compare un popup con i contatti dell'assistenza tecnica e l'opzione di **stampare un avviso** da esporre sulla macchina guasta.

Quando il problema è risolto, apri il ticket e clicca **Chiudi con risoluzione**: inserisci brevemente cosa è stato fatto.

---

## AWP — Storico refill

In **Sala → AWP** trovi il registro storico di tutti i refill. Utile per consultare quando e quanto è stato inserito in ogni macchina.

---

## Prestiti e rientri

In **Sala → Prestiti** tracci i movimenti di denaro extra:

- **Prestito**: denaro uscito dalla cassa verso una persona — il saldo aumenta
- **Rientro**: denaro restituito — il saldo diminuisce

Il saldo attuale compare accanto al nome della persona. Ogni rientro va anche inserito nel campo **Rientri** del form cassa giornaliera.

---

## Documenti

In **Sala → Documenti** trovi i moduli, le istruzioni e i file caricati dal responsabile.

- **Apri** — visualizza il documento nel browser
- **Scarica** — salva una copia sul tuo dispositivo
- Per stampare: apri il documento e usa Ctrl+P (Cmd+P su Mac)

Se hai i permessi abilitati dal responsabile, puoi anche **caricare** nuovi documenti, **creare cartelle** e **spostare** i file tra cartelle con il drag & drop.

---

## Ricerca rapida nelle liste

In molte pagine (Utenti, Contatti, Macchine, Documenti) trovi una barra di ricerca in cima alla lista. Inizia a digitare e la lista si filtra in tempo reale — non serve premere Invio.

---

## Report

Hai accesso ai report in sola lettura:

- **Settimanale** — dati Bet/Win per settimana
- **Mensile** — cassa giorno per giorno con confronto Δ% sul mese precedente
- **Annuale** — panoramica mese per mese

Tutti i report hanno i pulsanti **CSV** (per Excel) e **Stampa** (per PDF). Nel mensile trovi anche **Excel** per scaricare un `.xlsx` completo con cassa, Bet/Win e incasso VLT per macchina.

---

## Profilo

In **Profilo** puoi modificare:

- **Nome visualizzato**
- **Telefono** — visibile nella rubrica contatti della sala
- **Email** — usata per il reset password self-service
- **Password** (serve inserire quella attuale; se hai un'email configurata ricevi una notifica di conferma)
- **Foto profilo** (JPG o PNG, max 5 MB — viene ritagliata automaticamente)

---

## Tema chiaro / scuro

In fondo alla barra laterale c'è il bottone con l'icona **luna/sole**. Clicca per passare al tema scuro o tornare a quello chiaro. La preferenza viene ricordata anche alla prossima sessione.

---

## Installare l'app sul telefono (PWA)

Apri l'URL della sala nel browser mobile (Chrome su Android, Safari su iPhone).

- **Android/Chrome**: clicca i tre puntini in alto → "Aggiungi alla schermata Home"
- **iPhone/Safari**: tocca il pulsante Condividi → "Aggiungi alla schermata Home"

Una volta installata, l'app si avvia come un'app nativa senza la barra del browser. Su mobile le funzioni disponibili dipendono dai permessi abilitati dal responsabile (Impostazioni → Mobile).
