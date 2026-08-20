<div class="pa-page" wire:loading.class="is-loading">
    @include('pharma::livewire.reporting.partials.nav')
    @include('pharma::livewire.reporting.partials.page-head', [
        'code' => 'RP0202',
        'title' => 'Rentabilité',
        'lead' => 'Marge brute, coût des ventes et performance par produit.',
        'icon' => 'chart',
        'tone' => 'green',
        'canExport' => $canExport,
        'excelUrl' => $canExport ? $this->exportUrl('excel') : null,
        'pdfUrl' => $canExport ? $this->exportUrl('pdf') : null,
    ])

    <section class="pa-filters pa-filters--compact card" aria-label="Filtres">
        <label class="pa-filters__field">
            <span>Période</span>
            <select class="input input-sm" wire:model.live="period">
                <option value="today">Aujourd’hui</option>
                <option value="last_7_days">7 derniers jours</option>
                <option value="this_month">Ce mois</option>
                <option value="last_month">Mois dernier</option>
                <option value="this_year">Cette année</option>
                <option value="custom">Personnalisée</option>
            </select>
        </label>
        @if ($period === 'custom')
            <label class="pa-filters__field pa-filters__field--date">
                <span>Du</span>
                <input type="date" class="input input-sm" wire:model="dateFrom">
            </label>
            <label class="pa-filters__field pa-filters__field--date">
                <span>Au</span>
                <input type="date" class="input input-sm" wire:model="dateTo">
            </label>
        @endif
        <label class="pa-filters__field">
            <span>Affichage</span>
            <select class="input input-sm" wire:model.live="viewBy">
                <option value="category">Par catégorie</option>
                <option value="product">Par produit</option>
            </select>
        </label>
        <div class="pa-filters__toolbar">
            <span class="pa-muted">{{ $periodLabel }}</span>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
            @if ($period === 'custom')
                <button type="button" class="btn btn-primary btn-sm" wire:click="applySearch">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
            @endif
        </div>
    </section>

    <section class="pa-section">
        <h2 class="pa-section__title">Récapitulatif</h2>
        <div class="pa-kpi-grid">
            @foreach ($cards as $card)
                @include('pharma::livewire.reporting.partials.kpi', ['card' => $card])
            @endforeach
        </div>
    </section>

    <section class="pa-panel pa-panel--table">
        <div class="pa-panel__head">
            <h2 class="pa-panel__title">{{ $viewBy === 'product' ? 'Par produit' : 'Par catégorie' }}</h2>
        </div>
        <div class="table-scroll">
            @if ($viewBy === 'product')
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th class="is-num">Qté</th>
                            <th class="is-num">CA</th>
                            <th class="is-num">Coût</th>
                            <th class="is-num">Marge</th>
                            <th class="is-num">Taux</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byProduct as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['name'] }}</strong>
                                    @if ($row['sku'])<div class="pa-muted">{{ $row['sku'] }}</div>@endif
                                </td>
                                <td>{{ $row['category'] }}</td>
                                <td class="is-num">{{ fmt_num($row['qty']) }}</td>
                                <td class="is-num">{{ fmt_money($row['ca']) }}</td>
                                <td class="is-num">{{ fmt_money($row['cogs']) }}</td>
                                <td class="is-num">{{ fmt_money($row['margin']) }}</td>
                                <td class="is-num">{{ fmt_num($row['margin_rate'], 1) }} %</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="pa-empty">Pas de données.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Catégorie</th>
                            <th class="is-num">Qté</th>
                            <th class="is-num">CA</th>
                            <th class="is-num">Marge</th>
                            <th class="is-num">Taux</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byCategory as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="is-num">{{ fmt_num($row['qty']) }}</td>
                                <td class="is-num">{{ fmt_money($row['ca']) }}</td>
                                <td class="is-num">{{ fmt_money($row['margin']) }}</td>
                                <td class="is-num">{{ fmt_num($row['margin_rate'], 1) }} %</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="pa-empty">Pas de données.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</div>
