@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    <div style="margin-bottom:16px;"><a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.index', ['tenant' => $tenantCode]) }}">&larr; Retours</a></div>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">
                Crédits clients
                @if (! is_null($balance))<span class="badge badge-success" style="margin-left:8px;">Solde : {{ fmt_money($balance) }}</span>@endif
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <select class="input input-sm" wire:model.live="client">
                    <option value="">Tous les clients</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Date</th><th>Client</th><th>Sens</th><th>Montant</th><th>Solde après</th><th>Origine</th><th>Référence</th></tr></thead>
                <tbody>
                    @foreach ($entries as $e)
                    <tr>
                        <td>{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $e->client?->name ?? '—' }}</td>
                        <td>
                            @if ($e->direction === 'credit')<span class="badge badge-success">Crédit</span>
                            @else<span class="badge badge-warning">Débit</span>@endif
                        </td>
                        <td>{{ fmt_money($e->amount) }}</td>
                        <td>{{ fmt_money($e->balance_after) }}</td>
                        <td>{{ $e->source_type }}</td>
                        <td>{{ $e->reference ?? '—' }}</td>
                    </tr>
                    @endforeach
                    @if ($entries->count() === 0)<tr><td colspan="7">Aucune écriture de crédit.</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div style="padding:12px;">{{ $entries->links() }}</div>
    </section>
</div>
