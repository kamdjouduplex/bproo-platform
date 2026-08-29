@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Devis</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <input class="input input-sm" type="text" wire:model.live.debounce.300ms="search" placeholder="N° devis, demande ou client" style="min-width: 220px;">
                <select class="input input-sm" wire:model="statusFilter">
                    <option value="all">Tous statuts</option>
                    <option value="draft">Brouillon</option>
                    <option value="sent">Envoyé</option>
                    <option value="accepted">Accepté</option>
                    <option value="suspended">Suspendu</option>
                    <option value="rejected">Rejeté</option>
                </select>
                <select class="input input-sm" wire:model="clientFilter">
                    <option value="">Tous clients</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
                <button type="button" class="btn btn-secondary" wire:click="resetFilters">Réinitialiser</button>
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.quotations.create', ['tenant' => $tenantCode]) }}">Nouveau devis</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Client</th>
                        <th>N° de Demande</th>
                        <th>Date</th>
                        <th>Validité</th>
                        <th>Rév.</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quotations as $q)
                    <tr>
                        <td><strong>{{ $q->number }}</strong></td>
                        <td>
                            @php $clientName = $q->client->name ?? '—'; @endphp
                            <span title="{{ $clientName }}">{{ \Illuminate\Support\Str::limit($clientName, 30) }}</span>
                        </td>
                        <td>
                            @if (filled($q->customer_purchase_order))
                                <code style="font-size:12px;">{{ $q->customer_purchase_order }}</code>
                            @else
                                <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                        <td>{{ $q->quote_date->format('d/m/Y') }}</td>
                        <td>{{ $q->valid_until ? $q->valid_until->format('d/m/Y') : '—' }}</td>
                        <td>{{ $q->revision }}</td>
                        <td>{{ fmt_money($q->total) }} FCFA</td>
                        <td>
                            @php $badge = match(true) {
                                in_array($q->status, ['accepted', 'validated']) && ($q->fulfillment_status ?? '') === 'delivered' => 'badge-success',
                                in_array($q->status, ['accepted', 'validated']) && ($q->fulfillment_status ?? '') === 'partial' => 'badge-warning',
                                in_array($q->status, ['accepted', 'validated']) => 'badge-success',
                                $q->status === 'rejected' => 'badge-error',
                                $q->status === 'suspended' => 'badge-warning',
                                $q->status === 'sent' => 'badge-info',
                                default => 'badge-secondary',
                            }; @endphp
                            <span class="badge {{ $badge }}">{{ $q->commercialStatusLabel() }}</span>
                        </td>
                        <td style="display:flex; gap:4px; flex-wrap:wrap;">
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.quotations.edit', [$q->id, 'tenant' => $tenantCode]) }}">Voir</a>
                            @if ($canCreate)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="duplicate({{ $q->id }})">Dupliquer</button>
                            @endif
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.quotations.print', [$q->id, 'tenant' => $tenantCode]) }}">Imprimer</a>
                        </td>
                    </tr>
                    @endforeach
                    @if ($quotations->count() === 0)<tr><td colspan="9">Aucun devis.</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div style="padding: 12px;">{{ $quotations->links() }}</div>
    </section>
</div>
