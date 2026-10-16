/* vector7 — plain JavaScript, no framework. Loaded with `defer`; no inline scripts (strict CSP). */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Money (Indian grouping) ---------- */
  function inr(n, dec) {
    n = Number(n || 0);
    dec = dec === undefined ? (n % 1 === 0 ? 0 : 2) : dec;
    var neg = n < 0; n = Math.abs(n);
    var parts = n.toFixed(dec).split('.');
    var i = parts[0], last3 = i.slice(-3), rest = i.slice(0, -3);
    if (rest) { last3 = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3; }
    return (neg ? '-' : '') + '₹' + last3 + (parts[1] ? '.' + parts[1] : '');
  }
  window.v7inr = inr;

  /* ---------- Translations for marketplace pages (English / Tamil), from <script id="v7-i18n"> ---------- */
  var I18N = null;
  function T(key, params) {
    if (I18N === null) { try { I18N = JSON.parse(document.getElementById('v7-i18n').textContent) || {}; } catch (e) { I18N = {}; } }
    var out = I18N[key] || key;
    Object.keys(params || {}).forEach(function (p) { out = out.split(':' + p).join(params[p]); });
    return out;
  }

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Sidebar (mobile) ---------- */
  function initSidebar() {
    var sb = $('#sidebar'), bd = $('#sidebar-backdrop');
    if (!sb) return;
    var open = function () { sb.classList.remove('-translate-x-full'); bd && bd.classList.remove('hidden'); $$('[data-sidebar-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); }); };
    var close = function () { sb.classList.add('-translate-x-full'); bd && bd.classList.add('hidden'); $$('[data-sidebar-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); }); };
    $$('[data-sidebar-open]').forEach(function (b) { b.addEventListener('click', open); });
    $$('[data-sidebar-close]').forEach(function (b) { b.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  }

  /* ---------- Mobile public nav ---------- */
  function initPublicNav() {
    var btn = $('[data-nav-toggle]'), menu = $('#public-nav');
    if (!btn || !menu) return;
    btn.addEventListener('click', function () {
      var hidden = menu.classList.toggle('hidden');
      btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    });
  }

  /* ---------- Dropdowns (<details>) close on outside click ---------- */
  function initDropdowns() {
    $$('[data-copy-link]').forEach(function (b) {
      b.addEventListener('click', function () {
        var url = b.getAttribute('data-copy-link'), done = function () { var t = b.innerHTML; b.textContent = T('Copied'); setTimeout(function () { b.innerHTML = t; }, 1500); };
        if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done, function () { alertBox(url); }); } else { alertBox(url); }
      });
    });
    document.addEventListener('click', function (e) {
      $$('details[data-dropdown][open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
    });
  }

  /* ---------- Password show/hide + 8–32 rule (browser side) ---------- */
  function initPasswords() {
    $$('[data-toggle-password]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        $('[data-eye]', btn).classList.toggle('hidden', show);
        $('[data-eye-off]', btn).classList.toggle('hidden', !show);
      });
    });
    var check = function (input) {
      var v = input.value, msg = '';
      if (v.length && v.length < 8) msg = 'Use at least 8 characters.';
      else if (v.length > 32) msg = 'Use at most 32 characters.';
      else if (v.length && /^\s|\s$/.test(v)) msg = 'No spaces at the start or end.';
      var box = $('[data-password-error="' + input.id + '"]');
      if (box) { box.textContent = msg; box.classList.toggle('hidden', !msg); }
      input.setCustomValidity(msg);
      return !msg;
    };
    $$('input[data-password-policy]').forEach(function (input) {
      input.addEventListener('input', function () { check(input); });
      input.addEventListener('blur', function () { check(input); });
    });
    $$('form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var ok = true;
        $$('input[data-password-policy]', form).forEach(function (i) { if (!check(i)) ok = false; });
        var pw = $('input[name="password"]', form), pc = $('input[name="password_confirmation"]', form);
        if (pw && pc && pw.value !== pc.value) {
          ok = false;
          var box = $('[data-password-error="' + pc.id + '"]');
          if (box) { box.textContent = 'Passwords do not match.'; box.classList.remove('hidden'); }
        }
        if (!ok) { e.preventDefault(); e.stopImmediatePropagation(); }
      });
    });
  }

  /* ---------- Progress bars & widths from data-w (CSP: no inline styles) ---------- */
  function initWidths(root) {
    $$('[data-w]', root).forEach(function (el) {
      var w = Math.max(0, Math.min(100, parseFloat(el.getAttribute('data-w')) || 0));
      requestAnimationFrame(function () { el.style.width = w + '%'; });
    });
    $$('[data-left]', root).forEach(function (el) { el.style.left = el.getAttribute('data-left') + '%'; el.style.top = el.getAttribute('data-top') + '%'; });
  }

  /* ---------- Confirm dialog (no window.confirm) ---------- */
  function initConfirm() {
    var modal = $('#v7-confirm');
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.hasAttribute || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') return;
      e.preventDefault();
      if (!modal) { form.dataset.confirmed = '1'; form.submit(); return; }
      $('#v7-confirm-text').textContent = form.getAttribute('data-confirm');
      var needPw = form.hasAttribute('data-confirm-password');
      var pwBox = $('[data-confirm-password-box]', modal), pwInput = $('#v7-confirm-password');
      pwBox.classList.toggle('hidden', !needPw);
      pwInput.value = '';
      modal.classList.remove('hidden'); modal.classList.add('flex');
      (needPw ? pwInput : $('[data-confirm-ok]', modal)).focus();
      var done = function (ok) {
        modal.classList.add('hidden'); modal.classList.remove('flex');
        $('[data-confirm-ok]', modal).onclick = null; $('[data-confirm-cancel]', modal).onclick = null;
        if (ok) {
          if (needPw) { var f = $('input[name="current_password"]', form); if (f) f.value = pwInput.value; }
          form.dataset.confirmed = '1';
          var sub = e.submitter;
          if (sub && sub.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = sub.name; h.value = sub.value; form.appendChild(h); }
          form.submit();
        }
      };
      $('[data-confirm-ok]', modal).onclick = function () { done(true); };
      $('[data-confirm-cancel]', modal).onclick = function () { done(false); };
      pwInput.onkeydown = function (k) { if (k.key === 'Enter') { k.preventDefault(); done(true); } };
    }, true);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) { $('[data-confirm-cancel]', modal).click(); }
    });
  }

  /* ---------- Copy to clipboard ---------- */
  function initCopy() {
    $$('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var el = document.getElementById(btn.getAttribute('data-copy'));
        var text = el ? (el.value || el.textContent) : '';
        var ok = function () { var t = btn.textContent; btn.textContent = T('Copied'); setTimeout(function () { btn.textContent = t; }, 1500); };
        if (navigator.clipboard) { navigator.clipboard.writeText(text.trim()).then(ok); }
        else { var r = document.createRange(); r.selectNodeContents(el); var s = getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy'); ok(); }
      });
    });
  }

  /* ---------- Select all / bulk ---------- */
  function initBulk() {
    $$('[data-check-all]').forEach(function (all) {
      var name = all.getAttribute('data-check-all');
      all.addEventListener('change', function () { $$('input[type=checkbox][data-bulk="' + name + '"]').forEach(function (c) { c.checked = all.checked; }); });
    });
    $$('[data-bulk-submit]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var form = document.getElementById(btn.getAttribute('data-bulk-submit'));
        var name = btn.getAttribute('data-bulk-name');
        var picked = $$('input[type=checkbox][data-bulk="' + name + '"]:checked');
        if (!picked.length) { e.preventDefault(); alertBox('Select at least one row first.'); return; }
        $$('input[data-bulk-hidden]', form).forEach(function (x) { x.remove(); });
        picked.forEach(function (c) { var h = document.createElement('input'); h.type = 'hidden'; h.name = 'ids[]'; h.value = c.value; h.setAttribute('data-bulk-hidden', '1'); form.appendChild(h); });
      });
    });
  }
  function alertBox(msg) {
    var m = $('#v7-confirm');
    if (!m) return;
    $('#v7-confirm-text').textContent = msg;
    $('[data-confirm-password-box]', m).classList.add('hidden');
    $('[data-confirm-cancel]', m).classList.add('hidden');
    m.classList.remove('hidden'); m.classList.add('flex');
    $('[data-confirm-ok]', m).onclick = function () { m.classList.add('hidden'); m.classList.remove('flex'); $('[data-confirm-cancel]', m).classList.remove('hidden'); };
  }

  /* ---------- Dynamic rows (instalments, refund rules, estimate lines, witnesses) ---------- */
  function initRepeaters() {
    $$('[data-repeater]').forEach(function (wrap) {
      var tpl = $('template', wrap), body = $('[data-repeater-body]', wrap);
      var idx = $$('[data-repeater-row]', body).length + 1000;
      var add = $('[data-repeater-add]', wrap);
      if (add) add.addEventListener('click', function () {
        var html = tpl.innerHTML.replace(/__i__/g, String(idx++));
        var tmp = document.createElement('tbody'); tmp.innerHTML = html.trim();
        var row = tmp.firstElementChild || tmp.firstChild;
        body.appendChild(row);
        wire(row);
        wrap.dispatchEvent(new Event('repeater:change'));
      });
      var wire = function (row) {
        var rm = $('[data-repeater-remove]', row);
        if (rm) rm.addEventListener('click', function () { row.remove(); wrap.dispatchEvent(new Event('repeater:change')); });
      };
      $$('[data-repeater-row]', body).forEach(wire);
    });
  }

  /* ---------- Live estimate totals ---------- */
  function initEstimate() {
    var form = $('[data-estimate]');
    if (!form) return;
    var calc = function () {
      var totals = { facility: 0, stage: 0, other: 0 };
      $$('[data-line]', form).forEach(function (row) {
        var inc = $('[data-inc]', row);
        var q = parseFloat(($('[data-qty]', row) || {}).value) || 0;
        var c = parseFloat(($('[data-cost]', row) || {}).value) || 0;
        var amt = (inc && !inc.checked) ? 0 : q * c;
        var out = $('[data-amount]', row); if (out) out.textContent = inr(amt);
        totals[row.getAttribute('data-line')] += amt;
      });
      var sqft = parseFloat(form.getAttribute('data-sqft')) || 0;
      var pct = parseFloat(($('[name=sellable_pct]', form) || {}).value) || 0;
      var mult = parseFloat(($('[name=mrp_multiplier]', form) || {}).value) || 0;
      var actualSellable = parseFloat(form.getAttribute('data-actual-sellable')) || 0;
      var total = totals.facility + totals.stage + totals.other;
      var sellable = actualSellable > 0 ? actualSellable : sqft * pct / 100;
      var perSqft = sellable > 0 ? total / sellable : 0;
      var set = function (k, v) { var el = $('[data-total="' + k + '"]', form); if (el) el.textContent = v; };
      set('facility', inr(totals.facility, 0)); set('stage', inr(totals.stage, 0)); set('other', inr(totals.other, 0));
      set('total', inr(total, 0)); set('sellable', Math.round(sellable).toLocaleString('en-IN') + ' sq ft');
      set('per_sqft', inr(perSqft, 2)); set('mrp', inr(perSqft * mult, 2));
    };
    form.addEventListener('input', calc);
    form.addEventListener('change', calc);
    form.addEventListener('repeater:change', calc, true);
    calc();
  }

  /* ---------- Simple live calculators: [data-calc="a*b"] → target ---------- */
  function initCalc() {
    $$('[data-calc]').forEach(function (out) {
      var parts = out.getAttribute('data-calc').split('*');
      var a = document.getElementById(parts[0]), b = document.getElementById(parts[1]);
      var mult = parseFloat(out.getAttribute('data-calc-factor') || '1');
      var run = function () {
        var v = (parseFloat(a && a.value) || 0) * (parseFloat(b && b.value) || 0) * mult;
        out.textContent = out.hasAttribute('data-calc-number') ? Math.round(v).toLocaleString('en-IN') : inr(v, 0);
      };
      [a, b].forEach(function (x) { x && x.addEventListener('input', run); });
      run();
    });
  }

  /* ---------- Plot tooltips (hover / focus / tap) ---------- */
  var tip = null, tipOwner = null;
  function tooltipHtml(d) {
    var price = d.offer
      ? '<p class="mt-2"><span class="text-navy-100 line-through mr-2">' + inr(d.actual) + '</span><span class="text-lg font-extrabold text-teal-200">' + inr(d.offer) + '</span></p>' +
        '<p class="text-xs text-gold font-semibold">' + esc(d.offerText || 'Offer') + ' · Offer valid till ' + esc(d.offerTill) + '</p>'
      : '<p class="mt-2 text-lg font-extrabold">' + inr(d.actual) + '</p>';
    var row = function (k, v) { return v === null || v === undefined || v === '' ? '' : '<div><dt>' + k + '</dt><dd>' + esc(v) + '</dd></div>'; };
    return '<div class="flex items-start justify-between gap-2"><p class="text-base font-extrabold">Plot ' + esc(d.no) + '</p><span class="badge st-' + esc(d.statusKey) + '">' + esc(d.status) + '</span></div>' +
      '<dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1.5">' +
      row('Patta no.', d.patta) + row('Size', Number(d.sqft).toLocaleString('en-IN') + ' sq ft · ' + d.cents + ' cents') +
      row('Dimensions', d.dim) + row('Facing', d.facing) + row('Road width', d.road ? d.road + ' ft' : null) + row('Corner plot', d.corner ? 'Yes' : 'No') +
      row('East', d.e) + row('West', d.w) + row('North', d.n) + row('South', d.s) +
      '</dl>' + price + '<p class="text-xs text-navy-100">' + inr(d.rate, 0) + ' / sq ft</p>' +
      (d.statusKey === 'available' && d.url ? '<p class="mt-3"><a href="' + esc(d.url) + '">Book now →</a></p>' : '');
  }
  function showTip(el) {
    var data;
    try { data = JSON.parse(el.getAttribute('data-plot')); } catch (e) { return; }
    if (!tip) { tip = document.createElement('div'); tip.className = 'v7-tip'; tip.setAttribute('role', 'tooltip'); tip.id = 'v7-tip'; document.body.appendChild(tip); }
    tip.innerHTML = tooltipHtml(data);
    tip.style.display = 'block';
    tipOwner = el;
    el.setAttribute('aria-describedby', 'v7-tip');
    var r = el.getBoundingClientRect(), tw = tip.offsetWidth, th = tip.offsetHeight, m = 12;
    var left = r.left + r.width / 2 - tw / 2;
    left = Math.max(m, Math.min(left, window.innerWidth - tw - m));
    var top = r.top - th - 10;
    if (top < m) top = r.bottom + 10;
    if (top + th > window.innerHeight - m) top = Math.max(m, window.innerHeight - th - m);
    tip.style.left = left + 'px'; tip.style.top = top + 'px';
  }
  function hideTip() { if (tip) tip.style.display = 'none'; if (tipOwner) tipOwner.removeAttribute('aria-describedby'); tipOwner = null; }
  function initTooltips() {
    var touch = window.matchMedia('(hover: none)').matches;
    $$('[data-plot]').forEach(function (el) {
      if (!el.hasAttribute('tabindex') && el.tagName !== 'A' && el.tagName !== 'BUTTON') el.setAttribute('tabindex', '0');
      if (!touch) {
        el.addEventListener('mouseenter', function () { showTip(el); });
        el.addEventListener('mouseleave', function () { setTimeout(function () { if (tip && !tip.matches(':hover')) hideTip(); }, 120); });
      }
      el.addEventListener('focus', function () { showTip(el); });
      el.addEventListener('blur', function () { setTimeout(function () { if (tip && !tip.contains(document.activeElement)) hideTip(); }, 150); });
      el.addEventListener('click', function (e) {
        if (touch || el.tagName === 'g' || el.tagName === 'DIV' || el.classList.contains('plot-shape')) {
          if (tipOwner === el) { hideTip(); } else { e.preventDefault(); showTip(el); }
        }
      });
      el.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideTip(); });
    });
    document.addEventListener('click', function (e) { if (tip && tipOwner && !tipOwner.contains(e.target) && !tip.contains(e.target)) hideTip(); });
    window.addEventListener('scroll', function () { if (tipOwner && !tip.matches(':hover')) hideTip(); }, { passive: true });
    if (!touch) document.addEventListener('mouseover', function (e) { if (tip && tip.contains(e.target)) return; });
  }

  /* ---------- Plot grid filters & sort (client side) ---------- */
  function initPlotFilters() {
    var form = $('[data-plot-filters]'), grid = $('[data-plot-grid]');
    if (!form || !grid) return;
    var cards = $$('[data-card]', grid);
    var count = $('[data-plot-count]');
    var apply = function () {
      var f = {};
      $$('input,select', form).forEach(function (i) { f[i.name] = i.type === 'checkbox' ? i.checked : i.value; });
      var shown = 0;
      cards.forEach(function (c) {
        var d = c.dataset, ok = true;
        var size = +d.size, price = +d.price;
        if (f.min_size && size < +f.min_size) ok = false;
        if (f.max_size && size > +f.max_size) ok = false;
        if (f.facing && d.facing !== f.facing) ok = false;
        if (f.min_price && price < +f.min_price) ok = false;
        if (f.max_price && price > +f.max_price) ok = false;
        if (f.corner && d.corner !== '1') ok = false;
        if (f.offer && d.offer !== '1') ok = false;
        if (f.status && d.status !== f.status) ok = false;
        c.classList.toggle('hidden', !ok);
        if (ok) shown++;
      });
      var sort = f.sort || 'plot';
      var sorted = cards.slice().sort(function (a, b) {
        if (sort === 'price_asc') return a.dataset.price - b.dataset.price;
        if (sort === 'price_desc') return b.dataset.price - a.dataset.price;
        if (sort === 'size_asc') return a.dataset.size - b.dataset.size;
        if (sort === 'size_desc') return b.dataset.size - a.dataset.size;
        return a.dataset.no.localeCompare(b.dataset.no, undefined, { numeric: true });
      });
      sorted.forEach(function (c) { grid.appendChild(c); });
      if (count) count.textContent = shown + ' plot' + (shown === 1 ? '' : 's');
    };
    form.addEventListener('input', apply);
    form.addEventListener('change', apply);
    form.addEventListener('submit', function (e) { e.preventDefault(); apply(); });
    var reset = $('[data-plot-reset]', form);
    if (reset) reset.addEventListener('click', function () { setTimeout(apply, 0); });
    apply();
  }

  /* ---------- Carousel ---------- */
  function initCarousels() {
    $$('[data-carousel]').forEach(function (c) {
      var track = $('.carousel-track', c), slides = $$('.carousel-slide', c), dots = $$('[data-dot]', c);
      if (!track || slides.length < 2) return;
      var i = 0, timer = null;
      var go = function (n) {
        i = (n + slides.length) % slides.length;
        track.style.transform = 'translateX(-' + (i * 100) + '%)';
        slides.forEach(function (s, k) { s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); $$('a,button', s).forEach(function (a) { a.tabIndex = k === i ? 0 : -1; }); });
        dots.forEach(function (d, k) { d.setAttribute('aria-current', k === i ? 'true' : 'false'); d.classList.toggle('bg-teal', k === i); d.classList.toggle('bg-navy-100', k !== i); });
      };
      var start = function () { if (!reduceMotion) { stop(); timer = setInterval(function () { go(i + 1); }, 5500); } };
      var stop = function () { if (timer) clearInterval(timer); timer = null; };
      var prev = $('[data-prev]', c), next = $('[data-next]', c);
      prev && prev.addEventListener('click', function () { go(i - 1); start(); });
      next && next.addEventListener('click', function () { go(i + 1); start(); });
      dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); start(); }); });
      c.addEventListener('mouseenter', stop); c.addEventListener('mouseleave', start);
      c.addEventListener('focusin', stop); c.addEventListener('focusout', start);
      var sx = null;
      c.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
      c.addEventListener('touchend', function (e) { if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1)); sx = null; start(); });
      go(0); start();
    });
  }

  /* ---------- Reveal on scroll ---------- */
  function initReveal() {
    var els = $$('.reveal');
    if (!els.length) return;
    if (reduceMotion || !('IntersectionObserver' in window)) { els.forEach(function (e) { e.classList.add('in'); }); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (e) { io.observe(e); });
  }

  /* ---------- Charts (Chart.js, data from JSON blocks) ---------- */
  function initCharts() {
    var nodes = $$('canvas[data-chart]');
    if (!nodes.length) return;
    var load = function (cb) {
      if (window.Chart) return cb();
      var s = document.createElement('script');
      s.src = document.querySelector('meta[name=chart-src]') ? document.querySelector('meta[name=chart-src]').content : '/js/vendor/chart.umd.min.js';
      s.onload = cb; document.head.appendChild(s);
    };
    load(function () {
      Chart.defaults.font.family = '"Plus Jakarta Sans", system-ui, sans-serif';
      Chart.defaults.color = '#5B6677';
      if (reduceMotion) Chart.defaults.animation = false;
      nodes.forEach(function (cv) {
        var cfg;
        try { cfg = JSON.parse(document.getElementById(cv.getAttribute('data-chart')).textContent); } catch (e) { return; }
        var money = cfg.money;
        var opts = {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { display: cfg.type === 'doughnut' || (cfg.datasets || []).length > 1, position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
            tooltip: { callbacks: { label: function (ctx) { var v = ctx.parsed.y !== undefined && cfg.type !== 'doughnut' ? (cfg.horizontal ? ctx.parsed.x : ctx.parsed.y) : ctx.parsed; return (ctx.dataset.label ? ctx.dataset.label + ': ' : (ctx.label ? ctx.label + ': ' : '')) + (money ? inr(v, 0) : Number(v).toLocaleString('en-IN')); } } }
          }
        };
        if (cfg.type !== 'doughnut') {
          opts.scales = {
            x: { grid: { display: false }, stacked: !!cfg.stacked, ticks: cfg.horizontal && money ? { callback: function (v) { return inr(v, 0); } } : {} },
            y: { beginAtZero: true, grid: { color: '#E8EBF0' }, stacked: !!cfg.stacked, ticks: !cfg.horizontal && money ? { callback: function (v) { return inr(v, 0); } } : {} }
          };
          if (cfg.horizontal) opts.indexAxis = 'y';
        } else { opts.cutout = '62%'; }
        new Chart(cv, { type: cfg.type || 'bar', data: { labels: cfg.labels, datasets: cfg.datasets.map(function (d) {
          return Object.assign({ borderRadius: cfg.type === 'bar' ? 6 : 0, borderWidth: cfg.type === 'line' ? 2 : 0, tension: 0.3, pointRadius: cfg.type === 'line' ? 3 : 0, maxBarThickness: 42 }, d);
        }) }, options: opts });
      });
    });
  }

  /* ---------- Plot map pin editor (Launch) ---------- */
  function initPinEditor() {
    var ed = $('[data-pin-editor]');
    if (!ed) return;
    var img = $('img', ed), select = $('[data-pin-plot]'), token = $('meta[name=csrf-token]').content;
    var layer = $('[data-pin-layer]', ed);
    ed.addEventListener('click', function (e) {
      if (!select.value) { alertBox('Choose a plot number first, then click its position on the layout.'); return; }
      var r = img.getBoundingClientRect();
      var x = ((e.clientX - r.left) / r.width * 100).toFixed(3), y = ((e.clientY - r.top) / r.height * 100).toFixed(3);
      var opt = select.options[select.selectedIndex];
      fetch(opt.getAttribute('data-url'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ x: x, y: y }) })
        .then(function (res) { return res.json(); })
        .then(function () {
          var pin = $('[data-pin="' + opt.value + '"]', layer);
          if (!pin) { pin = document.createElement('span'); pin.className = 'absolute -translate-x-1/2 -translate-y-1/2 rounded-full bg-navy px-1.5 py-0.5 text-[10px] font-bold text-white'; pin.setAttribute('data-pin', opt.value); pin.textContent = opt.textContent.split(' ')[0]; layer.appendChild(pin); }
          pin.style.left = x + '%'; pin.style.top = y + '%';
          if (select.selectedIndex < select.options.length - 1) select.selectedIndex++;
        });
    });
  }

  /* ---------- Show/hide by select value: data-show-when="field=value" ---------- */
  function initConditional() {
    $$('[data-show-when]').forEach(function (el) {
      var parts = el.getAttribute('data-show-when').split('=');
      var field = document.querySelector('[name="' + parts[0] + '"]');
      var radios = $$('[name="' + parts[0] + '"]');
      var values = parts[1].split('|');
      var run = function () {
        var v = field && field.type === 'checkbox' ? (field.checked ? '1' : '0') : (radios.length > 1 ? (radios.filter(function (r) { return r.checked; })[0] || {}).value : field && field.value);
        el.classList.toggle('hidden', values.indexOf(v) === -1);
      };
      radios.forEach(function (r) { r.addEventListener('change', run); });
      run();
    });
  }

  /* ---------- Customer typeahead (booking form) ---------- */
  function initLookups() {
    $$('[data-lookup]').forEach(function (input) {
      var list = document.getElementById(input.getAttribute('data-lookup-list'));
      var hidden = document.getElementById(input.getAttribute('data-lookup-target'));
      var url = input.getAttribute('data-lookup'), t = null;
      input.addEventListener('input', function () {
        clearTimeout(t);
        hidden.value = '';
        t = setTimeout(function () {
          if (input.value.length < 2) { list.innerHTML = ''; list.classList.add('hidden'); return; }
          fetch(url + '?q=' + encodeURIComponent(input.value), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (rows) {
              list.innerHTML = rows.map(function (r) { return '<li><button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-page" data-id="' + r.id + '" data-label="' + esc(r.label) + '">' + esc(r.label) + '</button></li>'; }).join('') || '<li class="px-3 py-2 text-sm text-muted">No match</li>';
              list.classList.remove('hidden');
              $$('button', list).forEach(function (b) { b.addEventListener('click', function () { hidden.value = b.getAttribute('data-id'); input.value = b.getAttribute('data-label'); list.classList.add('hidden'); hidden.dispatchEvent(new Event('change', { bubbles: true })); }); });
            });
        }, 250);
      });
    });
  }

  /* ---------- Dependent selects: project → plots ---------- */
  function initPlotPicker() {
    $$('[data-plot-picker]').forEach(function (sel) {
      var target = document.getElementById(sel.getAttribute('data-plot-picker'));
      var url = sel.getAttribute('data-url'), statusFilter = sel.getAttribute('data-statuses') || 'available';
      var info = document.getElementById(sel.getAttribute('data-info'));
      var load = function () {
        if (!sel.value) { target.innerHTML = '<option value="">Choose a project first</option>'; return; }
        fetch(url + '?project=' + sel.value + '&statuses=' + statusFilter, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (rows) {
            var cur = target.getAttribute('data-current');
            target.innerHTML = '<option value="">Choose a plot</option>' + rows.map(function (p) {
              return '<option value="' + p.id + '" data-price="' + p.price + '" data-actual="' + p.actual + '" data-offer="' + (p.offer || '') + '"' + (String(p.id) === cur ? ' selected' : '') + '>Plot ' + esc(p.no) + ' · ' + p.sqft + ' sq ft · ' + inr(p.price, 0) + (p.offer ? ' (offer)' : '') + '</option>';
            }).join('');
            target.dispatchEvent(new Event('change'));
          });
      };
      sel.addEventListener('change', load);
      if (info) target.addEventListener('change', function () {
        var o = target.options[target.selectedIndex];
        info.textContent = o && o.value ? 'Price: ' + inr(+o.getAttribute('data-price'), 0) + (o.getAttribute('data-offer') ? ' (offer price; actual ' + inr(+o.getAttribute('data-actual'), 0) + ')' : '') : '';
      });
      if (sel.value) load();
    });
  }

  /* ---------- Option groups filtered by another select: <select data-depends="project_id"> + <option data-group="12"> ---------- */
  function initDepends() {
    $$('select[data-depends]').forEach(function (sel) {
      var form = sel.form || document;
      var parent = form.querySelector('[name="' + sel.getAttribute('data-depends') + '"]');
      if (!parent) return;
      var run = function () {
        $$('option[data-group]', sel).forEach(function (o) {
          var show = !parent.value || o.getAttribute('data-group') === parent.value;
          o.hidden = !show; o.disabled = !show;
          if (!show && o.selected) sel.value = '';
        });
      };
      parent.addEventListener('change', run); run();
    });
  }

  /* ---------- Auto-submit selects ---------- */
  function initAutoSubmit() {
    $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form && el.form.submit(); }); });
  }

  /* ---------- Editable grid → JSON (plot import preview; avoids max_input_vars limits) ---------- */
  function initGrid() {
    $$('form[data-grid-form]').forEach(function (form) {
      $$('[data-grid-remove]', form).forEach(function (b) { b.addEventListener('click', function () { b.closest('tr').remove(); }); });
      form.addEventListener('submit', function () {
        var rows = $$('tr[data-grid-row]', form).map(function (tr) {
          var o = {};
          $$('[data-col]', tr).forEach(function (el) { o[el.getAttribute('data-col')] = el.value; });
          return o;
        });
        $('input[name=rows_json]', form).value = JSON.stringify(rows);
      });
    });
  }

  /* ---------- Leads kanban: drag cards between stages (keyboard users use the "Move to" form on each card) ---------- */
  function initKanban() {
    var board = $('[data-kanban]');
    if (!board || board.getAttribute('data-can-move') !== '1') return;
    var token = ($('meta[name=csrf-token]') || {}).content;
    var dragged = null;
    $$('.kanban-card', board).forEach(function (card) {
      card.addEventListener('dragstart', function (e) { dragged = card; card.classList.add('opacity-50'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', card.getAttribute('data-lead')); });
      card.addEventListener('dragend', function () { card.classList.remove('opacity-50'); dragged = null; $$('.kanban-col', board).forEach(function (c) { c.classList.remove('ring-2'); }); });
    });
    var recount = function () { $$('.kanban-col', board).forEach(function (c) { var n = $('[data-count]', c); if (n) n.textContent = $$('.kanban-card', c).length; }); };
    $$('.kanban-col', board).forEach(function (col) {
      col.addEventListener('dragover', function (e) { if (dragged) { e.preventDefault(); col.classList.add('ring-2'); } });
      col.addEventListener('dragleave', function () { col.classList.remove('ring-2'); });
      col.addEventListener('drop', function (e) {
        e.preventDefault();
        col.classList.remove('ring-2');
        if (!dragged) return;
        var card = dragged, from = card.parentNode, stage = col.getAttribute('data-stage');
        if (col.getAttribute('data-lost') === '1') { window.location = card.querySelector('a').getAttribute('href') + '?lost=1'; return; }
        $('[data-drop]', col).prepend(card);
        recount();
        var sel = $('select[name=stage_id]', card); if (sel) sel.value = stage;
        fetch(card.getAttribute('data-url'), {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
          body: JSON.stringify({ stage_id: stage })
        }).then(function (r) {
          if (!r.ok) { from.prepend(card); recount(); return r.json().then(function (j) { if (j && j.url) window.location = j.url; }); }
        }).catch(function () { from.prepend(card); recount(); });
      });
    });
    $$('.kanban-move', board).forEach(function (f) { f.classList.add('sr-only-focusable'); });
  }

  /* ---------- Maps (Leaflet 1.9.4 + OpenStreetMap) ---------- */
  var OSM = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  var OSM_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
  function initMaps() {
    if (!window.L) return;
    // Pick a point: click the map or type latitude / longitude.
    $$('[data-map-picker]').forEach(function (el) {
      var lat = document.getElementById(el.getAttribute('data-lat')), lng = document.getElementById(el.getAttribute('data-lng'));
      var has = lat.value && lng.value;
      var map = L.map(el, { scrollWheelZoom: false }).setView(has ? [parseFloat(lat.value), parseFloat(lng.value)] : [10.787, 79.1378], has ? 15 : 9);
      L.tileLayer(OSM, { maxZoom: 19, attribution: OSM_ATTR }).addTo(map);
      var marker = has ? L.marker([parseFloat(lat.value), parseFloat(lng.value)]).addTo(map) : null;
      var place = function (ll) {
        if (marker) { marker.setLatLng(ll); } else { marker = L.marker(ll).addTo(map); }
        lat.value = ll.lat.toFixed(7); lng.value = ll.lng.toFixed(7);
      };
      map.on('click', function (e) { place(e.latlng); });
      [lat, lng].forEach(function (inp) {
        inp.addEventListener('change', function () {
          var a = parseFloat(lat.value), b = parseFloat(lng.value);
          if (!isNaN(a) && !isNaN(b)) { place(L.latLng(a, b)); map.setView([a, b], 15); }
        });
      });
    });
    // Show pins: data-map-pins="<id of JSON script>"; each pin {lat, lng, title, price, url, type}
    $$('[data-map-pins]').forEach(function (el) {
      var src = document.getElementById(el.getAttribute('data-map-pins'));
      var pins = []; try { pins = JSON.parse(src.textContent); } catch (e) { pins = []; }
      var map = L.map(el, { scrollWheelZoom: false });
      L.tileLayer(OSM, { maxZoom: 19, attribution: OSM_ATTR }).addTo(map);
      var bounds = [];
      pins.forEach(function (p) {
        if (p.lat === null || p.lng === null) return;
        var m = L.marker([p.lat, p.lng]).addTo(map);
        m.bindPopup('<strong>' + esc(p.title) + '</strong><br>' + esc(p.type || '') + (p.price ? '<br>' + esc(p.price) : '') + (p.url ? '<br><a href="' + esc(p.url) + '">View</a>' : ''));
        bounds.push([p.lat, p.lng]);
      });
      if (bounds.length > 1) { map.fitBounds(bounds, { padding: [30, 30] }); }
      else if (bounds.length === 1) { map.setView(bounds[0], 15); }
      else { map.setView([10.787, 79.1378], 8); }
    });
  }


  /* ---------- Contact block (spec 17.5): Call / WhatsApp / Show number, asked once for name + mobile + consent ---------- */
  function initContact() {
    $$('[data-contact]').forEach(function (box) {
      var form = $('[data-contact-form]', box), modeInput = $('[data-contact-mode-input]', box), err = $('[data-contact-error]', box);
      var known = box.getAttribute('data-known') === '1', pending = null;
      // With JavaScript the details form opens only when a button is tapped (it stays open after a validation error).
      if (form && !box.querySelector('.error:not(.hidden)')) { form.classList.add('hidden'); }
      var send = function (mode, withForm) {
        var fd = withForm && form ? new FormData(form) : new FormData();
        if (!withForm) { fd.append('_token', ($('meta[name=csrf-token]') || {}).content || ''); var src = form && $('input[name=src]', form); if (src) fd.append('src', src.value); }
        fd.set('mode', mode);
        // Open the WhatsApp window synchronously (popup blockers), then point it at wa.me once we have the link.
        var win = mode === 'whatsapp' ? window.open('', '_blank') : null;
        fetch(box.getAttribute('data-url'), { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': ($('meta[name=csrf-token]') || {}).content }, body: fd })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); })
          .then(function (res) {
            if (!res.ok) {
              if (win) win.close();
              if (res.status === 422 && res.j.errors && !withForm) { known = false; form.classList.remove('hidden'); pending = mode; return; }
              var msg = res.status === 429 ? T('Too many requests — please try again in a minute.') : ((res.j.errors && Object.values(res.j.errors)[0][0]) || res.j.message || 'Something went wrong.');
              if (err) { err.textContent = msg; err.classList.remove('hidden'); } else { alertBox(msg); }
              return;
            }
            known = true; if (form) form.classList.add('hidden'); if (err) err.classList.add('hidden');
            var num = $('[data-number]', box); if (num) num.textContent = res.j.display;
            if (mode === 'call') { window.location.href = res.j.tel; }
            if (mode === 'whatsapp') { if (win) { win.location.href = res.j.whatsapp; } else { window.location.href = res.j.whatsapp; } }
          })
          .catch(function () { if (win) win.close(); alertBox(T('Could not reach the server. Please try again.')); });
      };
      $$('[data-contact-mode]', box).forEach(function (btn) {
        btn.addEventListener('click', function () {
          var mode = btn.getAttribute('data-contact-mode');
          if (known) { send(mode, false); return; }
          pending = mode; if (modeInput) modeInput.value = mode;
          form.classList.remove('hidden'); var first = $('input[name=name]', form); if (first) first.focus();
        });
      });
      if (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          send(pending || (modeInput && modeInput.value) || 'show', true);
        });
      }
    });
  }

  /* ---------- Search page: refine toggle, compare bar (up to 3), dropdowns close on outside click ---------- */
  function initSearchUi() {
    var refine = $('[data-refine]');
    if (refine) {
      refine.addEventListener('change', function () { $$('[data-refine-field]').forEach(function (f) { f.disabled = !refine.checked; }); });
    }
    var bar = $('[data-compare-bar]');
    var boxes = $$('[data-compare]');
    if (boxes.length) {
      var key = 'v7compare', picked = [];
      try { picked = JSON.parse(sessionStorage.getItem(key) || '[]'); } catch (e) { picked = []; }
      var sync = function () {
        try { sessionStorage.setItem(key, JSON.stringify(picked)); } catch (e) { /* private mode */ }
        boxes.forEach(function (b) { b.checked = picked.indexOf(b.value) !== -1; b.disabled = !b.checked && picked.length >= 3; });
        if (bar) {
          bar.classList.toggle('hidden', picked.length === 0);
          $('[data-compare-count]', bar).textContent = picked.length;
          var go = $('[data-compare-go]', bar); go.href = go.href.split('?')[0] + '?keys=' + encodeURIComponent(picked.join(','));
        }
      };
      boxes.forEach(function (b) {
        b.addEventListener('change', function () {
          if (b.checked && picked.indexOf(b.value) === -1 && picked.length < 3) picked.push(b.value);
          if (!b.checked) picked = picked.filter(function (k) { return k !== b.value; });
          sync();
        });
      });
      sync();
    }
    $$('[data-copy-link]').forEach(function (b) {
      b.addEventListener('click', function () {
        var url = b.getAttribute('data-copy-link'), done = function () { var t = b.innerHTML; b.textContent = T('Copied'); setTimeout(function () { b.innerHTML = t; }, 1500); };
        if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done, function () { alertBox(url); }); } else { alertBox(url); }
      });
    });
    document.addEventListener('click', function (e) {
      $$('details[data-dropdown][open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
    });
  }


  /* ---------- Chatbots (spec 17.9 marketplace, 17.10 workspace): one panel, JSON endpoints ---------- */
  var lastField = null;
  document.addEventListener('focusin', function (e) {
    var t = e.target;
    if (t && (t.tagName === 'TEXTAREA' || (t.tagName === 'INPUT' && t.type === 'text')) && !t.closest('[data-chat]')) lastField = t;
  });
  function initChat() {
    $$('[data-chat]').forEach(function (root) {
      var panel = $('[data-chat-panel]', root), log = $('[data-chat-log]', root), form = $('[data-chat-form]', root), input = $('input', form);
      var token = ($('meta[name=csrf-token]') || {}).content, loaded = false, busy = false;
      var post = function (url, body) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(body) })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); });
      };
      var errText = function (res) {
        if (res.status === 429) return T('You are sending messages too fast — please wait a minute.');
        return (res.j && res.j.errors && Object.values(res.j.errors)[0][0]) || (res.j && res.j.message) || T('Something went wrong. Please try again.');
      };
      var scroll = function () { log.scrollTop = log.scrollHeight; };
      var bubble = function (role, text) {
        var d = document.createElement('div');
        d.className = role === 'user' ? 'ml-8 rounded-2xl rounded-br-sm bg-navy px-3 py-2 text-white' : 'mr-8 rounded-2xl rounded-bl-sm bg-white px-3 py-2 ring-1 ring-navy-50';
        d.innerHTML = esc(text).replace(/\n/g, '<br>');
        log.appendChild(d); scroll(); return d;
      };
      var cards = function (list) {
        (list || []).forEach(function (c) {
          var a = document.createElement('a');
          a.href = c.url; a.className = 'flex gap-3 rounded-xl bg-white p-2 text-navy no-underline ring-1 ring-navy-50 hover:ring-teal-600';
          a.innerHTML = (c.image ? '<img src="' + esc(c.image) + '" alt="" class="h-16 w-20 shrink-0 rounded-lg object-cover" loading="lazy">' : '') +
            '<span class="min-w-0"><span class="block truncate font-bold">' + esc(c.title) + '</span><span class="block truncate text-xs text-muted">' + esc(c.type || '') + ' · ' + esc(c.location || '') +
            '</span><span class="block text-sm font-semibold text-teal-700">' + esc(c.price || '') + '</span>' + (c.specs ? '<span class="block truncate text-xs">' + esc(c.specs) + '</span>' : '') + '</span>';
          log.appendChild(a);
        });
        scroll();
      };
      var table = function (t) {
        if (!t || !t.columns) return;
        var w = document.createElement('div'); w.className = 'overflow-x-auto rounded-xl bg-white ring-1 ring-navy-50';
        var links = t.links || [];
        w.innerHTML = (t.title ? '<p class="px-2 pt-2 text-xs font-bold">' + esc(t.title) + '</p>' : '') + '<table class="w-full text-xs"><thead><tr>' + t.columns.map(function (c) { return '<th class="px-2 py-1 text-left">' + esc(c) + '</th>'; }).join('') + (links.length ? '<th></th>' : '') + '</tr></thead><tbody>' +
          t.rows.map(function (r, i) { return '<tr class="border-t border-navy-50">' + r.map(function (v) { return '<td class="px-2 py-1">' + esc(v) + '</td>'; }).join('') + (links.length ? '<td class="px-2 py-1">' + (links[i] ? '<a href="' + esc(links[i]) + '">' + esc(T('Open')) + '</a>' : '') + '</td>' : '') + '</tr>'; }).join('') + '</tbody></table>' +
          (t.export ? '<a class="block px-2 py-1 text-xs font-semibold" href="' + esc(t.export) + '">' + esc(T('Download Excel')) + '</a>' : '');
        log.appendChild(w); scroll();
      };
      var action = function (a) {
        if (!a) return;
        var box = document.createElement('div'); box.className = 'rounded-xl bg-amber-50 p-3 ring-1 ring-amber-200';
        if (a.type === 'create_lead') {
          box.innerHTML = '<p class="font-semibold">' + esc(T('Share your details with the owner of :title?', { title: a.title })) + '</p>' +
            '<label class="mt-2 block text-xs">' + esc(T('Your name')) + '<input name="name" class="input mt-1" maxlength="100" required value="' + esc(a.name || '') + '"></label>' +
            '<label class="mt-2 block text-xs">' + esc(T('Mobile')) + '<input name="mobile" class="input mt-1" inputmode="numeric" maxlength="10" required value="' + esc(a.mobile || '') + '"></label>' +
            '<label class="mt-2 flex items-start gap-2 text-xs"><input type="checkbox" name="consent" class="checkbox mt-0.5"> ' + esc(T('I agree to share my name and mobile with the owner so they can contact me.')) + '</label>' +
            '<p class="error hidden" data-err></p><button type="button" class="btn-primary btn-sm mt-2">' + esc(T('Share and ask to call me')) + '</button>';
          $('button', box).addEventListener('click', function () {
            var body = { key: a.key, note: a.note || '', name: $('[name=name]', box).value, mobile: $('[name=mobile]', box).value, consent: $('[name=consent]', box).checked ? 1 : 0 };
            post(root.getAttribute('data-consent'), body).then(function (res) {
              if (!res.ok) { var e = $('[data-err]', box); e.textContent = errText(res); e.classList.remove('hidden'); return; }
              box.remove(); bubble('assistant', res.j.text);
            });
          });
        } else if (a.type === 'draft') {
          box.innerHTML = '<p class="text-xs font-semibold">' + esc(a.label || 'Draft') + '</p><textarea class="input mt-1 h-32 text-xs">' + esc(a.text) + '</textarea>' +
            '<div class="mt-2 flex gap-2"><button type="button" class="btn-light btn-sm" data-copy>' + esc(T('Copy')) + '</button>' + (a.apply ? '<button type="button" class="btn-primary btn-sm" data-apply>' + esc(T('Apply to field')) + '</button>' : '') + (a.apply_url ? '<a class="btn-primary btn-sm" href="' + esc(a.apply_url) + '">' + esc(a.apply_label || 'Open') + '</a>' : '') + '</div>';
          $('[data-copy]', box).addEventListener('click', function () { var t = $('textarea', box); t.select(); try { navigator.clipboard.writeText(t.value); } catch (e) { document.execCommand('copy'); } });
          var ap = $('[data-apply]', box);
          if (ap) ap.addEventListener('click', function () {
            if (!lastField || !document.body.contains(lastField)) { alertBox(T('Click the text box on the page you want to fill, then press Apply again.')); return; }
            lastField.value = $('textarea', box).value; lastField.dispatchEvent(new Event('input', { bubbles: true })); lastField.focus();
          });
        } else if (a.type === 'link') {
          box.innerHTML = '<a class="btn-primary btn-sm" href="' + esc(a.url) + '">' + esc(a.label) + '</a>';
        } else if (a.type === 'confirm') {
          box.innerHTML = '<p class="text-sm">' + esc(a.summary) + '</p><div class="mt-2 flex gap-2"><button type="button" class="btn-primary btn-sm" data-yes>' + esc(T('Confirm')) + '</button><button type="button" class="btn-ghost btn-sm" data-no>' + esc(T('Cancel')) + '</button></div><p class="error hidden" data-err></p>';
          $('[data-yes]', box).addEventListener('click', function () {
            post(a.url, a.payload || {}).then(function (res) {
              if (!res.ok) { var e = $('[data-err]', box); e.textContent = errText(res); e.classList.remove('hidden'); return; }
              box.remove(); bubble('assistant', res.j.text || T('Done.'));
            });
          });
          $('[data-no]', box).addEventListener('click', function () { box.remove(); bubble('assistant', T('Cancelled — nothing was changed.')); });
        }
        log.appendChild(box); scroll();
      };
      var render = function (m) { bubble(m.role, m.text); cards(m.cards); table(m.table); if (m.role === 'assistant') action(m.action); };
      var load = function () {
        if (loaded) return; loaded = true;
        fetch(root.getAttribute('data-history'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
          if (j.greeting) bubble('assistant', j.greeting);
          (j.messages || []).forEach(render);
        }).catch(function () { bubble('assistant', T('The assistant is not available right now.')); });
      };
      var key = 'v7chat-open-' + root.getAttribute('data-bot');
      var remember = function (on) { try { on ? sessionStorage.setItem(key, '1') : sessionStorage.removeItem(key); } catch (e) { /* storage blocked */ } };
      var open = function () { panel.classList.remove('hidden'); panel.classList.add('flex'); load(); input.focus(); remember(true); $$('[data-chat-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); }); };
      var close = function () { panel.classList.add('hidden'); panel.classList.remove('flex'); remember(false); $$('[data-chat-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); }); };
      $$('[data-chat-open]').forEach(function (b) { b.addEventListener('click', function () { panel.classList.contains('hidden') ? open() : close(); }); });
      $('[data-chat-close]', root).addEventListener('click', close);
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.classList.contains('hidden')) close(); });
      // The workspace panel stays open while moving between screens.
      try { if (root.getAttribute('data-bot') === 'workspace' && sessionStorage.getItem(key) === '1') open(); } catch (e) { /* storage blocked */ }
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim(); if (!text || busy) return;
        busy = true; input.value = ''; bubble('user', text);
        var wait = bubble('assistant', '…');
        post(root.getAttribute('data-send'), { message: text }).then(function (res) {
          wait.remove();
          if (!res.ok) { bubble('assistant', errText(res)); return; }
          render({ role: 'assistant', text: res.j.text, cards: res.j.cards, table: res.j.table, action: res.j.action });
        }).catch(function () { wait.remove(); bubble('assistant', T('Could not reach the server.')); }).finally(function () { busy = false; });
      });
    });
  }

  /* ---------- "Write with AI" buttons: send the form's fields, put the draft into a textarea ---------- */
  function initAiWrite() {
    $$('[data-ai-write]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var form = btn.closest('form'), target = document.getElementById(btn.getAttribute('data-ai-write'));
        var token = ($('meta[name=csrf-token]') || {}).content, fd = new FormData();
        $$('input, select, textarea', form).forEach(function (f) {
          if (!f.name || f.type === 'file' || f.type === 'password' || f.name === '_token' || f.name === '_method') return;
          if ((f.type === 'checkbox' || f.type === 'radio') && !f.checked) return;
          fd.append('fields[' + f.name.replace(/\[\]$/, '') + '][]', f.value);
        });
        fd.append('kind', btn.getAttribute('data-ai-kind') || 'description');
        var label = btn.innerHTML; btn.disabled = true; btn.textContent = 'Writing…';
        fetch(btn.getAttribute('data-url'), { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: fd })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
            if (res.ok && res.j.text) { target.value = res.j.text; target.dispatchEvent(new Event('input')); }
            else { alertBox((res.j && (res.j.message || res.j.error)) || 'The AI could not write a draft right now.'); }
          })
          .catch(function () { alertBox('The AI could not write a draft right now.'); })
          .finally(function () { btn.disabled = false; btn.innerHTML = label; });
      });
    });
  }

  /* ---------- Drop zones: show chosen file names, highlight on drag ---------- */
  function initDropzones() {
    $$('input[type=file][data-dropzone]').forEach(function (input) {
      var zone = input.closest('.dropzone');
      var note = document.createElement('span'); note.className = 'text-xs font-semibold text-teal-800'; note.setAttribute('aria-live', 'polite');
      zone.appendChild(note);
      var show = function () { var n = input.files ? input.files.length : 0; note.textContent = n ? n + ' file(s) chosen: ' + Array.prototype.slice.call(input.files, 0, 4).map(function (f) { return f.name; }).join(', ') + (n > 4 ? '…' : '') : ''; };
      input.addEventListener('change', show);
      ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-over'); }); });
      zone.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); } });
    });
  }

  /* ---------- Drag to reorder photos (data-sortable="<url>"); arrow buttons do the same for keyboard users ---------- */
  function initSortable() {
    $$('[data-sortable]').forEach(function (list) {
      var url = list.getAttribute('data-sortable'); if (!url) return;
      var token = ($('meta[name=csrf-token]') || {}).content, dragged = null;
      $$('[data-id]', list).forEach(function (item) {
        item.addEventListener('dragstart', function (e) { dragged = item; item.classList.add('opacity-50'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', item.getAttribute('data-id')); });
        item.addEventListener('dragend', function () { item.classList.remove('opacity-50'); });
        item.addEventListener('dragover', function (e) {
          if (!dragged || dragged === item) return; e.preventDefault();
          var r = item.getBoundingClientRect(), after = (e.clientX - r.left) > r.width / 2;
          list.insertBefore(dragged, after ? item.nextSibling : item);
        });
        item.addEventListener('drop', function (e) {
          e.preventDefault();
          var ids = $$('[data-id]', list).map(function (li) { return li.getAttribute('data-id'); });
          fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ ids: ids }) });
        });
      });
    });
  }

  /* ---------- Print buttons ---------- */
  function initPrint() { $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); }); }

  document.addEventListener('DOMContentLoaded', function () {
    initSidebar(); initPublicNav(); initDropdowns(); initPasswords(); initWidths(); initConfirm(); initCopy(); initBulk();
    initRepeaters(); initEstimate(); initCalc(); initTooltips(); initPlotFilters(); initCarousels(); initReveal(); initCharts();
    initPinEditor(); initConditional(); initLookups(); initPlotPicker(); initAutoSubmit(); initPrint(); initGrid(); initDepends();
    initKanban(); initMaps(); initAiWrite(); initDropzones(); initSortable(); initContact(); initSearchUi(); initChat();
  });
})();
