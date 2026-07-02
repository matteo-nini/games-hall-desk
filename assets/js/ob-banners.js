/* Onboarding system: contextual help button + first-run wizard */
(function () {
  'use strict';

  var BASE = window.GP_BASE || '/';
  var ROLE = window.GP_ROLE || '';
  var page = location.pathname.replace(/^.*\/([^/]+)\.php.*$/, '$1');

  /* ── Contextual help copy (da docs/) ────────────────────── */
  var HELP = {
    giornaliero: {
      title: 'Cassa giornaliera',
      text: 'Compila fondo cassa, contanti per taglio, scassettamenti VLT, ticket pagati e bancomat per ogni turno. La barra di stato mostra cassetto, versamento e scostamento in tempo reale. Salva ogni turno separatamente, poi chiudi la giornata.',
      link: BASE + 'docs/onboarding.php',
      linkText: 'Guida operativa'
    },
    settimanale: {
      title: 'Riepilogo settimanale',
      text: 'Incassi giornalieri con dati Bet/Win SNAI per fornitore. Naviga tra settimane con le frecce. I badge Δ% confrontano con la settimana precedente. Il responsabile può importare i dati XLS/XLSX scaricati da SISAL.'
    },
    mensile: {
      title: 'Riepilogo mensile',
      text: 'Totali giorno per giorno con confronto Δ% sul mese precedente. Filtra per operatore, esporta in Excel (3 fogli: cassa, Bet/Win, VLT per macchina) o CSV per il contabile.'
    },
    annuale: {
      title: 'Vista annuale',
      text: 'Incassi mese per mese per l\'anno selezionato. Clicca su un mese per aprire il riepilogo mensile. Il filtro operatore si propaga ai link mensili.'
    },
    awp: {
      title: 'Storico refill AWP',
      text: 'Registro completo di tutti i rifornimenti sulle macchine AWP. I refill si inseriscono nel giornaliero durante il turno — qui trovi solo la consultazione dello storico.'
    },
    turni: {
      title: 'Calendario turni',
      text: 'Pianificazione mensile dei turni per operatore. Il responsabile assegna i turni; gli operatori (se abilitati) possono aggiungersi. I revisori lo vedono in sola lettura se il permesso è attivo in Impostazioni.'
    },
    ticket: {
      title: 'Ticket assistenza',
      text: 'Apri un ticket per ogni guasto macchina. Dopo l\'apertura il sistema propone di stampare un avviso da esporre sulla macchina. Chiudi con la descrizione della risoluzione quando il problema è stato risolto.'
    },
    prestiti: {
      title: 'Prestiti e rientri',
      text: 'Traccia i prestiti in denaro per persona. Il saldo mostra quanto non è ancora rientrato. Ogni rientro va registrato anche nel campo "Rientri" del form cassa giornaliera.'
    },
    utenti: {
      title: 'Gestione utenti',
      text: 'Crea utenti con password (accesso immediato) o con solo email (il sistema invia un link di attivazione valido 24 ore). Con email configurata, ogni utente può fare il reset password autonomamente dalla pagina di login.',
      link: BASE + 'docs/onboarding.php',
      linkText: 'Guida per responsabili'
    },
    macchine: {
      title: 'Macchine e fornitori',
      text: 'Aggiungi VLT e AWP con codice, tipo, fornitore e ordine. Seriale e CIV sono modificabili inline. Le macchine disattivate scompaiono dal giornaliero ma restano nello storico.'
    },
    impostazioni: {
      title: 'Impostazioni sala',
      text: 'Identità sala, brand colori, turni, permessi operatori e moduli opzionali. Configura l\'email di sistema per abilitare reset password, link di attivazione account e riepilogo versamenti ai revisori.'
    },
    audit: {
      title: 'Audit log',
      text: 'Registro completo di login, salvataggi, chiusure e modifiche impostazioni. Filtra per utente, azione o date. La retention (configurabile in Impostazioni) definisce per quanti giorni mantenere i log.'
    },
    dashboard: {
      title: 'Dashboard',
      text: 'Riepilogo giornata e accesso rapido alle funzioni principali. I KPI si aggiornano automaticamente ogni 30 secondi — il badge live lampeggia ad ogni refresh.',
      link: BASE + 'docs/onboarding.php',
      linkText: 'Guida operativa'
    },
    revisore: {
      title: 'Dashboard revisore',
      text: 'Conferma i versamenti delle giornate chiuse — la conferma registra importo, IP e timestamp come prova di ricezione. I KPI mostrano giorni da confermare, totale confermato e copertura del mese.'
    },
    documenti: {
      title: 'Documenti',
      text: 'Visualizza e scarica i file caricati dal responsabile. I documenti richiedono l\'autenticazione. Il responsabile può creare cartelle e riorganizzare con drag & drop.'
    }
  };

  /* ── Help button ─────────────────────────────────────────── */
  var activePop = null;

  function closePop() {
    if (activePop) { activePop.remove(); activePop = null; }
  }

  function showHelp() {
    var info = HELP[page];
    if (!info) return;

    var btn = document.createElement('button');
    btn.className = 'ob-help-btn';
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Aiuto — ' + info.title);
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>';

    var actions = document.querySelector('.topbar-actions');
    if (actions) {
      actions.prepend(btn);
    } else {
      var topbar = document.querySelector('.topbar');
      if (topbar) topbar.appendChild(btn);
      else return;
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (activePop) { closePop(); return; }

      var pop = document.createElement('div');
      pop.className = 'ob-help-pop';
      pop.setAttribute('role', 'tooltip');

      var strong = document.createElement('strong');
      strong.className = 'ob-hp-title';
      strong.textContent = info.title;

      var p = document.createElement('p');
      p.className = 'ob-hp-body';
      p.textContent = info.text;

      pop.appendChild(strong);
      pop.appendChild(p);

      if (info.link) {
        var a = document.createElement('a');
        a.className = 'ob-hp-link';
        a.href = info.link;
        a.textContent = (info.linkText || 'Guida completa') + ' →';
        pop.appendChild(a);
      }

      document.body.appendChild(pop);
      activePop = pop;

      var bRect = btn.getBoundingClientRect();
      pop.style.top = (bRect.bottom + 8) + 'px';
      pop.style.right = Math.max(8, window.innerWidth - bRect.right) + 'px';

      function outside(ev) {
        if (activePop && !activePop.contains(ev.target) && ev.target !== btn) {
          closePop();
          document.removeEventListener('click', outside);
        }
      }
      setTimeout(function () { document.addEventListener('click', outside); }, 10);
    });
  }

  /* ── First-run wizard (responsabile only) ───────────────── */
  var WIZARD_STEPS = [
    {
      title: 'Benvenuto' + (window.GP_SALA ? ' in ' + window.GP_SALA : ''),
      body: 'Questo assistente ti guida nella configurazione iniziale della sala. Bastano pochi minuti.',
      next: 'Inizia'
    },
    {
      title: 'Configura la sala',
      body: 'Vai in <strong>Impostazioni</strong> per impostare nome sala, brand colori, turni, permessi operatori e l\'email di sistema per notifiche e reset password.',
      next: 'Avanti',
      link: BASE + 'admin/impostazioni.php',
      linkText: 'Vai alle impostazioni'
    },
    {
      title: 'Aggiungi le macchine',
      body: 'In <strong>Macchine</strong> inserisci tutte le VLT e AWP della sala con codice, fornitore e ordine di visualizzazione nel giornaliero.',
      next: 'Avanti',
      link: BASE + 'admin/macchine.php',
      linkText: 'Vai alle macchine'
    },
    {
      title: 'Crea gli operatori',
      body: 'In <strong>Utenti</strong> crea un account per ogni operatore. Puoi usare solo email — il sistema invia un link di attivazione valido 24 ore.',
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
        (idx === 0 ? '<div class="ob-wiz-icon" aria-hidden="true">' + ((window.GP_SALA || '').replace(/\s+/g, ' ').trim().split(' ').slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || 'CS') + '</div>' : '') +
        '<h2>' + esc(s.title) + '</h2>' +
        '<p>' + s.body + '</p>' +
        '<div class="ob-wiz-actions">' +
          '<div class="ob-wiz-dots">' + dotsHtml + '</div>' +
          '<div style="display:flex;gap:8px;align-items:center">' +
            (!s.isLast ? '<button class="ob-wiz-skip" data-skip>Salta tutto</button>' : '') +
            (s.link && !s.isLast ? '<a class="ob-wiz-next ob-wiz-ghost" href="' + s.link + '" onclick="localStorage.setItem(\'gp_wizard_done\',\'1\')">' + esc(s.linkText) + '</a>' : '') +
            (s.isLast
              ? '<a class="ob-wiz-next" href="' + s.link + '" onclick="localStorage.setItem(\'gp_wizard_done\',\'1\')">' + esc(s.next) + ' →</a>'
              : '<button class="ob-wiz-next" data-next>' + esc(s.next) + ' →</button>') +
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
      nextBtn.addEventListener('click', function () { renderStep(dlg, idx + 1); });
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

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ── Init ─────────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { showHelp(); showWizard(); });
  } else {
    showHelp();
    showWizard();
  }

}());
