@php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code');
    $reportingGlossaryNote = 'Le CA total (performance clients) = CA Facture + CA Vente Direct.'
        . ($hasQuotations ?? false ? ' Les montants devis incluent tous les devis de la période (hors annulés).' : '');
    $trendFmt = function (?float $pct) {
        if ($pct === null) {
            return null;
        }
        $sign = $pct >= 0 ? '+' : '';
        $cls = $pct >= 0 ? 'color:#16a34a' : 'color:#dc2626';

        return '<span style="font-size:12px;' . $cls . ';">' . $sign . fmt_num($pct) . ' % vs période préc.</span>';
    };
    $tabs = [
        'general' => ['label' => 'Général', 'icon' => 'chart'],
        'finances' => ['label' => 'Finances', 'icon' => 'wallet'],
        'commercial' => ['label' => 'Commercial', 'icon' => 'document'],
        'ventes' => ['label' => 'Ventes', 'icon' => 'shopping-bag'],
        'clients' => ['label' => 'Clients', 'icon' => 'users'],
        'explorer' => ['label' => 'Explorateur', 'icon' => 'package'],
    ];
    if (! ($hasQuotations || $hasInvoicing)) {
        unset($tabs['commercial']);
    }
@endphp
<div class="reporting">
    <header class="reporting-hero">
        <div class="reporting-hero__text">
            <p class="reporting-hero__eyebrow">Analyses</p>
            <h1 class="reporting-hero__title">Rapports et analyses</h1>
            <p class="reporting-hero__subtitle">Pilotage de l’activité · {{ $periodLabel }}</p>
        </div>
        <button type="button" class="btn btn-secondary reporting-hero__refresh" wire:click="$refresh" title="Actualiser">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Actualiser
        </button>
    </header>

    <nav class="reporting-nav" aria-label="Sections reporting">
        <div class="reporting-nav__tabs" role="tablist">
            @foreach ($tabs as $tabKey => $tabMeta)
                <button type="button"
                        class="reporting-nav__tab {{ $activeTab === $tabKey ? 'reporting-nav__tab--active' : '' }}"
                        role="tab"
                        wire:click="selectTab('{{ $tabKey }}')"
                        aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}">
                    {{ $tabMeta['label'] }}
                </button>
            @endforeach
        </div>
    </nav>

    <section class="reporting-filters">
        <div class="reporting-filters__row">
            <label class="reporting-filters__label">Période</label>
            <div class="reporting-filters__controls">
                <select class="reporting-select" wire:model.live="period">
                    <option value="daily">Journalier</option>
                    <option value="weekly">Hebdomadaire</option>
                    <option value="monthly">Mensuel</option>
                    <option value="yearly">Annuel</option>
                    <option value="custom">Personnalisée</option>
                </select>
                @if ($period === 'daily')
                    <input type="date" class="reporting-input" wire:model.live="periodValue">
                @elseif ($period === 'monthly')
                    <input type="month" class="reporting-input" wire:model.live="periodValue">
                @elseif ($period === 'yearly')
                    <input type="number" class="reporting-input reporting-input--number" min="2020" max="2035" wire:model.live="periodValue" placeholder="Année">
                @elseif ($period === 'weekly')
                    <input type="week" class="reporting-input" wire:model.live="periodValue" title="Semaine">
                @elseif ($period === 'custom')
                    <input type="date" class="reporting-input" wire:model="dateFrom" title="Du">
                    <span class="reporting-filters__sep">→</span>
                    <input type="date" class="reporting-input" wire:model="dateTo" title="Au">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="applyPeriod">Appliquer</button>
                @endif
            </div>
            <span class="reporting-filters__period-label">
                <span class="reporting-filters__period-pill">{{ $periodLabel }}</span>
            </span>
        </div>
    </section>

    {{-- ========== GÉNÉRAL ========== --}}
    @if ($activeTab === 'general')
        <section class="reporting-cards" aria-label="Indicateurs clés">
            @foreach ($summaryCards as $card)
                @php
                    $tone = $card['tone'] ?? 'teal';
                    if (($card['status'] ?? null) === 'good') {
                        $tone = 'green';
                    } elseif (($card['status'] ?? null) === 'warn') {
                        $tone = 'amber';
                    } elseif (($card['status'] ?? null) === 'danger') {
                        $tone = 'rose';
                    }
                @endphp
                <article class="reporting-card reporting-card--tone-{{ $tone }} {{ ($card['status'] ?? null) ? 'reporting-card--' . $card['status'] : '' }}">
                    <div class="reporting-card__top">
                        <x-ui-icon-box :tone="$tone" :icon="$card['icon'] ?? 'chart'" />
                        <span class="reporting-card__label">{{ $card['label'] }}</span>
                    </div>
                    <span class="reporting-card__value">{{ $card['value'] }}</span>
                </article>
            @endforeach
        </section>

        <div class="reporting-overview-grid">
            <section class="reporting-panel">
                <div class="reporting-panel__head">
                    <x-ui-icon-box tone="teal" icon="chart" />
                    <div>
                        <h2 class="reporting-panel__title">Vue d’ensemble</h2>
                        <p class="reporting-panel__hint">{{ $periodLabel }}</p>
                    </div>
                </div>
                <div class="reporting-panel__body">
                    <div class="reporting-stat"><span class="reporting-stat__label">CA Vente Direct (net)</span><span class="reporting-stat__value">{{ fmt_money($financial['pos_sales']) }} {{ $currency }}</span></div>
                    {!! $trendFmt($kpiTrends['pos_sales'] ?? null) !!}
                    @if ($hasInvoicing)
                        <div class="reporting-stat" style="margin-top:10px;"><span class="reporting-stat__label">CA Facture (TTC)</span><span class="reporting-stat__value">{{ fmt_money($financial['invoices_issued_total']) }} {{ $currency }}</span></div>
                        <div class="reporting-stat" style="margin-top:10px;"><span class="reporting-stat__label">CA HT / TVA</span><span class="reporting-stat__value">{{ fmt_money($financial['ca_ht'] ?? 0) }} / {{ fmt_money($financial['tva_total'] ?? 0) }} {{ $currency }}</span></div>
                    @endif
                    <div class="reporting-stat" style="margin-top:10px;"><span class="reporting-stat__label">Encaissements</span><span class="reporting-stat__value">{{ fmt_money($financial['encaissements_total']) }} {{ $currency }}</span></div>
                    <div class="reporting-stat" style="margin-top:10px;"><span class="reporting-stat__label">Charges</span><span class="reporting-stat__value">{{ fmt_money($financial['charges_total']) }} {{ $currency }}</span></div>
                    <div class="reporting-stat reporting-stat--{{ $financial['benefit'] >= 0 ? 'good' : 'warn' }}" style="margin-top:10px;">
                        <span class="reporting-stat__label">Bénéfice brut estimé</span>
                        <span class="reporting-stat__value">{{ fmt_money($financial['benefit']) }} {{ $currency }}</span>
                    </div>
                    {!! $trendFmt($kpiTrends['benefit'] ?? null) !!}
                    <div class="reporting-quick-links">
                        <button type="button" class="reporting-link-btn" wire:click="selectTab('finances')">Voir le détail des finances →</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="openExplorer('ca_client')">Analyser un client</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="openExplorer('top_products')">Top marchandises</button>
                    </div>
                </div>
            </section>

            <section class="reporting-panel">
                <div class="reporting-panel__head">
                    <x-ui-icon-box tone="amber" icon="alert" />
                    <div>
                        <h2 class="reporting-panel__title">Santé & alertes</h2>
                        <p class="reporting-panel__hint">Indicateurs de risque</p>
                    </div>
                </div>
                <div class="reporting-panel__body">
                    @foreach ($healthIndicators as $ind)
                        <div class="reporting-stat reporting-stat--{{ $ind['status'] }}" style="margin-bottom:10px;">
                            <span class="reporting-stat__label">{{ $ind['label'] }}</span>
                            <span class="reporting-stat__value">
                                @if (is_numeric($ind['value']) && ! is_int($ind['value']) && abs((float) $ind['value']) >= 1)
                                    {{ fmt_money((float) $ind['value']) }} {{ $currency }}
                                @else
                                    {{ is_numeric($ind['value']) && ! str_contains((string) $ind['value'], '%') ? $ind['value'] : $ind['value'] }}
                                    @if ($ind['desc'])
                                        <span class="reporting-status-chip reporting-status-chip--{{ $ind['status'] }}">{{ $ind['desc'] }}</span>
                                    @endif
                                @endif
                            </span>
                        </div>
                    @endforeach
                    @if (count($lowStock) > 0 || count($outOfStock) > 0)
                        <div class="reporting-quick-links">
                            @if (count($lowStock) > 0)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="openExplorer('stock_low')">Stock faible ({{ count($lowStock) }})</button>
                            @endif
                            @if (count($outOfStock) > 0)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="openExplorer('stock_out')">Ruptures ({{ count($outOfStock) }})</button>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            @if ($hasQuotations || $hasInvoicing)
                <section class="reporting-panel">
                    <div class="reporting-panel__head">
                        <x-ui-icon-box tone="blue" icon="document" />
                        <div>
                            <h2 class="reporting-panel__title">Pipeline commercial</h2>
                            <p class="reporting-panel__hint">Devis & facturation</p>
                        </div>
                    </div>
                    <div class="reporting-panel__body">
                        @if ($hasQuotations)
                            <div class="reporting-stat"><span class="reporting-stat__label">Devis</span><span class="reporting-stat__value">{{ $quotationsSummary['count'] }} · {{ fmt_money($quotationsSummary['total']) }} {{ $currency }}</span></div>
                            <div class="reporting-stat" style="margin-top:8px;"><span class="reporting-stat__label">Acceptés</span><span class="reporting-stat__value">{{ $quotationsSummary['accepted_count'] }}</span></div>
                        @endif
                        @if ($hasInvoicing)
                            <div class="reporting-stat" style="margin-top:10px;"><span class="reporting-stat__label">Factures émises</span><span class="reporting-stat__value">{{ $invoicesSummary['issued_count'] }} · {{ fmt_money($invoicesSummary['issued_total']) }} {{ $currency }}</span></div>
                            <div class="reporting-stat reporting-stat--warn" style="margin-top:8px;"><span class="reporting-stat__label">Reste à encaisser</span><span class="reporting-stat__value">{{ fmt_money($invoicesSummary['outstanding_balance']) }} {{ $currency }}</span></div>
                        @endif
                        <div class="reporting-quick-links">
                            <button type="button" class="reporting-link-btn" wire:click="selectTab('commercial')">Voir le détail commercial →</button>
                        </div>
                    </div>
                </section>
            @endif
        </div>

        @if (count($chartDaily) > 0)
            <section class="reporting-chart" aria-label="CA et bénéfice par jour">
                <h2 class="reporting-chart__title">CA et Bénéfice par jour</h2>
                <div class="reporting-chart__wrap">
                    <canvas id="reporting-daily-chart" class="reporting-chart__canvas" wire:ignore></canvas>
                </div>
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" wire:ignore></script>
                <script>
                    (function() {
                        if (window._reportingChart) { window._reportingChart.destroy(); window._reportingChart = null; }
                        const ctx = document.getElementById('reporting-daily-chart');
                        if (!ctx) return;
                        const data = @json($chartDaily);
                        const labels = data.map(d => d.label);
                        const salesData = data.map(d => d.sales);
                        const benefitData = data.map(d => d.benefit);
                        const maxSales = Math.max(...salesData);
                        const minSales = Math.min(...salesData);
                        const maxIdx = salesData.indexOf(maxSales);
                        const minIdx = salesData.indexOf(minSales);
                        const maxBenefit = Math.max(...benefitData);
                        const minBenefit = Math.min(...benefitData);
                        const maxBenefitIdx = benefitData.indexOf(maxBenefit);
                        const minBenefitIdx = benefitData.indexOf(minBenefit);
                        window._reportingChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [
                                    {
                                        label: 'CA',
                                        data: salesData,
                                        backgroundColor: salesData.map((v, i) => i === maxIdx ? '#2f8578' : i === minIdx ? '#9fd5cb' : '#3fa796'),
                                        borderColor: '#2f8578', borderWidth: 1.5, order: 2
                                    },
                                    {
                                        label: 'Bénéfice',
                                        data: benefitData,
                                        backgroundColor: benefitData.map((v, i) => v >= 0 ? (i === maxBenefitIdx ? '#047857' : '#10b981') : (i === minBenefitIdx ? '#b91c1c' : '#ef4444')),
                                        borderColor: benefitData.map(v => v >= 0 ? '#065f46' : '#991b1b'),
                                        borderWidth: 1.5, order: 1
                                    }
                                ]
                            },
                            options: {
                                responsive: true, maintainAspectRatio: true, aspectRatio: 2.2,
                                plugins: {
                                    legend: { position: 'top' },
                                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + c.raw.toLocaleString('fr-FR') + ' ' + @json($currency) } }
                                },
                                scales: {
                                    x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 11 } } },
                                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' }, ticks: { callback: (v) => typeof v === 'number' ? v.toLocaleString('fr-FR') : v } }
                                }
                            }
                        });
                    })();
                </script>
            </section>
        @endif

        @if (count($storePerformance) > 0)
            <section class="reporting-panel" style="margin-top:16px;">
                <h2 class="reporting-panel__title">Performance par boutique</h2>
                <div class="reporting-panel__body">
                    <div class="table-scroll">
                        <table class="reporting-table">
                            <thead><tr><th>Boutique</th><th class="reporting-table__num">Ventes</th><th class="reporting-table__num">CA</th></tr></thead>
                            <tbody>
                                @foreach ($storePerformance as $store)
                                    <tr>
                                        <td>{{ $store['store_name'] }}</td>
                                        <td class="reporting-table__num">{{ $store['sales_count'] }}</td>
                                        <td class="reporting-table__num"><strong>{{ fmt_money($store['sales_total']) }} {{ $currency }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        @if (($debtsSummary['receivables_total'] ?? 0) != 0 || count($topByRevenue) > 0)
            <div class="reporting-overview-grid" style="margin-top:16px;">
                @if (($debtsSummary['receivables_total'] ?? 0) != 0 || ($debtsSummary['overdue_count'] ?? 0) > 0)
                    <section class="reporting-panel reporting-panel--compact">
                        <h2 class="reporting-panel__title">Créances</h2>
                        <div class="reporting-panel__body">
                            <div class="reporting-stat"><span class="reporting-stat__label">En cours</span><span class="reporting-stat__value">{{ fmt_money($debtsSummary['receivables_total'] ?? 0) }} {{ $currency }}</span></div>
                            <div class="reporting-stat" style="margin-top:8px;"><span class="reporting-stat__label">Encaissées (période)</span><span class="reporting-stat__value">{{ fmt_money($debtsSummary['collected_in_period'] ?? 0) }} {{ $currency }}</span></div>
                            @if (($debtsSummary['overdue_count'] ?? 0) > 0)
                                <div class="reporting-stat reporting-stat--warn" style="margin-top:8px;"><span class="reporting-stat__label">En retard ({{ $debtsSummary['overdue_count'] }})</span><span class="reporting-stat__value">{{ fmt_money($debtsSummary['overdue_total'] ?? 0) }} {{ $currency }}</span></div>
                            @endif
                        </div>
                    </section>
                @endif
                @if (count($topByRevenue) > 0)
                    <section class="reporting-panel reporting-panel--compact">
                        <h2 class="reporting-panel__title">Top produits (aperçu)</h2>
                        <div class="reporting-panel__body">
                            <ul class="reporting-list">
                                @foreach (array_slice($topByRevenue, 0, 5) as $row)
                                    <li class="reporting-list__item">
                                        <x-item-label :reference="$row['item_sku'] ?? null" :name="$row['item_name'] ?? null" class="reporting-list__name" />
                                        <span class="reporting-list__meta">{{ fmt_money($row['revenue']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="reporting-quick-links">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="selectTab('ventes')">Voir ventes & tops</button>
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        @endif
    @endif

    {{-- ========== FINANCES ========== --}}
    @if ($activeTab === 'finances')
        <section class="reporting-panel">
            <h2 class="reporting-panel__title">Détail financier — {{ $periodLabel }}</h2>
            <div class="reporting-panel__body">
                <div class="table-scroll" style="margin-bottom:20px;">
                    <table class="reporting-table">
                        <thead><tr><th>Poste</th><th class="reporting-table__num">Montant</th></tr></thead>
                        <tbody>
                            <tr><td>CA Vente Direct</td><td class="reporting-table__num"><strong>{{ fmt_money($financial['pos_sales']) }}</strong></td></tr>
                            <tr><td>Coût des ventes</td><td class="reporting-table__num">−{{ fmt_money($financial['cogs']) }}</td></tr>
                            <tr><td>Marge brute Vente Direct</td><td class="reporting-table__num">{{ fmt_money($financial['gross_margin']) }}</td></tr>
                            @if ($hasInvoicing)
                                <tr><td>CA Facture (TTC)</td><td class="reporting-table__num">{{ fmt_money($financial['invoices_issued_total']) }}</td></tr>
                                <tr><td>CA HT</td><td class="reporting-table__num">{{ fmt_money($financial['ca_ht'] ?? 0) }}</td></tr>
                                <tr><td><strong>TVA période</strong></td><td class="reporting-table__num"><strong>{{ fmt_money($financial['tva_total'] ?? 0) }}</strong></td></tr>
                                @if (($financial['other_taxes_total'] ?? 0) > 0)
                                    <tr><td>Autres taxes</td><td class="reporting-table__num">{{ fmt_money($financial['other_taxes_total']) }}</td></tr>
                                @endif
                                <tr><td>Encaissements factures</td><td class="reporting-table__num">{{ fmt_money($financial['invoice_payments']) }}</td></tr>
                            @endif
                            <tr><td>Dépenses</td><td class="reporting-table__num">−{{ fmt_money($financial['expenses']) }}</td></tr>
                            <tr><td>Pertes stock</td><td class="reporting-table__num">−{{ fmt_money($financial['losses']) }}</td></tr>
                            @if ($financial['purchases'] > 0)
                                <tr><td>Achats reçus</td><td class="reporting-table__num">−{{ fmt_money($financial['purchases']) }}</td></tr>
                            @endif
                            @if ($financial['payroll'] > 0)
                                <tr><td>Masse salariale</td><td class="reporting-table__num">−{{ fmt_money($financial['payroll']) }}</td></tr>
                            @endif
                            <tr style="background:#f8fafc;"><td><strong>Bénéfice brut estimé</strong></td><td class="reporting-table__num"><strong>{{ fmt_money($financial['benefit']) }}</strong></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="reporting-split">
                    <div>
                        <h3 class="reporting-panel__sub">Dépenses par catégorie</h3>
                        @if (count($expensesByCategory) > 0)
                            <div class="table-scroll">
                                <table class="reporting-table">
                                    <thead><tr><th>Catégorie</th><th class="reporting-table__num">Total</th></tr></thead>
                                    <tbody>
                                        @foreach ($expensesByCategory as $row)
                                            <tr><td>{{ $row['category_name'] }}</td><td class="reporting-table__num">{{ fmt_money($row['total']) }} {{ $currency }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="reporting-empty">Aucune dépense.</p>
                        @endif
                    </div>
                    <div>
                        <h3 class="reporting-panel__sub">Pertes par motif</h3>
                        @if (count($lossesByReason) > 0)
                            <div class="table-scroll">
                                <table class="reporting-table">
                                    <thead><tr><th>Motif</th><th class="reporting-table__num">Qté</th><th class="reporting-table__num">Valeur</th></tr></thead>
                                    <tbody>
                                        @foreach ($lossesByReason as $row)
                                            <tr>
                                                <td>{{ $row['reason_name'] }}</td>
                                                <td class="reporting-table__num">{{ fmt_num($row['total_qty']) }}</td>
                                                <td class="reporting-table__num">{{ fmt_money($row['total_value']) }} {{ $currency }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="reporting-empty">Aucune perte.</p>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ========== COMMERCIAL ========== --}}
    @if ($activeTab === 'commercial')
        <div class="reporting-split">
            @if ($hasQuotations)
                <section class="reporting-panel">
                    <h2 class="reporting-panel__title">Devis</h2>
                    <div class="reporting-panel__body">
                        <div class="reporting-stats reporting-stats--inline" style="margin-bottom:12px;">
                            <div class="reporting-stat"><span class="reporting-stat__label">Total</span><span class="reporting-stat__value">{{ $quotationsSummary['count'] }}</span></div>
                            <div class="reporting-stat"><span class="reporting-stat__label">Montant</span><span class="reporting-stat__value">{{ fmt_money($quotationsSummary['total']) }}</span></div>
                            <div class="reporting-stat"><span class="reporting-stat__label">Acceptés</span><span class="reporting-stat__value">{{ $quotationsSummary['accepted_count'] }}</span></div>
                        </div>
                        @if (count($quotationsSummary['by_status'] ?? []) > 0)
                            <div class="table-scroll">
                                <table class="reporting-table">
                                    <thead><tr><th>Statut</th><th class="reporting-table__num">Nb</th><th class="reporting-table__num">Montant</th></tr></thead>
                                    <tbody>
                                        @foreach ($quotationsSummary['by_status'] as $status => $row)
                                            <tr>
                                                <td>{{ \InovCom\Reporting\Services\ReportingService::quotationStatusLabel($status) }}</td>
                                                <td class="reporting-table__num">{{ $row['count'] }}</td>
                                                <td class="reporting-table__num">{{ fmt_money($row['total']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="reporting-empty">Aucun devis.</p>
                        @endif
                    </div>
                </section>
            @endif

            @if ($hasInvoicing)
                <section class="reporting-panel">
                    <h2 class="reporting-panel__title">Factures & encaissements</h2>
                    <div class="reporting-panel__body">
                        <div class="reporting-stats reporting-stats--inline" style="margin-bottom:12px;">
                            <div class="reporting-stat"><span class="reporting-stat__label">CA Facture</span><span class="reporting-stat__value">{{ fmt_money($invoicesSummary['issued_total']) }}</span></div>
                            <div class="reporting-stat"><span class="reporting-stat__label">Payées</span><span class="reporting-stat__value">{{ $invoicesSummary['paid_count'] }}</span></div>
                            <div class="reporting-stat reporting-stat--warn"><span class="reporting-stat__label">Solde ouvert</span><span class="reporting-stat__value">{{ fmt_money($invoicesSummary['outstanding_balance']) }}</span></div>
                        </div>
                        @if (count($invoicesSummary['by_status'] ?? []) > 0)
                            <h3 class="reporting-panel__sub">Par statut</h3>
                            <div class="table-scroll" style="margin-bottom:16px;">
                                <table class="reporting-table">
                                    <thead><tr><th>Statut</th><th class="reporting-table__num">Nb</th><th class="reporting-table__num">TTC</th></tr></thead>
                                    <tbody>
                                        @foreach ($invoicesSummary['by_status'] as $status => $row)
                                            <tr>
                                                <td>{{ \InovCom\Reporting\Services\ReportingService::invoiceStatusLabel($status) }}</td>
                                                <td class="reporting-table__num">{{ $row['count'] }}</td>
                                                <td class="reporting-table__num">{{ fmt_money($row['total']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if ($hasInvoicePayments && count($invoicePaymentsByMethod) > 0)
                            <h3 class="reporting-panel__sub">Encaissements par mode</h3>
                            <div class="table-scroll" style="margin-bottom:16px;">
                                <table class="reporting-table">
                                    <thead><tr><th>Mode</th><th class="reporting-table__num">Ops</th><th class="reporting-table__num">Montant</th></tr></thead>
                                    <tbody>
                                        @foreach ($invoicePaymentsByMethod as $row)
                                            <tr>
                                                <td>{{ $row['method_label'] }}</td>
                                                <td class="reporting-table__num">{{ $row['count'] }}</td>
                                                <td class="reporting-table__num"><strong>{{ fmt_money($row['total']) }}</strong></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if (count($outstandingInvoices) > 0)
                            <h3 class="reporting-panel__sub">À encaisser (top)</h3>
                            <div class="table-scroll">
                                <table class="reporting-table">
                                    <thead><tr><th>N°</th><th>Client</th><th>Échéance</th><th class="reporting-table__num">Solde</th></tr></thead>
                                    <tbody>
                                        @foreach ($outstandingInvoices as $inv)
                                            <tr>
                                                <td>{{ $inv['invoice_number'] }}</td>
                                                <td>{{ $inv['client_name'] }}</td>
                                                <td>{{ $inv['due_date'] ? \Carbon\Carbon::parse($inv['due_date'])->format('d/m/Y') : '—' }}</td>
                                                <td class="reporting-table__num"><strong>{{ fmt_money($inv['balance']) }}</strong></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        <div class="reporting-quick-links">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="openExplorer('factures')">Explorer les factures</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="openExplorer('ca_ht_tva')">CA HT / TVA</button>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    @endif

    {{-- ========== VENTES ========== --}}
    @if ($activeTab === 'ventes')
        <section class="reporting-panel" style="margin-bottom:16px;">
            <h2 class="reporting-panel__title">Vente Direct — {{ $periodLabel }}</h2>
            <div class="reporting-panel__body">
                <div class="reporting-stats reporting-stats--inline">
                    <div class="reporting-stat"><span class="reporting-stat__label">CA net</span><span class="reporting-stat__value">{{ fmt_money($financial['pos_sales']) }} {{ $currency }}</span></div>
                    <div class="reporting-stat"><span class="reporting-stat__label">Nb ventes</span><span class="reporting-stat__value">{{ $salesCount }}</span></div>
                    <div class="reporting-stat"><span class="reporting-stat__label">Panier moyen</span><span class="reporting-stat__value">{{ fmt_money($avgSale) }} {{ $currency }}</span></div>
                    @if ($distinctClients > 0)
                        <div class="reporting-stat"><span class="reporting-stat__label">Clients</span><span class="reporting-stat__value">{{ $distinctClients }}</span></div>
                    @endif
                </div>
                @if (count($topSales) > 0)
                    <h3 class="reporting-panel__sub">Plus grosses ventes</h3>
                    <div class="table-scroll">
                        <table class="reporting-table">
                            <thead><tr><th>Date</th><th>Client</th><th class="reporting-table__num">Montant</th></tr></thead>
                            <tbody>
                                @foreach ($topSales as $row)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($row['sale_date'])->translatedFormat('d/m/Y') }}</td>
                                        <td>{{ $row['client_name'] ?? '–' }}</td>
                                        <td class="reporting-table__num"><strong>{{ fmt_money($row['total']) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="reporting-muted" style="margin-top:12px;">Aucune vente sur la période.</p>
                @endif
            </div>
        </section>

        <div class="reporting-split">
            <section class="reporting-panel">
                <h2 class="reporting-panel__title">Top 10 par CA</h2>
                <div class="reporting-panel__body">
                    @if (count($topByRevenue) > 0)
                        <div class="table-scroll">
                            <table class="reporting-table">
                                <thead><tr><th>Article</th><th class="reporting-table__num">Qté</th><th class="reporting-table__num">CA</th></tr></thead>
                                <tbody>
                                    @foreach ($topByRevenue as $row)
                                        <tr>
                                            <td><x-item-label :reference="$row['item_sku'] ?? null" :name="$row['item_name'] ?? null" /></td>
                                            <td class="reporting-table__num">{{ fmt_num($row['quantity']) }}</td>
                                            <td class="reporting-table__num"><strong>{{ fmt_money($row['revenue']) }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="reporting-empty">Aucune donnée.</p>
                    @endif
                </div>
            </section>
            <section class="reporting-panel">
                <h2 class="reporting-panel__title">Top 10 par quantité</h2>
                <div class="reporting-panel__body">
                    @if (count($topByQuantity) > 0)
                        <div class="table-scroll">
                            <table class="reporting-table">
                                <thead><tr><th>Article</th><th class="reporting-table__num">Qté</th><th class="reporting-table__num">CA</th></tr></thead>
                                <tbody>
                                    @foreach ($topByQuantity as $row)
                                        <tr>
                                            <td><x-item-label :reference="$row['item_sku'] ?? null" :name="$row['item_name'] ?? null" /></td>
                                            <td class="reporting-table__num">{{ fmt_num($row['quantity']) }}</td>
                                            <td class="reporting-table__num"><strong>{{ fmt_money($row['revenue']) }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="reporting-empty">Aucune donnée.</p>
                    @endif
                </div>
            </section>
        </div>
        <div class="reporting-quick-links" style="margin-top:12px;">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="openExplorer('ventes_direct')">Explorer toutes les ventes</button>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="openExplorer('top_products')">Export top CA</button>
        </div>
    @endif

    {{-- ========== CLIENTS ========== --}}
    @if ($activeTab === 'clients')
        <section class="reporting-panel">
            <h2 class="reporting-panel__title">Performance clients — {{ $periodLabel }}</h2>
            <div class="reporting-panel__body">
                @if (count($clientPerformance) > 0)
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px;">
                        <div class="reporting-stat"><span class="reporting-stat__label">Clients actifs</span><span class="reporting-stat__value">{{ $clientPerformanceTotals['client_count'] }}</span></div>
                        @if ($hasInvoicing)
                            <div class="reporting-stat"><span class="reporting-stat__label">CA Facture</span><span class="reporting-stat__value">{{ fmt_money($clientPerformanceTotals['invoice_revenue']) }}</span></div>
                        @endif
                        <div class="reporting-stat"><span class="reporting-stat__label">CA Vente Direct</span><span class="reporting-stat__value">{{ fmt_money($clientPerformanceTotals['pos_revenue']) }}</span></div>
                        <div class="reporting-stat"><span class="reporting-stat__label">CA total</span><span class="reporting-stat__value"><strong>{{ fmt_money($clientPerformanceTotals['total_revenue']) }}</strong></span></div>
                    </div>
                    <div class="table-scroll">
                        <table class="reporting-table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    @if ($hasInvoicing)<th class="reporting-table__num">CA Facture</th><th class="reporting-table__num">Factures</th>@endif
                                    <th class="reporting-table__num">CA Vente Direct</th>
                                    <th class="reporting-table__num">Ventes</th>
                                    <th class="reporting-table__num">CA total</th>
                                    <th class="reporting-table__num">Part</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($clientPerformance as $row)
                                    <tr>
                                        <td>{{ $row['client_name'] }}</td>
                                        @if ($hasInvoicing)
                                            <td class="reporting-table__num">{{ fmt_money($row['invoice_revenue']) }}</td>
                                            <td class="reporting-table__num">{{ $row['invoice_count'] }}</td>
                                        @endif
                                        <td class="reporting-table__num">{{ fmt_money($row['pos_revenue']) }}</td>
                                        <td class="reporting-table__num">{{ $row['pos_sale_count'] }}</td>
                                        <td class="reporting-table__num"><strong>{{ fmt_money($row['total_revenue']) }}</strong></td>
                                        <td class="reporting-table__num">{{ fmt_num($row['revenue_share_pct'], 2) }} %</td>
                                        <td>
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                    wire:click="openExplorerForClient({{ $row['client_id'] }}, 'ca_client')">
                                                Explorer
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="reporting-empty">Aucune activité client sur la période.</p>
                @endif
            </div>
        </section>
    @endif

    {{-- ========== EXPLORATEUR ========== --}}
    @if ($activeTab === 'explorer')
        <section class="reporting-panel reporting-explorer">
            <h2 class="reporting-panel__title">Explorateur stratégique — {{ $periodLabel }}</h2>
            <div class="reporting-panel__body">
                <p class="reporting-muted" style="margin-bottom:14px;">
                    Choisissez un scénario, croisez avec un client si besoin, puis exportez en Excel pour vos décisions.
                </p>

                <div class="reporting-explorer__presets" role="group" aria-label="Scénarios rapides">
                    @foreach ([
                        'ca_client' => ['CA client', 'Chiffre d’affaires par entreprise', 'users', 'blue'],
                        'top_products' => ['Top CA', 'Marchandises qui rapportent le plus', 'chart', 'teal'],
                        'top_products_qty' => ['Top qté', 'Marchandises les plus vendues', 'package', 'violet'],
                        'ventes_direct' => ['Ventes', 'Liste détaillée Vente Direct', 'shopping-bag', 'green'],
                        'factures' => ['Factures', 'CA Facture HT / TTC / solde', 'document', 'blue'],
                        'ca_ht_tva' => ['TVA', 'Séparation CA HT et TVA', 'banknotes', 'amber'],
                        'stock_low' => ['Stock bas', 'Articles sous le seuil', 'alert', 'amber'],
                        'stock_out' => ['Ruptures', 'Articles à 0', 'alert', 'rose'],
                    ] as $presetKey => $preset)
                        @if ($presetKey === 'factures' || $presetKey === 'ca_ht_tva')
                            @continue(!$hasInvoicing)
                        @endif
                        <button type="button"
                                class="reporting-explorer__preset {{ $reportType === $presetKey ? 'reporting-explorer__preset--active' : '' }}"
                                wire:click="$set('reportType', '{{ $presetKey }}')">
                            <span class="reporting-explorer__preset-top">
                                <x-ui-icon-box :tone="$preset[3]" :icon="$preset[2]" />
                                <strong>{{ $preset[0] }}</strong>
                            </span>
                            <span>{{ $preset[1] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="reporting-explorer__toolbar">
                    <div class="reporting-explorer__field reporting-explorer__field--report">
                        <label class="reporting-filters__label" for="explorer-report-type">Rapport</label>
                        <select id="explorer-report-type" class="reporting-select" wire:model.live="reportType">
                            @foreach ($reportTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="reporting-explorer__field reporting-explorer__field--limit">
                        <label class="reporting-filters__label" for="explorer-report-limit">Lignes</label>
                        <select id="explorer-report-limit" class="reporting-select" wire:model.live="reportLimit">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                    @unless (in_array($reportType, ['stock_low', 'stock_out', 'ca_ht_tva'], true))
                        <div class="reporting-explorer__field reporting-explorer__field--client">
                            <label class="reporting-filters__label" for="explorer-client-search">Client / entreprise</label>
                            <div class="reporting-explorer__client-control">
                                @if ($filterClientId)
                                    <div class="reporting-explorer__chip">
                                        <span>{{ $clientSuggestions[0]['name'] ?? ('#'.$filterClientId) }}</span>
                                        <button type="button" wire:click="clearClientFilter" title="Effacer le filtre">×</button>
                                    </div>
                                @else
                                    <input id="explorer-client-search"
                                           type="search"
                                           class="reporting-input"
                                           wire:model.live.debounce.300ms="filterClientSearch"
                                           placeholder="Rechercher un client…"
                                           autocomplete="off">
                                    @if (trim($filterClientSearch) !== '' && count($clientSuggestions) > 0)
                                        <ul class="reporting-explorer__suggest" role="listbox">
                                            @foreach ($clientSuggestions as $c)
                                                <li role="option" wire:click="selectClient({{ $c['id'] }})">{{ $c['name'] }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif (trim($filterClientSearch) !== '' && count($clientSuggestions) === 0)
                                        <div class="reporting-explorer__suggest reporting-explorer__suggest--empty">Aucun client trouvé</div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="reporting-explorer__field reporting-explorer__field--client reporting-explorer__field--spacer" aria-hidden="true"></div>
                    @endunless
                    <div class="reporting-explorer__field reporting-explorer__field--action">
                        <label class="reporting-filters__label reporting-filters__label--ghost">&nbsp;</label>
                        <button type="button" class="btn btn-export btn-export--excel reporting-explorer__export" wire:click="exportExplorerExcel">
                            <x-file-type-icon format="excel" class="btn-export__glyph" />
                            <span class="btn-export__label">Exporter Excel</span>
                        </button>
                    </div>
                </div>

                @if ($explorerReport)
                    <div class="reporting-explorer__result-head">
                        <h3>{{ $explorerReport['title'] }}</h3>
                        <div class="reporting-explorer__meta">
                            @if (!empty($explorerReport['meta']['count']))
                                <span>{{ $explorerReport['meta']['count'] }} ligne(s)</span>
                            @endif
                            @if (isset($explorerReport['meta']['total']))
                                <span>Total : {{ fmt_money((float) $explorerReport['meta']['total']) }} {{ $currency }}</span>
                            @endif
                            @if (isset($explorerReport['meta']['total_ttc']))
                                <span>TTC : {{ fmt_money((float) $explorerReport['meta']['total_ttc']) }} {{ $currency }}</span>
                            @endif
                            @if (isset($explorerReport['meta']['tva_total']))
                                <span>TVA : {{ fmt_money((float) $explorerReport['meta']['tva_total']) }} {{ $currency }}</span>
                            @endif
                        </div>
                    </div>
                    @if (count($explorerReport['rows'] ?? []) > 0)
                        @php
                            $columnTypes = $explorerReport['column_types'] ?? [];
                            $isNumericType = static fn (string $type): bool => in_array($type, ['money', 'money_emphasis', 'percent', 'qty', 'int'], true);
                            $colCount = max(1, count($explorerReport['headers']));
                            $textCols = 0;
                            foreach ($columnTypes as $t) {
                                if (! $isNumericType($t) && $t !== 'badge') {
                                    $textCols++;
                                }
                            }
                            if ($textCols === 0) {
                                $textCols = 1;
                            }
                            $numericCols = max(0, $colCount - $textCols);
                            $textWidth = $numericCols > 0 ? max(18, min(34, 100 - ($numericCols * 8))) : 100;
                            $numWidth = $numericCols > 0 ? round((100 - $textWidth) / $numericCols, 2) : 0;
                        @endphp
                        <div class="table-scroll reporting-explorer__table-wrap">
                            <table class="reporting-table reporting-table--explorer">
                                <colgroup>
                                    @foreach ($explorerReport['headers'] as $colIndex => $header)
                                        @php
                                            $type = $columnTypes[$colIndex] ?? 'text';
                                            $width = $isNumericType($type) ? $numWidth : round($textWidth / max(1, $textCols), 2);
                                        @endphp
                                        <col style="width: {{ $width }}%">
                                    @endforeach
                                </colgroup>
                                <thead>
                                    <tr>
                                        @foreach ($explorerReport['headers'] as $colIndex => $header)
                                            @php $type = $columnTypes[$colIndex] ?? 'text'; @endphp
                                            <th scope="col" class="{{ $isNumericType($type) ? 'reporting-table__num' : 'reporting-table__text' }}">
                                                <span class="reporting-table__head-label">{{ $header }}</span>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($explorerReport['rows'] as $row)
                                        <tr>
                                            @foreach ($row as $colIndex => $cell)
                                                @php
                                                    $type = $columnTypes[$colIndex] ?? (is_numeric($cell) ? 'qty' : 'text');
                                                    $cellClass = match ($type) {
                                                        'money', 'money_emphasis', 'percent', 'qty', 'int' => 'reporting-table__num',
                                                        'badge' => 'reporting-table__badge-cell',
                                                        'date' => 'reporting-table__date',
                                                        default => 'reporting-table__text',
                                                    };
                                                    if ($type === 'money_emphasis') {
                                                        $cellClass .= ' reporting-table__money reporting-table__money--emphasis';
                                                    } elseif ($type === 'money') {
                                                        $cellClass .= ' reporting-table__money';
                                                    } elseif ($type === 'percent') {
                                                        $cellClass .= ' reporting-table__pct';
                                                    } elseif ($type === 'int') {
                                                        $cellClass .= ' reporting-table__int';
                                                    }

                                                    $display = match ($type) {
                                                        'money', 'money_emphasis' => fmt_money((float) ($cell ?? 0)),
                                                        'percent' => fmt_num((float) ($cell ?? 0), 2) . ' %',
                                                        'qty' => $cell === '' || $cell === null ? '—' : fmt_num((float) $cell),
                                                        'int' => number_format((int) ($cell ?? 0), 0, ',', ' '),
                                                        'badge' => (string) ($cell ?? '—'),
                                                        default => ($cell === null || $cell === '') ? '—' : $cell,
                                                    };
                                                @endphp
                                                <td class="{{ $cellClass }}">
                                                    @if ($type === 'badge')
                                                        <span class="reporting-table__badge">{{ $display }}</span>
                                                    @else
                                                        <span class="reporting-table__cell-value">{{ $display }}</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="reporting-empty">Aucune donnée pour ces filtres. Élargissez la période ou changez de scénario.</p>
                    @endif
                @endif
            </div>
        </section>
    @endif

    @include('inovcom-reporting::partials.glossary', [
        'keys' => ['CA', 'VENTE_DIRECT', 'CA_FACTURE', 'HT', 'TVA', 'TTC', 'COGS', 'BENEFICE', 'PART_CA'],
        'note' => $reportingGlossaryNote,
        'standalone' => true,
    ])
</div>
