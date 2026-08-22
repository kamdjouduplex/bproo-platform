@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;

    $statusLabels = [
        'all' => 'Tous',
        'open' => 'Ouvert',
        'partial' => 'Partiel',
        'paid' => 'Soldé',
        'overdue' => 'En retard',
    ];
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif
    @if (! $validationWorkflowReady)
        <div class="alert alert-error" style="margin-bottom: 16px;">Validation des dettes indisponible: exécutez les migrations tenant du module Dettes.</div>
    @endif

    @if ($totalOutstanding > 0)
        <div style="padding: 12px 14px; background: #fef3c7; border: 1px solid #fde68a; border-radius: 10px; margin-bottom: 16px;">
            <strong>Total des dettes impayées :</strong> {{ fmt_money($totalOutstanding) }} {{ currency_label() }}
        </div>
    @endif

    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Dettes</h2>
            <div class="client-list-head__actions">
                @if ($canExport ?? true)
                    <x-export-btn format="excel" class="btn-sm" wire:click="exportExcel">Exporter Excel</x-export-btn>
                    <x-export-btn format="pdf" class="btn-sm" wire:click="exportPdf">Exporter PDF</x-export-btn>
                @endif
                @if ($canCreate)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.debts.create', ['tenant' => $tenantCode]) }}">Nouvelle dette</a>
                @endif
            </div>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input
                    class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Référence, client ou code…"
                    aria-label="Rechercher une dette"
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
                @if ($search !== '' || $statusFilter !== 'all' || $validationFilter !== 'all' || $activeFiltersCount > 0)
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
                    class="client-status-pill {{ $statusFilter === $value && $validationFilter === 'all' ? 'client-status-pill--active' : '' }}"
                    wire:click="setStatusFilter('{{ $value }}')"
                >
                    {{ $label }}
                </button>
            @endforeach
            @if ($validationWorkflowReady)
                <button
                    type="button"
                    class="client-status-pill {{ $validationFilter === 'pending' ? 'client-status-pill--active' : '' }}"
                    wire:click="setValidationPending"
                >
                    À valider
                </button>
            @endif
        </div>

        @if ($showAdvancedFilters)
            <div class="client-filter-panel">
                <div class="client-filter-panel__grid client-filter-panel__grid--debts">
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Client</span>
                        <select class="input input-sm" wire:model.live="clientFilter">
                            <option value="">Tous les clients</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="client-filter-field client-filter-field--dates">
                        <span class="client-filter-field__label">Période d’ouverture</span>
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
                        <th>Client</th>
                        <th>Date ouverture</th>
                        <th>Échéance</th>
                        <th>Montant total</th>
                        <th>Solde restant</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($debts as $debt)
                        <tr wire:key="debt-{{ $debt->id }}">
                            <td><strong>{{ $debt->reference }}</strong></td>
                            <td>{{ $debt->client->name }} ({{ $debt->client->code }})</td>
                            <td>{{ $debt->opened_at->format('d/m/Y') }}</td>
                            <td>{{ $debt->due_date ? $debt->due_date->format('d/m/Y') : '—' }}</td>
                            <td>{{ fmt_money($debt->total_amount) }} {{ currency_label() }}</td>
                            <td><strong>{{ fmt_money($debt->balance) }} {{ currency_label() }}</strong></td>
                            <td>
                                @if (! $debt->is_validated)
                                    <span class="badge badge-error">En attente de validation</span>
                                @elseif ($debt->status === 'open')
                                    <span class="badge badge-warning">Ouvert</span>
                                @elseif ($debt->status === 'partial')
                                    <span class="badge badge-info">Partiel</span>
                                @elseif ($debt->status === 'paid')
                                    <span class="badge badge-success">Soldé</span>
                                @else
                                    <span class="badge badge-error">En retard</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.debts.edit', [$debt->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @if ($validationWorkflowReady && ! $debt->is_validated && $canValidate)
                                    <button class="btn btn-primary btn-sm" wire:click="validateDebt({{ $debt->id }})">Valider</button>
                                @endif
                                @if (! $debt->isPaid() && (float) $debt->balance > 0 && (! $validationWorkflowReady || $debt->is_validated) && $canReceivePayment)
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.debts.pay', [$debt->id, 'tenant' => $tenantCode]) }}">Encaisser</a>
                                @endif
                                @if ($debt->isPaid() && $canDelete)
                                    <button class="btn btn-secondary btn-sm" wire:click="delete({{ $debt->id }})" wire:confirm="Supprimer cette dette soldée ?">Supprimer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($debts->count() === 0)
                        <tr><td colspan="8">Aucune dette pour ces filtres.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">{{ $debts->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
