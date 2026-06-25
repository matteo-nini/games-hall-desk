(function () {
  'use strict';

  var DONE_KEY = 'gp_wizard_done';

  function init(steps) {
    if (!steps || !steps.length) return;
    if (localStorage.getItem(DONE_KEY)) return;

    var idx = 0;
    var hl  = document.createElement('div');
    var tip = document.createElement('div');
    hl.className  = 'gp-tour-hl';
    tip.className = 'gp-tour-tip';
    document.body.appendChild(hl);
    document.body.appendChild(tip);

    function hide() {
      hl.classList.remove('gp-tour-visible');
      tip.classList.remove('gp-tour-visible');
    }

    function show() {
      hl.classList.add('gp-tour-visible');
      tip.classList.add('gp-tour-visible');
    }

    function done() {
      hide();
      setTimeout(function () {
        hl.remove();
        tip.remove();
      }, 200);
      localStorage.setItem(DONE_KEY, '1');
    }

    function render(i) {
      var step = steps[i];
      var el   = step.selector ? document.querySelector(step.selector) : null;

      hide();

      setTimeout(function () {
        var rect  = el ? el.getBoundingClientRect() : null;
        var GAP   = 8;

        if (rect) {
          hl.style.top    = (rect.top    - GAP) + 'px';
          hl.style.left   = (rect.left   - GAP) + 'px';
          hl.style.width  = (rect.width  + GAP * 2) + 'px';
          hl.style.height = (rect.height + GAP * 2) + 'px';
        } else {
          hl.style.top    = '50%';
          hl.style.left   = '50%';
          hl.style.width  = '0';
          hl.style.height = '0';
        }

        var dotsHtml = steps.map(function (_, di) {
          return '<span class="gp-tour-dot' + (di === i ? ' active' : '') + '"></span>';
        }).join('');

        var isLast = i === steps.length - 1;
        tip.innerHTML =
          '<div class="gp-tour-badge">Guida ' + (i + 1) + ' / ' + steps.length + '</div>' +
          '<h2 class="gp-tour-title">' + esc(step.title) + '</h2>' +
          '<p class="gp-tour-body">'  + step.body + '</p>' +
          '<div class="gp-tour-footer">' +
            '<div class="gp-tour-dots">' + dotsHtml + '</div>' +
            (i > 0 ? '<button class="gp-tour-btn gp-tour-prev" type="button">← Indietro</button>' : '') +
            '<button class="gp-tour-btn gp-tour-next" type="button">' + (isLast ? 'Fine ✓' : 'Avanti →') + '</button>' +
            '<button class="gp-tour-skip" type="button">Salta</button>' +
          '</div>';

        // posiziona tooltip
        var arrow = 'none';
        if (rect) {
          var tipH = 180;
          var tipW = 300;
          var spaceBelow = window.innerHeight - rect.bottom;
          var tipLeft = Math.min(Math.max(rect.left, 12), window.innerWidth - tipW - 12);
          if (spaceBelow >= tipH + 20) {
            tip.style.top  = (rect.bottom + 12) + 'px';
            tip.style.left = tipLeft + 'px';
            arrow = 'top';
          } else {
            tip.style.top  = Math.max(rect.top - tipH - 20, 8) + 'px';
            tip.style.left = tipLeft + 'px';
            arrow = 'bottom';
          }
        } else {
          tip.style.top  = '50%';
          tip.style.left = '50%';
          tip.style.transform = 'translate(-50%, -50%)';
        }
        tip.setAttribute('data-arrow', arrow);

        tip.querySelector('.gp-tour-next').onclick = function () {
          if (isLast) { done(); } else { idx++; render(idx); }
        };
        var prevBtn = tip.querySelector('.gp-tour-prev');
        if (prevBtn) prevBtn.onclick = function () { idx--; render(idx); };
        tip.querySelector('.gp-tour-skip').onclick = done;

        show();
      }, 120);
    }

    function esc(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    render(idx);
  }

  window.GP_Tour = { init: init };
})();
