@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;

    $statusLabels = [
        'all' => 'Tous',
        'draft' => 'Brouillon',
        'confirmed' => 'Confirmé',
    ];
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    @if ($totalValue > 0)
        <div style="padding: 12px 14px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; margin-bottom: 16px;">
            <strong>Total des pertes confirmées :</strong> {{ fmt_money($totalValue) }} {{ currency_label() }}
        </div>
    @endif

    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Pertes</h2>
            <div class="client-list-head__actions">
                @if ($canExport ?? true)
                    <x-export-btn format="excel" class="btn-sm" wire:click="exportExcel">Exporter Excel</x-export-btn>
                    <x-export-btn format="pdf" class="btn-sm" wire:click="exportPdf">Exporter PDF</x-export-btn>
                @endif
                @if ($canCreate ?? true)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.losses.create', ['tenant' => $tenantCode]) }}">Nouvelle perte</a>
                @endif
            </div>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input
                    class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Référence ou article…"
                    aria-label="Rechercher une perte"
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
                        <option value="20">20</option>
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
                <div class="client-filter-panel__grid client-filter-panel__grid--debts">
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Raison</span>
                        <select class="input input-sm" wire:model.live="reasonFilter">
                            <option value="">Toutes les raisons</option>
                            @foreach ($reasons as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="client-filter-field client-filter-field--dates">
                        <span class="client-filter-field__label">Période</span>
                        <div class="client-filter-dates">
                            <input
                                class="input input-sm"
                                type="date"
                                wire:model.live="dateFrom"
                                title="Du"
                                aria-label="Date de début"
                            >
                            <span class="client-filter-dates__sep" aria-hidden="true">→</span>
                            <input
                                class="input input-sm"
                                type="date"
                                wire:model.live="dateTo"
                                title="Au"
                                aria-label="Date de fin"
                            >
                        </div>
                    </div>

                    <div class="client-filter-field">
                        <span class="client-filter-field__label">Raccourcis</span>
                        <div class="client-filter-period-pills">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('day')">Aujourd’hui</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('week')">Semaine</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('month')">Mois</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('clear')">Effacer dates</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Date</th>
                        <th>Article</th>
                        <th>Raison</th>
                        <th>Quantité</th>
                        <th>Valeur</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr wire:key="loss-{{ $record->id }}">
                            <td><strong>{{ $record->reference }}</strong></td>
                            <td>{{ $record->loss_date->format('d/m/Y') }}</td>
                            <td>
                                <x-item-label :reference="$record->item?->sku" :name="$record->item?->name" />
                            </td>
                            <td>{{ $record->reason?->name ?? '—' }}</td>
                            <td>{{ fmt_num($record->quantity) }} {{ $record->item?->unit?->abbreviation ?? 'pc' }}</td>
                            <td><strong>{{ fmt_money($record->value) }} {{ currency_label() }}</strong></td>
                            <td>
                                @if ($record->status === 'draft')
                                    <span class="badge badge-secondary">Brouillon</span>
                                @else
                                    <span class="badge badge-success">Confirmé</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.losses.edit', [$record->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @if ($record->isDraft())
                                    @if ($canConfirmLoss)
                                        <button
                                            class="btn btn-success btn-sm"
                                            wire:click="confirmLoss({{ $record->id }})"
                                            wire:confirm="Confirmer cette perte et déduire le stock ?"
                                        >Confirmer</button>
                                    @endif
                                    @if ($canDeleteLoss)
                                        <button
                                            class="btn btn-error btn-sm"
                                            wire:click="delete({{ $record->id }})"
                                            wire:confirm="Supprimer cette perte ?"
                                        >Supprimer</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($records->count() === 0)
                        <tr><td colspan="8">Aucune perte pour ces filtres.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $records->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </section>
</div>
