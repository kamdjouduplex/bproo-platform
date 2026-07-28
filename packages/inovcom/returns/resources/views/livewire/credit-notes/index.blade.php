@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="margin-bottom:16px;"><a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.index', ['tenant' => $tenantCode]) }}">&larr; Retours</a></div>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Avoirs clients</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input class="input input-sm" type="text" wire:model.live.debounce.300ms="search" placeholder="N° avoir / client" style="min-width:200px;">
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="all">Tous statuts</option>
                    @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <button class="btn btn-secondary" wire:click="resetFilters">Réinitialiser</button>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>N° avoir</th><th>Client</th><th>Date</th><th>Total</th><th>Utilisé</th><th>Reste</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    @foreach ($creditNotes as $cn)
                    <tr>
                        <td><strong>{{ $cn->credit_note_number }}</strong></td>
                        <td>{{ $cn->client?->name ?? '—' }}</td>
                        <td>{{ $cn->issue_date?->format('d/m/Y') }}</td>
                        <td>{{ fmt_money($cn->total) }}</td>
                        <td>{{ fmt_money($cn->used_amount) }}</td>
                        <td><strong>{{ fmt_money($cn->remaining_amount) }}</strong></td>
                        <td><span class="badge {{ $cn->status?->badgeClass() }}">{{ $cn->status?->label() }}</span></td>
                        <td><a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.credit_notes.show', [$cn->id, 'tenant' => $tenantCode]) }}">Voir</a></td>
                    </tr>
                    @endforeach
                    @if ($creditNotes->count() === 0)<tr><td colspan="8">Aucun avoir.</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div style="padding:12px;">{{ $creditNotes->links() }}</div>
    </section>
</div>
