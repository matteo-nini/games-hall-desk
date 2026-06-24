/* Onboarding system: section banners + first-run wizard */
(function () {
  'use strict';

  var BASE = window.GP_BASE || '/';
  var ROLE = window.GP_ROLE || '';

  var page = location.pathname.replace(/^.*\/([^/]+)\.php.*$/, '$1');

  /* ── Section banners ─────────────────────────────────────── */
  var BANNERS = {
    giornaliero: {
      title: 'Cassa giornaliera',
      text: 'Inserisci i dati del turno attivo: contanti per taglio, scassettamenti VLT, ticket e bancomat. Versamento e scostamento si calcolano in automatico.',
      link: BASE + 'onboarding.php',
      linkText: 'Leggi la guida completa'
    },
    settimanale: {
      title: 'Riepilogo settimanale',
      text: 'Incassi giorno per giorno della settimana selezionata. Clicca su una riga per aprire il giornaliero di quella data.'
    },
    mensile: {
      title: 'Riepilogo mensile',
      text: 'Totali aggregati per mese. Usa il pulsante Esporta CSV per inviare i dati al contabile.'
    },
    annuale: {
      title: 'Vista annuale',
      text: 'Incassi mese per mese per l\'anno selezionato. Clicca su un mese per aprire il riepilogo mensile.'
    },
    awp: {
      title: 'Refill AWP',
      text: 'Registra i rifornimenti effettuati sulle macchine AWP durante il turno. Ogni riga corrisponde a un intervento su una macchina specifica.'
    },
    turni: {
      title: 'Turni programmati',
      text: 'Calendario dei turni assegnati agli operatori. Le celle mostrano chi è in servizio in ogni fascia oraria.'
    },
    ticket: {
      title: 'Ticket assistenza',
      text: 'Apri un ticket per ogni guasto o anomalia su una macchina. Chiudi il ticket quando il problema è risolto, aggiungendo la risoluzione.'
    },
    prestiti: {
      title: 'Prestiti e rientri',
      text: 'Traccia prestiti in denaro per persona. Il saldo mostra il totale ancora non rientrato. Registra ogni movimento con data e importo.'
    },
    utenti: {
      title: 'Gestione utenti',
      text: 'Crea e modifica gli account degli operatori e dei responsabili. Solo il responsabile può creare utenti o cambiare le password.',
      link: BASE + 'onboarding.php',
      linkText: 'Guida per responsabili'
    },
    macchine: {
      title: 'Parco macchine',
      text: 'Aggiungi le macchine VLT e AWP della sala. Le macchine disattivate non compaiono nel giornaliero ma rimangono nello storico.'
    },
    impostazioni: {
      title: 'Impostazioni sala',
      text: 'Configura orari turni, prezzi, permessi operatori e moduli attivi. La politica di retention determina per quanti giorni conservare i log audit.'
    },
    audit: {
      title: 'Audit log',
      text: 'Log di tutte le operazioni effettuate nel sistema. Usa il pannello di retention per eliminare i record più vecchi del limite configurato.'
    },
    dashboard: {
      title: 'Dashboard operatore',
      text: 'Riepilogo rapido della giornata corrente. Salva i dati regolarmente durante il turno per tenere i calcoli aggiornati.',
      link: BASE + 'onboarding.php',
      linkText: 'Leggi la guida operativa'
    },
    responsabile: {
      title: 'Dashboard responsabile',
      text: 'Controlla l\'incasso giornaliero e mensile, l\'andamento delle ultime giornate e accedi rapidamente alle funzioni di amministrazione.',
      link: BASE + 'onboarding.php',
      linkText: 'Leggi la guida per responsabili'
    }
  };

  function showBanner() {
    var info = BANNERS[page];
    if (!info) return;
    var key = 'gp_ob_' + page;
    if (localStorage.getItem(key)) return;

    var el = document.createElement('div');
    el.className = 'ob-section-banner';
    el.setAttribute('role', 'note');
    el.innerHTML =
      '<div class="ob-sb-content">' +
        '<strong>' + info.title + '</strong>' +
        '<span>' + info.text + '</span>' +
        (info.link ? '<a class="ob-sb-link" href="' + info.link + '">' + info.linkText + '</a>' : '') +
      '</div>' +
      '<button class="ob-sb-close" aria-label="Chiudi suggerimento" title="Non mostrare più">&times;</button>';

    var topbar = document.querySelector('.topbar');
    if (topbar) {
      topbar.insertAdjacentElement('afterend', el);
    } else {
      document.body.prepend(el);
    }

    el.querySelector('.ob-sb-close').addEventListener('click', function () {
      localStorage.setItem(key, '1');
      el.remove();
    });
  }

  /* ── First-run wizard ────────────────────────────────────── */
  var WIZARD_STEPS = [
    {
      title: 'Benvenuto' + (window.GP_SALA ? ' in ' + window.GP_SALA : ''),
      body: 'Questo assistente ti guida nella configurazione iniziale della sala. Bastano pochi minuti.',
      next: 'Inizia'
    },
    {
      title: 'Configura la sala',
      body: 'Vai in <strong>Impostazioni</strong> per impostare orari turni, prezzi e permessi degli operatori.',
      next: 'Avanti',
      link: BASE + 'admin/impostazioni.php',
      linkText: 'Vai alle impostazioni'
    },
    {
      title: 'Aggiungi le macchine',
      body: 'In <strong>Macchine</strong> inserisci tutte le VLT e AWP della sala con codice, fornitore e ordine.',
      next: 'Avanti',
      link: BASE + 'admin/macchine.php',
      linkText: 'Vai alle macchine'
    },
    {
      title: 'Crea gli operatori',
      body: 'In <strong>Utenti</strong> crea un account per ogni operatore. Ogni operatore accede con le proprie credenziali.',
      next: 'Avanti',
      link: BASE + 'admin/utenti.php',
      linkText: 'Vai agli utenti'
    },
    {
      title: 'Sei pronto!',
      body: 'La configurazione è completa. Apri il giornaliero e inizia a registrare i dati del primo turno.',
      next: 'Inizia a lavorare',
      link: BASE + 'cassa/giornaliero.php',
      isLast: true
    }
  ];

  function buildWizard() {
    var dlg = document.createElement('dialog');
    dlg.className = 'ob-wizard-dlg';
    dlg.id = 'ob-wizard';
    document.body.appendChild(dlg);
    return dlg;
  }

  function renderStep(dlg, idx) {
    var s = WIZARD_STEPS[idx];
    var dotsHtml = WIZARD_STEPS.map(function (_, i) {
      return '<span class="ob-wiz-dot' + (i === idx ? ' active' : '') + '"></span>';
    }).join('');

    dlg.innerHTML =
      '<div class="ob-wiz-step">' +
        '<div class="ob-wiz-num">Passo ' + (idx + 1) + ' di ' + WIZARD_STEPS.length + '</div>' +
        (idx === 0 ? '<div class="ob-wiz-icon" aria-hidden="true">' + ((window.GP_SALA||'').replace(/\s+/g,' ').trim().split(' ').slice(0,2).map(function(w){return w.charAt(0).toUpperCase();}).join('')||'CS') + '</div>' : '') +
        '<h2>' + s.title + '</h2>' +
        '<p>' + s.body + '</p>' +
        '<div class="ob-wiz-actions">' +
          '<div class="ob-wiz-dots">' + dotsHtml + '</div>' +
          '<div style="display:flex;gap:8px;align-items:center">' +
            (!s.isLast ? '<button class="ob-wiz-skip" data-skip>Salta tutto</button>' : '') +
            (s.link && !s.isLast
              ? '<a class="ob-wiz-next" style="background:var(--surface);color:var(--accent);border:1px solid var(--border2)" href="' + s.link + '" onclick="localStorage.setItem(\'gp_wizard_done\',\'1\')">' + s.linkText + '</a>'
              : '') +
            (s.isLast
              ? '<a class="ob-wiz-next" href="' + s.link + '" onclick="localStorage.setItem(\'gp_wizard_done\',\'1\')">' + s.next + ' →</a>'
              : '<button class="ob-wiz-next" data-next>' + s.next + ' →</button>') +
          '</div>' +
        '</div>' +
      '</div>';

    var skipBtn = dlg.querySelector('[data-skip]');
    if (skipBtn) {
      skipBtn.addEventListener('click', function () {
        localStorage.setItem('gp_wizard_done', '1');
        dlg.close();
      });
    }

    var nextBtn = dlg.querySelector('[data-next]');
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        renderStep(dlg, idx + 1);
      });
    }
  }

  function showWizard() {
    if (ROLE !== 'responsabile') return;
    if (page !== 'responsabile') return;
    if (localStorage.getItem('gp_wizard_done')) return;

    var dlg = buildWizard();
    renderStep(dlg, 0);
    dlg.showModal();
  }

  /* ── Init ────────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      showBanner();
      showWizard();
    });
  } else {
    showBanner();
    showWizard();
  }

}());
