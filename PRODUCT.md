# Product

## Register

product

## Users

Operatori di sala giochi e responsabili di turno che compilano la cassa due volte al giorno (turno mattino e sera). Lavorano alla cassa di una sala VLT/AWP, spesso in ambienti rumorosi, con tablet o PC fisso. Non sono tecnici: si aspettano che lo strumento li guidi, non che richiedano attenzione. La sessione tipo dura 5-10 minuti, a fine turno, con l'obiettivo di chiudere in quadratura e andare.

## Product Purpose

GestHall Suite è il registro operativo della cassa giornaliera: raccoglie i dati di ogni turno (contanti, scassettamenti, refill, bancomat, ticket), calcola la quadratura in tempo reale e segnala immediatamente se qualcosa non torna. Il successo si misura sulla velocità di inserimento e sull'assenza di ambiguità sul risultato: verde = tutto ok, rosso = c'è qualcosa da verificare.

## Brand Personality

Precisa · Seria · Affidabile. L'app non deve impressionare: deve ispirare fiducia. Chi la usa deve sentirsi sicuro che i numeri siano corretti, che il verde significhi davvero verde, e che ogni salvataggio sia andato a buon fine.

## Anti-references

- **Gestionali ERP classici** (SAP, Zucchetti, vecchi software da commercialista): interfacce dense e ostili, font piccoli, nessuna gerarchia visiva. Il livello zero da non raggiungere.
- **Dashboard decorative**: widget con numeri enormi, grafici ovunque, effetti visual che rallentano la lettura. I dati devono essere leggibili, non celebrati.
- **SaaS "fun"**: palette pastello, illustrazioni, micro-animazioni a ogni click. Questa è una cassa: l'estetica deve sostenere la serietà del contesto.

## Design Principles

1. **I numeri parlano da soli** — Gerarchia tipografica chiara tra input e risultati calcolati. Tabular-nums ovunque ci siano valori monetari. Il valore dello scostamento deve essere la cosa più leggibile della pagina.
2. **Lo stato non si interpreta, si legge** — Verde, giallo, rosso devono essere inequivocabili anche su tablet in controluce. Mai affidarsi al colore da solo: icone e label di supporto sempre presenti.
3. **Consistenza come fiducia** — Stessa struttura, stesso ordine, stesso comportamento su ogni turno e ogni giornata. L'operatore che usa l'app da sei mesi non deve mai chiedersi dove si trova.
4. **Densità intenzionale** — Le informazioni di entrambi i turni devono stare in una vista sola. La densità è una feature, non un problema da risolvere nascondendo dati.
5. **Touch-aware, non touch-first** — Target di tocco adeguati per tablet alla cassa, ma la struttura informativa rimane ottimizzata per desktop.

## Accessibility & Inclusion

WCAG AA. Target di tocco minimo 44px per elementi interattivi. Contrasto testo/sfondo ≥ 4.5:1 per testo normale, ≥ 3:1 per testo grande. Il feedback di stato (ok/warn/bad) non si basa mai sul solo colore. Riduzione del motion rispettata per utenti con prefers-reduced-motion.
