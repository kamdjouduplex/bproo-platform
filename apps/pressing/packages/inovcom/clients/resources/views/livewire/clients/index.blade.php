@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;

    $segmentName = $segments->firstWhere('id', $segmentFilter)?->name;
    $categoryName = $categories->firstWhere('id', $categoryFilter)?->name;
    $zoneName = $zones->firstWhere('id', $zoneFilter)?->name;
    $salesrepName = $salesreps->firstWhere('id', $salesrepFilter)?->name ?? null;
    $statusLabels = [
        'active' => 'Actifs',
        'inactive' => 'Inactifs',
        'blocked' => 'Bloqués',
    ];
@endphp

<div class="page-body">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Clients</h2>
            <div class="client-list-head__actions">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="export">Exporter</button>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.clients.duplicates', ['tenant' => $tenantCode]) }}">Doublons</a>
                @if ($canCreate)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.clients.create', ['tenant' => $tenantCode]) }}">Nouveau</a>
                @endif
            </div>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Rechercher nom, code, email, tél…"
                    aria-label="Rechercher un client">
            </div>
            <div class="client-filter-bar__tools">
                <button type="button"
                    class="client-filter-toggle {{ $showAdvancedFilters ? 'client-filter-toggle--open' : '' }}"
                    wire:click="toggleAdvancedFilters"
                    aria-expanded="{{ $showAdvancedFilters ? 'true' : 'false' }}">
                    Filtres
                    @if ($activeFiltersCount > 0)
                        <span class="client-filter-toggle__badge">{{ $activeFiltersCount }}</span>
                    @endif
                </button>
                @if ($search !== '' || $activeFiltersCount > 0)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters" title="Réinitialiser">Réinit.</button>
                @endif
                <label class="client-filter-bar__per-page">
                    <span class="sr-only">Résultats par page</span>
                    <select class="input input-sm" wire:model.live="perPage" aria-label="Par page">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="client-status-pills" role="group" aria-label="Filtrer par statut">
            @foreach ([
                'all' => 'Tous',
                'active' => 'Actifs',
                'inactive' => 'Inactifs',
                'blocked' => 'Bloqués',
            ] as $value => $label)
                <button type="button"
                    class="client-status-pill {{ $statusFilter === $value ? 'client-status-pill--active' : '' }}"
                    wire:click="$set('statusFilter', '{{ $value }}')">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($showAdvancedFilters)
            <div class="client-filter-panel">
                <div class="client-filter-panel__grid">
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Segment</span>
                        <select class="input input-sm" wire:model.live="segmentFilter">
                            <option value="">Tous</option>
                            @foreach ($segments as $segment)
                                <option value="{{ $segment->id }}">{{ $segment->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Catégorie</span>
                        <select class="input input-sm" wire:model.live="categoryFilter">
                            <option value="">Toutes</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Zone</span>
                        <select class="input input-sm" wire:model.live="zoneFilter">
                            <option value="">Toutes</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Commercial</span>
                        <select class="input input-sm" wire:model.live="salesrepFilter">
                            <option value="">Tous</option>
                            @foreach ($salesreps as $rep)
                                <option value="{{ $rep->id }}">{{ $rep->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>
        @endif

        @if ($activeFiltersCount > 0 || $search !== '')
            <div class="client-filter-chips">
                @if ($search !== '')
                    <span class="client-filter-chip">
                        « {{ \Illuminate\Support\Str::limit($search, 28) }} »
                        <button type="button" wire:click="$set('search', '')" aria-label="Effacer la recherche">&times;</button>
                    </span>
                @endif
                @if ($statusFilter !== 'all')
                    <span class="client-filter-chip">
                        {{ $statusLabels[$statusFilter] ?? $statusFilter }}
                        <button type="button" wire:click="$set('statusFilter', 'all')" aria-label="Retirer le filtre statut">&times;</button>
                    </span>
                @endif
                @if ($segmentName)
                    <span class="client-filter-chip">
                        {{ $segmentName }}
                        <button type="button" wire:click="$set('segmentFilter', null)" aria-label="Retirer le segment">&times;</button>
                    </span>
                @endif
                @if ($categoryName)
                    <span class="client-filter-chip">
                        {{ $categoryName }}
                        <button type="button" wire:click="$set('categoryFilter', null)" aria-label="Retirer la catégorie">&times;</button>
                    </span>
                @endif
                @if ($zoneName)
                    <span class="client-filter-chip">
                        {{ $zoneName }}
                        <button type="button" wire:click="$set('zoneFilter', null)" aria-label="Retirer la zone">&times;</button>
                    </span>
                @endif
                @if ($salesrepName)
                    <span class="client-filter-chip">
                        {{ $salesrepName }}
                        <button type="button" wire:click="$set('salesrepFilter', null)" aria-label="Retirer le commercial">&times;</button>
                    </span>
                @endif
            </div>
        @endif

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Téléphone</th>
                        <th>Dettes</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        @php $debtLevel = $debtSummaries[$client->id]['level'] ?? 'clear'; @endphp
                        <tr @class(['client-row--debt-overdue' => $debtLevel === 'overdue', 'client-row--debt-active' => $debtLevel === 'active'])>
                            <td>{{ $client->code }}</td>
                            <td>{{ $client->name }}</td>
                            <td>
                                <span class="badge badge-info">{{ $client->type === 'individual' ? 'Particulier' : 'Entreprise' }}</span>
                            </td>
                            <td>{{ $client->phone ?? '—' }}</td>
                            <td>
                                @include('inovcom-clients::components.debt-indicator', [
                                    'summary' => $debtSummaries[$client->id] ?? [],
                                    'variant' => 'compact',
                                    'tenantCode' => $tenantCode,
                                    'clientId' => $client->id,
                                    'debtsModule' => $debtsModule,
                                ])
                            </td>
                            <td>
                                @if ($client->is_blocked)
                                    <span class="badge badge-error" title="{{ $client->block_reason }}">Bloqué</span>
                                @elseif ($client->is_active)
                                    <span class="badge badge-success">Actif</span>
                                @else
                                    <span class="badge badge-warning">Inactif</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.clients.show', [$client->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.clients.edit', [$client->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                    @if ($canDelete)
                                        <button type="button" class="btn btn-danger btn-sm"
                                            wire:click="delete({{ $client->id }})"
                                            wire:confirm="Supprimer ce client ? (impossible si encours ou historique de ventes)">Suppr.</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if ($clients->count() === 0)
                        <tr>
                            <td colspan="7">Aucun client pour le moment.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            {{ $clients->appends(['tenant' => $tenantCode, 'search' => $search])->links() }}
        </div>
    </section>
</div>
