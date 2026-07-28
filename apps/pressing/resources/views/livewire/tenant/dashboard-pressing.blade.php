@php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code');
    $m = $pressingMetrics;
    $profile = $profile ?? ($m['profile'] ?? 'reception');
    $isDriver = $profile === 'driver';
    $isProduction = $profile === 'production';
    $showFinance = $m['show_finance'] ?? ! $isDriver && ! $isProduction;
    $showFullOps = $m['show_full_ops'] ?? ! $isDriver;
@endphp

<div class="dashboard">
    <header class="dashboard-hero">
        <div class="dashboard-hero__text">
            <p class="dashboard-hero__eyebrow">
                Pressing
                @if (!empty($profileLabel))
                    · {{ $profileLabel }}
                @endif
            </p>
            <h1 class="dashboard-greeting">Bonjour{{ $userName ? ', ' . $userName : '' }}</h1>
            <p class="dashboard-date">{{ now()->translatedFormat('l d F Y') }}</p>
            @if (!empty($agenceScopeLabel) || !empty($m['agence_scope_label']))
                <p class="dashboard-agence-scope" style="margin:8px 0 0;font-size:13px;color:#0f766e;font-weight:600;">
                    {{ __('Agence') }} : {{ $agenceScopeLabel ?? $m['agence_scope_label'] }}
                </p>
            @endif
        </div>
        @if (count($quickActions) > 0)
            <div class="dashboard-quick-actions">
                @foreach ($quickActions as $action)
                    <a href="{{ route($action['route'], ['tenant' => $tenantCode]) }}"
                       class="btn {{ ($action['style'] ?? '') === 'primary' ? 'btn-primary' : 'btn-secondary' }}">
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </header>

    {{-- ===== DRIVER DASHBOARD ===== --}}
    @if ($isDriver)
        @if (($m['waiting_deliveries'] ?? 0) > 0 || ($m['waiting_mine'] ?? 0) > 0)
            <section class="dashboard-alerts">
                <article class="dashboard-alert dashboard-alert--info">
                    <strong>{{ ($m['waiting_mine'] ?? $m['waiting_deliveries']) }} {{ __('livraison(s) à traiter') }}</strong>
                    <span>{{ __('Priorisez les tournées et remises en attente') }}</span>
                    @if (\Illuminate\Support\Facades\Route::has('tenant.pressing_deliveries.index'))
                        <a href="{{ route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]) }}" class="dashboard-alert__link">{{ __('Ouvrir les livraisons') }} →</a>
                    @endif
                </article>
            </section>
        @endif

        <section class="dashboard-kpis">
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('En attente (moi)') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['waiting_mine'] ?? 0 }}</div>
                <div class="dashboard-kpi__meta">{{ __('Assignées / à prendre') }}</div>
            </article>
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('En route') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['in_transit_mine'] ?? 0 }}</div>
                <div class="dashboard-kpi__meta">{{ __('Tournées en cours') }}</div>
            </article>
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('Livrées aujourd’hui') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['delivered_mine_today'] ?? 0 }}</div>
                <div class="dashboard-kpi__meta">{{ __('Mes remises du jour') }}</div>
            </article>
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('Domicile en attente') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['domicile_waiting'] ?? 0 }}</div>
                <div class="dashboard-kpi__meta">{{ __('Agence') }} : {{ $m['waiting_deliveries'] ?? 0 }} {{ __('total') }}</div>
            </article>
        </section>

        <section class="dashboard-panel" style="margin-top:16px;">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">{{ __('Mes livraisons à faire') }}</h2>
                @if (\Illuminate\Support\Facades\Route::has('tenant.pressing_deliveries.index'))
                    <a href="{{ route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]) }}" class="dashboard-alert__link">{{ __('Tout voir') }} →</a>
                @endif
            </div>
            <div class="dashboard-panel__body">
                @forelse ($waitingDeliveries as $delivery)
                    @php $order = $delivery->order; @endphp
                    <div style="display:flex;justify-content:space-between;gap:8px;padding:10px 0;border-bottom:1px solid #e2e8f0;">
                        <div>
                            <strong>{{ $order?->number }}</strong>
                            <div style="font-size:13px;color:#64748b;">
                                {{ $order?->client?->full_name }}
                                @if ($order?->client?->whatsapp ?: $order?->client?->phone)
                                    · {{ $order?->client?->whatsapp ?: $order?->client?->phone }}
                                @endif
                            </div>
                            @if ($delivery->address)
                                <div style="font-size:12px;color:#94a3b8;">{{ $delivery->address }}</div>
                            @endif
                        </div>
                        <div style="text-align:right;font-size:12px;">
                            <span class="badge {{ $delivery->type === 'agence' ? 'badge-info' : 'badge-neutral' }}">
                                {{ $delivery->type === 'agence' ? __('Agence') : __('Domicile') }}
                            </span>
                            <div style="margin-top:4px;color:#64748b;">{{ __($delivery->status === 'in_transit' ? 'En route' : 'En attente') }}</div>
                        </div>
                    </div>
                @empty
                    <p class="dashboard-empty">{{ __('Aucune livraison en attente pour vous.') }}</p>
                @endforelse
            </div>
        </section>

    {{-- ===== PRODUCTION DASHBOARD ===== --}}
    @elseif ($isProduction)
        @if (($m['overdue_orders'] ?? 0) > 0 || ($m['ready_orders'] ?? 0) > 0)
            <section class="dashboard-alerts">
                @if (($m['overdue_orders'] ?? 0) > 0)
                    <article class="dashboard-alert dashboard-alert--danger">
                        <strong>{{ $m['overdue_orders'] }} commande(s) en retard</strong>
                        <span>{{ __('À prioriser en atelier') }}</span>
                    </article>
                @endif
                @if (($m['ready_orders'] ?? 0) > 0)
                    <article class="dashboard-alert dashboard-alert--info">
                        <strong>{{ $m['ready_orders'] }} commande(s) prête(s)</strong>
                        <span>{{ __('Fin de production / emballage') }}</span>
                    </article>
                @endif
            </section>
        @endif

        <section class="dashboard-kpis">
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('En production') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['pending_orders'] }}</div>
                <div class="dashboard-kpi__meta">{{ __('Ouvertes + prêtes') }}</div>
            </article>
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('En retard') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['overdue_orders'] }}</div>
                <div class="dashboard-kpi__meta">{{ __('Délai dépassé') }}</div>
            </article>
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('Prêtes') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['ready_orders'] }}</div>
                <div class="dashboard-kpi__meta">{{ __('À emballer / remettre') }}</div>
            </article>
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('Reçues aujourd’hui') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['orders_today'] }}</div>
                <div class="dashboard-kpi__meta">{{ $m['items_received_today'] }} {{ __('article(s)') }}</div>
            </article>
        </section>

        <div class="dashboard-panels-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-top:16px;">
            <section class="dashboard-panel">
                <div class="dashboard-panel__head">
                    <h2 class="dashboard-panel__title">{{ __('Commandes récentes') }}</h2>
                </div>
                <div class="dashboard-panel__body">
                    @forelse ($recentOrders as $order)
                        <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid #e2e8f0;">
                            <div>
                                <strong>{{ $order->number }}</strong>
                                <div style="font-size:13px;color:#64748b;">{{ $order->client?->full_name }} · {{ $order->currentStage?->name ?? $order->status }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="dashboard-empty">{{ __('Aucune commande.') }}</p>
                    @endforelse
                </div>
            </section>
            <section class="dashboard-panel">
                <div class="dashboard-panel__head">
                    <h2 class="dashboard-panel__title">{{ __('En retard') }}</h2>
                </div>
                <div class="dashboard-panel__body">
                    @forelse ($overdueOrders as $order)
                        <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid #e2e8f0;">
                            <div>
                                <strong>{{ $order->number }}</strong>
                                <div style="font-size:13px;color:#64748b;">{{ $order->client?->full_name }}</div>
                            </div>
                            <div style="text-align:right;font-size:12px;color:#dc2626;">
                                {{ $order->due_at?->format('d/m H:i') }}
                            </div>
                        </div>
                    @empty
                        <p class="dashboard-empty">{{ __('Aucun retard.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

    {{-- ===== ADMIN / RECEPTION DASHBOARD ===== --}}
    @else
        @if (($m['overdue_orders'] ?? 0) > 0 || ($m['ready_orders'] ?? 0) > 0 || ($m['waiting_deliveries'] ?? 0) > 0)
            <section class="dashboard-alerts">
                @if (($m['ready_orders'] ?? 0) > 0)
                    <article class="dashboard-alert dashboard-alert--info">
                        <strong>{{ $m['ready_orders'] }} commande(s) prête(s)</strong>
                        <span>{{ __('En attente de retrait ou livraison') }}</span>
                        @if (\Illuminate\Support\Facades\Route::has('tenant.pressing_deliveries.index'))
                            <a href="{{ route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]) }}" class="dashboard-alert__link">{{ __('Livraisons') }} →</a>
                        @endif
                    </article>
                @endif
                @if (($m['overdue_orders'] ?? 0) > 0)
                    <article class="dashboard-alert dashboard-alert--danger">
                        <strong>{{ $m['overdue_orders'] }} commande(s) en retard</strong>
                        <span>{{ __('Délai dépassé — à traiter en priorité') }}</span>
                    </article>
                @endif
            </section>
        @endif

        <section class="dashboard-kpis">
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('Commandes du jour') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['orders_today'] }}</div>
                <div class="dashboard-kpi__meta">{{ $m['items_received_today'] }} {{ __('article(s) reçu(s)') }}</div>
            </article>
            <article class="dashboard-kpi">
                <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('Livrées aujourd’hui') }}</span></div>
                <div class="dashboard-kpi__value">{{ $m['delivered_today'] }}</div>
                <div class="dashboard-kpi__meta">{{ $m['pending_orders'] }} {{ __('en cours') }}</div>
            </article>
            @if ($showFinance)
                <article class="dashboard-kpi dashboard-kpi--sales-month">
                    <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('CA du jour') }}</span></div>
                    <div class="dashboard-kpi__value">{{ fmt_money($m['revenue_today']) }} <span class="dashboard-kpi__currency">{{ $currency }}</span></div>
                    <div class="dashboard-kpi__meta">{{ __('Paiements encaissés') }}</div>
                </article>
                <article class="dashboard-kpi dashboard-kpi--benefit">
                    <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('CA du mois') }}</span></div>
                    <div class="dashboard-kpi__value">{{ fmt_money($m['revenue_month']) }} <span class="dashboard-kpi__currency">{{ $currency }}</span></div>
                    <div class="dashboard-kpi__meta">{{ $monthLabel }}</div>
                </article>
                <article class="dashboard-kpi dashboard-kpi--invoices">
                    <div class="dashboard-kpi__head"><span class="dashboard-kpi__label">{{ __('Reste à encaisser') }}</span></div>
                    <div class="dashboard-kpi__value">{{ fmt_money($m['balance_due']) }} <span class="dashboard-kpi__currency">{{ $currency }}</span></div>
                    <div class="dashboard-kpi__meta">{{ __('Soldes ouverts') }}</div>
                </article>
            @endif
        </section>

        @if ($showFinance && count($salesChart) > 0)
            <section class="dashboard-panel dashboard-panel--chart">
                <div class="dashboard-panel__head">
                    <h2 class="dashboard-panel__title">{{ __('Encaissements — 7 derniers jours') }}</h2>
                </div>
                <div class="dashboard-panel__body">
                    <div class="dashboard-chart" role="img" aria-label="{{ __('Graphique encaissements 7 jours') }}">
                        @foreach ($salesChart as $day)
                            <div class="dashboard-chart__col {{ $day['is_today'] ? 'dashboard-chart__col--today' : '' }}">
                                <div class="dashboard-chart__bar-wrap">
                                    <div class="dashboard-chart__bar" style="height: {{ max(4, $day['bar_pct']) }}%;" title="{{ fmt_money($day['total']) }}"></div>
                                </div>
                                <span class="dashboard-chart__value">{{ $day['total'] > 0 ? fmt_money($day['total']) : '—' }}</span>
                                <span class="dashboard-chart__label">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($showFullOps)
            <div class="dashboard-panels-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-top:16px;">
                <section class="dashboard-panel">
                    <div class="dashboard-panel__head">
                        <h2 class="dashboard-panel__title">{{ __('Commandes récentes') }}</h2>
                    </div>
                    <div class="dashboard-panel__body">
                        @forelse ($recentOrders as $order)
                            <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid #e2e8f0;">
                                <div>
                                    <strong>{{ $order->number }}</strong>
                                    <div style="font-size:13px;color:#64748b;">{{ $order->client?->full_name }} · {{ $order->currentStage?->name ?? $order->status }}</div>
                                </div>
                                <div style="text-align:right;font-size:13px;">{{ fmt_money((float) $order->total) }}</div>
                            </div>
                        @empty
                            <p class="dashboard-empty">{{ __('Aucune commande.') }}</p>
                        @endforelse
                    </div>
                </section>

                <section class="dashboard-panel">
                    <div class="dashboard-panel__head">
                        <h2 class="dashboard-panel__title">{{ __('En retard') }}</h2>
                    </div>
                    <div class="dashboard-panel__body">
                        @forelse ($overdueOrders as $order)
                            <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid #e2e8f0;">
                                <div>
                                    <strong>{{ $order->number }}</strong>
                                    <div style="font-size:13px;color:#64748b;">{{ $order->client?->full_name }}</div>
                                </div>
                                <div style="text-align:right;font-size:12px;color:#dc2626;">
                                    {{ $order->due_at?->format('d/m H:i') }}
                                </div>
                            </div>
                        @empty
                            <p class="dashboard-empty">{{ __('Aucun retard.') }}</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif
    @endif
</div>
