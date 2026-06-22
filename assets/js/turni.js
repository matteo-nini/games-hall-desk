(function () {
  /* --- dialog inizia turno (operatore) --- */
  var btnI = document.getElementById('btn-inizia');
  var dlgI = document.getElementById('dlg-inizia');
  if (btnI && dlgI) {
    btnI.addEventListener('click', function () {
      var n         = this.dataset.n;
      var label     = this.dataset.label;
      var assegnato = this.dataset.assegnato;
      var altroNome = this.dataset.altroNome;
      var altro     = this.dataset.altro === '1';
      document.getElementById('dlg-numero').value = n;
      var html = '<p class="tp-dlg-turno"><strong>' + label + '</strong></p>';
      if (assegnato === 'me')
        html += '<p class="tp-dlg-ok">Sei assegnato a questo turno nel calendario.</p>';
      else if (assegnato === 'nessuno')
        html += '<p class="tp-dlg-warn">Non sei nel calendario per questo turno, ma puoi comunque iniziarlo.</p>';
      else
        html += '<p class="tp-dlg-warn">Il turno è assegnato a <strong>' + assegnato + '</strong>. Procedendo, risulterai tu come operatore.</p>';
      if (altro && altroNome)
        html += '<p class="tp-dlg-danger"><strong>Attenzione:</strong> ' + altroNome + ' ha già iniziato un turno oggi.</p>';
      document.getElementById('dlg-body').innerHTML = html;
      dlgI.showModal();
    });
    document.getElementById('dlg-cancel').addEventListener('click', function () { dlgI.close(); });
    dlgI.addEventListener('click', function (e) { if (e.target === dlgI) dlgI.close(); });
  }

  /* --- dialog assegna turno (responsabile) --- */
  var dlgA = document.getElementById('dlg-assegna');
  if (dlgA) {
    document.querySelectorAll('.tp-slot-add').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('ass-data').value   = this.dataset.data;
        document.getElementById('ass-numero').value = this.dataset.n;
        document.getElementById('ass-title').textContent = 'Assegna — ' + this.dataset.label;
        document.getElementById('ass-op').value = '';
        dlgA.showModal();
      });
    });
    document.getElementById('ass-cancel').addEventListener('click', function () { dlgA.close(); });
    dlgA.addEventListener('click', function (e) { if (e.target === dlgA) dlgA.close(); });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    [dlgI, dlgA].forEach(function (d) { if (d && d.open) d.close(); });
  });
}());
