@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body stock-page">
    <section class="card app-table-card stock-page__card">
        <header class="stock-page__header">
            <div class="stock-page__top">
                <div class="stock-page__actions">
                    <div class="stock-page__action-group">
                        <span class="stock-page__action-label">Consultation</span>
                        <div class="stock-page__action-btns">
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.movements', ['tenant' => $tenantCode]) }}">Mouvements</a>
                            <a class="btn btn-primary btn-sm" href="{{ route('tenant.stock.lookup', ['tenant' => $tenantCode]) }}">Où est le produit ?</a>
                        </div>
                    </div>
                    <div class="stock-page__action-divider" aria-hidden="true"></div>
                    <div class="stock-page__action-group">
                        <span class="stock-page__action-label">Opérations</span>
                        <div class="stock-page__action-btns">
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.adjust', ['tenant' => $tenantCode]) }}">Ajuster</a>
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.transfer', ['tenant' => $tenantCode]) }}">Transférer</a>
                        </div>
                    </div>
                    <div class="stock-page__action-divider" aria-hidden="true"></div>
                    <div class="stock-page__action-group">
                        <span class="stock-page__action-label">Export</span>
                        <div class="stock-page__action-btns">
                            <x-export-btn format="excel" class="btn-sm" wire:click="exportExcel">Exporter Excel</x-export-btn>
                            <x-export-btn format="pdf" class="btn-sm" wire:click="exportPdf">Exporter PDF</x-export-btn>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stock-page__filters">
                <div class="stock-page__filters-row">
                    <div class="stock-page__field stock-page__field--search">
                        <label class="stock-page__field-label" for="stock-search">Rechercher</label>
                        <input id="stock-search" class="input input-sm" type="search"
                               wire:model.live.debounce.300ms="search"
                               placeholder="{{ item_search_placeholder(true, 'emplacement') }}"
                               autocomplete="off">
                    </div>
                    <div class="stock-page__field stock-page__field--statuses">
                        <span class="stock-page__field-label">Statut stock</span>
                        <div class="stock-page__status-toggles" role="group" aria-label="Filtrer par statut">
                            @foreach ($statusOptions as $value => $label)
                                <button
                                    type="button"
                                    class="stock-page__status-toggle {{ in_array($value, $statusFilters, true) ? 'is-active' : '' }}"
                                    wire:click="toggleStatusFilter('{{ $value }}')"
                                    aria-pressed="{{ in_array($value, $statusFilters, true) ? 'true' : 'false' }}"
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="stock-page__field stock-page__field--narrow">
                        <label class="stock-page__field-label" for="stock-per-page">Par page</label>
                        <select id="stock-per-page" class="input input-sm" wire:model.live="perPage">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="stock-page__filters-row stock-page__filters-row--refine">
                    <div class="stock-page__field stock-page__field--select">
                        <label class="stock-page__field-label" for="stock-category">Catégorie</label>
                        <select id="stock-category" class="input input-sm" wire:model.live="categoryId">
                            <option value="">Toutes les catégories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="stock-page__field stock-page__field--select">
                        <label class="stock-page__field-label" for="stock-brand">Marque</label>
                        <select id="stock-brand" class="input input-sm" wire:model.live="brandId">
                            <option value="">Toutes les marques</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($providersAvailable ?? false)
                        <div class="stock-page__field stock-page__field--select">
                            <label class="stock-page__field-label" for="stock-provider">Fournisseur</label>
                            <select id="stock-provider" class="input input-sm" wire:model.live="providerId">
                                <option value="">Tous les fournisseurs</option>
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="stock-page__field stock-page__field--actions">
                        <span class="stock-page__field-label stock-page__field-label--hidden">Actions</span>
                        <div class="stock-page__filter-btns">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters"
                                    @disabled(count($activeChips ?? []) === 0)>
                                Tout effacer
                            </button>
                        </div>
                    </div>
                </div>

                @if (count($activeChips ?? []) > 0)
                    <div class="stock-page__chips" aria-label="Filtres actifs">
                        <span class="stock-page__chips-label">Filtres actifs</span>
                        @foreach ($activeChips as $chip)
                            <button type="button"
                                    class="stock-page__chip"
                                    wire:click="clearFilter('{{ $chip['key'] }}')"
                                    title="Retirer ce filtre">
                                <span>{{ $chip['label'] }}</span>
                                <span class="stock-page__chip-x" aria-hidden="true">×</span>
                            </button>
                        @endforeach
                    </div>
                @endif

            </div>
        </header>

        <div class="table-scroll stock-page__table">
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Désignation</th>
                        <th>Stock disponible</th>
                        <th>Stock total</th>
                        <th>Stock d'alerte</th>
                        @if ($locationsEnabled ?? false)
                            <th>Emplacement</th>
                        @endif
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php
                            $availableQty = (float) ($item->available_quantity ?? 0);
                            $totalQty = (float) ($item->quantity ?? 0);
                            $reorderPoint = $item->reorder_point;
                            $isLowStock = $reorderPoint !== null && $availableQty <= $reorderPoint;
                            $isOutOfStock = $availableQty <= 0;
                        @endphp
                        <tr>
                            <td><strong>{{ $item->sku ?? '—' }}</strong></td>
                            <td>{{ $item->name }}</td>
                            <td>
                                <strong class="stock-qty {{ $isOutOfStock ? 'stock-qty--out' : ($isLowStock ? 'stock-qty--low' : 'stock-qty--ok') }}">
                                    {{ fmt_num($availableQty) }}
                                </strong>
                            </td>
                            <td>{{ fmt_num($totalQty) }}</td>
                            <td>{{ $reorderPoint ? fmt_num($reorderPoint) : '-' }}</td>
                            @if ($locationsEnabled ?? false)
                                <td>
                                    @if ($item->location_code ?? null)
                                        <code class="stock-location-code">{{ $item->location_code }}</code>
                                    @else
                                        <span class="stock-muted">—</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                @if ($isOutOfStock)
                                    <span class="badge badge-error">Rupture</span>
                                @elseif ($isLowStock)
                                    <span class="badge badge-warning">Stock faible</span>
                                @else
                                    <span class="badge badge-success">En stock</span>
                                @endif
                            </td>
                            <td class="stock-row-actions">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.movements.item', ['itemId' => $item->id, 'tenant' => $tenantCode]) }}">Mouvements</a>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.adjust.item', ['itemId' => $item->id, 'tenant' => $tenantCode]) }}">Ajuster</a>
                            </td>
                        </tr>
                    @endforeach
                    @if ($items->count() === 0)
                        <tr>
                            <td colspan="{{ ($locationsEnabled ?? false) ? 8 : 7 }}" class="stock-empty">
                                Aucun article ne correspond à vos critères.
                                @if (count($activeChips ?? []) > 0)
                                    <button type="button" class="btn btn-secondary btn-sm" style="margin-left:8px;" wire:click="resetFilters">Effacer les filtres</button>
                                @endif
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="table-pagination stock-page__pagination">
                {{ $items->appends(['tenant' => $tenantCode])->links() }}
            </div>
        @endif
    </section>
</div>
