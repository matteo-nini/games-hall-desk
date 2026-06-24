<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lib.php';
$user = require_login();
$role = $user['ruolo'] ?? 'operatore';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Guida operativa</title>
<link rel="stylesheet" href="<?= asset_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/onboarding.css') ?>">
<style>
.ob-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:8px; }
.ob-tab-btn { padding:8px 18px; font-size:14px; font-weight:600; color:var(--muted); background:none; border:none; border-bottom:2px solid transparent; cursor:pointer; margin-bottom:-1px; transition:color .1s; }
.ob-tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
.ob-panel { display:none; }
.ob-panel.active { display:block; }
.ob-panel-link { display:inline-block; margin-top:4px; font-size:13px; font-weight:600; color:var(--accent); }
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
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Tab operatori -->
  <div class="ob-panel active" id="ob-panel-op">
    <div class="ob-steps">

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">1</div>
        <div class="ob-body">
          <h3>Il flusso della giornata</h3>
          <p>Ogni giornata è divisa in due turni: <strong>Mattino (controllo)</strong> e <strong>Sera (chiusura)</strong>.</p>
          <ul>
            <li>Il turno di <strong>mattino</strong> registra il controllo cassetto effettuato all'apertura.</li>
            <li>Il turno di <strong>sera</strong> registra la chiusura con tutti gli incassi e gli scassettamenti VLT.</li>
            <li>Il sistema salva <em>solo il turno visualizzato</em>, senza toccare quello dell'altro operatore.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">2</div>
        <div class="ob-body">
          <h3>Cosa inserire nella scheda cassa</h3>
          <ul>
            <li><strong>Fondo cassa</strong>: importo in cassa all'inizio del turno.</li>
            <li><strong>Contanti</strong>: conta le banconote per taglio. Il totale si calcola automaticamente.</li>
            <li><strong>Monete</strong>: totale in monete (importo unico).</li>
            <li><strong>Bancomat</strong>: totale incassato tramite POS nel turno.</li>
            <li><strong>Ticket pagati</strong>: totale ticket vincita pagati per ogni fornitore.</li>
          </ul>
          <div class="ob-tip">Il <strong>cassetto</strong> e il <strong>versamento VLT</strong> vengono calcolati automaticamente. Controlla che lo scostamento sia il più vicino possibile a zero.</div>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">3</div>
        <div class="ob-body">
          <h3>Scassettamenti VLT</h3>
          <p>Per ogni macchina VLT inserisci l'importo prelevato dalla cassetta durante il turno.</p>
          <ul>
            <li>Le macchine sono raggruppate per fornitore (NOVO, INSPIRED, SPIELO).</li>
            <li>Lascia a zero le macchine non scassettate nel turno.</li>
          </ul>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">4</div>
        <div class="ob-body">
          <h3>La quadratura — «I conti tornano»</h3>
          <p>Il banner in cima cambia colore in base allo <strong>scostamento</strong>:</p>
          <div class="ob-legend"><span class="ob-dot ob-green"></span> <strong>Verde</strong> &mdash; scostamento &lt; 4 € (ottimo)</div>
          <div class="ob-legend"><span class="ob-dot ob-amber"></span> <strong>Giallo</strong> &mdash; scostamento 4–5 € (tollerabile)</div>
          <div class="ob-legend"><span class="ob-dot ob-red"></span> <strong>Rosso</strong> &mdash; scostamento &gt; 5 € (verificare)</div>
          <p style="margin-top:10px">In caso di scostamento elevato controlla: conteggio contanti, bancomat, ticket e scassettamenti.</p>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">5</div>
        <div class="ob-body">
          <h3>Chiusura della giornata</h3>
          <p>Al termine del turno sera, dopo aver salvato, clicca <strong>«Chiudi giornata»</strong>. Una giornata chiusa non può essere modificata dagli operatori.</p>
          <div class="ob-tip">Chiudi sempre la giornata al termine del turno sera: blocca modifiche accidentali e aggiorna lo stato nel calendario.</div>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">6</div>
        <div class="ob-body">
          <h3>Ticket assistenza macchine</h3>
          <p>Se una macchina presenta un guasto, apri un ticket dalla sezione <strong>Assistenze</strong>. Inserisci la macchina, descrivi il problema e (se disponibile) il numero di ticket del fornitore. Chiudi il ticket quando il problema è risolto.</p>
          <a class="ob-panel-link" href="<?= base_url('sala/ticket.php') ?>">Vai alle assistenze →</a>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">7</div>
        <div class="ob-body">
          <h3>Prestiti e rientri</h3>
          <p>La sezione <strong>Prestiti</strong> traccia i prestiti in denaro a clienti o collaboratori. Il saldo mostra quanto non è ancora rientrato.</p>
          <ul>
            <li><strong>Prestito</strong>: denaro dato alla persona → il saldo aumenta.</li>
            <li><strong>Rientro</strong>: denaro restituito → il saldo diminuisce.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('sala/prestiti.php') ?>">Vai ai prestiti →</a>
        </div>
      </div>

    </div>
  </div>

  <?php if ($role === 'responsabile'): ?>
  <!-- Tab responsabile -->
  <div class="ob-panel" id="ob-panel-res">
    <div class="ob-steps">

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">1</div>
        <div class="ob-body">
          <h3>Configurazione iniziale</h3>
          <p>Prima di avviare le operazioni, accedi a <strong>Impostazioni</strong> e configura:</p>
          <ul>
            <li><strong>Orari turni</strong>: definisci le fasce orarie di mattino e sera per il riconoscimento automatico del turno corrente.</li>
            <li><strong>Costo turni</strong>: importo corrisposto per turno, visibile nel riepilogo guadagni degli operatori.</li>
            <li><strong>Permessi</strong>: stabilisci se gli operatori possono modificare i turni programmati.</li>
            <li><strong>Moduli</strong>: attiva/disattiva Ticket assistenza e Prestiti secondo le necessità della sala.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('account/admin/impostazioni.php') ?>">Vai alle impostazioni →</a>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">2</div>
        <div class="ob-body">
          <h3>Gestione macchine</h3>
          <p>Aggiungi tutte le macchine presenti in sala dalla sezione <strong>Macchine</strong>:</p>
          <ul>
            <li><strong>Codice</strong>: identificativo univoco (es. VLT01, AWP03).</li>
            <li><strong>Tipo</strong>: VLT (videolottery) o AWP (slot da bar).</li>
            <li><strong>Fornitore</strong>: usato per raggruppare scassettamenti e ticket.</li>
            <li><strong>Ordine</strong>: sequenza di visualizzazione nella pagina giornaliero.</li>
          </ul>
          <div class="ob-tip">Le macchine disattivate non compaiono nel giornaliero ma rimangono nello storico. Non eliminarle: usa il toggle attiva/disattiva.</div>
          <a class="ob-panel-link" href="<?= base_url('account/admin/macchine.php') ?>">Vai alle macchine →</a>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">3</div>
        <div class="ob-body">
          <h3>Gestione operatori</h3>
          <p>Crea un account per ogni operatore dalla sezione <strong>Utenti</strong>. Ogni operatore accede con le proprie credenziali e il sistema registra chi ha compilato ogni turno.</p>
          <ul>
            <li>Ruolo <strong>operatore</strong>: accesso a cassa, sala, assistenze e prestiti.</li>
            <li>Ruolo <strong>responsabile</strong>: accesso completo incluse le funzioni admin.</li>
          </ul>
          <div class="ob-tip">Cambia le password periodicamente. Il reset password va fatto dalla pagina Utenti (solo responsabile).</div>
          <a class="ob-panel-link" href="<?= base_url('account/admin/utenti.php') ?>">Vai agli utenti →</a>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">4</div>
        <div class="ob-body">
          <h3>Report e analisi</h3>
          <p>Le sezioni di report aggregano i dati su periodi estesi:</p>
          <ul>
            <li><strong>Settimanale</strong>: incassi giorno per giorno nella settimana.</li>
            <li><strong>Mensile</strong>: totali per mese, con link ai dettagli giornalieri.</li>
            <li><strong>Annuale</strong>: panoramica anno completo mese per mese.</li>
          </ul>
          <p>Ogni vista offre un pulsante <em>Esporta CSV</em> per il contabile o il commercialista.</p>
          <a class="ob-panel-link" href="<?= base_url('cassa/annuale.php') ?>">Vai al report annuale →</a>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">5</div>
        <div class="ob-body">
          <h3>Audit log e retention</h3>
          <p>La sezione <strong>Audit</strong> mostra tutte le operazioni effettuate nel sistema: login, salvataggi, chiusure giornata, modifiche impostazioni.</p>
          <ul>
            <li>I log sono paginati (100 per pagina) ed esportabili in CSV.</li>
            <li>La <strong>politica di retention</strong> (configurabile in Impostazioni) definisce per quanti giorni mantenere i log. Il pulsante «Applica retention» nella pagina Audit elimina i record più vecchi del limite impostato.</li>
          </ul>
          <div class="ob-tip">La retention è un'operazione irreversibile. Prima di applicarla verifica il conteggio dei record mostrato nel pannello.</div>
          <a class="ob-panel-link" href="<?= base_url('account/admin/audit.php') ?>">Vai all'audit log →</a>
        </div>
      </div>

    </div>
  </div>
  <?php endif; ?>

  <?php if ($role !== 'revisore'): ?>
  <div style="text-align:center; margin-top:32px; padding:20px 0 0; border-top:1px solid var(--border)">
    <p style="font-size:13px; color:var(--muted); margin:0 0 10px">Vuoi rivedere il wizard di benvenuto?</p>
    <button class="btnlink" id="btn-replay-wizard" type="button">Rivedi guida popup</button>
    <p style="font-size:11px; color:var(--faint); margin:8px 0 0">Il popup riapparirà alla prossima apertura del giornaliero.</p>
  </div>

  <div style="text-align:center; padding:24px 0 40px">
    <a class="btnlink" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero →</a>
  </div>
  <?php endif; ?>

  <!-- Tab revisore -->
  <div class="ob-panel <?= $role === 'revisore' ? 'active' : '' ?>" id="ob-panel-rev">
    <div class="ob-steps">

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">1</div>
        <div class="ob-body">
          <h3>Il ruolo revisore</h3>
          <p>Come revisore hai accesso in sola lettura ai report finanziari della sala. Non puoi effettuare operazioni di cassa né modificare dati.</p>
          <p style="margin-top:8px">Le sezioni disponibili sono:</p>
          <ul>
            <li><strong>Settimanale</strong> — dati Bet/Win SNAI per ogni settimana del mese, con versamenti e bancomat.</li>
            <li><strong>Mensile</strong> — riepilogo cassa giorno per giorno e tabella fornitori per il mese.</li>
            <li><strong>Annuale</strong> — panoramica incassi e versamenti mese per mese per l'intero anno.</li>
          </ul>
          <a class="ob-panel-link" href="<?= base_url('cassa/settimanale.php') ?>">Vai al settimanale →</a>
        </div>
      </div>

      <div class="ob-step">
        <div class="ob-num" aria-hidden="true">2</div>
        <div class="ob-body">
          <h3>Come navigare i report</h3>
          <ul>
            <li>Usa le frecce ← → nell'header per spostarti tra settimane, mesi o anni.</li>
            <li>Clicca il nome di un mese nella pagina annuale per aprire il dettaglio mensile.</li>
            <li>Usa i pulsanti <strong>CSV</strong> o <strong>Stampa</strong> per esportare i dati in formato foglio di calcolo o PDF.</li>
          </ul>
          <div class="ob-tip">I dati sono in sola lettura: le tabelle Bet/Win mostrano i valori inseriti dagli operatori ma non possono essere modificati da te.</div>
        </div>
      </div>

    </div>
  </div>

</div>

<script>
document.getElementById('btn-replay-wizard')?.addEventListener('click', function() {
  localStorage.removeItem('gp_wizard_done');
  window.location.href = '<?= base_url('cassa/giornaliero.php') ?>';
});

document.querySelectorAll('.ob-tab-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.ob-tab-btn').forEach(function(b) { b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
    document.querySelectorAll('.ob-panel').forEach(function(p) { p.classList.remove('active'); });
    btn.classList.add('active');
    btn.setAttribute('aria-selected','true');
    var panel = document.getElementById('ob-panel-' + btn.dataset.tab);
    if (panel) panel.classList.add('active');
  });
});
</script>
</body></html>
