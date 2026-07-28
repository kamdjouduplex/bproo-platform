@php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code');
@endphp

<div class="dashboard">
    <header class="dashboard-hero">
        <div class="dashboard-hero__text">
            <p class="dashboard-hero__eyebrow">Tableau de bord</p>
            <h1 class="dashboard-greeting">Bonjour{{ $userName ? ', ' . $userName : '' }}</h1>
            <p class="dashboard-date">{{ now()->translatedFormat('l d F Y') }}</p>
        </div>
        @if (count($quickActions) > 0)
            <div class="dashboard-quick-actions">
                @foreach ($quickActions as $action)
                    <a
                        href="{{ route($action['route'], ['tenant' => $tenantCode]) }}"
                        class="btn {{ ($action['style'] ?? '') === 'primary' ? 'btn-primary' : 'btn-secondary' }}"
                    >
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </header>

    @if (count($lowStockItems) > 0 || ($hasInvoicing && $pendingInvoices > 0))
        <section class="dashboard-alerts">
            @if ($hasInvoicing && $pendingInvoices > 0)
                <article class="dashboard-alert dashboard-alert--info">
                    <strong>{{ $pendingInvoices }} facture(s) en attente</strong>
                    <span>Solde à encaisser : {{ fmt_money($unpaidInvoicesTotal) }} {{ $currency }}</span>
                    @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.index'))
                        <a href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}" class="dashboard-alert__link">Voir les factures →</a>
                    @endif
                </article>
            @endif
            @if (count($lowStockItems) > 0)
                <article class="dashboard-alert dashboard-alert--danger">
                    <strong>{{ count($lowStockItems) }} article(s) en stock faible ou rupture</strong>
                    @if (\Illuminate\Support\Facades\Route::has('tenant.stock.index'))
                        <a href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}" class="dashboard-alert__link">Voir le stock →</a>
                    @endif
                </article>
            @endif
        </section>
    @endif

    <section class="dashboard-kpis">
        @if ($hasInvoicing)
            <article class="dashboard-kpi dashboard-kpi--tint-teal dashboard-kpi--sales-month">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="teal" icon="document" />
                    <span class="dashboard-kpi__label">CA facture du mois</span>
                </div>
                <div class="dashboard-kpi__value">{{ fmt_money($invoiceRevenueMonth) }} <span class="dashboard-kpi__currency">{{ $currency }}</span></div>
                <div class="dashboard-kpi__meta">{{ $invoiceCountMonth }} facture{{ $invoiceCountMonth !== 1 ? 's' : '' }} · HT net · {{ $monthLabel }}</div>
            </article>

            <article class="dashboard-kpi dashboard-kpi--tint-green dashboard-kpi--benefit">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="green" icon="wallet" />
                    <span class="dashboard-kpi__label">CA encaissé du mois</span>
                </div>
                <div class="dashboard-kpi__value">{{ fmt_money($invoiceCollectedMonth) }} <span class="dashboard-kpi__currency">{{ $currency }}</span></div>
                <div class="dashboard-kpi__meta">Encaissements HT sur factures · {{ $monthLabel }}</div>
            </article>

            <article class="dashboard-kpi dashboard-kpi--tint-blue dashboard-kpi--invoices">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="blue" icon="receipt" />
                    <span class="dashboard-kpi__label">Factures à encaisser</span>
                </div>
                <div class="dashboard-kpi__value">{{ $pendingInvoices }}</div>
                <div class="dashboard-kpi__meta">{{ fmt_money($unpaidInvoicesTotal) }} {{ $currency }} restant(s)</div>
            </article>
        @endif

        @if ($canViewReporting)
            <article class="dashboard-kpi dashboard-kpi--tint-amber dashboard-kpi--expenses">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="amber" icon="shopping-bag" />
                    <span class="dashboard-kpi__label">Dépenses {{ $monthLabel }}</span>
                </div>
                <div class="dashboard-kpi__value">{{ fmt_money($expensesMonth) }} <span class="dashboard-kpi__currency">{{ $currency }}</span></div>
            </article>
        @endif
    </section>

    @if ($hasSales && count($salesChart) > 0)
        <section class="dashboard-panel dashboard-panel--chart">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Activité des 7 derniers jours</h2>
                <span class="dashboard-panel__hint">Chiffre d'affaires quotidien</span>
            </div>
            <div class="dashboard-panel__body">
                <div class="dashboard-chart" role="img" aria-label="Graphique CA 7 jours">
                    @foreach ($salesChart as $day)
                        <div class="dashboard-chart__col {{ $day['is_today'] ? 'dashboard-chart__col--today' : '' }}">
                            <div class="dashboard-chart__bar-wrap">
                                <div
                                    class="dashboard-chart__bar"
                                    style="height: {{ max(4, $day['bar_pct']) }}%;"
                                    title="{{ fmt_money($day['total']) }} {{ $currency }}"
                                ></div>
                            </div>
                            <span class="dashboard-chart__value">{{ $day['total'] > 0 ? fmt_money($day['total']) : '—' }}</span>
                            <span class="dashboard-chart__label">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (count($storePerformance) > 1)
        <section class="dashboard-panel">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Performance par boutique</h2>
                <span class="dashboard-panel__hint">{{ $monthLabel }}</span>
            </div>
            <div class="dashboard-panel__body">
                @if (!$storeDimensionReady)
                    <p class="dashboard-empty dashboard-empty--inline">
                        Le multi-boutiques est actif ; le détail par boutique sera disponible lorsque les ventes sont liées aux magasins.
                    </p>
                @endif
                <div class="dashboard-store-bars">
                    @php $maxStoreCa = max(array_column($storePerformance, 'sales_total')) ?: 1; @endphp
                    @foreach ($storePerformance as $store)
                        <div class="dashboard-store-bar">
                            <div class="dashboard-store-bar__head">
                                <span class="dashboard-store-bar__name">{{ $store['store_name'] }}</span>
                                <span class="dashboard-store-bar__figures">
                                    {{ fmt_money($store['sales_total']) }} {{ $currency }}
                                    <small>({{ (int) $store['sales_count'] }} ventes)</small>
                                </span>
                            </div>
                            <div class="dashboard-store-bar__track">
                                <div
                                    class="dashboard-store-bar__fill"
                                    style="width: {{ round(($store['sales_total'] / $maxStoreCa) * 100, 1) }}%;"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="dashboard-grid">
        <div class="dashboard-panel">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Dernières factures</h2>
                @if ($hasInvoicing && \Illuminate\Support\Facades\Route::has('tenant.invoicing.index'))
                    <a href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}" class="dashboard-panel__link">Tout voir</a>
                @endif
            </div>
            <div class="dashboard-panel__body">
                @if (count($recentInvoices) > 0)
                    <div class="table-scroll">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>N° facture</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th class="dashboard-table__num">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentInvoices as $invoice)
                                    <tr>
                                        <td>
                                            @if ($hasInvoicing && \Illuminate\Support\Facades\Route::has('tenant.invoicing.edit'))
                                                <a href="{{ route('tenant.invoicing.edit', ['invoice' => $invoice['id'], 'tenant' => $tenantCode]) }}" class="dashboard-table__link">
                                                    <strong>{{ $invoice['invoice_number'] }}</strong>
                                                </a>
                                            @else
                                                <strong>{{ $invoice['invoice_number'] }}</strong>
                                            @endif
                                        </td>
                                        <td>{{ $invoice['client_name'] ?? '—' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($invoice['invoice_date'])->format('d/m/Y') }}</td>
                                        <td class="dashboard-table__num"><strong>{{ fmt_money($invoice['total']) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="dashboard-empty">
                        @if ($hasInvoicing)
                            Aucune facture pour le moment.
                            @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.create'))
                                <a href="{{ route('tenant.invoicing.create', ['tenant' => $tenantCode]) }}" class="dashboard-panel__link">Créer une facture</a>
                            @endif
                        @else
                            Activez le module Facturation pour suivre vos factures.
                        @endif
                    </p>
                @endif
            </div>
        </div>

        <div class="dashboard-panel {{ count($lowStockItems) > 0 ? 'dashboard-panel--alert' : '' }}">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Stock à surveiller</h2>
                @if (\Illuminate\Support\Facades\Route::has('tenant.stock.index'))
                    <a href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}" class="dashboard-panel__link">Stock</a>
                @endif
            </div>
            <div class="dashboard-panel__body">
                @if (count($lowStockItems) > 0)
                    <ul class="dashboard-stock-list">
                        @foreach ($lowStockItems as $item)
                            <li class="dashboard-stock-list__item">
                                <div class="dashboard-stock-list__info">
                                    <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" class="dashboard-stock-list__name" />
                                </div>
                                <span class="dashboard-stock-list__qty {{ $item['available'] <= 0 ? 'dashboard-stock-list__qty--out' : 'dashboard-stock-list__qty--low' }}">
                                    {{ fmt_num($item['available']) }} / {{ fmt_num($item['reorder_point']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="dashboard-empty">
                        @if ($hasStock)
                            Aucun article sous le seuil de réapprovisionnement.
                        @else
                            Activez le module Stock pour surveiller vos niveaux.
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </section>

    @if (count($moduleLinks) > 0)
        <section class="dashboard-modules">
            <h2 class="dashboard-modules__title">Accès rapide aux modules</h2>
            <div class="dashboard-modules__grid">
                @foreach ($moduleLinks as $link)
                    <a href="{{ route($link['route'], ['tenant' => $tenantCode]) }}" class="dashboard-module-card">
                        <span class="dashboard-module-card__label">{{ $link['label'] }}</span>
                        <span class="dashboard-module-card__arrow">→</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
