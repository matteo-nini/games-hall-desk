(function () {
  var STAGE   = 280;
  var modal   = document.getElementById('crop-modal');
  var stage   = document.getElementById('crop-stage');
  var imgEl   = document.getElementById('crop-img');
  var zoomR   = document.getElementById('crop-zoom');
  var zoomOut = document.getElementById('crop-zoom-out');
  var zoomIn  = document.getElementById('crop-zoom-in');
  var btnOk   = document.getElementById('crop-confirm');
  var btnClose= document.getElementById('crop-close');
  var btnCn   = document.getElementById('crop-cancel');
  var canvas  = document.getElementById('crop-canvas');
  var fileIn  = document.querySelector('input[name="foto"]');

  if (!modal || !fileIn) return;

  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var scale = 1, initScale = 1, ox = 0, oy = 0;
  var dragging = false, startX = 0, startY = 0, startOx = 0, startOy = 0;
  var selectedMime = 'image/png';

  fileIn.addEventListener('change', function (e) {
    var file = e.target.files[0];
    if (!file) return;
    selectedMime = file.type === 'image/jpeg' ? 'image/jpeg' : 'image/png';
    var url = URL.createObjectURL(file);
    imgEl.onload = function () {
      imgEl.style.width  = imgEl.naturalWidth  + 'px';
      imgEl.style.height = imgEl.naturalHeight + 'px';
      initScale = Math.max(STAGE / imgEl.naturalWidth, STAGE / imgEl.naturalHeight);
      scale = initScale;
      ox = 0; oy = 0;
      zoomR.value = 100;
      applyTransform();
      modal.hidden = false;
    };
    imgEl.src = url;
    e.target.value = '';
  });

  function applyTransform() {
    var tx = (STAGE - imgEl.naturalWidth  * scale) / 2 + ox;
    var ty = (STAGE - imgEl.naturalHeight * scale) / 2 + oy;
    imgEl.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
  }

  function clamp(v, nDim) {
    var half = Math.max(0, (nDim * scale - STAGE) / 2);
    return Math.max(-half, Math.min(half, v));
  }

  stage.addEventListener('mousedown', function (e) {
    dragging = true;
    startX = e.clientX; startY = e.clientY;
    startOx = ox; startOy = oy;
    stage.classList.add('dragging');
    e.preventDefault();
  });
  window.addEventListener('mousemove', function (e) {
    if (!dragging) return;
    ox = clamp(startOx + (e.clientX - startX), imgEl.naturalWidth);
    oy = clamp(startOy + (e.clientY - startY), imgEl.naturalHeight);
    applyTransform();
  });
  window.addEventListener('mouseup', function () {
    dragging = false;
    stage.classList.remove('dragging');
  });

  stage.addEventListener('touchstart', function (e) {
    var t = e.touches[0];
    dragging = true;
    startX = t.clientX; startY = t.clientY;
    startOx = ox; startOy = oy;
    e.preventDefault();
  }, { passive: false });
  window.addEventListener('touchmove', function (e) {
    if (!dragging) return;
    var t = e.touches[0];
    ox = clamp(startOx + (t.clientX - startX), imgEl.naturalWidth);
    oy = clamp(startOy + (t.clientY - startY), imgEl.naturalHeight);
    applyTransform();
  });
  window.addEventListener('touchend', function () { dragging = false; });

  zoomR.addEventListener('input', function () {
    scale = initScale * (parseInt(this.value) / 100);
    ox = clamp(ox, imgEl.naturalWidth);
    oy = clamp(oy, imgEl.naturalHeight);
    applyTransform();
  });
  zoomOut.addEventListener('click', function () {
    zoomR.value = Math.max(100, parseInt(zoomR.value) - 10);
    zoomR.dispatchEvent(new Event('input'));
  });
  zoomIn.addEventListener('click', function () {
    zoomR.value = Math.min(300, parseInt(zoomR.value) + 10);
    zoomR.dispatchEvent(new Event('input'));
  });

  function closeModal() {
    modal.hidden = true;
    URL.revokeObjectURL(imgEl.src);
    imgEl.src = '';
    btnOk.disabled = false;
    btnOk.textContent = 'Usa questa foto';
  }
  btnClose.addEventListener('click', closeModal);
  btnCn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeModal(); });

  btnOk.addEventListener('click', function () {
    var ctx = canvas.getContext('2d');
    var tx  = (STAGE - imgEl.naturalWidth  * scale) / 2 + ox;
    var ty  = (STAGE - imgEl.naturalHeight * scale) / 2 + oy;
    var srcX = -tx / scale;
    var srcY = -ty / scale;
    var srcW = STAGE / scale;
    var srcH = STAGE / scale;
    ctx.clearRect(0, 0, 256, 256);
    ctx.drawImage(imgEl, srcX, srcY, srcW, srcH, 0, 0, 256, 256);
    var ext = selectedMime === 'image/jpeg' ? 'jpg' : 'png';
    canvas.toBlob(function (blob) {
      var fd = new FormData();
      fd.append('csrf',   csrf);
      fd.append('azione', 'foto');
      fd.append('foto',   blob, 'profilo.' + ext);
      btnOk.disabled = true;
      btnOk.textContent = 'Salvataggio…';
      fetch('profilo.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.ok) {
            var av = document.querySelector('.profilo-avatar-img');
            if (av) { av.src = d.src + '?t=' + Date.now(); closeModal(); }
            else { location.reload(); }
          } else {
            alert(d.err || 'Errore nel salvataggio.');
            btnOk.disabled = false;
            btnOk.textContent = 'Usa questa foto';
          }
        })
        .catch(function () {
          alert('Errore di rete.');
          btnOk.disabled = false;
          btnOk.textContent = 'Usa questa foto';
        });
    }, selectedMime, selectedMime === 'image/jpeg' ? 0.88 : undefined);
  });
})();
