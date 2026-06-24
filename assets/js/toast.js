(function () {
  'use strict';

  var SOUNDS = {
    ok:    [[587, 0, 0.07], [740, 0.07, 0.07], [880, 0.14, 0.13]],
    warn:  [[659, 0, 0.1],  [600, 0.13, 0.1]],
    error: [[440, 0, 0.09], [370, 0.1, 0.09], [330, 0.2, 0.13]]
  };

  function playSound(type) {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      (SOUNDS[type] || SOUNDS.ok).forEach(function (note) {
        var osc  = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type            = 'sine';
        osc.frequency.value = note[0];
        var t0 = ctx.currentTime + note[1];
        gain.gain.setValueAtTime(0.10, t0);
        gain.gain.exponentialRampToValueAtTime(0.001, t0 + note[2]);
        osc.start(t0);
        osc.stop(t0 + note[2] + 0.01);
      });
    } catch (e) {}
  }

  var container = null;
  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.id = 'gp-toast-wrap';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'false');
      document.body.appendChild(container);
    }
    return container;
  }

  var ICONS = {
    ok:    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>',
    warn:  '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    error: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
  };

  function dismiss(el) {
    el.classList.remove('gp-toast-in');
    el.classList.add('gp-toast-out');
    el.addEventListener('transitionend', function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, { once: true });
  }

  function showToast(message, type, withSound) {
    type = type || 'ok';
    var c = getContainer();
    var t = document.createElement('div');
    t.className   = 'gp-toast gp-toast-' + type;
    t.role        = 'status';
    t.innerHTML   = '<span class="gp-toast-ico">' + (ICONS[type] || ICONS.ok) + '</span>'
                  + '<span class="gp-toast-msg">' + message + '</span>';
    c.appendChild(t);

    requestAnimationFrame(function () {
      requestAnimationFrame(function () { t.classList.add('gp-toast-in'); });
    });

    if (withSound !== false) playSound(type);

    var timer = setTimeout(function () { dismiss(t); }, 4200);
    t.addEventListener('click', function () { clearTimeout(timer); dismiss(t); });
  }

  window.GP       = window.GP || {};
  window.GP.toast = showToast;

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('div.ok, div.warn').forEach(function (el) {
      if (el.children.length > 0) return;
      var msg  = (el.textContent || el.innerText || '').trim();
      if (!msg) return;
      var type = el.classList.contains('warn') ? 'warn' : 'ok';
      el.style.display = 'none';
      showToast(msg, type, true);
    });
  });
}());
