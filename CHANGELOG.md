# Changelog

Tutte le modifiche notevoli a Games Palace Desk sono documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e il progetto adotta il [Versionamento Semantico](https://semver.org/lang/it/).

---

## [1.6.0] — 2026-07-02

### Added
- Mailer centralizzato (`includes/mail/mailer.php`) con quattro funzioni dedicate:
  `mail_reset_password`, `mail_nuovo_account`, `mail_cambio_password`,
  `mail_chiusura_giornata`; tutti i template usano logo e accent brand dalla tabella
  `impostazioni`
- Salvataggio indirizzo email nel profilo utente; se presente, alla creazione account
  senza password viene inviata l'email di benvenuto con link di attivazione valido
  24 ore

### Fixed
- Aggiunto punto finale e margine mancanti nella pagina di login

---

## [1.5.0] — 2026-07-01

### Added
- Dashboard: sezione "Versamenti in sospeso" e "Versamenti recenti" per revisori e
  responsabili
- Email di reset password con template HTML, logo personalizzato della sala e URL
  dinamico (protocollo + host rilevati dal server)
- Supporto al salvataggio dati bet/win SNAI su più date nella stessa sessione di
  import; interfaccia di chiusura giornata aggiornata di conseguenza
- Import XLS/XLSX giornaliero SISAL direttamente nel modulo settimanale
- Guida chiusura sala: nuovo tab con procedura passo-passo per gli operatori in
  `cassa/giornaliero.php`
- Campo telefono nel profilo utente; telefono e sito web della sala in Impostazioni;
  login tramite email oltre che username
- Ricerca live (filter-as-you-type) nelle pagine Utenti, Contatti, Macchine e
  Documenti

### Changed
- Tab stile: da underline a card-tab (segmented control) nelle pagine Macchine e
  Onboarding
- Hero sezione-cassa (`sh-hero`) resa come card distinte con shadow su sticky e
  maggiore spaziatura verso il form
- Ottimizzazione CSS responsive delle tabelle; rimosso codice morto

### Fixed
- Sincronizzazione contatti: eliminati duplicati su telefono/email e aggiornamento
  corretto di `email_sala`

---

## [1.4.0] — 2026-06-30

### Added
- Sistema di conferma versamento tracciata: tabella `versamenti_confermati` con
  importo, IP, user-agent e timestamp; badge verde `gd-conf-badge` visibile nel
  giornaliero
- Dashboard dedicata per revisori (`account/revisore.php`) con KPI mensili (da
  confermare, totale €, giorni coperti, % copertura), tabella giornate da confermare,
  andamento ultimi 6 mesi e storico ultimi 100 versamenti confermati
- Email agli utenti: campo email nella tabella `utenti`; flusso reset password
  self-service da pagina di login (`account/reset_password.php` +
  `account/reset_confirm.php`), token 64-char, scadenza 1 ora
- Sezione Permessi unificata in Impostazioni: `operatori_modifica_turni`,
  `turno_edit_libero`, `mobile_giornaliero`, `mobile_turni_edit`,
  `revisori_vedi_turni`; i revisori con permesso vedono il calendario turni in
  sola lettura
- Pagina Contatti (`sala/contatti.php`) con tabella `ul-table`, menu 3-punti e
  aggiunta tramite dialog popup
- Unificazione Macchine + Fornitori in un'unica sezione; eliminazione redirect
  `fornitori.php`
- Dashboard unificata per ruolo in `account/dashboard.php`; i file
  `responsabile.php` e `revisore.php` diventano redirect di compatibilità

### Changed
- Refactoring contatti: usa layout `ul-table` + menu 3-punti coerente con
  `utenti.php`
- Campo `macchine.fornitore` convertito da ENUM a VARCHAR per maggiore flessibilità

### Fixed
- Navigazione settimanale: settimane fisse 1–7 / 8–15 / 16–23 / 24+ del mese;
  il link al calendario turni mantiene il mese corrente
- Rimosso warning P1116 Intelephense in `dashboard.php`

---

## [1.3.0] — 2026-06-27

### Added
- Mobile webapp completa: bottom tab bar, meta PWA (`manifest.json`, `theme-color`),
  calcolo stipendio mensile per operatori
- Impostazioni Mobile in Impostazioni → Permessi: `mobile_giornaliero` e
  `mobile_turni_edit`
- Sidebar nascosta su mobile (≤ 760 px); giornaliero e turni in sola lettura su
  dispositivi mobili quando il permesso è disattivato
- Mobile craft: calendario turni a chip, hero dashboard nativa ottimizzata,
  fix layout impostazioni su schermi piccoli
- Tab Cassa condizionale: mostrata solo quando la giornata è aperta su mobile
- Bottom sheet dettaglio giorno con riepilogo turni su mobile nel calendario

---

## [1.2.0] — 2026-06-26

### Added
- Avatar con doppia iniziale e gradiente deterministico (crc32 → HSL) per sidebar,
  lista operatori e ogni elemento che mostra un utente; helper `avatar_initials()` e
  `avatar_style()` in `lib.php`
- Modulo Documenti: cartelle con drag & drop HTML5 (`doc-draggable` /
  `doc-dropzone`), riorganizzazione documenti tra cartelle, menu 3-dot per azioni
  secondarie (Scarica, Sposta, Elimina)

### Fixed
- Contrasti dark mode: colori faint, testo accent e moduli resi leggibili con
  `color-mix()`
- Operatori abilitati a caricare documenti, creare cartelle e spostare file
- Colonna totali nel report settimanale; allineamento topbar globale

---

## [1.1.0] — 2026-06-24 / 2026-06-25

### Added
- Logo personalizzabile della sala (upload in Impostazioni → Aspetto); utilizzato in
  header, email e stampa guasto
- Statistiche operatori con grafici (Chart.js) e confronto tra settimane
- Layout fornitori con configurazione da interfaccia; supporto turni 1–3; timezone e
  alert giornata aperta
- Raggruppamento card in Impostazioni; layout a 2 colonne nel report settimanale
- Import SQL prestiti da wizard; alert banner giornata aperta nella dashboard
- Orari turno visualizzati in tab, export Excel e calendario
- Export CSV mensile completo
- Pagina report annuale con navigazione tra anni
- Rate limiting tentativi di accesso (tabella `login_attempts`)
- Ritaglio foto profilo utente (Cropper.js)
- Setup wizard in 6 passi per nuove installazioni (`install/setup.php`)
- Pagine di errore personalizzate 403 e 404 via `.htaccess`
- Modulo Ticket assistenza (`sala/ticket.php`) con print guasto standalone
  (`sala/print_guasto.php`)
- Cambio ruolo utente direttamente dalla lista utenti
- Campi seriale e CIV per le macchine

### Changed
- Refactoring struttura file: cartelle `cassa/`, `sala/`, `account/`, `utils/`
- Sidebar con icone SVG per ogni voce di menu
- Visualizzazione rettifiche nel modulo di cassa rinominata e ristrutturata
- Campo '2ª cassa' (ex 'II cassa') nel form giornaliero
- Calcolo versamento aggiornato per includere arrotondamenti corretti in tutti i
  report (settimanale, mensile, annuale)

### Fixed
- Percorso immagine profilo utente corretto dopo il refactoring cartelle

---

## [1.0.0] — 2026-06-18 / 2026-06-22

### Added
- Sistema di gestione cassa giornaliera con turni mattino/sera (tab swipe)
- Logica cassa: cassetto, versamento, totale, scostamento calcolati live in JS e
  server-side in `calcola_turno()`; banner colorato per soglie scostamento
- Sidebar con layout e navigazione a icone
- Calendario turni mensile con auto-assegnazione operatori
- Gestione macchine VLT/AWP e utenti (operatore / responsabile / revisore)
- Profilo utente con foto
- Dashboard con KPI giornata e mese; polling live ogni 30 secondi
- Report settimanale e mensile
- Modulo Prestiti e rientri
- White label: accent color brand da `impostazioni`, derivazione `--accent-weak` e
  `--accent-ink` con `brand_derive()`
- Dark mode con toggle nella sidebar, anti-FOUC, variabili CSS ridefinite in
  `html[data-theme="dark"]`
- Tour onboarding spotlight (`assets/js/tour.js`) con flag `gp_wizard_done`
- Export Excel nativo (`includes/XlsxWriter.php`) senza dipendenze esterne
- Audit log su tutte le operazioni significative
- Protezione CSRF su tutti i form POST
- Prepared statement PDO su ogni query; `htmlspecialchars()` su tutti gli output

---

[1.6.0]: https://github.com/matteo-nini/games-palace-desk/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/matteo-nini/games-palace-desk/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/matteo-nini/games-palace-desk/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/matteo-nini/games-palace-desk/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/matteo-nini/games-palace-desk/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/matteo-nini/games-palace-desk/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/matteo-nini/games-palace-desk/releases/tag/v1.0.0
