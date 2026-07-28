@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif
    @if (!$validationWorkflowReady)
        <div class="alert alert-error" style="margin-bottom: 16px;">Validation des dettes indisponible: exécutez les migrations tenant du module Dettes.</div>
    @endif
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Dettes</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <form wire:submit.prevent="applyFilters" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="Réf. ou client" style="min-width: 180px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model="statusFilter">
                    <option value="all">Tous</option>
                    <option value="open">Ouvert</option>
                    <option value="partial">Partiel</option>
                    <option value="paid">Soldé</option>
                    <option value="overdue">En retard</option>
                </select>
                <select class="input input-sm" wire:model="clientFilter">
                    <option value="">Tous les clients</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
                <input class="input input-sm" type="date" wire:model="dateFrom">
                <input class="input input-sm" type="date" wire:model="dateTo">
                <select class="input input-sm" wire:model="perPage"><option value="20">20</option><option value="50">50</option><option value="100">100</option></select>
                <button type="button" class="btn btn-primary" wire:click="applyFilters">Rechercher</button>
                <button type="button" class="btn btn-secondary" wire:click="resetFilters">Réinitialiser</button>
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.debts.create', ['tenant' => $tenantCode]) }}">Nouvelle dette</a>
                @endif
            </div>
        </div>
        @if ($totalOutstanding > 0)
            <div style="padding: 12px; background: #fef3c7; border-radius: 4px; margin-bottom: 16px;"><strong>Total des dettes impayées: {{ fmt_money($totalOutstanding) }} FCFA</strong></div>
        @endif
        <div class="table-scroll">
            <table>
                <thead><tr><th>Référence</th><th>Client</th><th>Date ouverture</th><th>Échéance</th><th>Montant total</th><th>Solde restant</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                    @foreach ($debts as $debt)
                    <tr>
                        <td><strong>{{ $debt->reference }}</strong></td>
                        <td>{{ $debt->client->name }} ({{ $debt->client->code }})</td>
                        <td>{{ $debt->opened_at->format('d/m/Y') }}</td>
                        <td>{{ $debt->due_date ? $debt->due_date->format('d/m/Y') : '-' }}</td>
                        <td>{{ fmt_money($debt->total_amount) }} FCFA</td>
                        <td><strong>{{ fmt_money($debt->balance) }} FCFA</strong></td>
                        <td>
                            @if (!$debt->is_validated)
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
                            @if ($validationWorkflowReady && !$debt->is_validated && $canValidate)
                                <button class="btn btn-primary btn-sm" wire:click="validateDebt({{ $debt->id }})">Valider</button>
                            @endif
                            @if (!$debt->isPaid() && (float) $debt->balance > 0 && (!$validationWorkflowReady || $debt->is_validated) && $canReceivePayment)
                                <a class="btn btn-primary btn-sm" href="{{ route('tenant.debts.pay', [$debt->id, 'tenant' => $tenantCode]) }}">Encaisser</a>
                            @endif
                            @if ($debt->isPaid() && $canDelete)
                                <button class="btn btn-secondary btn-sm" wire:click="delete({{ $debt->id }})" onclick="return confirm('Supprimer ?')">Supprimer</button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @if ($debts->count() === 0)<tr><td colspan="8">Aucune dette enregistrée.</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">{{ $debts->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
