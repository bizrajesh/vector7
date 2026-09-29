/*
 * Vector7 front-end behaviour. Plain JavaScript, loaded from 'self' only (strict CSP):
 * no inline scripts, no eval, no innerHTML with data — DOM text is set with textContent.
 */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    // Mobile navigation drawer
    function setDrawer(open) {
        const drawer = $('#drawer');
        const backdrop = $('#drawer-backdrop');
        if (!drawer) return;
        drawer.classList.toggle('-translate-x-full', !open);
        drawer.classList.toggle('translate-x-0', open);
        if (backdrop) backdrop.classList.toggle('hidden', !open);
    }
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-open-drawer]')) setDrawer(true);
        if (e.target.closest('[data-close-drawer]')) setDrawer(false);
        if (e.target.closest('[data-print]')) window.print();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setDrawer(false); });

    // Confirmation for money and status actions: <form data-confirm="...">
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            e.preventDefault();
            return;
        }
        // Prevent double submission (double bookings / duplicate payments).
        $$('button[type="submit"], button:not([type])', form).forEach((btn) => {
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
        });
    });

    // Show/hide blocks: <input data-toggle-target="#id"> (checkbox or radio) or <select data-toggle-value="x" ...>
    function syncToggles() {
        $$('[data-show-when]').forEach((block) => {
            const [name, value] = block.getAttribute('data-show-when').split('=');
            const checked = $(`[name="${name}"]:checked`) || $(`select[name="${name}"]`);
            const current = checked ? checked.value : '';
            const show = value === '*' ? current !== '' : current === value;
            block.hidden = !show;
            $$('input, select, textarea', block).forEach((el) => { el.disabled = !show; });
        });
    }
    document.addEventListener('change', (e) => {
        syncToggles();
        if (e.target.matches('[data-autosubmit]') && e.target.form) e.target.form.requestSubmit();
    });
    syncToggles();

    // Plot bottom sheet: tiles carry data-* attributes; the <dialog> is filled with textContent.
    // On the public plot map the sheet opens on every screen size (data-sheet-always).
    const sheet = $('#plot-sheet');
    if (sheet && typeof sheet.showModal === 'function') {
        const always = sheet.hasAttribute('data-sheet-always');
        document.addEventListener('click', (e) => {
            const tile = e.target.closest('[data-plot]');
            if (!tile || (!always && window.matchMedia('(min-width: 1024px)').matches)) return;
            e.preventDefault();
            const d = tile.dataset;
            $$('[data-field]', sheet).forEach((el) => { el.textContent = d[el.getAttribute('data-field')] || '—'; });
            const badge = $('[data-badge]', sheet);
            badge.className = 'badge-' + d.css;
            badge.textContent = d.status;
            const book = $('[data-book]', sheet);
            const buy = $('[data-buy]', sheet);
            const view = $('[data-view]', sheet);
            view.href = d.url;
            book.href = d.bookUrl || '#';
            buy.href = d.sellUrl || '#';
            book.hidden = !d.bookUrl;
            buy.hidden = !d.sellUrl && !d.callback;
            if (d.callback) {
                buy.href = '#callback';
                buy.setAttribute('data-callback-plot', d.no.replace(/^Plot\s+/, ''));
            }
            sheet.showModal();
        });
        sheet.addEventListener('click', (e) => {
            const cb = e.target.closest('[data-callback-plot]');
            if (cb) {
                const field = $('#f-plot_no');
                if (field) field.value = cb.getAttribute('data-callback-plot');
                sheet.close();
                return;
            }
            if (e.target === sheet || e.target.closest('[data-close-sheet]')) sheet.close();
        });
    }

    // Count-up numbers: <span data-count="120">120</span> (the final value is in the HTML for no-JS / reduced motion).
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduceMotion && 'IntersectionObserver' in window) {
        const counters = $$('[data-count]');
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                io.unobserve(el);
                const target = parseFloat(el.getAttribute('data-count')) || 0;
                const start = performance.now();
                const duration = 1200;
                const fmt = new Intl.NumberFormat('en-IN');
                const step = (now) => {
                    const p = Math.min(1, (now - start) / duration);
                    el.textContent = fmt.format(Math.round(target * (1 - Math.pow(1 - p, 3))));
                    if (p < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });
        }, { threshold: 0.4 });
        counters.forEach((el) => io.observe(el));
    }

    // Charts: <canvas data-chart="line|bar|doughnut" data-source="json-script-id">
    function renderCharts() {
        if (typeof window.Chart === 'undefined') return;
        const palette = ['#227C70', '#1C315E', '#88A47C', '#C9B458', '#5E7A53', '#2E4A80', '#B9C4DB'];
        window.Chart.defaults.font.family = '"Plus Jakarta Sans", system-ui, sans-serif';
        window.Chart.defaults.color = '#58607A';
        $$('canvas[data-chart]').forEach((canvas) => {
            const source = document.getElementById(canvas.getAttribute('data-source'));
            if (!source) return;
            let data;
            try { data = JSON.parse(source.textContent); } catch (err) { return; }
            const type = canvas.getAttribute('data-chart');
            const datasets = data.datasets.map((ds, i) => Object.assign({
                borderColor: palette[i % palette.length],
                backgroundColor: type === 'line' ? 'rgba(34,124,112,0.12)' : (type === 'doughnut' ? palette : palette[i % palette.length]),
                borderWidth: type === 'doughnut' ? 0 : 2,
                tension: 0.3,
                fill: type === 'line',
                pointRadius: type === 'line' ? 3 : 0,
                borderRadius: type === 'bar' ? 6 : 0,
            }, ds));
            new window.Chart(canvas, {
                type,
                data: { labels: data.labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: datasets.length > 1 || type === 'doughnut', position: 'bottom' } },
                    scales: type === 'doughnut' ? {} : {
                        x: { grid: { display: false } },
                        y: { grid: { color: '#EEEBDD' }, beginAtZero: type === 'bar' },
                    },
                },
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderCharts);
    } else {
        renderCharts();
    }
})();
