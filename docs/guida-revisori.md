# Guida revisori — Games Palace Desk

Guida per chi ha accesso in sola lettura ai report della sala giochi.

---

## Il ruolo revisore

Il revisore può consultare tutti i report ma non può modificare dati operativi: niente cassa, niente turni, niente ticket. È il ruolo ideale per commercialisti, supervisori esterni o titolari che vogliono solo monitorare i numeri.

---

## Accesso

Login con le credenziali fornite dal responsabile. Arrivi direttamente al report settimanale.

Dalla barra laterale hai accesso a:
- **Report** → Settimanale, Mensile, Annuale
- **Profilo** → cambia nome, password, foto
- **Guida** → tutorial interattivo

---

## Tema chiaro/scuro

Nella barra laterale in basso trovi il bottone con l'icona luna/sole. Clicca per passare al tema scuro o tornare a quello chiaro. La preferenza viene ricordata tra sessioni.

---

## Report settimanale (`cassa/settimanale.php`)

Visualizza i dati Bet/Win SNAI per settimana.

**Come navigare**
- Usa i selettori Anno / Mese / Settimana per scegliere il periodo
- La tabella mostra i giorni della settimana con: giocato, pagato, payout % per fornitore (NOVO, INSPIRED, SPIELO)
- I badge +/−% mostrano il confronto con la settimana precedente
- In fondo: totali settimanali e riepilogo versamenti, bancomat, ticket

**Esporta CSV**
Premi **Esporta CSV** per scaricare i dati compatibili con Excel (separatore `;`, codifica UTF-8).

**Stampa / PDF**
Premi **Stampa** per aprire la vista ottimizzata per la stampa. Dal browser usa Ctrl+P (Cmd+P su Mac) e scegli "Salva come PDF".

---

## Report mensile (`cassa/mensile.php`)

Riepilogo giornaliero per il mese selezionato.

**Come leggere la tabella**
- Una riga per ogni giorno del mese
- Colonne: incasso VLT, ticket, bancomat, versamento
- Riga **Δ%** in fondo: variazione percentuale rispetto al mese precedente (verde = miglioramento, rosso = calo)
- **Tabella VLT per macchina**: in fondo alla pagina, incasso per singola macchina con percentuale sul totale

**Filtro operatore**
Puoi selezionare un singolo operatore dal dropdown per vedere solo i dati dei turni compilati da lui/lei.

**Esporta Excel**
Premi **Excel** per scaricare un file `.xlsx` con tre sezioni: cassa giornaliera, Bet/Win SNAI e incasso VLT per macchina. Apri direttamente con Excel o LibreOffice.

**Stampa / PDF**
Ottimizzata per stampa A4 orizzontale. Usa il pulsante **Stampa** o Ctrl+P.

---

## Report annuale (`cassa/annuale.php`)

Panoramica anno per mese.

**Come leggere la tabella**
- Una riga per ogni mese con giorni operativi, incasso, ticket, bancomat, versamento
- Clicca sul nome di un mese per aprire il mensile corrispondente
- Il filtro operatore selezionato si propaga al mensile

**Esporta CSV**
Premi **Esporta CSV** per scaricare il riepilogo annuale.

---

## Profilo (`account/profilo.php`)

- Cambia il tuo nome visualizzato
- Cambia la password (inserisci prima quella attuale)
- Carica una foto profilo (JPG o PNG, max 5 MB)

---

## Cosa non puoi fare

| Operazione | Note |
|---|---|
| Compilare la cassa giornaliera | Accesso negato |
| Modificare i dati Bet/Win | I campi sono in sola lettura |
| Aprire o chiudere ticket | Accesso negato |
| Gestire utenti, macchine, impostazioni | Solo responsabile |
| Caricare documenti | Solo responsabile |

Se hai bisogno di accedere a sezioni aggiuntive, contatta il responsabile per un cambio ruolo.
