@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="margin-bottom:16px;"><a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.index', ['tenant' => $tenantCode]) }}">&larr; Retours</a></div>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Remboursements</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input class="input input-sm" type="text" wire:model.live.debounce.300ms="search" placeholder="N° / client" style="min-width:200px;">
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="all">Tous statuts</option>
                    @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <button class="btn btn-secondary" wire:click="resetFilters">Réinitialiser</button>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>N°</th><th>Client</th><th>Méthode</th><th>Montant</th><th>Date</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    @foreach ($refunds as $rf)
                    <tr>
                        <td><strong>{{ $rf->refund_number }}</strong></td>
                        <td>{{ $rf->client?->name ?? '—' }}</td>
                        <td>{{ $rf->method?->label() }}</td>
                        <td>{{ fmt_money($rf->amount) }}</td>
                        <td>{{ $rf->refund_date?->format('d/m/Y') }}</td>
                        <td><span class="badge {{ $rf->status?->badgeClass() }}">{{ $rf->status?->label() }}</span></td>
                        <td>
                            @if ($canValidate && $rf->status?->value !== 'paid' && $rf->status?->value !== 'cancelled')
                                <button class="btn btn-primary btn-sm" wire:click="markPaid({{ $rf->id }})" onclick="return confirm('Marquer comme payé ?')">Marquer payé</button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @if ($refunds->count() === 0)<tr><td colspan="7">Aucun remboursement.</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div style="padding:12px;">{{ $refunds->links() }}</div>
    </section>
</div>
