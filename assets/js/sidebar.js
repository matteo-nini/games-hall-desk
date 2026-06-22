(function () {
  var SB_KEY = 'gp_sb_collapsed';
  var sidebar  = document.getElementById('sidebar');
  var toggle   = document.getElementById('sb-toggle');
  var overlay  = document.getElementById('sb-overlay');
  var colBtn   = document.getElementById('sb-collapse');

  /* === Mobile open/close === */
  function openMob()  { sidebar.classList.add('open');  overlay.classList.add('open');  toggle && toggle.setAttribute('aria-expanded','true');  document.body.classList.add('sb-open'); }
  function closeMob() { sidebar.classList.remove('open'); overlay.classList.remove('open'); toggle && toggle.setAttribute('aria-expanded','false'); document.body.classList.remove('sb-open'); }
  if (toggle)  toggle.addEventListener('click', function () { sidebar.classList.contains('open') ? closeMob() : openMob(); });
  if (overlay) overlay.addEventListener('click', closeMob);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sidebar.classList.contains('open')) { closeMob(); if(toggle) toggle.focus(); } });

  /* === Desktop collapse === */
  var collapsed = localStorage.getItem(SB_KEY) === '1';
  function applyCollapse(c) {
    sidebar.classList.toggle('sb-collapsed', c);
    document.body.classList.toggle('sb-collapsed', c);
    if (colBtn) colBtn.setAttribute('aria-expanded', c ? 'false' : 'true');
    localStorage.setItem(SB_KEY, c ? '1' : '0');
  }
  applyCollapse(collapsed);
  if (colBtn) colBtn.addEventListener('click', function () { applyCollapse(!sidebar.classList.contains('sb-collapsed')); });
}());
