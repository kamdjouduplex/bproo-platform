@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;

    $methodLabels = [
        'cash' => 'Espèces',
        'check' => 'Chèque',
        'bank_transfer' => 'Virement',
        'mobile_money' => 'Mobile Money',
        'other' => 'Autre',
    ];

    $statusLabels = [
        'all' => 'Tous',
        'draft' => 'Brouillon',
        'pending' => 'En attente',
        'approved' => 'Approuvé',
        'rejected' => 'Rejeté',
        'paid' => 'Payé',
    ];
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <div class="expenses-summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px;">
        <div class="card" style="padding:14px 16px;margin:0;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">Total filtré</div>
            <div style="font-size:1.25rem;font-weight:700;color:#0f172a;margin-top:4px;">{{ fmt_money($totalAmount) }} <span style="font-size:12px;font-weight:600;color:#94a3b8;">{{ currency_label() }}</span></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Hors rejetés (sauf filtre Rejeté)</div>
        </div>
        <div class="card" style="padding:14px 16px;margin:0;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">En attente</div>
            <div style="font-size:1.25rem;font-weight:700;color:#b45309;margin-top:4px;">{{ $pendingCount }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">À approuver</div>
        </div>
        <div class="card" style="padding:14px 16px;margin:0;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">Brouillons</div>
            <div style="font-size:1.25rem;font-weight:700;color:#475569;margin-top:4px;">{{ $draftCount }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Non soumis</div>
        </div>
        <div class="card" style="padding:14px 16px;margin:0;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">Cette page</div>
            <div style="font-size:1.25rem;font-weight:700;color:#0f172a;margin-top:4px;">{{ $expenses->total() }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Résultat{{ $expenses->total() > 1 ? 's' : '' }}</div>
        </div>
    </div>

    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Dépenses</h2>
            <div class="client-list-head__actions">
                @if ($canExport ?? true)
                    <x-export-btn format="excel" class="btn-sm" wire:click="exportExcel">Exporter Excel</x-export-btn>
                    <x-export-btn format="pdf" class="btn-sm" wire:click="exportPdf">Exporter PDF</x-export-btn>
                @endif
                @if ($canCreate)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.expenses.create', ['tenant' => $tenantCode]) }}">Nouvelle dépense</a>
                @endif
            </div>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Référence, description…"
                    aria-label="Rechercher une dépense">
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
                <button type="button"
                    class="client-status-pill {{ $statusFilter === $value ? 'client-status-pill--active' : '' }}"
                    wire:click="$set('statusFilter', '{{ $value }}')">
                    {{ $label }}
                    @if ($value !== 'all' && isset($statusCounts[$value]))
                        <span style="opacity:.75;font-weight:700;">· {{ $statusCounts[$value] }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @if ($showAdvancedFilters)
            <div class="client-filter-panel">
                <div class="client-filter-panel__grid">
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
                        <span class="client-filter-field__label">Méthode</span>
                        <select class="input input-sm" wire:model.live="paymentMethodFilter">
                            <option value="">Toutes</option>
                            @foreach ($methodLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
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

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Date</th>
                        <th>Catégorie</th>
                        <th>Description</th>
                        <th>Montant</th>
                        <th>Méthode</th>
                        <th>Statut</th>
                        <th>Créé par</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr wire:key="expense-{{ $expense->id }}">
                            <td><strong>{{ $expense->reference }}</strong></td>
                            <td>{{ $expense->expense_date->format('d/m/Y') }}</td>
                            <td>{{ $expense->category?->name ?? '—' }}</td>
                            <td title="{{ $expense->description }}">{{ \Illuminate\Support\Str::limit($expense->description ?: '—', 48) }}</td>
                            <td style="white-space:nowrap;font-weight:700;">{{ fmt_money($expense->amount) }}</td>
                            <td>
                                <span class="badge badge-info">{{ $methodLabels[$expense->payment_method] ?? 'Autre' }}</span>
                            </td>
                            <td>
                                @if ($expense->status === 'draft')
                                    <span class="badge badge-neutral">Brouillon</span>
                                @elseif ($expense->status === 'pending')
                                    <span class="badge badge-warning">En attente</span>
                                @elseif ($expense->status === 'approved')
                                    <span class="badge badge-success">Approuvé</span>
                                @elseif ($expense->status === 'rejected')
                                    <span class="badge badge-error">Rejeté</span>
                                @else
                                    <span class="badge badge-info">Payé</span>
                                @endif
                            </td>
                            <td>{{ $expense->creator?->name ?? '—' }}</td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.expenses.edit', [$expense->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                @if ($canApprove && $expense->isPending())
                                    <button type="button" class="btn btn-success btn-sm" wire:click="approve({{ $expense->id }})" wire:confirm="Approuver cette dépense ?">Approuver</button>
                                    <button type="button" class="btn btn-error btn-sm" wire:click="openReject({{ $expense->id }})">Rejeter</button>
                                @endif
                                @if ($canApprove && $expense->isApproved())
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="markAsPaid({{ $expense->id }})" wire:confirm="Marquer comme payée ?">Payer</button>
                                @endif
                                @if ($canDelete && ($expense->isDraft() || $expense->isRejected()))
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $expense->id }})" wire:confirm="Supprimer cette dépense ?">Suppr.</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align:center;padding:28px 12px;color:#64748b;">
                                Aucune dépense trouvée.
                                @if ($canCreate)
                                    <div style="margin-top:10px;">
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.expenses.create', ['tenant' => $tenantCode]) }}">Créer une dépense</a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px;">{{ $expenses->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>

    @if ($rejectingId)
        <div class="modal-backdrop" style="position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:80;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="cancelReject">
            <div class="card" style="width:100%;max-width:420px;padding:20px;margin:0;" role="dialog" aria-modal="true" aria-labelledby="reject-expense-title">
                <h3 id="reject-expense-title" style="margin:0 0 8px;font-size:1.05rem;">Rejeter la dépense</h3>
                <p style="margin:0 0 14px;color:#64748b;font-size:13px;">Indiquez le motif du rejet. Il sera enregistré sur la dépense.</p>
                <label class="field-label" for="rejectionReason">Motif *</label>
                <textarea id="rejectionReason" class="input" rows="3" wire:model="rejectionReason" placeholder="Ex. : montant incorrect, pièce manquante…" style="width:100%;"></textarea>
                @error('rejectionReason') <div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div> @enderror
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                    <button type="button" class="btn btn-secondary" wire:click="cancelReject">Annuler</button>
                    <button type="button" class="btn btn-error" wire:click="confirmReject">Confirmer le rejet</button>
                </div>
            </div>
        </div>
    @endif
</div>
