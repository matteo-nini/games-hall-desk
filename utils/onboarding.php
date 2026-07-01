<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$role = $user['ruolo'] ?? 'operatore';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

$navOp = [
  'Il flusso',
  'Scheda cassa',
  'Scassettamenti VLT',
  'Refill AWP',
  'Quadratura',
  'Chiusura',
  'Ticket assistenza',
  'Prestiti',
  'Documenti',
  'Dati Bet/Win',
];

$navRes = [
  'Configurazione',
  'Macchine',
  'Operatori',
  'Report',
  'Dati Bet/Win',
  'Documenti',
  'Audit log',
];
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Guida operativa</title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/onboarding.css') ?>">
<style>
.ob-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:12px; }
.ob-tab-btn { padding:8px 18px; font-size:14px; font-weight:600; color:var(--muted); background:none; border:none; border-bottom:2px solid transparent; cursor:pointer; margin-bottom:-1px; transition:color .1s; }
.ob-tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
.ob-panel { display:none; }
.ob-panel.active { display:block; }
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

  <div class="ob-tabs" role="tablist">
    <?php if ($role === 'revisore'): ?>
    <button class="ob-tab-btn active" role="tab" aria-selected="true" data-tab="rev">Revisore</button>
    <?php else: ?>
    <button class="ob-tab-btn active" role="tab" aria-selected="true" data-tab="op">Operatori</button>
    <?php if ($role === 'responsabile'): ?>
    <button class="ob-tab-btn" role="tab" aria-selected="false" data-tab="res">Responsabile</button>
    <button class="ob-tab-btn" role="tab" aria-selected="false" data-tab="rev">Revisore</button>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Tab operatori -->
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
          <h3 class="ob-pane-head">Il flusso della giornata</h3>
          <p>Ogni giornata è divisa in due turni: <strong>Mattino (controllo)</strong> e <strong>Sera (chiusura)</strong>.</p>
          <ul class="ob-ul">
            <li>Il turno di <strong>mattino</strong> registra il controllo cassetto effettuato all'apertura della sala.</li>
            <li>Il turno di <strong>sera</strong> registra la chiusura con tutti gli incassi e gli scassettamenti VLT.</li>
            <li>Il sistema salva <em>solo il turno visualizzato</em>, senza toccare quello dell'altro operatore.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
        </div>

        <div class="ob-pane" data-pane="1">
          <h3 class="ob-pane-head">Cosa inserire nella scheda cassa</h3>
          <ul class="ob-ul">
            <li><strong>Fondo cassa</strong>: importo in cassa all'inizio del turno.</li>
            <li><strong>Contanti</strong>: conta le banconote per taglio — il totale si calcola automaticamente.</li>
            <li><strong>Monete</strong>: totale in monete (importo unico).</li>
            <li><strong>Bancomat</strong>: totale incassato tramite POS nel turno.</li>
            <li><strong>Ticket pagati</strong>: totale ticket vincita pagati per ogni fornitore.</li>
          </ul>
          <div class="ob-tip">Il <strong>cassetto</strong> e il <strong>versamento VLT</strong> vengono calcolati automaticamente. Controlla che lo scostamento sia il più vicino possibile a zero.</div>
        </div>

        <div class="ob-pane" data-pane="2">
          <h3 class="ob-pane-head">Scassettamenti VLT</h3>
          <p>Per ogni macchina <strong>VLT</strong> inserisci l'importo prelevato dalla cassetta durante il turno.</p>
          <ul class="ob-ul">
            <li>Le macchine sono raggruppate per fornitore (NOVO, INSPIRED, SPIELO).</li>
            <li>Lascia a zero le macchine non scassettate nel turno corrente.</li>
            <li>Lo scassettamento <em>riduce</em> il versamento VLT: è il denaro già incassato dalla cassa elettronica.</li>
          </ul>
        </div>

        <div class="ob-pane" data-pane="3">
          <h3 class="ob-pane-head">Refill AWP</h3>
          <p>Le macchine <strong>AWP</strong> (slot da bar) funzionano diversamente dalle VLT: quando esauriscono le monete interne si riforniscono manualmente.</p>
          <ul class="ob-ul">
            <li>Ogni refill è denaro che <em>esce dalla cassa</em> e va nella macchina — <strong>aumenta</strong> il cassetto stimato.</li>
            <li>Registra ogni refill nel campo <strong>Refill AWP</strong>: indica numero macchina, importo in € e ora.</li>
            <li>Puoi aggiungere più refill nello stesso turno con il pulsante + sulla destra.</li>
            <li>Il totale refill viene sommato automaticamente al cassetto nel calcolo finale.</li>
          </ul>
          <div class="ob-tip">Refill AWP e scassettamenti VLT hanno effetti opposti: il refill <em>aggiunge</em> denaro alla macchina, lo scassettamento lo <em>preleva</em>.</div>
        </div>

        <div class="ob-pane" data-pane="4">
          <h3 class="ob-pane-head">La quadratura — «I conti tornano»</h3>
          <p>Il banner in cima cambia colore in base allo <strong>scostamento</strong> tra cassetto atteso e fondo cassa effettivo:</p>
          <div class="ob-legend"><span class="ob-dot ob-green"></span> <strong>Verde</strong> &mdash; scostamento &lt; 4 € (ottimo)</div>
          <div class="ob-legend"><span class="ob-dot ob-amber"></span> <strong>Giallo</strong> &mdash; scostamento 4–5 € (tollerabile)</div>
          <div class="ob-legend"><span class="ob-dot ob-red"></span> <strong>Rosso</strong> &mdash; scostamento &gt; 5 € (verificare)</div>
          <p style="margin-top:12px">In caso di scostamento elevato controlla: conteggio contanti, bancomat, ticket, refill AWP e scassettamenti.</p>
        </div>

        <div class="ob-pane" data-pane="5">
          <h3 class="ob-pane-head">Chiusura della giornata</h3>
          <p>Al termine del turno sera, dopo aver salvato, clicca <strong>«Chiudi giornata»</strong>. Una giornata chiusa non può essere modificata dagli operatori.</p>
          <div class="ob-tip">Chiudi sempre la giornata al termine del turno sera: blocca modifiche accidentali e aggiorna lo stato nel calendario.</div>
        </div>

        <div class="ob-pane" data-pane="6">
          <h3 class="ob-pane-head">Ticket assistenza macchine</h3>
          <p>Se una macchina presenta un guasto, apri un ticket dalla sezione <strong>Assistenze</strong>. Inserisci la macchina, descrivi il problema e (se disponibile) il numero di ticket del fornitore.</p>
          <ul class="ob-ul">
            <li>Dopo aver aperto il ticket, il sistema propone di <strong>stampare un avviso</strong> da esporre sulla macchina.</li>
            <li>Clicca <em>Stampa avviso</em> nel popup per aprire la pagina di stampa con la data del guasto.</li>
            <li>Chiudi il ticket (con la risoluzione) quando il problema è risolto.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('sala/ticket.php') ?>">Vai alle assistenze →</a>
        </div>

        <div class="ob-pane" data-pane="7">
          <h3 class="ob-pane-head">Prestiti e rientri</h3>
          <p>La sezione <strong>Prestiti</strong> traccia i prestiti in denaro a clienti o collaboratori. Il saldo mostra quanto non è ancora rientrato.</p>
          <ul class="ob-ul">
            <li><strong>Prestito</strong>: denaro dato alla persona → il saldo aumenta.</li>
            <li><strong>Rientro</strong>: denaro restituito → il saldo diminuisce.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('sala/prestiti.php') ?>">Vai ai prestiti →</a>
        </div>

        <div class="ob-pane" data-pane="8">
          <h3 class="ob-pane-head">Documenti operativi</h3>
          <p>La sezione <strong>Documenti</strong> raccoglie i moduli e le istruzioni caricati dal responsabile — ad esempio il modulo antiriciclaggio per vincite alte.</p>
          <ul class="ob-ul">
            <li>Clicca <strong>Apri</strong> per visualizzare il documento nel browser.</li>
            <li>Clicca la freccia ↓ per scaricarlo sul tuo dispositivo.</li>
            <li>Per i moduli cartacei, apri il documento e stampa direttamente dal browser.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('sala/documenti.php') ?>">Vai ai documenti →</a>
        </div>

        <div class="ob-pane" data-pane="9">
          <h3 class="ob-pane-head">Dati Bet/Win settimanali</h3>
          <p>La sezione <strong>Settimanale</strong> include i campi per inserire i dati Bet/Win di ogni fornitore (NOVO, INSPIRED, SPIELO).</p>
          <ul class="ob-ul">
            <li><strong>Giocato</strong>: totale puntate della settimana per quel fornitore.</li>
            <li><strong>Pagato</strong>: totale vincite pagate nella settimana.</li>
            <li>Inserisci i valori quando ricevi il bollettino settimanale dal concessionario.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/settimanale.php') ?>">Vai al settimanale →</a>
        </div>

      </div>
    </div>
  </div>

  <?php if ($role === 'responsabile'): ?>
  <!-- Tab responsabile -->
  <div class="ob-panel" id="ob-panel-res">
    <div class="ob-panes">

      <nav class="ob-step-nav" aria-label="Passi guida">
        <?php foreach ($navRes as $i => $lbl): ?>
        <button type="button" class="ob-snav-item<?= $i === 0 ? ' active' : '' ?>" data-step="<?= $i ?>">
          <span class="ob-snav-num"><?= $i + 1 ?></span>
          <span class="ob-snav-lbl"><?= $h($lbl) ?></span>
        </button>
        <?php endforeach; ?>
      </nav>

      <div class="ob-pane-area">

        <div class="ob-pane active" data-pane="0">
          <h3 class="ob-pane-head">Configurazione iniziale</h3>
          <p>Prima di avviare le operazioni, accedi a <strong>Impostazioni</strong> e configura:</p>
          <ul class="ob-ul">
            <li><strong>Nome e logo</strong>: compaiono nell'header, nella PWA e nei documenti generati.</li>
            <li><strong>Brand colori</strong>: palette di 24 swatches con anteprima live — bottoni, badge e link attivi.</li>
            <li><strong>Turni</strong>: numero (1–3), nomi e orari dei turni giornalieri.</li>
            <li><strong>Fuso orario</strong>: importante per sale fuori dall'Italia.</li>
            <li><strong>Costo turni</strong>: compenso per turno, visibile nel riepilogo guadagni.</li>
            <li><strong>Permessi</strong>: sezione unica per operatori (calendario, turni giornalieri), mobile (compilazione e modifica da smartphone) e revisori (accesso opzionale al calendario turni in sola lettura).</li>
            <li><strong>Moduli</strong>: attiva/disattiva Ticket assistenza, Prestiti e Documenti.</li>
            <li><strong>Email di sistema</strong>: indirizzo mittente per le email di reset password (in Sistema → Email).</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('account/admin/impostazioni.php') ?>">Vai alle impostazioni →</a>
        </div>

        <div class="ob-pane" data-pane="1">
          <h3 class="ob-pane-head">Gestione macchine</h3>
          <p>Aggiungi tutte le macchine presenti in sala dalla sezione <strong>Macchine</strong>:</p>
          <ul class="ob-ul">
            <li><strong>Codice</strong>: identificativo univoco (es. VLT01, AWP03).</li>
            <li><strong>Tipo</strong>: VLT (videolottery) o AWP (slot da bar).</li>
            <li><strong>Fornitore</strong>: raggruppa scassettamenti e ticket. Configura i fornitori dalla sezione <a href="<?= base_url('account/admin/fornitori.php') ?>">Fornitori</a>.</li>
            <li><strong>Ordine</strong>: sequenza di visualizzazione nel giornaliero.</li>
          </ul>
          <div class="ob-tip">Le macchine disattivate non compaiono nel giornaliero ma rimangono nello storico. Non eliminarle: usa il toggle attiva/disattiva.</div>
          <a class="ob-panel-link" href="<?= base_url('account/admin/macchine.php') ?>">Vai alle macchine →</a>
        </div>

        <div class="ob-pane" data-pane="2">
          <h3 class="ob-pane-head">Gestione operatori</h3>
          <p>Crea un account per ogni operatore dalla sezione <strong>Utenti</strong>. Il sistema registra chi ha compilato ogni turno.</p>
          <ul class="ob-ul">
            <li>Ruolo <strong>operatore</strong>: accesso a cassa, sala, assistenze e prestiti.</li>
            <li>Ruolo <strong>responsabile</strong>: accesso completo incluse le funzioni admin.</li>
            <li>Ruolo <strong>revisore</strong>: sola lettura dei report. Il calendario turni è opzionale (da Impostazioni → Permessi).</li>
            <li><strong>Email utente</strong>: aggiungila dal menu azione (⋮) della riga utente. Abilita il reset password self-service dalla pagina di login.</li>
          </ul>
          <div class="ob-tip">Con un'email configurata, ogni operatore può reimpostare la propria password autonomamente dalla pagina di login, senza contattare il responsabile.</div>
          <a class="ob-panel-link" href="<?= base_url('account/admin/utenti.php') ?>">Vai agli utenti →</a>
        </div>

        <div class="ob-pane" data-pane="3">
          <h3 class="ob-pane-head">Report e analisi</h3>
          <ul class="ob-ul">
            <li><strong>Settimanale</strong>: incassi giorno per giorno con i dati Bet/Win per fornitore.</li>
            <li><strong>Mensile</strong>: totali per mese, link ai dettagli giornalieri e tabella fornitori.</li>
            <li><strong>Annuale</strong>: panoramica mese per mese per l'intero anno.</li>
          </ul>
          <p>Ogni vista ha un pulsante <em>Esporta CSV</em> per il contabile o il commercialista.</p>
          <p style="margin-top:10px">Nella <strong>dashboard operatori</strong> trovi le performance degli ultimi 30 giorni: scostamento medio, % turni in quadratura e numero turni effettuati.</p>
          <a class="ob-panel-link" href="<?= base_url('cassa/annuale.php') ?>">Vai al report annuale →</a>
        </div>

        <div class="ob-pane" data-pane="4">
          <h3 class="ob-pane-head">Dati Bet/Win SNAI</h3>
          <p>I dati Bet/Win (giocato/pagato per fornitore) si inseriscono nella pagina <strong>Settimanale</strong> quando ricevi il bollettino dal concessionario.</p>
          <ul class="ob-ul">
            <li>Naviga alla settimana di riferimento con le frecce ← →.</li>
            <li>Per ogni fornitore inserisci <strong>Giocato</strong> e <strong>Pagato</strong> e salva.</li>
            <li>I valori appaiono nel report mensile e annuale per il calcolo della marginalità.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/settimanale.php') ?>">Vai al settimanale →</a>
        </div>

        <div class="ob-pane" data-pane="5">
          <h3 class="ob-pane-head">Modulo Documenti</h3>
          <p>La sezione <strong>Documenti</strong> permette di caricare moduli, avvisi e istruzioni per tutti gli operatori.</p>
          <ul class="ob-ul">
            <li>Modulo antiriciclaggio da stampare per vincite sopra soglia.</li>
            <li>Istruzioni per le procedure di apertura e chiusura.</li>
            <li>Avvisi e comunicazioni interne.</li>
          </ul>
          <p style="margin-top:10px">Attivare in Impostazioni → Moduli. Solo il responsabile può caricare ed eliminare; tutti gli utenti possono aprire e scaricare. I file richiedono sempre l'autenticazione.</p>
          <a class="ob-panel-link" href="<?= base_url('sala/documenti.php') ?>">Vai ai documenti →</a>
        </div>

        <div class="ob-pane" data-pane="6">
          <h3 class="ob-pane-head">Audit log e retention</h3>
          <p>La sezione <strong>Audit</strong> mostra tutte le operazioni nel sistema: login, salvataggi, chiusure giornata, modifiche impostazioni.</p>
          <ul class="ob-ul">
            <li>Log paginati (100 per pagina) ed esportabili in CSV.</li>
            <li>La <strong>politica di retention</strong> (configurabile in Impostazioni) definisce per quanti giorni mantenere i log.</li>
            <li>Il pulsante «Applica retention» elimina i record più vecchi del limite impostato.</li>
          </ul>
          <div class="ob-tip">La retention è un'operazione irreversibile. Prima di applicarla verifica il conteggio mostrato nel pannello.</div>
          <a class="ob-panel-link" href="<?= base_url('account/admin/audit.php') ?>">Vai all'audit log →</a>
        </div>

      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tab revisore -->
  <div class="ob-panel <?= $role === 'revisore' ? 'active' : '' ?>" id="ob-panel-rev">
    <div class="ob-panes">

      <nav class="ob-step-nav" aria-label="Passi guida">
        <button type="button" class="ob-snav-item active" data-step="0">
          <span class="ob-snav-num">1</span>
          <span class="ob-snav-lbl">Il ruolo revisore</span>
        </button>
        <button type="button" class="ob-snav-item" data-step="1">
          <span class="ob-snav-num">2</span>
          <span class="ob-snav-lbl">Navigare i report</span>
        </button>
      </nav>

      <div class="ob-pane-area">

        <div class="ob-pane active" data-pane="0">
          <h3 class="ob-pane-head">Il ruolo revisore</h3>
          <p>Come revisore hai accesso in <strong>sola lettura</strong> ai report finanziari della sala. Non puoi effettuare operazioni di cassa né modificare dati.</p>
          <p style="margin-top:10px">Le sezioni disponibili:</p>
          <ul class="ob-ul">
            <li><strong>Settimanale</strong> — dati Bet/Win SNAI con versamenti e bancomat per ogni settimana.</li>
            <li><strong>Mensile</strong> — riepilogo cassa giorno per giorno e tabella fornitori.</li>
            <li><strong>Annuale</strong> — panoramica incassi e versamenti mese per mese.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/settimanale.php') ?>">Vai al settimanale →</a>
        </div>

        <div class="ob-pane" data-pane="1">
          <h3 class="ob-pane-head">Come navigare i report</h3>
          <ul class="ob-ul">
            <li>Usa le frecce ← → nell'header per spostarti tra settimane, mesi o anni.</li>
            <li>Clicca il nome di un mese nella pagina annuale per aprire il dettaglio mensile.</li>
            <li>Usa i pulsanti <strong>CSV</strong> o <strong>Stampa</strong> per esportare i dati.</li>
          </ul>
          <div class="ob-tip">I dati sono in sola lettura: le tabelle Bet/Win mostrano i valori inseriti dagli operatori ma non possono essere modificati da te.</div>
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
