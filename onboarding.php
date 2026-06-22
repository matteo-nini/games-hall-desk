<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lib.php';
$user = require_login();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Guida operativa</title><link rel="stylesheet" href="assets/css/core.css">
<link rel="stylesheet" href="assets/css/onboarding.css"></head><body>
<?php require __DIR__ . '/includes/nav.php'; top_menu($user); ?>

<div class="ob-wrap">

  <div class="ob-hero">
    <div class="ob-icon">&#9654;</div>
    <h1>Guida operativa</h1>
    <p>Come usare il sistema di cassa giornaliera</p>
  </div>

  <div class="ob-steps">

    <div class="ob-step">
      <div class="ob-num">1</div>
      <div class="ob-body">
        <h3>Il flusso della giornata</h3>
        <p>Ogni giornata è divisa in due turni: <strong>Mattino (controllo)</strong> e <strong>Sera (chiusura)</strong>.</p>
        <ul>
          <li>Il turno di <strong>mattino</strong> registra il controllo cassetto effettuato all'apertura.</li>
          <li>Il turno di <strong>sera</strong> registra la chiusura con tutti gli incassi e gli scassettamenti VLT.</li>
          <li>Ogni operatore compila il proprio turno e salva: il sistema salva <em>solo il turno visualizzato</em>, senza toccare quello dell'altro operatore.</li>
        </ul>
      </div>
    </div>

    <div class="ob-step">
      <div class="ob-num">2</div>
      <div class="ob-body">
        <h3>Cosa inserire nella scheda cassa</h3>
        <ul>
          <li><strong>Fondo cassa</strong>: importo in cassa all'inizio del turno (stabilito dal responsabile).</li>
          <li><strong>Contanti</strong>: conta le banconote e inserisci il numero di pezzi per ogni taglio. Il totale si calcola in automatico.</li>
          <li><strong>Monete</strong>: totale in monete (da inserire come unico importo).</li>
          <li><strong>Bancomat</strong>: totale incassato tramite POS nel turno.</li>
          <li><strong>Ticket pagati</strong>: totale ticket vincita pagati per ogni fornitore (NOVO, INSPIRED, SPIELO).</li>
        </ul>
        <div class="ob-tip">&#9432; Il <strong>cassetto</strong> e il <strong>versamento VLT</strong> vengono calcolati automaticamente. Controlla sempre che lo scostamento sia il più vicino possibile a zero.</div>
      </div>
    </div>

    <div class="ob-step">
      <div class="ob-num">3</div>
      <div class="ob-body">
        <h3>Scassettamenti VLT</h3>
        <p>Per ogni macchina VLT inserisci l'importo prelevato dalla cassetta durante il turno. Il totale incasso VLT si calcola automaticamente.</p>
        <ul>
          <li>Le macchine sono raggruppate per fornitore (NOVO, INSPIRED, SPIELO).</li>
          <li>Lascia a zero le macchine non scassettate nel turno.</li>
        </ul>
      </div>
    </div>

    <div class="ob-step">
      <div class="ob-num">4</div>
      <div class="ob-body">
        <h3>La quadratura &laquo;I conti tornano&raquo;</h3>
        <p>Il banner in cima cambia colore in base allo <strong>scostamento</strong> (differenza tra totale in cassa e fondo cassa):</p>
        <div class="ob-legend">
          <span class="ob-dot ob-green"></span> <strong>Verde</strong> &mdash; scostamento &lt; 4 € (ottimo)
        </div>
        <div class="ob-legend">
          <span class="ob-dot ob-amber"></span> <strong>Giallo</strong> &mdash; scostamento 4–5 € (tollerabile)
        </div>
        <div class="ob-legend">
          <span class="ob-dot ob-red"></span> <strong>Rosso</strong> &mdash; scostamento &gt; 5 € (verificare)
        </div>
        <p style="margin-top:10px">In caso di scostamento elevato controlla: conteggio contanti, bancomat, ticket e scassettamenti.</p>
      </div>
    </div>

    <div class="ob-step">
      <div class="ob-num">5</div>
      <div class="ob-body">
        <h3>Chiusura della giornata</h3>
        <p>Al termine del turno sera, dopo aver salvato, clicca <strong>&laquo;Chiudi giornata&raquo;</strong>. Una giornata chiusa non può essere modificata dagli operatori (solo il responsabile può riaprirla).</p>
        <div class="ob-tip">&#9432; Chiudi sempre la giornata al termine del turno sera: serve per bloccare modifiche accidentali e per far apparire la giornata come &laquo;Chiusa&raquo; nel calendario.</div>
      </div>
    </div>

    <div class="ob-step">
      <div class="ob-num">6</div>
      <div class="ob-body">
        <h3>Ticket assistenza macchine</h3>
        <p>Se una macchina presenta un guasto o un'anomalia, apri un ticket dalla sezione <strong>Ticket</strong> nel menu. Inserisci la macchina, descrivi il problema e (se disponibile) il numero di ticket fornito dall'assistenza. Quando il problema è risolto, chiudi il ticket con la descrizione dell'intervento.</p>
      </div>
    </div>

    <div class="ob-step">
      <div class="ob-num">7</div>
      <div class="ob-body">
        <h3>Prestiti e rientri</h3>
        <p>La sezione <strong>Prestiti</strong> traccia i prestiti in denaro effettuati a clienti o collaboratori. Per ogni persona è visibile il saldo attuale (colonna <em>Dare</em>). Registra ogni prestito o rientro con la data e l'importo.</p>
        <ul>
          <li><strong>Prestito</strong>: denaro dato alla persona → il saldo aumenta.</li>
          <li><strong>Rientro</strong>: denaro restituito → il saldo diminuisce.</li>
        </ul>
      </div>
    </div>

  </div>

  <div style="text-align:center; padding: 24px 0 40px">
    <a class="btnlink" href="<?= base_url('cassa/giornaliero.php') ?>">Vai al giornaliero &rarr;</a>
  </div>

</div>
</body></html>
