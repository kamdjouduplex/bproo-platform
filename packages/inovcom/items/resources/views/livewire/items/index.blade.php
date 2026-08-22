@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $colCount = count($visibleColumns) + 1;
    $singular = $catalogNoun['singular'] ?? 'article';
    $title = $catalogNoun['title'] ?? 'Catalogue';

    $statusLabels = [
        'all' => 'Tous',
        'active' => 'Actifs',
        'inactive' => 'Inactifs',
    ];
@endphp

<div class="page-body">
    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">{{ $title }}</h2>
            <div class="client-list-head__actions">
                @if ($canExport ?? false)
                    <x-export-btn format="excel" class="btn-sm" wire:click="exportExcel">Exporter Excel</x-export-btn>
                    <x-export-btn format="pdf" class="btn-sm" wire:click="exportPdf">Exporter PDF</x-export-btn>
                @endif
                @if ($canConfigureList)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.list-config', ['tenant' => $tenantCode]) }}">Config</a>
                @endif
                @if ($canCreate)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.import', ['tenant' => $tenantCode]) }}">Importer Excel</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.items.create', ['tenant' => $tenantCode]) }}">Nouveau</a>
                @endif
            </div>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input
                    class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Désignation, référence ou code-barres…"
                    aria-label="Rechercher un {{ $singular }}"
                >
            </div>
            <div class="client-filter-bar__tools">
                <button
                    type="button"
                    class="client-filter-toggle {{ $showAdvancedFilters ? 'client-filter-toggle--open' : '' }}"
                    wire:click="toggleAdvancedFilters"
                    aria-expanded="{{ $showAdvancedFilters ? 'true' : 'false' }}"
                >
                    Filtres
                    @if ($activeFiltersCount > 0)
                        <span class="client-filter-toggle__badge">{{ $activeFiltersCount }}</span>
                    @endif
                </button>
                @if ($search !== '' || $statusFilter !== 'all' || $activeFiltersCount > 0)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters" title="Réinitialiser">Réinit.</button>
                @endif
                <label class="client-filter-bar__per-page">
                    <span class="sr-only">Résultats par page</span>
                    <select class="input input-sm" wire:model.live="perPage" aria-label="Par page">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="client-status-pills" role="group" aria-label="Filtrer par statut">
            @foreach ($statusLabels as $value => $label)
                <button
                    type="button"
                    class="client-status-pill {{ $statusFilter === $value ? 'client-status-pill--active' : '' }}"
                    wire:click="setStatusFilter('{{ $value }}')"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($showAdvancedFilters)
            <div class="client-filter-panel">
                <div class="client-filter-panel__grid client-filter-panel__grid--items">
                    @if ($categories->isNotEmpty())
                        <label class="client-filter-field">
                            <span class="client-filter-field__label">Catégorie</span>
                            <select class="input input-sm" wire:model.live="categoryFilter">
                                <option value="">Toutes</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @if ($brands->isNotEmpty())
                        <label class="client-filter-field">
                            <span class="client-filter-field__label">Marque</span>
                            <select class="input input-sm" wire:model.live="brandFilter">
                                <option value="">Toutes</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </div>
            </div>
        @endif

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        @foreach ($visibleColumns as $col)
                            <th>{{ $col['label'] }}</th>
                        @endforeach
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr wire:key="item-{{ $item->id }}">
                            @foreach ($visibleColumns as $col)
                                <td>
                                    @switch($col['key'])
                                        @case('reference')
                                            <strong>{{ $item->sku ?? '—' }}</strong>
                                            @break
                                        @case('designation')
                                            {{ $item->name }}
                                            @if (!empty(($item->metadata ?? [])['is_set']))
                                                <span class="badge" style="margin-left:6px;background:#eef2ff;color:#4338ca;">Lot</span>
                                            @endif
                                            @break
                                        @case('category')
                                            {{ $item->category?->name ?? '—' }}
                                            @break
                                        @case('brand')
                                            {{ $item->brand?->name ?? '—' }}
                                            @break
                                        @case('unit')
                                            {{ $item->unit?->abbreviation ?? $item->unit?->name ?? '—' }}
                                            @break
                                        @case('price')
                                            @if ($item->unitPrices->isEmpty())
                                                {{ fmt_money($item->price) }}
                                            @else
                                                @foreach ($item->unitPrices as $p)
                                                    <span style="display: block; font-size: 12px;">{{ fmt_money($p->price) }} / {{ $p->unit->abbreviation ?? $p->unit->name }}</span>
                                                @endforeach
                                            @endif
                                            @break
                                        @case('cost')
                                            @if ($item->unitPrices->isEmpty())
                                                {{ fmt_money($item->cost) }}
                                            @else
                                                @foreach ($item->unitPrices as $p)
                                                    <span style="display: block; font-size: 12px;">{{ fmt_money($p->cost) }} / {{ $p->unit->abbreviation ?? $p->unit->name }}</span>
                                                @endforeach
                                            @endif
                                            @break
                                        @case('margin')
                                            @php
                                                $salePrice = (float) $item->price;
                                                $buyPrice = (float) $item->cost;
                                                if ($item->unitPrices->isNotEmpty()) {
                                                    $baseUnit = $item->unitPrices->first(function ($p) {
                                                        return abs((float) $p->conversion_factor - 1.0) < 0.0001;
                                                    }) ?? $item->unitPrices->sortBy(fn ($p) => (float) $p->conversion_factor)->first();
                                                    if ($baseUnit) {
                                                        $salePrice = (float) $baseUnit->price;
                                                        $buyPrice = (float) $baseUnit->cost;
                                                    }
                                                }
                                                $margin = $salePrice - $buyPrice;
                                            @endphp
                                            <span @style(['color: #b91c1c; font-weight: 600' => $margin < 0, 'color: #15803d; font-weight: 600' => $margin > 0])>
                                                {{ fmt_money($margin) }}
                                            </span>
                                            @break
                                        @case('barcode')
                                            {{ $item->barcode ?? '—' }}
                                            @break
                                        @case('status')
                                            @if ($item->is_active)
                                                <span class="badge badge-success">Actif</span>
                                            @else
                                                <span class="badge badge-warning">Inactif</span>
                                            @endif
                                            @break
                                        @default
                                            —
                                    @endswitch
                                </td>
                            @endforeach
                            <td style="display:flex; gap:4px; flex-wrap:wrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.show', [$item->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @if ($canUpdate)
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.edit', [$item->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                @endif
                                @if ($canDelete)
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"
                                        wire:click="delete({{ $item->id }})"
                                        wire:confirm="Supprimer ce {{ $singular }} ?"
                                    >Supprimer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($items->count() === 0)
                        <tr>
                            <td colspan="{{ $colCount }}">Aucun {{ $singular }} pour ces filtres.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())
            <div class="table-pagination">
                {{ $items->appends(['tenant' => $tenantCode])->links('livewire.inovcom') }}
            </div>
        @endif
    </section>
</div>
