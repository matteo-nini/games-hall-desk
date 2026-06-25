# Guida operatori — Games Palace Desk

Guida passo per passo per l'uso quotidiano del sistema di cassa.

---

## Accesso

Apri l'URL della sala nel browser. Inserisci username e password. Se hai installato la PWA (l'app sul telefono), il login funziona allo stesso modo.

**Al primo accesso** compare una guida interattiva: un tour passo per passo che evidenzia i componenti principali. Puoi saltarla in qualsiasi momento. Per riavviarla vai su **Guida** → "Rivedi guida popup".

---

## Cassa giornaliera (`cassa/giornaliero.php`)

### Selezionare la giornata

- La cassa si apre automaticamente sulla giornata odierna
- Per consultare o correggere una giornata precedente usa il selettore data in alto
- Se ieri è rimasta aperta, compare un avviso giallo in cima alla pagina

### Navigare tra turni

La pagina ha 1, 2 o 3 tab in base alla configurazione della sala (es. Mattino · Sera).

- Clicca sul tab per cambiare turno
- **Su mobile**: puoi scorrere con uno swipe destra/sinistra — i pallini sotto i tab indicano il turno attivo
- Ogni nuova giornata parte sempre dal **primo turno** (Mattino)

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
| **Differenze** | Aggiustamenti manuali (valore positivo o negativo) |

### Scassettamenti VLT

Per ogni macchina VLT inserisci l'importo prelevato dalla cassetta. Lascia a zero le macchine non scassettate nel turno.

### Refill AWP

Ogni refill è denaro che esce dalla cassa e va nella macchina AWP. Inserisci numero macchina, importo e ora. Puoi aggiungere più refill con il pulsante +.

### La barra di stato (statusbar)

In cima vedi in tempo reale:

- **Fondo** — fondo cassa inserito
- **Contanti** — totale banconote contate
- **Cassetto** — contanti + refill + differenze − 2ª cassa − rientri
- **Versamento** — scassettamenti − bancomat − ticket
- **Scostamento** — differenza tra cassetto calcolato e fondo dichiarato

Il banner cambia colore:
- **Verde** — scostamento < 4 € (quadratura corretta)
- **Giallo** — 4–5 € (tollerabile)
- **Rosso** — > 5 € (da verificare)

### Note del turno

Campo libero per annotazioni (eventi, anomalie, ricariche particolari).

### Salvare

Clicca **Salva turno**. Il sistema salva solo il turno che stai visualizzando, senza toccare l'altro.

**Auto-salvataggio locale**: ogni 500 ms il form salva automaticamente nel browser. Se la pagina si ricarica accidentalmente, i dati vengono ripristinati.

### Chiudere la giornata

Dopo aver compilato e salvato tutti i turni, clicca **Chiudi giornata**. Una giornata chiusa non può essere modificata dagli operatori.

---

## Tema chiaro/scuro

Nella barra laterale in basso trovi il bottone con l'icona luna/sole. Clicca per passare al tema scuro o tornare a quello chiaro. La preferenza viene ricordata tra sessioni.

---

## Ticket assistenza (`sala/ticket.php`)

Se una macchina ha un guasto:

1. Clicca **+ Nuovo ticket** in alto a destra
2. Inserisci la macchina, descrivi il problema e (se disponibile) il codice ticket del fornitore
3. Clicca **Apri ticket**

Dopo l'apertura compare un popup con i contatti dell'assistenza tecnica e l'opzione di **stampare un avviso da esporre sulla macchina**.

Quando il problema è risolto, apri il ticket e clicca **Chiudi con risoluzione**: inserisci cosa è stato fatto.

---

## AWP — Refill (`sala/awp.php`)

Registro storico dei refill. Utile per consultare quando e quanto è stato inserito nelle singole macchine AWP.

---

## Turni programmati (`sala/turni.php`)

Il calendario mostra chi è programmato per ogni turno. Se il responsabile ha abilitato la modifica, puoi segnare la tua disponibilità cliccando sul giorno.

---

## Prestiti e rientri (`sala/prestiti.php`)

Traccia i movimenti di denaro con persone specifiche:

- **Prestito**: denaro dato → il saldo aumenta
- **Rientro**: denaro restituito → il saldo diminuisce

Il saldo attuale compare accanto al nome della persona.

---

## Documenti (`sala/documenti.php`)

Raccoglie i moduli e le istruzioni caricati dal responsabile.

- **Apri**: visualizza il documento nel browser
- **↓ Scarica**: salva sul dispositivo
- Per stampare: apri il documento e usa Ctrl+P (Cmd+P su Mac)

---

## Report

Hai accesso ai report in sola lettura:

- **Settimanale** — dati Bet/Win per settimana
- **Mensile** — cassa giorno per giorno, confronto con il mese precedente (Δ%)
- **Annuale** — panoramica mese per mese

Tutti i report hanno i pulsanti **CSV** (per Excel) e **Stampa** (per PDF).

Nel mensile trovi anche un bottone **Excel** che scarica un file `.xlsx` con tre sezioni: cassa giornaliera, Bet/Win SNAI e incasso VLT per macchina.

---

## Profilo (`account/profilo.php`)

- Cambia il nome visualizzato
- Cambia la password (serve inserire quella attuale)
- Carica una foto profilo (JPG o PNG, max 5 MB)

---

## Installare l'app sul telefono (PWA)

Apri l'URL della sala nel browser mobile. Il browser mostra un banner o nel menu trovi "Aggiungi alla schermata Home". Una volta installata, l'app si avvia come un'app nativa, senza barra del browser.
