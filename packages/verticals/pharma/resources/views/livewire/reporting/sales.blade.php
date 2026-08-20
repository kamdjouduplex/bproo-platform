<div class="pa-page" wire:loading.class="is-loading">
    @include('pharma::livewire.reporting.partials.nav')

    @include('pharma::livewire.reporting.partials.page-head', [
        'code' => 'RP0201',
        'title' => 'Analyse des ventes',
        'lead' => 'Consultez et analysez vos ventes avec précision.',
        'icon' => 'document',
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
            <span>Catégorie</span>
            <select class="input input-sm" wire:model.live="categoryId">
                <option value="">Toutes</option>
                @foreach ($options['categories'] as $cat)
                    <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="pa-filters__field">
            <span>Vendeur</span>
            <select class="input input-sm" wire:model.live="userId">
                <option value="">Tous</option>
                @foreach ($options['users'] as $user)
                    <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="pa-filters__field">
            <span>Paiement</span>
            <select class="input input-sm" wire:model.live="paymentMethod">
                <option value="">Tous</option>
                @foreach ($options['methods'] as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="pa-filters__field pa-filters__field--search">
            <span>Recherche</span>
            <input class="input input-sm" type="search" wire:model.live.debounce.300ms="search" placeholder="N° vente, client, vendeur…">
        </label>
        <div class="pa-filters__toolbar">
            <span class="pa-muted">{{ $periodLabel }}</span>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
            <button type="button" class="btn btn-primary btn-sm" wire:click="applySearch">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Rechercher
            </button>
        </div>
    </section>

    <section class="pa-section">
        <h2 class="pa-section__title">Récapitulatif des ventes</h2>
        <div class="pa-kpi-grid">
            @foreach ($cards as $card)
                @include('pharma::livewire.reporting.partials.kpi', ['card' => $card])
            @endforeach
        </div>
    </section>

    <section class="pa-panel pa-panel--table">
        <div class="pa-panel__head">
            <h2 class="pa-panel__title">Détail des ventes</h2>
            <label class="pa-inline">
                Afficher
                <select class="input input-sm" wire:model.live="perPage">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                résultats
            </label>
        </div>
        <div class="table-scroll">
            <table class="pa-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>N° vente</th>
                        <th class="is-num">Produits</th>
                        <th class="is-num">Qté</th>
                        <th class="is-num">CA</th>
                        <th class="is-num">Marge</th>
                        <th>Vendeur</th>
                        <th>Client</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $row)
                        @php
                            $status = $service->saleStatus((float) $row->credit_amount, (float) $row->paid_amount, (float) $row->total);
                            $margin = (float) $row->total - (float) $row->cogs;
                            $idx = $rows->firstItem() ? $rows->firstItem() + $i : $i + 1;
                        @endphp
                        <tr wire:key="sale-{{ $row->id }}">
                            <td>{{ $idx }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->sale_date)->format('d/m/Y') }}</td>
                            <td>
                                @if (\Illuminate\Support\Facades\Route::has('tenant.sales.show'))
                                    <a href="{{ route('tenant.sales.show', ['sale' => $row->id, 'tenant' => $tenantCode]) }}">{{ $row->sale_number }}</a>
                                @else
                                    {{ $row->sale_number }}
                                @endif
                            </td>
                            <td class="is-num">{{ fmt_num($row->products_count, 0) }}</td>
                            <td class="is-num">{{ fmt_num($row->qty_total) }}</td>
                            <td class="is-num">{{ fmt_money($row->total) }}</td>
                            <td class="is-num">{{ fmt_money($margin) }}</td>
                            <td>{{ $row->seller_name ?: '—' }}</td>
                            <td>{{ $row->client_name ?: 'Comptant' }}</td>
                            <td><span class="pa-badge pa-badge--{{ $status['key'] === 'paid' ? 'good' : ($status['key'] === 'credit' ? 'warn' : 'bad') }}">{{ $status['label'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="pa-empty">Aucune vente pour ces filtres.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pa-pager">
            {{ $rows->links() }}
        </div>
    </section>
</div>
