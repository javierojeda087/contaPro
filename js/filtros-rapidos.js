/**
 * Filtros rápidos de fecha limitados al ejercicio contable activo.
 * Uso:
 *   FiltrosRapidos.init({
 *     container: 'qfBar', ini: '2026-01-01', fin: '2026-12-31', ejercicio: '2026',
 *     desde: 'fechaDesde', hasta: 'fechaHasta', onApply: () => cargarReporte()
 *   });
 */
(function () {
    const pad = n => String(n).padStart(2, '0');
    const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parseIso = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };

    function injectCss() {
        if (document.getElementById('qf-css')) return;
        const st = document.createElement('style');
        st.id = 'qf-css';
        st.textContent = `
            .qf-bar { display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
            .qf-lbl { font-size:.72rem; font-weight:800; color:#475569; text-transform:uppercase; margin-right:4px; }
            .qf-btn { background:#fff; border:1px solid #cbd5e1; color:#334155; font-size:.76rem; font-weight:700; padding:4px 10px; border-radius:999px; cursor:pointer; transition:all .15s; }
            .qf-btn:hover:not(:disabled) { border-color:#16a34a; color:#15803d; background:#f0fdf4; }
            .qf-btn.active { background:#16a34a; border-color:#16a34a; color:#fff; }
            .qf-btn:disabled { opacity:.4; cursor:not-allowed; }
            .qf-mes { padding:4px 8px; min-width:40px; }
            .qf-sep { width:1px; height:22px; background:#cbd5e1; margin:0 6px; }
            @media print { .qf-bar { display:none !important; } }`;
        document.head.appendChild(st);
    }

    function init(opt) {
        injectCss();
        const INI = opt.ini, FIN = opt.fin;
        const clamp = s => (s < INI ? INI : (s > FIN ? FIN : s));
        const elD = document.getElementById(opt.desde || 'fechaDesde');
        const elH = document.getElementById(opt.hasta || 'fechaHasta');
        const bar = document.getElementById(opt.container);
        if (!bar || !elD || !elH || !INI || !FIN) return;

        elD.min = elH.min = INI;
        elD.max = elH.max = FIN;
        bar.classList.add('qf-bar');
        bar.innerHTML = `<span class="qf-lbl"><i class="ph ph-lightning" style="color:#16a34a;"></i> Rápido · Ejercicio ${opt.ejercicio || ''}:</span>`;

        const clearActive = () => bar.querySelectorAll('.qf-btn').forEach(b => b.classList.remove('active'));
        const apply = (d, h, btn) => {
            elD.value = clamp(iso(d));
            elH.value = clamp(iso(h));
            clearActive();
            if (btn) btn.classList.add('active');
            if (opt.onApply) opt.onApply();
        };
        const ref = () => parseIso(clamp(iso(new Date())));
        const add = (txt, fn, cls) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'qf-btn' + (cls ? ' ' + cls : ''); b.textContent = txt;
            b.onclick = () => fn(b);
            bar.appendChild(b);
            return b;
        };

        add('Hoy', b => { const r = ref(); apply(r, r, b); });
        add('Ayer', b => { const r = ref(); const a = new Date(r.getFullYear(), r.getMonth(), r.getDate() - 1); apply(a, a, b); });
        add('Este mes', b => { const r = ref(); apply(new Date(r.getFullYear(), r.getMonth(), 1), new Date(r.getFullYear(), r.getMonth() + 1, 0), b); });
        add('Mes anterior', b => { const r = ref(); apply(new Date(r.getFullYear(), r.getMonth() - 1, 1), new Date(r.getFullYear(), r.getMonth(), 0), b); });
        add('Trimestre', b => { const r = ref(); const q = Math.floor(r.getMonth() / 3) * 3; apply(new Date(r.getFullYear(), q, 1), new Date(r.getFullYear(), q + 3, 0), b); });
        add('Todo el ejercicio', b => apply(parseIso(INI), parseIso(FIN), b));

        const sep = document.createElement('span'); sep.className = 'qf-sep'; bar.appendChild(sep);

        const y = parseIso(INI).getFullYear();
        ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'].forEach((m, i) => {
            const ini = new Date(y, i, 1), fin = new Date(y, i + 1, 0);
            const b = add(m, btn => apply(ini, fin, btn), 'qf-mes');
            if (iso(fin) < INI || iso(ini) > FIN) b.disabled = true;
        });

        [elD, elH].forEach(el => el.addEventListener('change', () => {
            if (el.value) el.value = clamp(el.value);
            clearActive();
        }));
    }

    window.FiltrosRapidos = { init };
})();
