<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$role = $user['ruolo'] ?? 'operatore';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

$navOp = [
  'Accesso e dashboard',
  'La cassa giornaliera',
  'Cosa inserire',
  'Scassettamenti VLT',
  'Refill AWP',
  'Barra di stato',
  'Salva e chiudi',
  'Calendario turni',
  'Ticket assistenza',
  'Strumenti extra',
];

$navRes = [
  'Dashboard live',
  'Gestione utenti',
  'Macchine e fornitori',
  'Rubrica contatti',
  'Impostazioni',
  'Supervisione cassa',
  'Report e import',
  'Documenti',
  'Audit log',
];

$navChiusura = [
  'Contanti e ticket',
  'Scassettamenti',
  'SPIELO — POS',
  'NOVO — POS',
  'INSPIRED — POS',
  'Raccolta rapporti',
  'Chiudi nel sistema',
];

$navRev = [
  'Il ruolo revisore',
  'Dashboard e KPI',
  'Conferma versamento',
  'Email chiusura',
  'Report',
];
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Guida operativa</title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/onboarding.css') ?>">
<style>
.ob-tabs { display:flex; padding:0 0 20px }
.ob-tabs-card { display:inline-flex; gap:2px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--rs); padding:3px }
.ob-tab-btn { padding:7px 18px; font-size:13px; font-weight:500; color:var(--muted); background:transparent; border:none; border-radius:var(--rxs); cursor:pointer; transition:color .12s,background .12s,box-shadow .12s; white-space:nowrap }
.ob-tab-btn:hover { color:var(--text) }
.ob-tab-btn.active { color:var(--text); font-weight:600; background:var(--surface); box-shadow:var(--sh) }
.ob-panel { display:none }
.ob-panel.active { display:block }
.ob-kv { display:grid; grid-template-columns:max-content 1fr; gap:6px 16px; margin:10px 0 }
.ob-kv dt { font-size:13px; font-weight:600; color:var(--text) }
.ob-kv dd { font-size:13px; color:var(--muted); margin:0 }
</style>
</head><body>
<?php require __DIR__ . '/../includes/nav.php'; top_menu($user); ?>

<div class="ob-wrap">

  <div class="ob-hero">
    <div class="ob-icon" aria-hidden="true">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
    </div>
    <h1>Guida operativa</h1>
    <p>Come usare il sistema di cassa giornaliera</p>
  </div>

  <div class="ob-tabs">
    <div class="ob-tabs-card" role="tablist">
      <?php if ($role === 'revisore'): ?>
      <button class="ob-tab-btn active" role="tab" aria-selected="true" data-tab="rev">Revisore</button>
      <?php else: ?>
      <button class="ob-tab-btn active" role="tab" aria-selected="true" data-tab="op">Operatori</button>
      <button class="ob-tab-btn" role="tab" aria-selected="false" data-tab="chiusura">Chiusura sala</button>
      <?php if ($role === 'responsabile'): ?>
      <button class="ob-tab-btn" role="tab" aria-selected="false" data-tab="res">Responsabile</button>
      <button class="ob-tab-btn" role="tab" aria-selected="false" data-tab="rev">Revisore</button>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ════════════════ Tab operatori ════════════════ -->
  <div class="ob-panel active" id="ob-panel-op">
    <div class="ob-panes">

      <nav class="ob-step-nav" aria-label="Passi guida">
        <?php foreach ($navOp as $i => $lbl): ?>
        <button type="button" class="ob-snav-item<?= $i === 0 ? ' active' : '' ?>" data-step="<?= $i ?>">
          <span class="ob-snav-num"><?= $i + 1 ?></span>
          <span class="ob-snav-lbl"><?= $h($lbl) ?></span>
        </button>
        <?php endforeach; ?>
      </nav>

      <div class="ob-pane-area">

        <div class="ob-pane active" data-pane="0">
          <h3 class="ob-pane-head">Accesso e dashboard personale</h3>
          <p>Apri l'URL della sala nel browser. Inserisci il tuo <strong>username</strong> (o indirizzo email, se configurato) e la password.</p>
          <ul class="ob-ul">
            <li><strong>Primo accesso</strong>: compare una guida interattiva che evidenzia i componenti principali. Puoi saltarla in qualsiasi momento.</li>
            <li><strong>Password dimenticata</strong>: nella pagina di login clicca <em>Password dimenticata?</em>, inserisci il tuo username e ricevi il link di reset via email (valido 1 ora). Se non hai un'email configurata, chiedi al responsabile.</li>
          </ul>
          <p style="margin-top:12px">Dopo il login arrivi alla <strong>dashboard personale</strong>. Trovi:</p>
          <ul class="ob-ul">
            <li>Card turno corrente con accesso diretto alla cassa</li>
            <li>Stipendio del mese: guadagnato, previsto e totale</li>
            <li>Prossimi 6 turni programmati</li>
            <li>Le tue performance negli ultimi 30 giorni</li>
            <li>Link rapidi a Cassa, Turni, AWP, Assistenze, Prestiti, Profilo</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
        </div>

        <div class="ob-pane" data-pane="1">
          <h3 class="ob-pane-head">La cassa giornaliera</h3>
          <p>La cassa si apre automaticamente sulla giornata odierna. Se ieri è rimasta aperta, compare un avviso giallo — chiudi prima la giornata precedente.</p>
          <ul class="ob-ul">
            <li>Per consultare o correggere una giornata passata usa il <strong>selettore data</strong> in alto (solo se il responsabile ha abilitato la modifica libera dei turni).</li>
          </ul>
          <p style="margin-top:12px">La pagina ha <strong>1, 2 o 3 tab</strong> in base alla configurazione della sala (es. <em>Mattino · Sera</em>).</p>
          <ul class="ob-ul">
            <li>Clicca sul tab per cambiare turno</li>
            <li><strong>Su mobile</strong>: scorri con uno swipe destra/sinistra — i pallini sotto indicano il turno attivo</li>
            <li>Ogni nuova giornata parte sempre dal primo turno</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
        </div>

        <div class="ob-pane" data-pane="2">
          <h3 class="ob-pane-head">Cosa inserire</h3>
          <dl class="ob-kv">
            <dt>Fondo cassa</dt><dd>Importo in cassa all'inizio del turno</dd>
            <dt>Contanti</dt><dd>Banconote per taglio — il totale si calcola automaticamente</dd>
            <dt>Monete</dt><dd>Totale monete in cassa (importo unico)</dd>
            <dt>Bancomat</dt><dd>Incasso POS del turno</dd>
            <dt>Ticket pagati</dt><dd>Totale ticket vincita per fornitore</dd>
            <dt>2ª cassa</dt><dd>Eventuale seconda cassa del turno</dd>
            <dt>Rientri</dt><dd>Denaro che rientra in cassa (es. da prestiti)</dd>
            <dt>Differenze</dt><dd>Aggiustamenti manuali positivi o negativi</dd>
          </dl>
          <div class="ob-tip">I campi <strong>Contanti</strong> e <strong>Refill AWP</strong> sono sezioni espandibili all'interno del form. I <strong>Scassettamenti VLT</strong> appaiono sotto ai dati del turno.</div>
        </div>

        <div class="ob-pane" data-pane="3">
          <h3 class="ob-pane-head">Scassettamenti VLT</h3>
          <p>Per ogni macchina <strong>VLT</strong> inserisci l'importo prelevato dalla cassetta durante il turno.</p>
          <ul class="ob-ul">
            <li>Le macchine sono raggruppate per fornitore (es. NOVO, INSPIRED, SPIELO).</li>
            <li>Lascia a zero le macchine non scassettate nel turno corrente.</li>
            <li>Lo scassettamento <strong>aumenta il versamento VLT</strong>: è il denaro da consegnare al gestore.</li>
          </ul>
          <div class="ob-tip">Tieni le banconote di ogni macchina separate finché non hai inserito tutti i valori. In caso di dubbio puoi ricontare senza mescolare i mazzetti.</div>
        </div>

        <div class="ob-pane" data-pane="4">
          <h3 class="ob-pane-head">Refill AWP</h3>
          <p>Le macchine <strong>AWP</strong> funzionano diversamente dalle VLT: quando esauriscono le monete interne si riforniscono manualmente.</p>
          <ul class="ob-ul">
            <li>Ogni refill è denaro che <strong>esce dalla cassa</strong> e va nella macchina — aumenta il cassetto stimato.</li>
            <li>Registra ogni refill: numero macchina, importo in € e ora.</li>
            <li>Puoi aggiungere più refill nello stesso turno con il pulsante <strong>+</strong>.</li>
            <li>Il totale refill viene sommato automaticamente al cassetto.</li>
          </ul>
          <div class="ob-tip">Refill AWP e scassettamenti VLT hanno effetti opposti: il refill <em>aggiunge</em> denaro alla macchina, lo scassettamento lo <em>preleva</em> per versarlo al gestore.</div>
        </div>

        <div class="ob-pane" data-pane="5">
          <h3 class="ob-pane-head">La barra di stato — «I conti tornano»</h3>
          <p>Il banner in cima mostra tre valori calcolati in tempo reale mentre compili i campi:</p>
          <dl class="ob-kv" style="margin-bottom:14px">
            <dt>Cassetto</dt><dd>contanti + refill + differenze − 2ª cassa − rientri</dd>
            <dt>Versamento</dt><dd>scassettamenti − bancomat − ticket</dd>
            <dt>Scostamento</dt><dd>cassetto + monete − fondo cassa</dd>
          </dl>
          <div class="ob-legend"><span class="ob-dot ob-green"></span> <strong>Verde</strong> &mdash; scostamento &lt; 4 € — ottimo</div>
          <div class="ob-legend"><span class="ob-dot ob-amber"></span> <strong>Giallo</strong> &mdash; scostamento 4–5 € — tollerabile, ricontrolla</div>
          <div class="ob-legend"><span class="ob-dot ob-red"></span> <strong>Rosso</strong> &mdash; scostamento &gt; 5 € — verifica contanti, ticket e refill</div>
        </div>

        <div class="ob-pane" data-pane="6">
          <h3 class="ob-pane-head">Salva turno e chiusura giornata</h3>
          <p>Clicca <strong>Salva turno</strong>. Il sistema salva <em>solo il turno che stai visualizzando</em>, senza toccare gli altri.</p>
          <ul class="ob-ul">
            <li><strong>Auto-salvataggio locale</strong>: ogni 500 ms il form si salva automaticamente nel browser. Se la pagina si ricarica accidentalmente, i dati vengono ripristinati.</li>
          </ul>
          <p style="margin-top:12px">Nel tab <strong>Guida chiusura</strong> trovi la procedura passo per passo per chiudere correttamente la giornata. Usala come checklist prima di cliccare <em>Chiudi giornata</em>.</p>
          <ul class="ob-ul">
            <li>Dopo aver compilato e salvato tutti i turni, premi <strong>Chiudi giornata</strong>.</li>
            <li>Una giornata chiusa <strong>non può essere modificata</strong> dagli operatori — solo il responsabile può riaprirla.</li>
            <li>Alla chiusura viene inviata automaticamente un'email riepilogativa a tutti i revisori attivi.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
        </div>

        <div class="ob-pane" data-pane="7">
          <h3 class="ob-pane-head">Calendario turni</h3>
          <p>Nel calendario mensile vedi chi è programmato per ogni turno.</p>
          <ul class="ob-ul">
            <li>Se il responsabile ha abilitato la modifica, puoi segnare la tua disponibilità cliccando sul giorno e aggiungendoti al turno.</li>
            <li>La dashboard personale mostra i tuoi prossimi 6 turni programmati e lo stipendio maturato del mese.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('sala/turni.php') ?>">Vai ai turni →</a>
        </div>

        <div class="ob-pane" data-pane="8">
          <h3 class="ob-pane-head">Ticket assistenza macchine</h3>
          <p>Se una macchina ha un guasto, apri un ticket da <strong>Sala → Assistenze</strong>.</p>
          <ul class="ob-ul">
            <li>Seleziona la macchina, descrivi il problema e inserisci il codice ticket del fornitore (se disponibile).</li>
            <li>Dopo l'apertura compare un popup con i contatti dell'assistenza tecnica e l'opzione di <strong>stampare un avviso</strong> da esporre sulla macchina.</li>
            <li>Quando il problema è risolto, apri il ticket e clicca <strong>Chiudi con risoluzione</strong>.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('sala/ticket.php') ?>">Vai alle assistenze →</a>
        </div>

        <div class="ob-pane" data-pane="9">
          <h3 class="ob-pane-head">Strumenti extra</h3>
          <p><strong>Prestiti e rientri</strong> — traccia movimenti di denaro extra verso persone. Il saldo mostra quanto non è ancora rientrato. Ogni rientro va registrato anche nel campo <em>Rientri</em> della cassa giornaliera.</p>
          <p style="margin-top:10px"><strong>Documenti</strong> — visualizza e scarica moduli e istruzioni caricati dal responsabile. Apri, scarica o stampa direttamente dal browser.</p>
          <p style="margin-top:10px"><strong>Profilo</strong> — modifica nome, telefono, email (per il reset password), password e foto profilo. Se hai un'email configurata ricevi una notifica ad ogni cambio password.</p>
          <p style="margin-top:10px"><strong>PWA</strong> — installa l'app sul telefono: Chrome Android → menu → <em>Aggiungi alla schermata Home</em>. Safari iPhone → Condividi → <em>Aggiungi alla schermata Home</em>.</p>
        </div>

      </div>
    </div>
  </div>

  <!-- ════════════════ Tab chiusura sala ════════════════ -->
  <div class="ob-panel" id="ob-panel-chiusura">
    <div class="ob-panes">

      <nav class="ob-step-nav" aria-label="Passi chiusura">
        <?php foreach ($navChiusura as $i => $lbl): ?>
        <button type="button" class="ob-snav-item<?= $i === 0 ? ' active' : '' ?>" data-step="<?= $i ?>">
          <span class="ob-snav-num"><?= $i + 1 ?></span>
          <span class="ob-snav-lbl"><?= $h($lbl) ?></span>
        </button>
        <?php endforeach; ?>
      </nav>

      <div class="ob-pane-area">

        <div class="ob-pane active" data-pane="0">
          <h3 class="ob-pane-head">Contanti e ticket pagati</h3>
          <p>Prima di scassettare, conta il cassetto e inserisci i dati nel giornaliero.</p>
          <ul class="ob-ul">
            <li><strong>Conta le banconote</strong> per taglio (200€, 100€, 50€, 20€, 10€, 5€) e inseriscile nella griglia contanti del turno sera.</li>
            <li><strong>Conta le monete</strong> e inserisci il totale nel campo Monete.</li>
            <li><strong>Ticket di vincita pagati</strong>: per ogni fornitore inserisci il totale dei ticket pagati durante il turno.</li>
            <li>Salva il turno e controlla il banner: deve essere <strong>verde</strong> (scostamento &lt; 4 €). Se è rosso, riverifica contanti e ticket prima di procedere.</li>
          </ul>
          <div class="ob-tip">Conta sempre le banconote due volte prima di inserirle. Un errore qui si propaga ai controlli successivi.</div>
        </div>

        <div class="ob-pane" data-pane="1">
          <h3 class="ob-pane-head">Scassettamenti</h3>
          <p>Svuota le cassette di tutte le macchine <strong>VLT</strong> e registra ogni importo nel giornaliero.</p>
          <ul class="ob-ul">
            <li>Apri la cassetta di ogni macchina VLT e preleva le banconote.</li>
            <li>Conta le banconote di ogni macchina <strong>separatamente</strong> e annota l'importo.</li>
            <li>Nel giornaliero inserisci l'importo per ogni macchina. Lascia a zero le macchine non scassettate.</li>
            <li>Il versamento VLT viene aggiornato automaticamente.</li>
          </ul>
          <div class="ob-tip">Tieni le banconote di ogni macchina separate finché non hai inserito tutti i dati. In caso di dubbio puoi ricontare senza mescolare.</div>
          <a class="ob-panel-link" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
        </div>

        <div class="ob-pane" data-pane="2">
          <h3 class="ob-pane-head">SPIELO — Rapporto raccolta</h3>
          <p>Dopo aver inserito gli scassettamenti nel giornaliero, genera il rapporto dal POS SPIELO.</p>
          <ol class="ob-ul">
            <li>Sul POS SPIELO: clicca su <strong>«Rapporti»</strong>.</li>
            <li>Clicca su <strong>«Raccolta»</strong>.</li>
            <li>Clicca sull'<strong>icona stampa</strong> per stampare il rapporto.</li>
            <li>Controlla che i valori stampati corrispondano a quanto inserito nel giornaliero. Se ci sono differenze, segnalale con una foto prima di procedere.</li>
          </ol>
          <div class="ob-tip">Conserva il rapporto stampato: va allegato insieme agli altri ticket a fine turno.</div>
        </div>

        <div class="ob-pane" data-pane="3">
          <h3 class="ob-pane-head">NOVO — POS e riconciliazione</h3>
          <p>La procedura NOVO si divide in tre fasi: raccolta, dump e riconciliazione.</p>
          <ol class="ob-ul">
            <li><strong>Login POS</strong>: accedi come utente <em>Vault</em>.</li>
            <li>Tab <strong>«Scassettamento»</strong>: clicca su <strong>«Banconote»</strong>, poi su <strong>«Monete»</strong>.</li>
            <li><strong>Controlla le macchine</strong>: se compare <em>«DUMP REQUIRED»</em>, esegui il dump:
              <ul class="ob-ul" style="margin-top:6px">
                <li>Sulla macchina: <strong>Giocatore → Cash dump → Stampa</strong>.</li>
                <li>La macchina stampa il <strong>ticket di chiusura</strong> — conservalo.</li>
              </ul>
            </li>
            <li>Torna al POS, sottotab <strong>«Scassettamento»</strong>: verifica che i valori di ogni ticket corrispondano. Se tutto torna, clicca <strong>«Conferma»</strong> per ogni macchina.</li>
            <li>Tab <strong>«Turno»</strong> → sottotab <strong>«Riconciliazione»</strong>: clicca <strong>«Riconcilia tutto»</strong> e attendi il report.</li>
            <li>Clicca <strong>«Salva» → PDF</strong> e salva con il nome <strong>DDMM</strong> nella cartella del mese (es. <code>3006</code> per il 30 giugno).</li>
          </ol>
          <div class="ob-tip">Il file PDF va salvato subito: il POS NOVO non conserva i report precedenti.</div>
        </div>

        <div class="ob-pane" data-pane="4">
          <h3 class="ob-pane-head">INSPIRED — Raccolta incassi</h3>
          <p>Per INSPIRED la raccolta avviene sia sulle singole macchine che sul POS.</p>
          <ol class="ob-ul">
            <li><strong>Su ogni macchina INSPIRED</strong>: accedi con le credenziali → <strong>Raccolta incassi → Stampa rapporto</strong>.</li>
            <li>Controlla che i valori stampati corrispondano a quanto inserito nel giornaliero. Se non tornano, segnala con una foto.</li>
            <li><strong>Sul POS INSPIRED</strong>: clicca su <strong>«Manutenzione»</strong> → <strong>Login</strong> → <strong>Raccolta incassi</strong>.</li>
            <li>Stampa il rapporto riepilogativo dal POS.</li>
          </ol>
          <div class="ob-tip">Esegui prima tutte le macchine e poi il POS: il POS aggrega i dati già caricati dalle singole macchine.</div>
        </div>

        <div class="ob-pane" data-pane="5">
          <h3 class="ob-pane-head">Raccolta rapporti</h3>
          <p>A fine turno raccogli tutta la documentazione cartacea e mettila al suo posto.</p>
          <ul class="ob-ul">
            <li>Rapporto raccolta <strong>SPIELO</strong> (stampato dal POS).</li>
            <li>Ticket di chiusura <strong>NOVO</strong> (uno per ogni macchina che richiedeva il dump).</li>
            <li>Rapporti <strong>INSPIRED</strong> (uno per macchina + il riepilogo dal POS).</li>
            <li><strong>Ticket di vincita</strong> pagati durante il turno.</li>
          </ul>
          <p style="margin-top:10px">Metti tutti i documenti insieme e riponili nella <strong>cartella del giorno</strong> nella posizione stabilita dalla sala.</p>
        </div>

        <div class="ob-pane" data-pane="6">
          <h3 class="ob-pane-head">Chiudi la giornata nel sistema</h3>
          <p>Questo è l'<strong>ultimo passaggio</strong> — va fatto solo dall'operatore dell'ultimo turno.</p>
          <ul class="ob-ul">
            <li>Apri il <strong>Giornaliero</strong> della data corrente.</li>
            <li>Verifica che tutti i turni siano salvati e che lo scostamento sia accettabile (verde o giallo).</li>
            <li>Premi <strong>«Chiudi giornata»</strong> in fondo alla pagina e conferma.</li>
          </ul>
          <div class="ob-tip">Dopo la chiusura la giornata è in sola lettura. Solo il responsabile può riaprirla.</div>
          <h4 style="margin:16px 0 6px;font-size:13px;font-weight:600">Cosa succede dopo la chiusura</h4>
          <ul class="ob-ul">
            <li>Il sistema calcola il <strong>versamento netto</strong> (scassettamenti − bancomat − ticket pagati).</li>
            <li>Viene inviata una <strong>email automatica</strong> a tutti i revisori attivi con il riepilogo della giornata.</li>
            <li>Il revisore dovrà confermare il ritiro dalla propria dashboard — genera una traccia firmata con data, ora e IP.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
        </div>

      </div>
    </div>
  </div>

  <?php if ($role === 'responsabile'): ?>
  <!-- ════════════════ Tab responsabile ════════════════ -->
  <div class="ob-panel" id="ob-panel-res">
    <div class="ob-panes">

      <nav class="ob-step-nav" aria-label="Passi guida responsabile">
        <?php foreach ($navRes as $i => $lbl): ?>
        <button type="button" class="ob-snav-item<?= $i === 0 ? ' active' : '' ?>" data-step="<?= $i ?>">
          <span class="ob-snav-num"><?= $i + 1 ?></span>
          <span class="ob-snav-lbl"><?= $h($lbl) ?></span>
        </button>
        <?php endforeach; ?>
      </nav>

      <div class="ob-pane-area">

        <div class="ob-pane active" data-pane="0">
          <h3 class="ob-pane-head">Dashboard — KPI live e grafici</h3>
          <p>Alla login arrivi alla dashboard. I KPI si aggiornano automaticamente ogni <strong>30 secondi</strong> senza ricaricare la pagina — il badge live lampeggia ad ogni refresh.</p>
          <ul class="ob-ul">
            <li><strong>KPI giorno e mese</strong>: incasso VLT, versamento, incasso mese, giorni operativi</li>
            <li><strong>Grafici</strong>: incasso ultimi 30 giorni (barre) e ultimi 6 mesi (linea)</li>
            <li><strong>Statistiche operatori</strong>: turni compilati, scostamento medio, % turni nella soglia verde (ultimi 30 giorni)</li>
            <li><strong>Stipendi del mese</strong>: importo maturato per ogni operatore</li>
            <li><strong>Versamenti in sospeso</strong>: giornate chiuse non ancora confermate dal revisore</li>
            <li><strong>Versamenti recenti</strong>: storico delle ultime conferme</li>
          </ul>
        </div>

        <div class="ob-pane" data-pane="1">
          <h3 class="ob-pane-head">Gestione utenti</h3>
          <p>In <strong>Admin → Utenti</strong> gestisci gli account. Due modalità di creazione:</p>
          <ul class="ob-ul">
            <li><strong>Con password</strong>: l'operatore accede immediatamente con le credenziali fornite.</li>
            <li><strong>Con email, senza password</strong>: lascia vuoto il campo password. Il sistema invia un link di attivazione valido <strong>24 ore</strong> all'email inserita. L'utente imposta la propria password al primo accesso.</li>
          </ul>
          <p style="margin-top:10px"><strong>Azioni disponibili su utenti esistenti:</strong></p>
          <ul class="ob-ul">
            <li><strong>Cambia ruolo</strong>: operatore, responsabile o revisore — effetto immediato</li>
            <li><strong>Reset password</strong>: imposta una nuova password; se l'utente ha un'email riceve notifica con IP della modifica</li>
            <li><strong>Disabilita / Riabilita</strong>: l'utente disabilitato non può accedere ma i suoi turni restano nello storico</li>
          </ul>
          <div class="ob-tip">Con email configurata, ogni utente può fare il reset password autonomamente dalla pagina di login senza contattare il responsabile.</div>
          <a class="ob-panel-link" href="<?= base_url('account/admin/utenti.php') ?>">Vai agli utenti →</a>
        </div>

        <div class="ob-pane" data-pane="2">
          <h3 class="ob-pane-head">Macchine e fornitori</h3>
          <p>In <strong>Admin → Macchine</strong> gestisci in un'unica sezione macchine VLT/AWP e fornitori.</p>
          <ul class="ob-ul">
            <li><strong>Aggiungi macchina</strong>: codice (es. VLT-01), tipo (VLT/AWP), fornitore, seriale, CIV, ordine di visualizzazione</li>
            <li><strong>Seriale e CIV</strong>: modificabili inline — clicca sul campo, modifica e salva in automatico</li>
            <li><strong>Disattiva</strong>: la macchina scompare dal giornaliero ma rimane nei ticket e negli scassettamenti storici</li>
            <li><strong>Storico guasti</strong>: ogni macchina mostra il numero di ticket aperti/risolti</li>
          </ul>
          <p style="margin-top:10px">I <strong>fornitori</strong> determinano la colonna di scassettamento nel giornaliero e le righe nei report Bet/Win. Disabilitarli non elimina i dati storici.</p>
          <a class="ob-panel-link" href="<?= base_url('account/admin/macchine.php') ?>">Vai alle macchine →</a>
        </div>

        <div class="ob-pane" data-pane="3">
          <h3 class="ob-pane-head">Rubrica contatti</h3>
          <p>In <strong>Sala → Contatti</strong> trovi tutti i numeri utili della sala: tecnici, fornitori, operatori, commercialisti.</p>
          <ul class="ob-ul">
            <li><strong>Aggiungi contatto</strong>: nome, telefono, email, ruolo (es. "Tecnico SNAI")</li>
            <li>I profili degli operatori si sincronizzano automaticamente se hanno un numero di telefono nel profilo</li>
            <li><strong>Ricerca rapida</strong>: digita nella barra per filtrare la lista in tempo reale</li>
          </ul>
        </div>

        <div class="ob-pane" data-pane="4">
          <h3 class="ob-pane-head">Impostazioni</h3>
          <p>La sezione è divisa in tab navigabili.</p>
          <ul class="ob-ul">
            <li><strong>Identità sala</strong>: nome, logo (JPG/PNG/WebP/SVG, max 2 MB), sito e telefono</li>
            <li><strong>Brand colori</strong>: 24 swatches predefiniti o color picker con anteprima live. Il sistema deriva automaticamente badge, hover e varianti dark mode.</li>
            <li><strong>Turni</strong>: numero (1–3), nomi, orari e costo per turno (usato nel calcolo stipendi)</li>
            <li><strong>Permessi</strong>: calendario turni, modifica libera turni, cassa da mobile, modifica turni da mobile, revisori vedono i turni</li>
            <li><strong>Moduli</strong>: attiva/disattiva Ticket assistenza, Prestiti e Documenti</li>
            <li><strong>Email</strong>: indirizzo mittente per reset password, attivazione account, notifiche cambio password e riepilogo versamenti ai revisori</li>
          </ul>
          <div class="ob-tip">Se il campo email mittente è vuoto, il sistema usa un fallback generico che potrebbe finire nello spam.</div>
          <a class="ob-panel-link" href="<?= base_url('account/admin/impostazioni.php') ?>">Vai alle impostazioni →</a>
        </div>

        <div class="ob-pane" data-pane="5">
          <h3 class="ob-pane-head">Supervisione cassa</h3>
          <p>Hai le stesse funzionalità degli operatori, più:</p>
          <ul class="ob-ul">
            <li><strong>Riapri giornata chiusa</strong>: solo in caso di errore — la riapertura viene loggata nell'audit</li>
            <li><strong>Chiusura di emergenza</strong>: se un operatore non ha chiuso, puoi farlo tu selezionando la data dalla pagina giornaliero</li>
            <li><strong>Conferma versamento</strong>: clicca <em>Conferma ritiro</em> nella pagina giornaliero per registrare che il versamento è stato ritirato — traccia importo, data, orario e IP</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
        </div>

        <div class="ob-pane" data-pane="6">
          <h3 class="ob-pane-head">Report e import SISAL</h3>
          <p><strong>Settimanale</strong>: dati Bet/Win SNAI per settimana. Puoi inserire i dati direttamente dalla tabella oppure usare <strong>Importa XLS/XLSX</strong> per caricare il file scaricato dal portale SISAL — il sistema aggiorna automaticamente tutti i fornitori per la settimana selezionata.</p>
          <p style="margin-top:10px"><strong>Mensile</strong>: riepilogo giornaliero per il mese con riga Δ% di confronto. Filtro operatore per vedere solo i suoi turni. Esportabile in:</p>
          <ul class="ob-ul">
            <li><strong>Excel (.xlsx)</strong>: tre fogli — cassa giornaliera, Bet/Win SNAI, incasso VLT per macchina</li>
            <li><strong>CSV</strong>: compatibile con Excel Italia (separatore ;)</li>
            <li><strong>Stampa</strong>: layout A4 orizzontale ottimizzato</li>
          </ul>
          <p style="margin-top:10px"><strong>Annuale</strong>: panoramica mese per mese. Il filtro operatore si propaga ai link mensili.</p>
        </div>

        <div class="ob-pane" data-pane="7">
          <h3 class="ob-pane-head">Modulo Documenti</h3>
          <p>Solo il responsabile può caricare ed eliminare. Tutti gli utenti possono visualizzare e scaricare. I file richiedono sempre l'autenticazione — non sono accessibili direttamente.</p>
          <ul class="ob-ul">
            <li>Tipi accettati: PDF, PNG, JPG, WebP, DOCX, XLSX, ODT, ODS (max 20 MB)</li>
            <li><strong>Cartelle</strong>: crea con <em>+ Nuova cartella</em>, sposta documenti con <strong>drag & drop</strong></li>
            <li><strong>Visibilità</strong>: nascondi un documento agli operatori senza eliminarlo</li>
            <li><strong>Attivazione</strong>: il modulo si abilita in Impostazioni → Moduli</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('sala/documenti.php') ?>">Vai ai documenti →</a>
        </div>

        <div class="ob-pane" data-pane="8">
          <h3 class="ob-pane-head">Audit log</h3>
          <p>In <strong>Admin → Audit</strong> trovi il registro completo di tutte le operazioni: login, salvataggi, chiusure giornata, modifiche impostazioni, conferme versamento.</p>
          <ul class="ob-ul">
            <li>Filtri disponibili: per utente, tipo azione e intervallo date</li>
            <li>Paginazione a 100 righe, esportabile in CSV</li>
            <li><strong>Retention</strong>: configurabile in Impostazioni → Sistema (minimo 7 giorni). Il pulsante <em>Elimina log più vecchi di X giorni</em> è irreversibile — verifica il conteggio prima di procedere.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('account/admin/audit.php') ?>">Vai all'audit →</a>
        </div>

      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ════════════════ Tab revisore ════════════════ -->
  <div class="ob-panel <?= $role === 'revisore' ? 'active' : '' ?>" id="ob-panel-rev">
    <div class="ob-panes">

      <nav class="ob-step-nav" aria-label="Passi guida revisore">
        <?php foreach ($navRev as $i => $lbl): ?>
        <button type="button" class="ob-snav-item<?= $i === 0 ? ' active' : '' ?>" data-step="<?= $i ?>">
          <span class="ob-snav-num"><?= $i + 1 ?></span>
          <span class="ob-snav-lbl"><?= $h($lbl) ?></span>
        </button>
        <?php endforeach; ?>
      </nav>

      <div class="ob-pane-area">

        <div class="ob-pane active" data-pane="0">
          <h3 class="ob-pane-head">Il ruolo revisore</h3>
          <p>Il revisore <strong>non può modificare dati operativi</strong> — niente cassa, niente turni, niente ticket. È il ruolo ideale per commercialisti, supervisori esterni o titolari che vogliono monitorare i numeri e tracciare i versamenti.</p>
          <p style="margin-top:10px">Puoi accedere con username o con l'indirizzo email al posto dello username. <strong>Password dimenticata?</strong> → clicca il link nel login, inserisci lo username e ricevi il link via email (valido 1 ora).</p>
          <p style="margin-top:10px"><strong>Cosa puoi fare:</strong></p>
          <ul class="ob-ul">
            <li>Confermare i versamenti delle giornate chiuse</li>
            <li>Consultare tutti i report in sola lettura (settimanale, mensile, annuale)</li>
            <li>Vedere il calendario turni se il responsabile ha attivato il permesso</li>
            <li>Modificare il tuo profilo (nome, email, telefono, password)</li>
          </ul>
        </div>

        <div class="ob-pane" data-pane="1">
          <h3 class="ob-pane-head">Dashboard revisore — KPI del mese</h3>
          <p>Alla login arrivi alla tua dashboard. Quattro KPI del mese in corso:</p>
          <dl class="ob-kv" style="margin-bottom:14px">
            <dt>Da confermare</dt><dd>Giornate chiuse ancora senza conferma versamento</dd>
            <dt>Totale confermato</dt><dd>Somma in € di tutti i versamenti già confermati</dd>
            <dt>Giorni coperti</dt><dd>Numero di giornate con versamento confermato</dd>
            <dt>Copertura %</dt><dd>Percentuale di giorni chiusi con almeno una conferma</dd>
          </dl>
          <p>Più in basso trovi la <strong>tabella andamento mensile</strong> (ultimi 6 mesi) con giorni chiusi, confermati, copertura % e totale versato. Utile per verificare se ci sono mesi con lacune nelle conferme.</p>
        </div>

        <div class="ob-pane" data-pane="2">
          <h3 class="ob-pane-head">Conferma versamento</h3>
          <p>La tabella <strong>«Da confermare»</strong> mostra tutte le giornate chiuse senza conferma, con la data e il versamento calcolato (scassettamenti − bancomat − ticket).</p>
          <p style="margin-top:10px"><strong>Come confermare:</strong></p>
          <ol class="ob-ul">
            <li>Trova la riga della giornata nella tabella <em>Da confermare</em></li>
            <li>Verifica l'importo mostrato</li>
            <li>Clicca <strong>Conferma ritiro</strong></li>
            <li>Conferma nella finestra di dialogo</li>
          </ol>
          <p style="margin-top:10px">La conferma registra: importo, chi ha confermato, data e ora, indirizzo IP. Appare come badge verde nella pagina giornaliero per operatori e responsabili. Una giornata confermata non compare più in <em>Da confermare</em>.</p>
          <p style="margin-top:10px">Lo <strong>storico</strong> in fondo alla dashboard mostra gli ultimi 100 versamenti confermati: importo, chi li ha confermati, data/ora e IP — serve come prova di ricezione.</p>
        </div>

        <div class="ob-pane" data-pane="3">
          <h3 class="ob-pane-head">Email di chiusura giornata</h3>
          <p>Ogni volta che un operatore chiude la giornata, ricevi automaticamente un'email riepilogativa con:</p>
          <ul class="ob-ul">
            <li>Data della giornata e nome dell'operatore che ha chiuso</li>
            <li>Dettaglio scassettamenti per fornitore (VLT)</li>
            <li>Bancomat e ticket pagati</li>
            <li><strong>Versamento netto</strong> (scassettamenti − bancomat − ticket)</li>
            <li>Link diretto all'app per confermare il ritiro</li>
          </ul>
          <div class="ob-tip">Mantieni l'email aggiornata nel tuo profilo: è l'unico modo in cui il sistema ti contatta. Se non ricevi le email, verifica con il responsabile che il mittente sia configurato in Impostazioni.</div>
        </div>

        <div class="ob-pane" data-pane="4">
          <h3 class="ob-pane-head">Report in sola lettura</h3>
          <p>Hai accesso completo a tutti i report.</p>
          <ul class="ob-ul">
            <li><strong>Settimanale</strong>: dati Bet/Win SNAI per settimana, navigabile con le frecce. Badge +/−% sul confronto settimana precedente. Esporta CSV.</li>
            <li><strong>Mensile</strong>: riepilogo giorno per giorno con riga Δ% in fondo. Filtro operatore. Esporta Excel (.xlsx) o CSV. Stampa A4 orizzontale.</li>
            <li><strong>Annuale</strong>: panoramica mese per mese. Clicca un mese per aprire il dettaglio mensile. Esporta CSV.</li>
          </ul>
          <div class="ob-tip">I dati sono in sola lettura: le tabelle Bet/Win mostrano i valori inseriti dagli operatori ma non possono essere modificati.</div>
          <a class="ob-panel-link" href="<?= base_url('cassa/settimanale.php') ?>">Vai al settimanale →</a>
        </div>

      </div>
    </div>
  </div>

  <?php if ($role !== 'revisore'): ?>
  <div class="ob-footer">
    <div>
      <p>Vuoi rivedere il wizard di benvenuto?</p>
      <button class="btnlink" id="btn-replay-wizard" type="button">Rivedi guida popup</button>
    </div>
    <a class="btnlink" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
  </div>
  <?php endif; ?>

</div>

<script>
document.getElementById('btn-replay-wizard')?.addEventListener('click', function () {
  localStorage.removeItem('gp_wizard_done');
  window.location.href = '<?= base_url('cassa/giornaliero.php') ?>';
});

document.querySelectorAll('.ob-tab-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.ob-tab-btn').forEach(function (b) { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
    document.querySelectorAll('.ob-panel').forEach(function (p) { p.classList.remove('active'); });
    btn.classList.add('active');
    btn.setAttribute('aria-selected', 'true');
    var panel = document.getElementById('ob-panel-' + btn.dataset.tab);
    if (panel) panel.classList.add('active');
  });
});

document.querySelectorAll('.ob-panel').forEach(function (panel) {
  var items = panel.querySelectorAll('.ob-snav-item');
  var panes = panel.querySelectorAll('.ob-pane');
  function activate(idx) {
    items.forEach(function (i) { i.classList.remove('active'); });
    panes.forEach(function (p) { p.classList.remove('active'); });
    if (items[idx]) items[idx].classList.add('active');
    if (panes[idx]) panes[idx].classList.add('active');
    if (items[idx]) items[idx].scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
  }
  items.forEach(function (item, idx) {
    item.addEventListener('click', function () { activate(idx); });
  });
});
</script>
</body></html>
