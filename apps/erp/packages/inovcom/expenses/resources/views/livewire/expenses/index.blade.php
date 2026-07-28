@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">
            {{ session('error') }}
        </div>
    @endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Dépenses</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap; max-width: 720px;">
                <form wire:submit.prevent="applyFilters" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="Réf. ou description" style="min-width: 180px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model="statusFilter">
                    <option value="all">Tous les statuts</option>
                    <option value="draft">Brouillon</option>
                    <option value="pending">En attente</option>
                    <option value="approved">Approuvé</option>
                    <option value="rejected">Rejeté</option>
                    <option value="paid">Payé</option>
                </select>
                <select class="input input-sm" wire:model="categoryFilter">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <input class="input input-sm" type="date" wire:model="dateFrom" placeholder="Du">
                <input class="input input-sm" type="date" wire:model="dateTo" placeholder="Au">
                <select class="input input-sm" wire:model="perPage">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button type="button" class="btn btn-primary" wire:click="applyFilters">Rechercher</button>
                <button type="button" class="btn btn-secondary" wire:click="resetFilters">Réinitialiser</button>
                <a class="btn btn-primary" href="{{ route('tenant.expenses.create', ['tenant' => $tenantCode]) }}">Nouvelle dépense</a>
            </div>
        </div>

        @if ($totalAmount > 0)
            <div style="padding: 12px; background: #f5f5f5; border-radius: 4px; margin-bottom: 16px;">
                <strong>Total des dépenses: {{ fmt_money($totalAmount) }} FCFA</strong>
            </div>
        @endif

        <div class="table-scroll">
            <table>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expenses as $expense)
                        <tr>
                            <td><strong>{{ $expense->reference }}</strong></td>
                            <td>{{ $expense->expense_date->format('d/m/Y') }}</td>
                            <td>{{ $expense->category->name }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($expense->description ?? '-', 50) }}</td>
                            <td><strong>{{ fmt_money($expense->amount) }} FCFA</strong></td>
                            <td>
                                @if ($expense->payment_method === 'cash')
                                    <span class="badge badge-info">Espèces</span>
                                @elseif ($expense->payment_method === 'check')
                                    <span class="badge badge-info">Chèque</span>
                                @elseif ($expense->payment_method === 'bank_transfer')
                                    <span class="badge badge-info">Virement</span>
                                @elseif ($expense->payment_method === 'mobile_money')
                                    <span class="badge badge-info">Mobile Money</span>
                                @else
                                    <span class="badge badge-info">Autre</span>
                                @endif
                            </td>
                            <td>
                                @if ($expense->status === 'draft')
                                    <span class="badge badge-secondary">Brouillon</span>
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
                            <td>{{ $expense->creator->name ?? '-' }}</td>
                            <td>
                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.expenses.edit', [$expense->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                    @if ($expense->isPending())
                                        <button class="btn btn-success btn-sm" wire:click="approve({{ $expense->id }})" onclick="return confirm('Approuver cette dépense ?')">Approuver</button>
                                        <button class="btn btn-error btn-sm" wire:click="reject({{ $expense->id }})">Rejeter</button>
                                    @endif
                                    @if ($expense->isApproved())
                                        <button class="btn btn-primary btn-sm" wire:click="markAsPaid({{ $expense->id }})" onclick="return confirm('Marquer comme payée ?')">Marquer payé</button>
                                    @endif
                                    @if ($expense->isDraft() || $expense->isRejected())
                                        <button class="btn btn-secondary btn-sm" wire:click="delete({{ $expense->id }})" onclick="return confirm('Supprimer cette dépense ?')">Supprimer</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if ($expenses->count() === 0)
                        <tr>
                            <td colspan="9">Aucune dépense trouvée.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $expenses->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </section>
</div>
