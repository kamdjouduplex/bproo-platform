    @php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code');
    $hasAlerts = (($hasBatches ?? false) && (($expiredCount ?? 0) > 0 || ($expiringCount ?? 0) > 0))
        || (($hasStock ?? false) && count($lowStockItems ?? []) > 0)
        || (($hasInvoicing ?? false) && ($pendingInvoices ?? 0) > 0);
    $showOpsPanels = ($hasSales ?? false)
        || ($hasBatches ?? false)
        || ($hasStock ?? false)
        || ($hasInvoicing ?? false)
        || ($hasCaisse ?? false);
@endphp

<div class="dashboard dashboard--pharma">
    <header class="dashboard-hero">
        <div class="dashboard-hero__text">
            <p class="dashboard-hero__eyebrow">Officine · {{ now()->translatedFormat('l d F Y') }}</p>
            <h1 class="dashboard-greeting">Bonjour{{ $userName ? ', ' . $userName : '' }}</h1>
            <p class="dashboard-date">
                @if ($canViewFinance ?? true)
                    Aperçu de l’activité pharmacie aujourd’hui.
                @else
                    Accès rapide à vos outils collaborateur.
                @endif
            </p>
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

    <section class="dashboard-kpis dashboard-kpis--pharma">
        @if ($hasSales)
            <article class="dashboard-kpi dashboard-kpi--tint-teal">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="teal" icon="shopping-bag" />
                    <span class="dashboard-kpi__label">Ventes du jour</span>
                </div>
                <div class="dashboard-kpi__value">
                    {{ fmt_money($posSalesToday) }}
                    <span class="dashboard-kpi__currency">{{ $currency }}</span>
                </div>
                <div class="dashboard-kpi__meta">
                    {{ $posSalesCountToday }} vente{{ $posSalesCountToday !== 1 ? 's' : '' }}
                    @if ($posTrend !== null)
                        <span class="dashboard-kpi__trend {{ $posTrend >= 0 ? 'dashboard-kpi__trend--up' : 'dashboard-kpi__trend--down' }}">
                            {{ $posTrend >= 0 ? '+' : '' }}{{ $posTrend }}% vs hier
                        </span>
                    @endif
                </div>
            </article>

            <article class="dashboard-kpi dashboard-kpi--tint-green">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="green" icon="banknotes" />
                    <span class="dashboard-kpi__label">Bénéfice du jour</span>
                </div>
                <div class="dashboard-kpi__value">
                    {{ fmt_money($posBenefitToday) }}
                    <span class="dashboard-kpi__currency">{{ $currency }}</span>
                </div>
                <div class="dashboard-kpi__meta">
                    Ventes nettes − coût d’achat (retours inclus)
                    @if (($posBenefitToday ?? 0) < 0)
                        <span class="dashboard-kpi__trend dashboard-kpi__trend--down">Marge négative</span>
                    @endif
                </div>
            </article>
        @endif

        @if ($hasStock)
            <article class="dashboard-kpi dashboard-kpi--tint-blue">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="blue" icon="package" />
                    <span class="dashboard-kpi__label">Produits en stock</span>
                </div>
                <div class="dashboard-kpi__value">{{ fmt_num($itemsInStock) }}</div>
                <div class="dashboard-kpi__meta">Références avec quantité disponible</div>
            </article>
        @endif

        @if ($hasBatches)
            <article class="dashboard-kpi dashboard-kpi--tint-amber">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="amber" icon="alert" />
                    <span class="dashboard-kpi__label">Péremptions (90 j)</span>
                </div>
                <div class="dashboard-kpi__value">{{ $expiringCount }}</div>
                <div class="dashboard-kpi__meta">
                    @if ($expiredCount > 0)
                        <span class="dashboard-kpi__trend dashboard-kpi__trend--down">{{ $expiredCount }} lot(s) déjà périmé(s)</span>
                    @else
                        Lots à surveiller
                    @endif
                </div>
            </article>
        @endif

        @if ($hasPrescriptions)
            <article class="dashboard-kpi dashboard-kpi--tint-blue">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="blue" icon="document" />
                    <span class="dashboard-kpi__label">Ordonnances actives</span>
                </div>
                <div class="dashboard-kpi__value">{{ $activeRx }}</div>
                <div class="dashboard-kpi__meta">En cours de dispensation</div>
            </article>
        @endif

        @if ($caisse && ($hasCaisse ?? true))
            <article class="dashboard-kpi {{ $caisse['is_open'] ? 'dashboard-kpi--caisse-open' : 'dashboard-kpi--caisse' }}">
                <div class="dashboard-kpi__icon-row">
                    <x-ui-icon-box tone="{{ $caisse['is_open'] ? 'green' : 'rose' }}" icon="wallet" />
                    <span class="dashboard-kpi__label">Caisse</span>
                    <span class="dashboard-kpi__badge {{ $caisse['is_open'] ? 'dashboard-kpi__badge--open' : 'dashboard-kpi__badge--closed' }}">
                        {{ $caisse['is_open'] ? 'Ouverte' : 'Fermée' }}
                    </span>
                </div>
                <div class="dashboard-kpi__value">
                    {{ fmt_money($caisse['balance']) }}
                    <span class="dashboard-kpi__currency">{{ $currency }}</span>
                </div>
                <div class="dashboard-kpi__meta">
                    {{ $caisse['session_number'] ? 'Session '.$caisse['session_number'] : 'Aucune du jour' }}
                </div>
            </article>
        @endif
    </section>

    <div class="dashboard-pharma-main">
        @if ($hasSales && count($salesChart) > 0)
            <section class="dashboard-panel dashboard-panel--chart">
                <div class="dashboard-panel__head">
                    <h2 class="dashboard-panel__title">Évolution des ventes</h2>
                    <span class="dashboard-panel__hint">7 derniers jours · POS</span>
                </div>
                <div class="dashboard-panel__body">
                    <div class="dashboard-chart" role="img" aria-label="Graphique ventes 7 jours">
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

        @if ($showOpsPanels)
        <aside class="dashboard-panel dashboard-panel--alerts-col">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Alertes</h2>
            </div>
            <div class="dashboard-panel__body">
                @if (! $hasAlerts)
                    <p class="dashboard-empty">Aucune alerte pour le moment.</p>
                @else
                    <ul class="dashboard-alert-feed">
                        @if (($hasBatches ?? false) && $expiredCount > 0)
                            <li class="dashboard-alert-feed__item dashboard-alert-feed__item--danger">
                                <strong>Lots périmés</strong>
                                <span>{{ $expiredCount }} lot(s) encore en stock</span>
                                @if (\Illuminate\Support\Facades\Route::has('tenant.batches.index'))
                                    <a href="{{ route('tenant.batches.index', ['tenant' => $tenantCode]) }}">Voir les lots</a>
                                @endif
                            </li>
                        @endif
                        @if (($hasBatches ?? false) && $expiringCount > 0)
                            <li class="dashboard-alert-feed__item dashboard-alert-feed__item--warning">
                                <strong>Péremption proche</strong>
                                <span>{{ $expiringCount }} lot(s) dans les 90 jours</span>
                                @if (\Illuminate\Support\Facades\Route::has('tenant.batches.index'))
                                    <a href="{{ route('tenant.batches.index', ['tenant' => $tenantCode]) }}">Surveiller</a>
                                @endif
                            </li>
                        @endif
                        @if (($hasStock ?? false) && count($lowStockItems) > 0)
                            <li class="dashboard-alert-feed__item dashboard-alert-feed__item--warning">
                                <strong>Stock faible</strong>
                                <span>{{ count($lowStockItems) }} référence(s) sous le seuil</span>
                                @if (\Illuminate\Support\Facades\Route::has('tenant.stock.index'))
                                    <a href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">Voir le stock</a>
                                @endif
                            </li>
                        @endif
                        @if ($hasInvoicing && $pendingInvoices > 0)
                            <li class="dashboard-alert-feed__item dashboard-alert-feed__item--info">
                                <strong>Factures à encaisser</strong>
                                <span>{{ $pendingInvoices }} · {{ fmt_money($unpaidInvoicesTotal) }} {{ $currency }}</span>
                                @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.index'))
                                    <a href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}">Facturation</a>
                                @endif
                            </li>
                        @endif
                    </ul>

                    @if (($hasBatches ?? false) && count($expiringBatches) > 0)
                        <div class="dashboard-expiry-list">
                            <p class="dashboard-expiry-list__title">Prochaines péremptions</p>
                            @foreach ($expiringBatches as $batch)
                                <div class="dashboard-expiry-list__row">
                                    <div>
                                        <strong>{{ $batch['item_name'] }}</strong>
                                        <span>Lot {{ $batch['batch_number'] }}</span>
                                    </div>
                                    <span class="dashboard-expiry-list__days {{ $batch['days_left'] <= 30 ? 'is-urgent' : '' }}">
                                        {{ $batch['days_left'] }} j
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </aside>
        @endif
    </div>

    @if (($hasStock ?? false) || ($hasSales ?? false))
    <section class="dashboard-grid">
        @if ($hasStock ?? false)
        <div class="dashboard-panel {{ count($lowStockItems) > 0 ? 'dashboard-panel--alert' : '' }}">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Produits à stock faible</h2>
                @if (\Illuminate\Support\Facades\Route::has('tenant.stock.index'))
                    <a href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}" class="dashboard-panel__link">Stock</a>
                @endif
            </div>
            <div class="dashboard-panel__body">
                @if (count($lowStockItems) > 0)
                    <div class="table-scroll">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th class="dashboard-table__num">Stock</th>
                                    <th class="dashboard-table__num">Seuil</th>
                                    <th>Statut</th>
                                    @if ($hasPurchases)
                                        <th></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lowStockItems as $item)
                                    @php
                                        $critical = ($item['available'] ?? 0) <= 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                        </td>
                                        <td class="dashboard-table__num {{ $critical ? 'dashboard-stock-list__qty--out' : 'dashboard-stock-list__qty--low' }}">
                                            <strong>{{ fmt_num($item['available']) }}</strong>
                                        </td>
                                        <td class="dashboard-table__num">{{ fmt_num($item['reorder_point']) }}</td>
                                        <td>
                                            <span class="dashboard-status {{ $critical ? 'dashboard-status--critical' : 'dashboard-status--low' }}">
                                                {{ $critical ? 'Critique' : 'Faible' }}
                                            </span>
                                        </td>
                                        @if ($hasPurchases)
                                            <td class="dashboard-table__num">
                                                @if (\Illuminate\Support\Facades\Route::has('tenant.purchases.create'))
                                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.purchases.create', ['tenant' => $tenantCode]) }}">Commander</a>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="dashboard-empty">Aucun article sous le seuil de réapprovisionnement.</p>
                @endif
            </div>
        </div>
        @endif

        @if ($hasSales ?? false)
        <div class="dashboard-panel">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Ventes récentes</h2>
                @if (\Illuminate\Support\Facades\Route::has('tenant.sales.index'))
                    <a href="{{ route('tenant.sales.index', ['tenant' => $tenantCode]) }}" class="dashboard-panel__link">Tout voir</a>
                @endif
            </div>
            <div class="dashboard-panel__body">
                @if (count($recentSales) > 0)
                    <ul class="dashboard-activity">
                        @foreach ($recentSales as $sale)
                            <li class="dashboard-activity__item">
                                <div>
                                    @if (\Illuminate\Support\Facades\Route::has('tenant.sales.show'))
                                        <a href="{{ route('tenant.sales.show', ['sale' => $sale['id'], 'tenant' => $tenantCode]) }}" class="dashboard-table__link">
                                            <strong>{{ $sale['sale_number'] }}</strong>
                                        </a>
                                    @else
                                        <strong>{{ $sale['sale_number'] }}</strong>
                                    @endif
                                    <span>{{ \Carbon\Carbon::parse($sale['sale_date'])->format('d/m/Y') }}</span>
                                </div>
                                <strong class="dashboard-activity__amount">{{ fmt_money($sale['total']) }} {{ $currency }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="dashboard-empty">
                        Aucune vente pour le moment.
                        @if (\Illuminate\Support\Facades\Route::has('tenant.sales.create'))
                            <a href="{{ route('tenant.sales.create', ['tenant' => $tenantCode]) }}" class="dashboard-panel__link">Nouvelle vente</a>
                        @endif
                    </p>
                @endif
            </div>
        </div>
        @endif
    </section>
    @endif

    @if ($hasInvoicing && ($invoiceRevenueMonth > 0 || $pendingInvoices > 0))
        <section class="dashboard-kpis dashboard-kpis--secondary">
            <article class="dashboard-kpi">
                <span class="dashboard-kpi__label">CA facture · {{ $monthLabel }}</span>
                <div class="dashboard-kpi__value dashboard-kpi__value--sm">{{ fmt_money($invoiceRevenueMonth) }} <span class="dashboard-kpi__currency">{{ $currency }}</span></div>
            </article>
            <article class="dashboard-kpi">
                <span class="dashboard-kpi__label">Encaissé factures</span>
                <div class="dashboard-kpi__value dashboard-kpi__value--sm">{{ fmt_money($invoiceCollectedMonth) }} <span class="dashboard-kpi__currency">{{ $currency }}</span></div>
            </article>
            <article class="dashboard-kpi">
                <span class="dashboard-kpi__label">À encaisser</span>
                <div class="dashboard-kpi__value dashboard-kpi__value--sm">{{ $pendingInvoices }} · {{ fmt_money($unpaidInvoicesTotal) }} {{ $currency }}</div>
            </article>
        </section>
    @endif
</div>
