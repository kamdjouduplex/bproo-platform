@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
        <a class="btn btn-secondary" href="{{ route('tenant.returns.credit_notes.index', ['tenant' => $tenantCode]) }}">Avoirs</a>
        <a class="btn btn-secondary" href="{{ route('tenant.returns.refunds.index', ['tenant' => $tenantCode]) }}">Remboursements</a>
        <a class="btn btn-secondary" href="{{ route('tenant.returns.customer_credits.index', ['tenant' => $tenantCode]) }}">Crédits clients</a>
    </div>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Retours clients</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input class="input input-sm" type="text" wire:model.live.debounce.300ms="search" placeholder="N° retour / facture / client" style="min-width:200px;">
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="all">Tous statuts</option>
                    @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <select class="input input-sm" wire:model.live="clientFilter">
                    <option value="">Tous clients</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
                <input class="input input-sm" type="date" wire:model.live="dateFrom" title="Du">
                <input class="input input-sm" type="date" wire:model.live="dateTo" title="Au">
                <button type="button" class="btn btn-secondary" wire:click="resetFilters">Réinitialiser</button>
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.returns.create', ['tenant' => $tenantCode]) }}">Nouveau retour</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° retour</th><th>Facture</th><th>Client</th><th>Date</th><th>Type</th><th>Montant</th><th>Statut</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($returns as $r)
                    <tr>
                        <td><strong>{{ $r->return_number }}</strong></td>
                        <td>{{ $r->source_number ?? '—' }}</td>
                        <td>{{ $r->client?->name ?? '—' }}</td>
                        <td>{{ $r->return_date?->format('d/m/Y') }}</td>
                        <td>{{ $r->type?->label() }}</td>
                        <td>{{ fmt_money($r->total_amount) }}</td>
                        <td><span class="badge {{ $r->status?->badgeClass() }}">{{ $r->status?->label() }}</span></td>
                        <td style="display:flex; gap:4px; flex-wrap:wrap;">
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.show', [$r->id, 'tenant' => $tenantCode]) }}">Voir</a>
                            @if ($canCancel && !$r->status?->isTerminal())
                                <button class="btn btn-secondary btn-sm" wire:click="cancel({{ $r->id }})" onclick="return confirm('Annuler ce retour ?')">Annuler</button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @if ($returns->count() === 0)<tr><td colspan="8">Aucun retour enregistré.</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div style="padding:12px;">{{ $returns->links() }}</div>
    </section>
</div>
