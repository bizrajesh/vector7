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
        var ok = function () { var t = btn.textContent; btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = t; }, 1500); };
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

  /* ---------- Print buttons ---------- */
  function initPrint() { $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); }); }

  document.addEventListener('DOMContentLoaded', function () {
    initSidebar(); initPublicNav(); initDropdowns(); initPasswords(); initWidths(); initConfirm(); initCopy(); initBulk();
    initRepeaters(); initEstimate(); initCalc(); initTooltips(); initPlotFilters(); initCarousels(); initReveal(); initCharts();
    initPinEditor(); initConditional(); initLookups(); initPlotPicker(); initAutoSubmit(); initPrint(); initGrid(); initDepends();
  });
})();
