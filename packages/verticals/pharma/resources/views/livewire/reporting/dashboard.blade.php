<div class="pa-page" wire:loading.class="is-loading">
    @include('pharma::livewire.reporting.partials.nav')

    <header class="pa-hero">
        <div>
            <p class="pa-hero__eyebrow">Rapport Pharmacie · {{ $periodLabel }}</p>
            <h1 class="pa-hero__title">Tableau de bord</h1>
            <p class="pa-hero__lead">Vue d’ensemble de l’officine : ventes, rentabilité, stock et points de vigilance.</p>
        </div>
        <div class="pa-hero__aside">
            @include('pharma::livewire.reporting.partials.period')
        </div>
    </header>

    <section class="pa-headline" aria-label="Indicateurs principaux">
        @foreach ($headline as $card)
            @php
                $trend = $card['trend'];
                $invert = $card['invert_trend'] ?? false;
                $up = $trend !== null && $trend >= 0;
                $good = $trend === null ? null : ($invert ? ! $up : $up);
            @endphp
            <article class="pa-headcard">
                <div class="pa-headcard__icon">
                    <x-ui-icon-box :tone="$card['tone']" :icon="$card['icon']" />
                </div>
                <div class="pa-headcard__label">{{ $card['label'] }}</div>
                <div class="pa-headcard__value">
                    {{ $card['money'] ? fmt_money($card['value']) : fmt_num($card['value']) }}
                    @if ($card['money'])
                        <span>{{ $currency }}</span>
                    @endif
                </div>
                <div class="pa-headcard__meta">
                    @if ($trend !== null)
                        <span class="pa-kpi__trend {{ $good ? 'is-up' : 'is-down' }}">
                            {{ $trend >= 0 ? '+' : '' }}{{ fmt_num($trend, 1) }} %
                        </span>
                    @endif
                    <span class="pa-muted">vs période préc.</span>
                </div>
            </article>
        @endforeach
    </section>

    <div class="pa-grid pa-grid--dash">
        <section class="pa-panel pa-panel--chart">
            <div class="pa-panel__head">
                <div>
                    <h2 class="pa-panel__title">Évolution du CA vente directe</h2>
                    <p class="pa-panel__hint">Montants nets de retours · {{ $periodLabel }}</p>
                </div>
            </div>
            <div class="pa-chart pa-chart--bars">
                @if ($chart['bars'] === [])
                    <p class="pa-empty">Aucune vente sur la période.</p>
                @else
                    @php
                        $axisCount = count($chart['bars']);
                        $axisStep = $axisCount > 14 ? (int) ceil($axisCount / 8) : 1;
                    @endphp
                    <div class="pa-bars" role="img" aria-label="Histogramme du chiffre d’affaires">
                        <div class="pa-bars__y" aria-hidden="true">
                            @foreach ($chart['ticks'] as $tick)
                                <span>{{ fmt_money($tick) }}</span>
                            @endforeach
                        </div>
                        <div class="pa-bars__main">
                            <div class="pa-bars__plot">
                                @foreach ($chart['bars'] as $bar)
                                    <div
                                        class="pa-bar {{ $bar['is_today'] ? 'is-today' : '' }} {{ $bar['clipped'] ? 'is-clipped' : '' }} {{ $bar['total'] <= 0 ? 'is-zero' : '' }}"
                                        style="height: {{ $bar['pct'] }}%"
                                        title="{{ $bar['label'] }} : {{ fmt_money($bar['total']) }} {{ $currency }}"
                                    ></div>
                                @endforeach
                            </div>
                            <div class="pa-chart__axis">
                                @foreach ($chart['bars'] as $i => $bar)
                                    <span>{{ $axisStep === 1 || $i % $axisStep === 0 || $i === $axisCount - 1 ? $bar['short'] : '' }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <p class="pa-chart__caption">
                        Moyenne {{ fmt_money($chart['avg']) }} {{ $currency }}/jour
                        @if ($chart['best'] && $chart['best']['total'] > 0)
                            · Pic {{ fmt_money($chart['best']['total']) }} {{ $currency }} le {{ $chart['best']['label'] }}
                        @endif
                        @if ($chart['clipped'])
                            · Un jour dépasse l’échelle : survolez la barre hachurée pour le montant exact.
                        @endif
                    </p>
                @endif
            </div>
        </section>

        <section class="pa-panel">
            <div class="pa-panel__head">
                <div>
                    <h2 class="pa-panel__title">Ventes par catégorie</h2>
                    <p class="pa-panel__hint">Répartition du CA</p>
                </div>
            </div>
            <div class="pa-donut-row">
                <div class="pa-donut" style="background: {{ $categoryGradient }};">
                    <div class="pa-donut__hole">
                        <strong>{{ fmt_money($kpis['ca']) }}</strong>
                        <span>{{ $currency }}</span>
                    </div>
                </div>
                <ul class="pa-legend">
                    @forelse ($categories as $slice)
                        <li>
                            <i style="background:{{ $slice['color'] }}"></i>
                            <span>{{ $slice['name'] }}</span>
                            <em>{{ fmt_num($slice['percent'], 1) }} %</em>
                            <strong>{{ fmt_money($slice['total']) }}</strong>
                        </li>
                    @empty
                        <li class="pa-muted">Pas encore de ventilation.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <section class="pa-panel pa-panel--alerts">
            <div class="pa-panel__head">
                <div>
                    <h2 class="pa-panel__title">À surveiller</h2>
                    <p class="pa-panel__hint">Alertes stock, lots et créances</p>
                </div>
            </div>
            <ul class="pa-alert-list">
                @forelse ($alerts as $alert)
                    <li class="pa-alert pa-alert--{{ $alert['tone'] }}">
                        <x-ui-icon-box :tone="$alert['tone']" :icon="$alert['icon']" />
                        <div>
                            <strong>{{ $alert['title'] }}</strong>
                            <div>{{ $alert['value'] }}</div>
                            <span class="pa-muted">{{ $alert['hint'] }}</span>
                        </div>
                        @if (!empty($alert['route']) && \Illuminate\Support\Facades\Route::has($alert['route']))
                            <a href="{{ route($alert['route'], $periodQuery) }}">Voir la liste</a>
                        @endif
                    </li>
                @empty
                    <li class="pa-empty">Rien à signaler pour le moment.</li>
                @endforelse
            </ul>
            @if (\Illuminate\Support\Facades\Route::has('tenant.pharma-reporting.alerts'))
                <a class="pa-panel__more" href="{{ route('tenant.pharma-reporting.alerts', $periodQuery) }}">Voir toutes les alertes</a>
            @endif
        </section>
    </div>

    <div class="pa-grid pa-grid--bottom">
        <section class="pa-panel">
            <div class="pa-panel__head">
                <div>
                    <h2 class="pa-panel__title">Top 5 produits vendus</h2>
                    <p class="pa-panel__hint">Classement par chiffre d’affaires</p>
                </div>
            </div>
            <div class="table-scroll">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produit</th>
                            <th class="is-num">Qté</th>
                            <th class="is-num">CA</th>
                            <th class="is-num">Marge</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topProducts as $row)
                            <tr>
                                <td>{{ $row['rank'] }}</td>
                                <td>
                                    <strong>{{ $row['name'] }}</strong>
                                    @if ($row['sku'])
                                        <div class="pa-muted">{{ $row['sku'] }}</div>
                                    @endif
                                </td>
                                <td class="is-num">{{ fmt_num($row['qty']) }}</td>
                                <td class="is-num">{{ fmt_money($row['ca']) }}</td>
                                <td class="is-num">{{ fmt_money($row['margin']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="pa-empty">Aucune vente.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <a class="pa-panel__more" href="{{ route('tenant.pharma-reporting.sales', $periodQuery) }}">Voir toutes les ventes</a>
        </section>

        <section class="pa-panel">
            <div class="pa-panel__head">
                <div>
                    <h2 class="pa-panel__title">Indicateurs clés</h2>
                    <p class="pa-panel__hint">Synthèse opérationnelle</p>
                </div>
            </div>
            <div class="pa-mini-grid">
                @foreach ($indicators as $item)
                    <div class="pa-mini">
                        <span class="pa-mini__label">{{ $item['label'] }}</span>
                        <strong>{{ $item['value'] }}</strong>
                        @if (!empty($item['hint']))
                            <em class="pa-badge pa-badge--{{ $item['hint_tone'] ?? 'good' }}">{{ $item['hint'] }}</em>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="pa-panel">
            <div class="pa-panel__head">
                <div>
                    <h2 class="pa-panel__title">Répartition des paiements</h2>
                    <p class="pa-panel__hint">Modes encaissés sur la période</p>
                </div>
            </div>
            <div class="pa-donut-row">
                <div class="pa-donut pa-donut--sm" style="background: {{ $paymentGradient }};">
                    <div class="pa-donut__hole">
                        <strong>{{ fmt_num(collect($payments)->sum('percent'), 0) }}%</strong>
                    </div>
                </div>
                <ul class="pa-legend">
                    @forelse ($payments as $slice)
                        <li>
                            <i style="background:{{ $slice['color'] }}"></i>
                            <span>{{ $slice['label'] }}</span>
                            <em>{{ fmt_num($slice['percent'], 1) }} %</em>
                            <strong>{{ fmt_money($slice['total']) }}</strong>
                        </li>
                    @empty
                        <li class="pa-muted">Aucun encaissement.</li>
                    @endforelse
                </ul>
            </div>
        </section>
    </div>
</div>
