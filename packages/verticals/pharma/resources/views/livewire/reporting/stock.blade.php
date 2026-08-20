<div class="pa-page" wire:loading.class="is-loading">
    @include('pharma::livewire.reporting.partials.nav')
    @include('pharma::livewire.reporting.partials.page-head', [
        'code' => 'RP0301',
        'title' => 'Stock',
        'lead' => 'Valeur, ruptures, péremption et stock dormant.',
        'icon' => 'package',
        'tone' => 'blue',
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
                <option value="stockouts">Ruptures</option>
                <option value="low">Stock bas</option>
                <option value="expiring">Lots bientôt périmés</option>
                <option value="dead">Stock dormant</option>
            </select>
        </label>
        <label class="pa-filters__field">
            <span>Horizon</span>
            <select class="input input-sm" wire:model.live="horizonDays">
                <option value="30">&lt; 30 jours</option>
                <option value="60">&lt; 60 jours</option>
                <option value="90">&lt; 90 jours</option>
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
            <h2 class="pa-panel__title">{{ $tableTitle }}</h2>
        </div>
        <div class="table-scroll" wire:key="stock-table-{{ $viewBy }}-{{ $horizonDays }}">
            @if ($viewBy === 'expiring')
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Lot</th>
                            <th>Produit</th>
                            <th>Expiration</th>
                            <th class="is-num">Qté</th>
                            <th class="is-num">Jours</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['batch_number'] }}</td>
                                <td>{{ $row['item_name'] }}</td>
                                <td>{{ $row['expiry_date'] }}</td>
                                <td class="is-num">{{ fmt_num($row['quantity']) }}</td>
                                <td class="is-num">{{ $row['days'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="pa-empty">Aucun lot à risque.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($viewBy === 'dead')
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th class="is-num">Dispo</th>
                            <th>Dernière vente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }} @if($row['sku'])<span class="pa-muted">· {{ $row['sku'] }}</span>@endif</td>
                                <td class="is-num">{{ fmt_num($row['available']) }}</td>
                                <td>{{ $row['last_sale'] ?: 'Jamais' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="pa-empty">Pas de stock dormant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th class="is-num">Dispo</th>
                            <th class="is-num">Seuil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }} @if($row['sku'])<span class="pa-muted">· {{ $row['sku'] }}</span>@endif</td>
                                <td class="is-num">{{ fmt_num($row['available']) }}</td>
                                <td class="is-num">{{ $row['reorder_point'] !== null ? fmt_num($row['reorder_point']) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="pa-empty">{{ $viewBy === 'low' ? 'Aucun article sous seuil.' : 'Aucune rupture.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</div>
