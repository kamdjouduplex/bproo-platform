@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Pertes</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <form wire:submit.prevent="applyFilters" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="Réf. ou article" style="min-width: 180px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model="statusFilter">
                    <option value="all">Tous</option>
                    <option value="draft">Brouillon</option>
                    <option value="confirmed">Confirmé</option>
                </select>
                <select class="input input-sm" wire:model="reasonFilter">
                    <option value="">Toutes les raisons</option>
                    @foreach ($reasons as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
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
                <a class="btn btn-primary" href="{{ route('tenant.losses.create', ['tenant' => $tenantCode]) }}">Nouvelle perte</a>
            </div>
        </div>

        @if ($totalValue > 0)
            <div style="padding: 12px; background: #fef2f2; border-radius: 4px; margin-bottom: 16px;">
                <strong>Total des pertes (confirmées): {{ fmt_money($totalValue) }} FCFA</strong>
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
                        <tr>
                            <td><strong>{{ $record->reference }}</strong></td>
                            <td>{{ $record->loss_date->format('d/m/Y') }}</td>
                            <td>{{ $record->item->name }}</td>
                            <td>{{ $record->reason->name }}</td>
                            <td>{{ fmt_num($record->quantity) }} {{ $record->item->unit->abbreviation ?? 'pc' }}</td>
                            <td><strong>{{ fmt_money($record->value) }} FCFA</strong></td>
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
                                        <button class="btn btn-success btn-sm" wire:click="confirmLoss({{ $record->id }})" onclick="return confirm('Confirmer cette perte et déduire le stock ?')">Confirmer</button>
                                    @endif
                                    @if ($canDeleteLoss)
                                        <button class="btn btn-error btn-sm" wire:click="delete({{ $record->id }})" onclick="return confirm('Supprimer cette perte ?')">Supprimer</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($records->count() === 0)
                        <tr><td colspan="8">Aucune perte enregistrée.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $records->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </section>
</div>
