@php
    $trendHtml = function (?float $pct) {
        if ($pct === null) {
            return '';
        }
        $up = $pct >= 0;
        $cls = $up ? 'pr-trend--up' : 'pr-trend--down';
        $sign = $up ? '+' : '';

        return '<span class="pr-trend '.$cls.'">'.$sign.number_format($pct, 1, ',', ' ').'% '.__('vs période préc.').'</span>';
    };

    $icons = [
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
        'cash' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'orders' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
        'truck' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8m0 0l4 4m0-4l-4 4"/>',
        'due' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'ticket' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>',
    ];

    $donutColors = ['#6366f1', '#3fa796', '#0ea5e9', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6'];
    $donutSegments = [];
    $offset = 0;
    $circumference = 2 * M_PI * 42;
    if ($pipelineTotal > 0) {
        foreach ($pipeline as $i => $seg) {
            if ($seg['count'] <= 0) {
                continue;
            }
            $len = ($seg['count'] / $pipelineTotal) * $circumference;
            $donutSegments[] = [
                'color' => $donutColors[$i % count($donutColors)],
                'dash' => $len.' '.($circumference - $len),
                'offset' => -$offset,
                'name' => $seg['name'],
                'count' => $seg['count'],
            ];
            $offset += $len;
        }
    }
@endphp

<div class="page-body pr-page">
    <style>
        .pr-page { --pr-accent: #6366f1; --pr-teal: #3fa796; }
        .pr-tabs { display:inline-flex; gap:4px; padding:4px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:16px; flex-wrap:wrap; }
        .pr-tab { border:0; background:transparent; color:#64748b; font-size:13px; font-weight:600; padding:9px 16px; border-radius:9px; cursor:pointer; transition:all .15s ease; }
        .pr-tab:hover { color:#0f172a; }
        .pr-tab--active { background:var(--pr-accent); color:#fff; box-shadow:0 2px 8px rgba(99,102,241,.28); }

        .pr-toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; margin-bottom:18px; padding:14px 16px; background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .pr-toolbar__filters { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
        .pr-toolbar__meta { font-size:12px; color:#94a3b8; display:flex; align-items:center; gap:8px; }
        .pr-select, .pr-input { border:1px solid #e2e8f0; background:#f8fafc; border-radius:10px; padding:8px 12px; font-size:13px; color:#0f172a; }
        .pr-select:focus, .pr-input:focus { outline:none; border-color:#a5b4fc; box-shadow:0 0 0 3px rgba(99,102,241,.15); background:#fff; }

        .pr-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:18px; }
        .pr-kpi { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:16px; box-shadow:0 1px 2px rgba(15,23,42,.04); transition:box-shadow .2s, transform .15s; }
        .pr-kpi:hover { box-shadow:0 8px 24px rgba(15,23,42,.06); transform:translateY(-1px); }
        .pr-kpi__top { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
        .pr-kpi__icon { width:36px; height:36px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; }
        .pr-kpi__icon svg { width:18px; height:18px; }
        .pr-kpi__icon--indigo { background:#eef2ff; color:#4f46e5; }
        .pr-kpi__icon--teal { background:#ecfdf5; color:#0f766e; }
        .pr-kpi__icon--sky { background:#e0f2fe; color:#0369a1; }
        .pr-kpi__icon--green { background:#dcfce7; color:#15803d; }
        .pr-kpi__icon--amber { background:#fff7ed; color:#c2410c; }
        .pr-kpi__icon--violet { background:#f5f3ff; color:#6d28d9; }
        .pr-kpi__label { font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#64748b; }
        .pr-kpi__value { font-size:1.25rem; font-weight:750; color:#0f172a; letter-spacing:-.02em; line-height:1.2; }
        .pr-kpi__suffix { font-size:.75rem; font-weight:500; color:#94a3b8; margin-left:2px; }
        .pr-trend { display:inline-block; margin-top:8px; font-size:12px; font-weight:600; }
        .pr-trend--up { color:#16a34a; }
        .pr-trend--down { color:#dc2626; }

        .pr-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        @media (max-width:1100px) { .pr-grid { grid-template-columns:1fr; } }

        .pr-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04); overflow:hidden; display:flex; flex-direction:column; min-height:280px; }
        .pr-card__head { padding:14px 18px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; gap:8px; }
        .pr-card__title { margin:0; font-size:.95rem; font-weight:700; color:#0f172a; }
        .pr-card__body { padding:16px 18px; flex:1; }
        .pr-card__foot { padding:12px 18px; border-top:1px solid #f1f5f9; }
        .pr-card__link { font-size:13px; font-weight:600; color:var(--pr-accent); text-decoration:none; }
        .pr-card__link:hover { text-decoration:underline; }

        .pr-stat { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid #f8fafc; font-size:13px; }
        .pr-stat:last-child { border-bottom:0; }
        .pr-stat__label { color:#64748b; }
        .pr-stat__value { font-weight:700; color:#0f172a; text-align:right; }

        .pr-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; }
        .pr-badge--good { background:#dcfce7; color:#15803d; }
        .pr-badge--warn { background:#ffedd5; color:#c2410c; }
        .pr-badge--danger { background:#fee2e2; color:#b91c1c; }
        .pr-badge--neutral { background:#f1f5f9; color:#64748b; }

        .pr-chart { display:flex; align-items:flex-end; gap:6px; height:120px; margin-top:14px; }
        .pr-chart__col { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; height:100%; justify-content:flex-end; min-width:0; }
        .pr-chart__bar { width:100%; max-width:28px; border-radius:6px 6px 2px 2px; background:linear-gradient(180deg,#818cf8,#6366f1); min-height:4px; }
        .pr-chart__col--today .pr-chart__bar { background:linear-gradient(180deg,#5eead4,#3fa796); }
        .pr-chart__label { font-size:9px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }

        .pr-pipeline { display:flex; gap:16px; align-items:center; }
        .pr-pipeline__list { flex:1; min-width:0; }
        .pr-pipeline__row { display:flex; justify-content:space-between; gap:8px; font-size:13px; padding:6px 0; border-bottom:1px solid #f8fafc; }
        .pr-pipeline__dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
        .pr-donut { position:relative; width:120px; height:120px; flex-shrink:0; }
        .pr-donut__center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; pointer-events:none; }
        .pr-donut__value { font-size:1.1rem; font-weight:800; color:#0f172a; }
        .pr-donut__label { font-size:10px; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; }

        .pr-methods { display:grid; gap:8px; }
        .pr-method { display:grid; grid-template-columns:1fr auto; gap:4px 12px; align-items:center; }
        .pr-method__bar { grid-column:1 / -1; height:6px; background:#f1f5f9; border-radius:999px; overflow:hidden; }
        .pr-method__fill { height:100%; background:linear-gradient(90deg,#818cf8,#6366f1); border-radius:999px; }

        .pr-status { display:inline-flex; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; text-transform:capitalize; }
        .pr-status--open { background:#e0f2fe; color:#0369a1; }
        .pr-status--ready { background:#fef3c7; color:#b45309; }
        .pr-status--delivered { background:#dcfce7; color:#15803d; }
    </style>

    {{-- Tabs --}}
    <div class="pr-tabs" role="tablist">
        <button type="button" class="pr-tab {{ $tab === 'general' ? 'pr-tab--active' : '' }}" wire:click="selectTab('general')">{{ __('Général') }}</button>
        <button type="button" class="pr-tab {{ $tab === 'production' ? 'pr-tab--active' : '' }}" wire:click="selectTab('production')">{{ __('Production') }}</button>
        <button type="button" class="pr-tab {{ $tab === 'finances' ? 'pr-tab--active' : '' }}" wire:click="selectTab('finances')">{{ __('Finances') }}</button>
    </div>

    {{-- Filters --}}
    <section class="pr-toolbar">
        <div class="pr-toolbar__filters">
            <label class="field-label" style="margin:0;">{{ __('Période') }}</label>
            <select class="pr-select" wire:model.live="period">
                <option value="day">{{ __('Jour') }}</option>
                <option value="week">{{ __('Semaine') }}</option>
                <option value="month">{{ __('Mois') }}</option>
                <option value="year">{{ __('Année') }}</option>
            </select>
            <input class="pr-input" type="date" wire:model.live="from">
            <span style="color:#94a3b8;">→</span>
            <input class="pr-input" type="date" wire:model.live="to">
            <button type="button" class="btn btn-primary btn-sm" wire:click="exportCsv">{{ __('Exporter CSV') }}</button>
        </div>
        <div class="pr-toolbar__meta">
            <span>{{ __('Mis à jour') }} {{ $updatedAt }}</span>
        </div>
    </section>

    {{-- KPI row --}}
    <section class="pr-kpis" aria-label="{{ __('Indicateurs clés') }}">
        @foreach ($kpis as $kpi)
            <article class="pr-kpi">
                <div class="pr-kpi__top">
                    <span class="pr-kpi__label">{{ $kpi['label'] }}</span>
                    <span class="pr-kpi__icon pr-kpi__icon--{{ $kpi['tone'] }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $icons[$kpi['icon']] !!}</svg>
                    </span>
                </div>
                <div class="pr-kpi__value">
                    {{ $kpi['value'] }}
                    @if ($kpi['suffix'] !== '')
                        <span class="pr-kpi__suffix">{{ $kpi['suffix'] }}</span>
                    @endif
                </div>
                {!! $trendHtml($kpi['trend']) !!}
            </article>
        @endforeach
    </section>

    @if ($tab === 'general')
        <div class="pr-grid">
            {{-- Overview --}}
            <section class="pr-card">
                <div class="pr-card__head">
                    <h3 class="pr-card__title">{{ __('Vue d’ensemble') }}</h3>
                    <span style="font-size:12px;color:#94a3b8;">{{ $periodLabel }}</span>
                </div>
                <div class="pr-card__body">
                    <div class="pr-stat"><span class="pr-stat__label">{{ __('CA commandes') }}</span><span class="pr-stat__value">{{ number_format($ordersTotal, 0, ',', ' ') }}</span></div>
                    <div class="pr-stat"><span class="pr-stat__label">{{ __('Encaissements') }}</span><span class="pr-stat__value">{{ number_format($paymentsTotal, 0, ',', ' ') }}</span></div>
                    <div class="pr-stat"><span class="pr-stat__label">{{ __('Reste dû') }}</span><span class="pr-stat__value">{{ number_format($balanceDue, 0, ',', ' ') }}</span></div>
                    <div class="pr-stat"><span class="pr-stat__label">{{ __('Commandes') }}</span><span class="pr-stat__value">{{ $ordersCount }}</span></div>
                    <div class="pr-chart" role="img" aria-label="{{ __('Encaissements journaliers') }}">
                        @foreach ($chart as $day)
                            <div class="pr-chart__col {{ $day['is_today'] ? 'pr-chart__col--today' : '' }}">
                                <div class="pr-chart__bar" style="height:{{ max(4, $day['pct']) }}%;" title="{{ number_format($day['total'], 0, ',', ' ') }}"></div>
                                <span class="pr-chart__label">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="pr-card__foot">
                    <button type="button" class="pr-card__link" style="border:0;background:none;cursor:pointer;padding:0;" wire:click="selectTab('finances')">{{ __('Voir le détail finances') }} →</button>
                </div>
            </section>

            {{-- Health --}}
            <section class="pr-card">
                <div class="pr-card__head">
                    <h3 class="pr-card__title">{{ __('Santé & alertes') }}</h3>
                </div>
                <div class="pr-card__body">
                    @foreach ($health as $item)
                        <div class="pr-stat" style="align-items:center;">
                            <div>
                                <div class="pr-stat__label" style="color:#0f172a;font-weight:600;">{{ $item['label'] }}</div>
                                <div style="font-size:11px;color:#94a3b8;">{{ $item['hint'] }}</div>
                            </div>
                            <div style="text-align:right;">
                                <div class="pr-stat__value">{{ $item['value'] }}</div>
                                <span class="pr-badge pr-badge--{{ $item['status'] }}">
                                    {{ match ($item['status']) {
                                        'good' => __('Bonne'),
                                        'warn' => __('Attention'),
                                        'danger' => __('Critique'),
                                        default => __('—'),
                                    } }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="pr-card__foot">
                    @if (\Illuminate\Support\Facades\Route::has('tenant.pressing_orders.index'))
                        <a class="pr-card__link" href="{{ route('tenant.pressing_orders.index', ['tenant' => $tenantCode]) }}">{{ __('Voir les commandes') }} →</a>
                    @endif
                </div>
            </section>

            {{-- Pipeline --}}
            <section class="pr-card">
                <div class="pr-card__head">
                    <h3 class="pr-card__title">{{ __('Pipeline production') }}</h3>
                </div>
                <div class="pr-card__body">
                    <div class="pr-pipeline">
                        <div class="pr-pipeline__list">
                            @foreach ($pipeline as $i => $seg)
                                <div class="pr-pipeline__row">
                                    <span>
                                        <span class="pr-pipeline__dot" style="background:{{ $donutColors[$i % count($donutColors)] }};"></span>
                                        {{ $seg['name'] }}
                                    </span>
                                    <strong>{{ $seg['count'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                        <div class="pr-donut">
                            <svg viewBox="0 0 100 100" width="120" height="120">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#f1f5f9" stroke-width="12"/>
                                @forelse ($donutSegments as $seg)
                                    <circle cx="50" cy="50" r="42" fill="none"
                                            stroke="{{ $seg['color'] }}" stroke-width="12"
                                            stroke-dasharray="{{ $seg['dash'] }}"
                                            stroke-dashoffset="{{ $seg['offset'] }}"
                                            transform="rotate(-90 50 50)"/>
                                @empty
                                @endforelse
                            </svg>
                            <div class="pr-donut__center">
                                <div class="pr-donut__value">{{ $pipelineTotal }}</div>
                                <div class="pr-donut__label">{{ __('En cours') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pr-card__foot">
                    @if (\Illuminate\Support\Facades\Route::has('tenant.pressing_workflow.index'))
                        <a class="pr-card__link" href="{{ route('tenant.pressing_workflow.index', ['tenant' => $tenantCode]) }}">{{ __('Ouvrir le Kanban') }} →</a>
                    @endif
                </div>
            </section>
        </div>
    @elseif ($tab === 'production')
        <div class="pr-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
            <section class="pr-card">
                <div class="pr-card__head"><h3 class="pr-card__title">{{ __('Pipeline production') }}</h3></div>
                <div class="pr-card__body">
                    @foreach ($pipeline as $i => $seg)
                        @php $pct = $pipelineTotal > 0 ? round($seg['count'] / $pipelineTotal * 100) : 0; @endphp
                        <div style="margin-bottom:12px;">
                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                                <span>{{ $seg['name'] }}</span>
                                <strong>{{ $seg['count'] }}</strong>
                            </div>
                            <div class="pr-method__bar"><div class="pr-method__fill" style="width:{{ $pct }}%;background:{{ $donutColors[$i % count($donutColors)] }};"></div></div>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="pr-card">
                <div class="pr-card__head"><h3 class="pr-card__title">{{ __('Livraisons') }}</h3></div>
                <div class="pr-card__body">
                    <div class="pr-stat"><span class="pr-stat__label">{{ __('Effectuées (période)') }}</span><span class="pr-stat__value">{{ $deliveriesDone }}</span></div>
                    <div class="pr-stat"><span class="pr-stat__label">{{ __('Prêtes à remettre') }}</span><span class="pr-stat__value">{{ $ready }}</span></div>
                    <div class="pr-stat"><span class="pr-stat__label">{{ __('En production') }}</span><span class="pr-stat__value">{{ $open }}</span></div>
                    <div class="pr-stat"><span class="pr-stat__label">{{ __('En retard') }}</span><span class="pr-stat__value" style="color:{{ $overdue ? '#dc2626' : '#0f172a' }};">{{ $overdue }}</span></div>
                </div>
                <div class="pr-card__foot">
                    @if (\Illuminate\Support\Facades\Route::has('tenant.pressing_deliveries.index'))
                        <a class="pr-card__link" href="{{ route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]) }}">{{ __('Voir les livraisons') }} →</a>
                    @endif
                </div>
            </section>
        </div>
    @else
        <div class="pr-grid" style="grid-template-columns:1.2fr .8fr;">
            <section class="pr-card">
                <div class="pr-card__head">
                    <h3 class="pr-card__title">{{ __('Encaissements journaliers') }}</h3>
                </div>
                <div class="pr-card__body">
                    <div class="pr-chart" style="height:160px;" role="img">
                        @foreach ($chart as $day)
                            <div class="pr-chart__col {{ $day['is_today'] ? 'pr-chart__col--today' : '' }}">
                                <div class="pr-chart__bar" style="height:{{ max(4, $day['pct']) }}%;" title="{{ number_format($day['total'], 0, ',', ' ') }}"></div>
                                <span class="pr-chart__label">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
            <section class="pr-card">
                <div class="pr-card__head"><h3 class="pr-card__title">{{ __('Par mode de paiement') }}</h3></div>
                <div class="pr-card__body">
                    @php $payMax = max(1, (float) $paymentsByMethod->max('total')); @endphp
                    <div class="pr-methods">
                        @forelse ($paymentsByMethod as $row)
                            <div class="pr-method">
                                <span style="font-size:13px;color:#475569;">{{ __($row['method']) }} · {{ $row['count'] }}</span>
                                <strong style="font-size:13px;">{{ number_format($row['total'], 0, ',', ' ') }}</strong>
                                <div class="pr-method__bar"><div class="pr-method__fill" style="width:{{ round($row['total'] / $payMax * 100) }}%;"></div></div>
                            </div>
                        @empty
                            <p style="color:#94a3b8;font-size:13px;">{{ __('Aucun encaissement sur la période.') }}</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    @endif

    {{-- Orders table --}}
    <section class="card app-table-card" style="border-radius:14px;overflow:hidden;">
        <div class="client-list-head" style="padding:14px 16px;">
            <h2 class="client-list-head__title" style="font-size:1rem;">{{ __('Commandes de la période') }}</h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('N°') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Agence') }}</th>
                        <th>{{ __('Étape') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th style="text-align:right;">{{ __('Total') }}</th>
                        <th style="text-align:right;">{{ __('Reste') }}</th>
                        <th>{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td style="font-weight:600;">{{ $order->number }}</td>
                            <td>{{ $order->client?->full_name }}</td>
                            <td>{{ $order->agence?->name }}</td>
                            <td style="font-size:13px;color:#64748b;">{{ $order->currentStage?->name ?? '—' }}</td>
                            <td>
                                <span class="pr-status pr-status--{{ $order->status }}">
                                    {{ match ($order->status) {
                                        'open' => __('En cours'),
                                        'ready' => __('Prête'),
                                        'delivered' => __('Livrée'),
                                        default => $order->status,
                                    } }}
                                </span>
                            </td>
                            <td style="text-align:right;font-weight:600;">{{ number_format((float) $order->total, 0, ',', ' ') }}</td>
                            <td style="text-align:right;color:{{ (float) $order->balance > 0 ? '#dc2626' : '#16a34a' }};">
                                {{ number_format((float) $order->balance, 0, ',', ' ') }}
                            </td>
                            <td style="white-space:nowrap;font-size:13px;color:#64748b;">{{ $order->received_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">{{ __('Aucune donnée sur la période.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
