@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $hasFilters = $search !== '' || $statusFilter !== 'all' || $activeFiltersCount > 0;
    $clientName = $clients->firstWhere('id', (int) $clientFilter)?->name;
@endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif

    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <div>
                <h2 class="client-list-head__title">Factures</h2>
                <p class="invoice-list-subtitle">{{ $invoices->total() }} résultat{{ $invoices->total() > 1 ? 's' : '' }}</p>
            </div>
            <div class="client-list-head__actions">
                @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.deliveries.index'))
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.deliveries.index', ['tenant' => $tenantCode]) }}">Livraisons</a>
                @endif
                @if (($canCollection ?? false) && \Illuminate\Support\Facades\Route::has('tenant.invoicing.collection_reminders.index'))
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.collection_reminders.index', ['tenant' => $tenantCode]) }}">Relances</a>
                @endif
                @if ($canCreate)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoicing.create', ['tenant' => $tenantCode]) }}">Nouvelle facture</a>
                @endif
            </div>
        </div>

        <div class="invoice-kpi-grid" role="group" aria-label="Indicateurs factures">
            <button type="button"
                class="invoice-kpi {{ $statusFilter === 'collect' ? 'invoice-kpi--active' : '' }}"
                wire:click="setStatusFilter('collect')">
                <div class="invoice-kpi__label">À encaisser</div>
                <div class="invoice-kpi__value">{{ fmt_money($kpis['outstanding_amount']) }}</div>
                <div class="invoice-kpi__meta">{{ $kpis['outstanding_count'] }} facture{{ $kpis['outstanding_count'] > 1 ? 's' : '' }} en cours</div>
            </button>
            <button type="button"
                class="invoice-kpi invoice-kpi--alert {{ $statusFilter === 'overdue' ? 'invoice-kpi--active' : '' }}"
                wire:click="setStatusFilter('overdue')">
                <div class="invoice-kpi__label">Échues</div>
                <div class="invoice-kpi__value">{{ fmt_money($kpis['overdue_amount']) }}</div>
                <div class="invoice-kpi__meta">{{ $kpis['overdue_count'] }} facture{{ $kpis['overdue_count'] > 1 ? 's' : '' }} en retard</div>
            </button>
            <button type="button"
                class="invoice-kpi {{ $statusFilter === 'draft' ? 'invoice-kpi--active' : '' }}"
                wire:click="setStatusFilter('draft')">
                <div class="invoice-kpi__label">Brouillons</div>
                <div class="invoice-kpi__value">{{ $kpis['draft_count'] }}</div>
                <div class="invoice-kpi__meta">À émettre</div>
            </button>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="N° facture, n° de demande, client…"
                    aria-label="Rechercher une facture">
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
                @if ($hasFilters)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinit.</button>
                @endif
                <label class="client-filter-bar__per-page">
                    <span class="sr-only">Résultats par page</span>
                    <select class="input input-sm" wire:model.live="perPage" aria-label="Par page">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="client-status-pills" role="group" aria-label="Filtrer par statut">
            @foreach ([
                'all' => 'Toutes',
                'collect' => 'À encaisser',
                'overdue' => 'Échues',
                'paid' => 'Payées',
                'draft' => 'Brouillons',
                'cancelled' => 'Annulées',
            ] as $value => $label)
                <button type="button"
                    class="client-status-pill {{ $statusFilter === $value ? 'client-status-pill--active' : '' }}"
                    wire:click="setStatusFilter('{{ $value }}')">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($showAdvancedFilters)
            <div class="client-filter-panel">
                <div class="client-filter-panel__grid">
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Type</span>
                        <select class="input input-sm" wire:model.live="declarationFilter">
                            <option value="all">Tous</option>
                            <option value="declared">Avec déclaration</option>
                            <option value="non_declared">Sans déclaration</option>
                        </select>
                    </label>
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Client</span>
                        <select class="input input-sm" wire:model.live="clientFilter">
                            <option value="">Tous les clients</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Du</span>
                        <input class="input input-sm" type="date" wire:model.live="dateFrom">
                    </label>
                    <label class="client-filter-field">
                        <span class="client-filter-field__label">Au</span>
                        <input class="input input-sm" type="date" wire:model.live="dateTo">
                    </label>
                </div>
            </div>
        @endif

        @if ($hasFilters)
            <div class="client-filter-chips">
                @if ($statusFilter !== 'all')
                    <span class="client-filter-chip">
                        {{ [
                            'collect' => 'À encaisser',
                            'overdue' => 'Échues',
                            'paid' => 'Payées',
                            'draft' => 'Brouillons',
                            'cancelled' => 'Annulées',
                            'issued' => 'Émises',
                            'partial' => 'Partielles',
                        ][$statusFilter] ?? $statusFilter }}
                        <button type="button" wire:click="setStatusFilter('all')" aria-label="Retirer le filtre statut">×</button>
                    </span>
                @endif
                @if ($declarationFilter !== 'all')
                    <span class="client-filter-chip">
                        {{ $declarationFilter === 'declared' ? 'Avec déclaration' : 'Sans déclaration' }}
                        <button type="button" wire:click="$set('declarationFilter', 'all')" aria-label="Retirer le filtre type">×</button>
                    </span>
                @endif
                @if ($clientFilter !== '')
                    <span class="client-filter-chip">
                        {{ $clientName ?? 'Client' }}
                        <button type="button" wire:click="$set('clientFilter', '')" aria-label="Retirer le filtre client">×</button>
                    </span>
                @endif
                @if ($dateFrom !== '' || $dateTo !== '')
                    <span class="client-filter-chip">
                        {{ $dateFrom !== '' ? \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') : '…' }}
                        →
                        {{ $dateTo !== '' ? \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') : '…' }}
                        <button type="button" wire:click="clearPeriod" aria-label="Retirer la période">×</button>
                    </span>
                @endif
                @if ($search !== '')
                    <span class="client-filter-chip">
                        « {{ \Illuminate\Support\Str::limit($search, 24) }} »
                        <button type="button" wire:click="$set('search', '')" aria-label="Effacer la recherche">×</button>
                    </span>
                @endif
            </div>
        @endif

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° facture</th>
                        <th>Type</th>
                        <th>Client</th>
                        <th>N° de demande</th>
                        <th>Date</th>
                        <th>Échéance</th>
                        <th>Total</th>
                        <th>Payé</th>
                        <th>Solde</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $inv)
                    <tr @class(['invoice-row--overdue' => $inv->isOverdue()])>
                        <td>
                            <div class="invoice-number-cell">
                                <strong>{{ $inv->invoice_number }}</strong>
                                @if ($inv->isOverdue())
                                    <span class="invoice-overdue-pill" title="Échue depuis {{ $inv->daysOverdue() }} jour(s)">
                                        <span class="invoice-overdue-pill__dot" aria-hidden="true"></span>
                                        Échu
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td><span class="badge {{ $inv->declaration_type === 'declared' ? 'badge-info' : 'badge-secondary' }}">{{ \InovCom\Invoicing\Models\Invoice::declarationLabel($inv->declaration_type) }}</span></td>
                        <td>
                            @php $name = $inv->client->name ?? '—'; @endphp
                            <span title="{{ $name }}">{{ \Illuminate\Support\Str::limit($name, 30) }}</span>
                        </td>
                        <td>
                            @if (filled($inv->customer_reference))
                                <code style="font-size:12px;">{{ $inv->customer_reference }}</code>
                            @else
                                <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                        <td>{{ $inv->invoice_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            @if ($inv->due_date)
                                <span @class(['invoice-due--late' => $inv->isOverdue()])>{{ $inv->due_date->format('d/m/Y') }}</span>
                            @else
                                <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                        <td>{{ fmt_money($inv->total) }}</td>
                        <td>{{ fmt_money($inv->amount_paid) }}</td>
                        <td><strong>{{ fmt_money($inv->balance) }}</strong></td>
                        <td>
                            @php $badge = match($inv->status) {
                                'paid' => 'badge-success',
                                'partial' => 'badge-info',
                                'cancelled' => 'badge-error',
                                'issued' => 'badge-warning',
                                default => 'badge-secondary',
                            }; @endphp
                            <span class="badge {{ $badge }}">{{ \InovCom\Invoicing\Models\Invoice::statusLabel($inv->status) }}</span>
                            @php
                                $deliveryStatus = ($deliveryByInvoice[$inv->id]['status'] ?? null)
                                    ?? ($inv->quotation->fulfillment_status ?? '');
                            @endphp
                            @if ($deliveryStatus === 'partial')
                                <div style="margin-top:4px;"><span class="badge badge-warning">Livraison partielle</span></div>
                            @elseif ($deliveryStatus === 'delivered')
                                <div style="margin-top:4px;"><span class="badge badge-success">Livré</span></div>
                            @endif
                            @if ($inv->isOverdue())
                                <div style="font-size:10px; color:#b91c1c; font-weight:600; margin-top:3px;">{{ $inv->daysOverdue() }} j de retard</div>
                            @elseif (!in_array($inv->status, ['paid', 'cancelled', 'draft'], true))
                                <div style="font-size:11px; color:#666;">{{ number_format($inv->paymentProgressPercent(), 0) }}% payé</div>
                            @endif
                        </td>
                        <td>
                            <div class="invoice-row-actions">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.edit', [$inv->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @if ($canPay && $inv->canReceivePayment())
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoice_payments.pay', [$inv->id, 'tenant' => $tenantCode]) }}">Encaisser</a>
                                @endif
                                @if (!in_array($inv->status, ['draft', 'cancelled']))
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.print', [$inv->id, 'tenant' => $tenantCode]) }}">Imprimer</a>
                                @endif
                                @if ($inv->status === 'draft' && $canIssue)
                                    <button class="btn btn-primary btn-sm" wire:click="issue({{ $inv->id }})">Émettre</button>
                                @endif
                                @if ($inv->status === 'draft' && $canUpdate)
                                    <button class="btn btn-secondary btn-sm" wire:click="deleteDraft({{ $inv->id }})"
                                            wire:confirm="Supprimer définitivement ce brouillon ?">Supprimer</button>
                                @endif
                                @if ($canCancel && !in_array($inv->status, ['paid', 'cancelled', 'draft']) && (float) $inv->amount_paid <= 0)
                                    <button class="btn btn-secondary btn-sm" wire:click="cancel({{ $inv->id }})" wire:confirm="Annuler cette facture ?">Annuler</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11">
                            <div class="invoice-empty">
                                <strong>Aucune facture{{ $hasFilters ? ' pour ces critères' : '' }}.</strong>
                                @if ($hasFilters)
                                    <p>Élargissez la recherche ou réinitialisez les filtres.</p>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
                                @elseif ($canCreate)
                                    <p>Créez une facture ou facturez depuis une livraison confirmée.</p>
                                    <div class="invoice-empty__actions">
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoicing.create', ['tenant' => $tenantCode]) }}">Nouvelle facture</a>
                                        @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.deliveries.index'))
                                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.deliveries.index', ['tenant' => $tenantCode]) }}">Voir les livraisons</a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">{{ $invoices->links() }}</div>
    </section>
</div>
