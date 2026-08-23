@once
<style>
.crm-v2{--crm-violet:#7c3aed;--crm-violet-soft:#f3e8ff;--crm-ink:#0f172a;--crm-muted:#64748b;--crm-line:#e2e8f0;--crm-bg:#f8fafc}
.crm-v2 .btn-primary{background:var(--crm-violet);border-color:var(--crm-violet);color:#fff}
.crm-v2 .btn-primary:hover{background:#6d28d9}
.crm-v2 .btn-success{background:#10b981;border-color:#10b981;color:#fff}
.crm-v2 .btn-success:hover{background:#059669}
.crm-v2-head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px}
.crm-v2-head h2{margin:0;font-size:1.55rem;font-weight:800;letter-spacing:-.03em;color:var(--crm-ink)}
.crm-v2-head p{margin:4px 0 0;color:var(--crm-muted);font-size:.92rem}
.crm-v2-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.crm-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:18px}
.crm-stat{background:#fff;border:1px solid var(--crm-line);border-radius:14px;padding:14px 16px 12px;position:relative;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.crm-stat__icon{width:32px;height:32px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;margin-bottom:8px}
.crm-stat__label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8}
.crm-stat__value{font-size:1.45rem;font-weight:800;letter-spacing:-.03em;color:var(--crm-ink);margin-top:2px}
.crm-stat__delta{font-size:.75rem;font-weight:600;margin-top:2px}
.crm-stat__delta.is-up{color:#059669}
.crm-stat__delta.is-down{color:#dc2626}
.crm-stat__bar{position:absolute;left:0;right:0;bottom:0;height:4px}
.crm-stat--blue .crm-stat__icon{background:#dbeafe;color:#2563eb}
.crm-stat--blue .crm-stat__bar{background:#3b82f6}
.crm-stat--orange .crm-stat__icon{background:#ffedd5;color:#ea580c}
.crm-stat--orange .crm-stat__bar{background:#f97316}
.crm-stat--green .crm-stat__icon{background:#dcfce7;color:#16a34a}
.crm-stat--green .crm-stat__bar{background:#22c55e}
.crm-stat--violet .crm-stat__icon{background:var(--crm-violet-soft);color:var(--crm-violet)}
.crm-stat--violet .crm-stat__bar{background:var(--crm-violet)}
.crm-stat--rose .crm-stat__icon{background:#ffe4e6;color:#e11d48}
.crm-stat--rose .crm-stat__bar{background:#f43f5e}
.crm-stat--cyan .crm-stat__icon{background:#cffafe;color:#0891b2}
.crm-stat--cyan .crm-stat__bar{background:#06b6d4}
.crm-dash-grid{display:grid;grid-template-columns:1.1fr 1.1fr .9fr;gap:14px}
.crm-card{background:#fff;border:1px solid var(--crm-line);border-radius:16px;padding:16px 18px;box-shadow:0 1px 2px rgba(15,23,42,.04);cursor:default}
.crm-v2 .crm-card,.crm-v2 .crm-next-card{cursor:default}
.crm-v2 .crm-card:hover{border-color:var(--crm-line);box-shadow:0 1px 2px rgba(15,23,42,.04);transform:none}
.crm-card__title{margin:0 0 12px;font-size:.95rem;font-weight:700;color:var(--crm-ink)}
.crm-action-pills{display:flex;flex-wrap:wrap;gap:8px}
.crm-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;font-size:.82rem;font-weight:600;text-decoration:none}
.crm-pill strong{font-size:1rem}
.crm-pill--rose{background:#fff1f2;color:#be123c}
.crm-pill--orange{background:#fff7ed;color:#c2410c}
.crm-pill--green{background:#ecfdf5;color:#047857}
.crm-act-row{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit}
.crm-act-row:last-child{border-bottom:0}
.crm-act-row__time{font-size:.75rem;font-weight:700;color:var(--crm-violet);min-width:44px}
.crm-act-row__meta{font-size:.75rem;color:var(--crm-muted)}
.crm-alert{padding:8px 10px;border-radius:10px;font-size:.82rem;font-weight:600;margin-bottom:8px}
.crm-alert--rose{background:#fff1f2;color:#be123c}
.crm-alert--orange{background:#fff7ed;color:#c2410c}
.crm-alert--blue{background:#eff6ff;color:#1d4ed8}
.crm-filterbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px;background:#fff;border:1px solid var(--crm-line);border-radius:14px;padding:10px 12px}
.crm-filterbar .input,.crm-filterbar select{min-width:140px}
.crm-filterbar__search{flex:1 1 240px}
.crm-toolbar{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px 14px;margin-bottom:14px;background:#fff;border:1px solid var(--crm-line);border-radius:16px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.crm-toolbar__search{position:relative;flex:1 1 260px;min-width:220px}
.crm-toolbar__search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none}
.crm-toolbar__search .input{width:100%;padding-left:38px;border-radius:10px;height:40px}
.crm-toolbar__field{display:flex;flex-direction:column;gap:4px;min-width:118px}
.crm-toolbar__field label{font-size:.72rem;font-weight:700;color:#1e293b}
.crm-toolbar__field select.input,.crm-toolbar__field input.input{border-radius:10px;height:40px;min-width:118px}
.crm-toolbar__actions{display:flex;align-items:center;gap:8px;margin-left:auto}
.crm-btn-advanced{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 14px;border:0;border-radius:10px;background:#f3e8ff;color:#6d28d9;font-weight:700;font-size:.82rem;cursor:pointer}
.crm-btn-advanced.is-on{background:#7c3aed;color:#fff}
.crm-btn-icon{width:40px;height:40px;border:1px solid var(--crm-line);border-radius:10px;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:#475569;text-decoration:none}
.crm-btn-icon:hover{background:#f8fafc}
.crm-toolbar-extra{display:flex;flex-wrap:wrap;gap:8px;margin:-4px 0 14px}
.crm-table-wrap{overflow-x:auto;background:#fff;border:1px solid var(--crm-line);border-radius:16px}
.crm-table-wrap--fit{overflow-x:hidden}
.crm-table{width:100%;border-collapse:collapse;font-size:.82rem}
.crm-table--fit{table-layout:fixed;width:100%;font-size:.72rem}
.crm-table th{text-align:left;padding:12px 14px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap}
.crm-table td{padding:14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.crm-table--fit th{padding:8px 10px;font-size:.68rem;font-weight:600;text-transform:none;letter-spacing:0;color:#64748b}
.crm-table--fit td{padding:8px 10px;overflow:hidden}
.crm-table--fit th:first-child,.crm-table--fit td:first-child{padding-left:18px}
.crm-table--fit th:last-child,.crm-table--fit td:last-child{padding-right:18px;width:44px;text-align:right;overflow:visible}
.crm-table--fit th:nth-child(1),.crm-table--fit td:nth-child(1){width:15%}
.crm-table--fit th:nth-child(2),.crm-table--fit td:nth-child(2){width:11%}
.crm-table--fit th:nth-child(3),.crm-table--fit td:nth-child(3){width:11%}
.crm-table--fit th:nth-child(4),.crm-table--fit td:nth-child(4){width:9%}
.crm-table--fit th:nth-child(5),.crm-table--fit td:nth-child(5){width:10%}
.crm-table--fit th:nth-child(6),.crm-table--fit td:nth-child(6){width:5%}
.crm-table--fit th:nth-child(7),.crm-table--fit td:nth-child(7){width:8%}
.crm-table--fit th:nth-child(8),.crm-table--fit td:nth-child(8){width:10%}
.crm-table--fit th:nth-child(9),.crm-table--fit td:nth-child(9){width:10%}
.crm-table--fit th:nth-child(10),.crm-table--fit td:nth-child(10){width:11%}
.crm-table--fit .crm-person{gap:6px;min-width:0}
.crm-table--fit .crm-person>div{min-width:0;overflow:hidden}
.crm-table--fit .crm-person strong,.crm-table--fit .crm-person span:not(.crm-avatar),.crm-table--fit .crm-co,.crm-table--fit .crm-last strong,.crm-table--fit .crm-last span,.crm-table--fit .crm-next strong,.crm-table--fit .crm-next time{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.crm-table--fit .crm-avatar{width:28px;height:28px;font-size:.62rem}
.crm-table--fit .crm-avatar--sm{width:22px;height:22px;font-size:.55rem}
.crm-table--fit .crm-badge{padding:2px 6px;font-size:.62rem;max-width:100%;overflow:hidden;text-overflow:ellipsis}
.crm-table--fit .crm-score{width:26px;height:26px;font-size:.65rem}
.crm-table--fit .crm-last,.crm-table--fit .crm-next,.crm-table--fit .crm-phone,.crm-table--fit .crm-person--owner span:not(.crm-avatar){font-size:.72rem}
.crm-table--fit .crm-last span,.crm-table--fit .crm-next time,.crm-table--fit .crm-person span:not(.crm-avatar){font-size:.62rem}
.crm-table--fit .crm-phone{gap:4px}
.crm-table--fit .crm-kebab{display:inline-flex;justify-content:flex-end}
.crm-table--fit .crm-kebab__btn{width:28px;height:28px;font-size:1rem;letter-spacing:0}
.crm-table tbody tr:last-child td{border-bottom:0}
.crm-table tr:hover td{background:#fafafa}
.crm-check{width:15px;height:15px;accent-color:#7c3aed;cursor:pointer}
.crm-person{display:flex;align-items:center;gap:10px}
.crm-avatar{width:36px;height:36px;border-radius:50%;background:var(--crm-violet-soft);color:var(--crm-violet);display:inline-flex !important;align-items:center;justify-content:center;flex-shrink:0;padding:0;margin:0;overflow:hidden !important;text-overflow:clip !important;white-space:nowrap;box-sizing:border-box;line-height:1;letter-spacing:0;text-align:center;font-weight:700;font-size:.72rem;font-style:normal;vertical-align:middle}
.crm-avatar i{font-style:normal;font-weight:700;font-size:inherit;line-height:1;letter-spacing:0;display:block;transform:translateY(.5px)}
.crm-avatar.is-solid{color:#fff}
.crm-avatar--sm{width:22px;height:22px;font-size:.55rem}
.crm-person strong{display:block;color:var(--crm-ink)}
.crm-person span:not(.crm-avatar){display:block;color:var(--crm-muted);font-size:.75rem}
.crm-person--owner span:not(.crm-avatar){color:var(--crm-ink);font-size:.82rem;font-weight:600}
.crm-co{font-weight:700;color:var(--crm-ink)}
.crm-phone{display:inline-flex;align-items:center;gap:6px;color:#16a34a;font-weight:600;text-decoration:none;white-space:nowrap}
.crm-phone svg{flex-shrink:0}
.crm-badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:.72rem;font-weight:700;white-space:nowrap}
.crm-badge--green{background:#dcfce7;color:#166534}
.crm-badge--orange{background:#ffedd5;color:#9a3412}
.crm-badge--rose{background:#ffe4e6;color:#9f1239}
.crm-badge--blue{background:#dbeafe;color:#1e40af}
.crm-badge--violet{background:var(--crm-violet-soft);color:#6d28d9}
.crm-badge--cyan{background:#cffafe;color:#155e75}
.crm-badge--slate{background:#f1f5f9;color:#475569}
.crm-score{width:36px;height:36px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem;border:2px solid;background:#fff}
.crm-score--hot{border-color:#22c55e;color:#16a34a}
.crm-score--warm{border-color:#f59e0b;color:#d97706}
.crm-score--cold{border-color:#f43f5e;color:#e11d48}
.crm-last{font-size:.82rem}
.crm-last strong{display:block;color:var(--crm-ink);font-weight:700}
.crm-last span{display:block;color:var(--crm-muted);font-size:.75rem;margin-top:1px}
.crm-next{font-size:.82rem}
.crm-next strong{display:block;font-weight:700}
.crm-next time{display:block;font-weight:600;font-size:.75rem;margin-top:1px}
.crm-next.is-ok strong,.crm-next.is-ok time{color:#059669}
.crm-next.is-soon strong,.crm-next.is-soon time{color:#d97706}
.crm-next.is-late strong,.crm-next.is-late time{color:#e11d48}
.crm-kebab{position:relative}
.crm-kebab__btn{width:32px;height:32px;border:0;background:transparent;border-radius:8px;cursor:pointer;color:#64748b;font-size:1.15rem;letter-spacing:.08em;line-height:1}
.crm-kebab__btn:hover{background:#f1f5f9;color:#0f172a}
.crm-kebab__menu{position:absolute;right:0;top:calc(100% + 4px);z-index:30;min-width:168px;background:#fff;border:1px solid var(--crm-line);border-radius:12px;box-shadow:0 10px 28px rgba(15,23,42,.12);padding:6px;display:flex;flex-direction:column}
.crm-kebab__menu--portal{position:fixed;right:auto;top:0;left:0;z-index:80;min-width:180px}
.crm-kebab__menu a,.crm-kebab__menu button{display:block;width:100%;text-align:left;padding:8px 10px;border:0;background:none;border-radius:8px;font-size:.82rem;cursor:pointer;color:var(--crm-ink);text-decoration:none}
.crm-kebab__menu a:hover,.crm-kebab__menu button:hover{background:#f8fafc}
.crm-kebab__menu .is-danger{color:#be123c}
.crm-kanban-scroll{overflow-x:auto;padding-bottom:8px}
.crm-board{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(220px,1fr);gap:12px;min-width:min-content}
.crm-col{background:#f8fafc;border:1px solid var(--crm-line);border-radius:14px;min-height:280px;display:flex;flex-direction:column}
.crm-col.is-over{box-shadow:inset 0 0 0 2px #7c3aed}
.crm-col__head{padding:12px 12px 8px;border-bottom:1px solid #eef2f7}
.crm-col__head h3{margin:0;font-size:.82rem;font-weight:800;text-transform:uppercase;letter-spacing:.03em}
.crm-col__meta{font-size:.72rem;color:var(--crm-muted);margin-top:2px}
.crm-col--blue{border-top:3px solid #3b82f6}
.crm-col--cyan{border-top:3px solid #06b6d4}
.crm-col--violet{border-top:3px solid #7c3aed}
.crm-col--orange{border-top:3px solid #f97316}
.crm-col--green{border-top:3px solid #10b981}
.crm-col--success{border-top:3px solid #16a34a;background:#f0fdf4}
.crm-col--rose{border-top:3px solid #f43f5e;background:#fff1f2}
.crm-col__body{padding:8px;display:flex;flex-direction:column;gap:8px;flex:1;min-height:180px}
.crm-deal{background:#fff;border:1px solid var(--crm-line);border-radius:12px;padding:10px 12px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.crm-deal.is-draggable{cursor:grab;user-select:none;-webkit-user-select:none;touch-action:none}
.crm-deal.is-draggable:active{cursor:grabbing}
.crm-deal.is-dragging{opacity:1;box-shadow:0 14px 30px rgba(15,23,42,.18);z-index:30}
.crm-deal:hover{border-color:#c4b5fd}
.crm-deal__title{font-weight:700;font-size:.85rem;color:var(--crm-ink)}
.crm-deal__co{font-size:.75rem;color:var(--crm-muted);margin:2px 0 6px}
.crm-deal__amt{font-weight:800;font-size:.95rem}
.crm-deal__row{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:6px;font-size:.72rem;color:var(--crm-muted)}
.crm-deal__quote{margin-top:8px;width:100%}
.crm-legend{display:flex;flex-wrap:wrap;gap:10px 16px;margin-top:12px;font-size:.75rem;color:var(--crm-muted)}
.crm-fiche-hero{display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;margin-bottom:14px}
.crm-tabs{display:flex;gap:4px;overflow-x:auto;border-bottom:1px solid var(--crm-line);margin-bottom:16px}
.crm-tabs button{background:none;border:0;padding:10px 14px;font-weight:600;font-size:.85rem;color:var(--crm-muted);cursor:pointer;border-bottom:2px solid transparent}
.crm-tabs button.is-on{color:var(--crm-violet);border-bottom-color:var(--crm-violet)}
.crm-fiche-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,360px);gap:16px}
.crm-identity{display:flex;gap:16px;align-items:flex-start}
.crm-identity .crm-avatar{width:64px;height:64px;font-size:1.2rem}
.crm-kv{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;margin-top:14px}
.crm-kv div span{display:block;font-size:.7rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em}
.crm-kv div strong{font-size:.85rem}
.crm-next-card{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:16px;padding:16px}
.crm-next-card h3{margin:0 0 8px;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase;color:#047857}
.crm-gauge{width:120px;height:120px;margin:0 auto 8px;position:relative}
.crm-side-stack{display:flex;flex-direction:column;gap:12px}
.crm-timeline-h{display:flex;gap:12px;overflow-x:auto;padding-bottom:6px}
.crm-tl{min-width:160px;background:#f8fafc;border:1px solid var(--crm-line);border-radius:12px;padding:10px}
.crm-tl-v{position:relative;margin:0;padding:4px 0 4px 4px}
.crm-tl-item{position:relative;padding:0 0 18px 28px}
.crm-tl-item:last-child{padding-bottom:2px}
.crm-tl-item:before{content:'';position:absolute;left:7px;top:14px;bottom:-4px;width:2px;background:#e2e8f0}
.crm-tl-item:last-child:before{display:none}
.crm-tl-dot{position:absolute;left:2px;top:6px;width:12px;height:12px;border-radius:50%;background:#fff;border:2px solid #7c3aed;z-index:1;box-sizing:border-box}
.crm-tl-item.is-rose .crm-tl-dot{border-color:#e11d48;background:#fff1f2}
.crm-tl-item.is-orange .crm-tl-dot{border-color:#ea580c;background:#fff7ed}
.crm-tl-item.is-green .crm-tl-dot{border-color:#16a34a;background:#ecfdf5}
.crm-tl-item.is-blue .crm-tl-dot{border-color:#2563eb;background:#eff6ff}
.crm-tl-item.is-slate .crm-tl-dot{border-color:#94a3b8;background:#f8fafc}
.crm-tl-when{font-size:.72rem;font-weight:700;color:#94a3b8;letter-spacing:.02em}
.crm-tl-head{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:4px}
.crm-tl-title{font-weight:700;font-size:.88rem;color:var(--crm-ink);margin-top:4px;line-height:1.35}
.crm-tl-meta{font-size:.75rem;color:var(--crm-muted);margin-top:3px}
.crm-tl-body{font-size:.8rem;color:#475569;margin-top:6px;line-height:1.4}
.crm-tl-more{margin-top:10px}
.crm-empty{text-align:center;padding:28px;color:var(--crm-muted)}
.crm-wa{color:#16a34a;text-decoration:none;font-weight:700}
.crm-picker-results__item{display:block;width:100%;text-align:left;background:#fff;border:0;border-bottom:1px solid #f1f5f9;padding:8px 10px;cursor:pointer}
.crm-picker-results__item strong{display:block}
.crm-picker-results__item span{font-size:.75rem;color:#64748b}
.crm-picker-selected{padding:8px 10px;background:#f8fafc;border-radius:8px;font-weight:600}
@media(max-width:1100px){.crm-dash-grid,.crm-fiche-grid{grid-template-columns:1fr}.crm-board{grid-auto-columns:minmax(200px,80vw)}}
</style>
<script>
(function () {
    if (window.__crmKanbanPointer) return;
    window.__crmKanbanPointer = true;
    var drag = null;
    var suppressClick = false;

    function livewireWire(el) {
        if (!el || !window.Livewire || typeof window.Livewire.find !== 'function') return null;
        var root = el.closest('[wire\\:id]');
        if (!root) return null;
        return window.Livewire.find(root.getAttribute('wire:id'));
    }

    function columnAt(x, y) {
        var under = document.elementFromPoint(x, y);
        return under && under.closest ? under.closest('.crm-col[data-stage]') : null;
    }

    function clearOver() {
        document.querySelectorAll('.crm-col.is-over').forEach(function (c) { c.classList.remove('is-over'); });
    }

    function resetCard(card) {
        if (!card) return;
        card.classList.remove('is-dragging');
        card.style.transform = '';
        card.style.pointerEvents = '';
        card.style.zIndex = '';
        card.style.position = '';
        card.style.left = '';
        card.style.top = '';
        card.style.width = '';
    }

    document.addEventListener('pointerdown', function (e) {
        if (e.button !== 0) return;
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('a, button, input, select, textarea, [data-no-drag]')) return;
        var card = t.closest('.crm-board .crm-deal.is-draggable[data-opp-id]');
        if (!card) return;
        drag = {
            id: card.getAttribute('data-opp-id'),
            card: card,
            startX: e.clientX,
            startY: e.clientY,
            moved: false
        };
    });

    document.addEventListener('pointermove', function (e) {
        if (!drag) return;
        var dx = e.clientX - drag.startX;
        var dy = e.clientY - drag.startY;
        if (!drag.moved && Math.abs(dx) + Math.abs(dy) < 8) return;
        if (!drag.moved) {
            drag.moved = true;
            drag.card.classList.add('is-dragging');
            drag.card.style.pointerEvents = 'none';
            drag.card.style.zIndex = '40';
            drag.card.style.position = 'relative';
        }
        drag.card.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
        clearOver();
        var col = columnAt(e.clientX, e.clientY);
        if (col) col.classList.add('is-over');
        if (e.cancelable) e.preventDefault();
    }, { passive: false });

    function finish(e) {
        if (!drag) return;
        var d = drag;
        drag = null;
        clearOver();
        resetCard(d.card);
        if (!d.moved) return;
        suppressClick = true;
        var col = columnAt(e.clientX, e.clientY);
        if (!col) return;
        var stage = col.getAttribute('data-stage');
        if (!stage) return;
        if (stage !== 'perdu' && !col.contains(d.card)) {
            var body = col.querySelector('.crm-col__body');
            if (body) {
                var addBtn = body.querySelector(':scope > button.btn-sm');
                if (addBtn) body.insertBefore(d.card, addBtn);
                else body.appendChild(d.card);
            }
        }
        var w = livewireWire(col);
        if (w) w.moveToStage(Number(d.id), stage);
    }

    document.addEventListener('pointerup', finish);
    document.addEventListener('pointercancel', finish);
    document.addEventListener('click', function (e) {
        if (!suppressClick) return;
        suppressClick = false;
        e.preventDefault();
        e.stopPropagation();
    }, true);
})();
</script>
@endonce

