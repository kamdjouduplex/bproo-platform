@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:16px;">
        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.credit_notes.index', ['tenant' => $tenantCode]) }}">&larr; Avoirs</a>
        <span class="badge {{ $creditNote->status?->badgeClass() }}" style="font-size:13px;">{{ $creditNote->status?->label() }}</span>
    </div>

    <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; align-items:start;">
        <div>
            <section class="card" style="padding:16px; margin-bottom:16px;">
                <div class="table-title" style="margin-bottom:12px;">Avoir {{ $creditNote->credit_note_number }}</div>
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px;">
                    <div><span style="color:#666;">Client</span><br>{{ $creditNote->client?->name }}</div>
                    <div><span style="color:#666;">Date</span><br>{{ $creditNote->issue_date?->format('d/m/Y') }}</div>
                    <div><span style="color:#666;">Retour</span><br>
                        @if ($creditNote->returnRequest)
                            <a href="{{ route('tenant.returns.show', [$creditNote->return_id, 'tenant' => $tenantCode]) }}">{{ $creditNote->returnRequest->return_number }}</a>
                        @else — @endif
                    </div>
                    <div><span style="color:#666;">Facture liée</span><br>{{ optional($creditNote->returnRequest)->source_number ?? '—' }}</div>
                    <div><span style="color:#666;">Total</span><br><strong>{{ fmt_money($creditNote->total) }}</strong></div>
                    <div><span style="color:#666;">Reste à utiliser</span><br><strong>{{ fmt_money($creditNote->remaining_amount) }}</strong></div>
                </div>
            </section>

            <section class="card app-table-card" style="margin-bottom:16px;">
                <div class="table-toolbar"><div class="table-title">Lignes</div></div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Article</th><th>Qté</th><th>PU</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach ($creditNote->items as $item)
                            <tr><td><x-item-label :reference="$item->item_sku" :name="$item->item_name" /></td><td>{{ fmt_num($item->quantity) }}</td><td>{{ fmt_money($item->unit_price) }}</td><td>{{ fmt_money($item->line_total) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($creditNote->refunds->count() > 0)
            <section class="card app-table-card">
                <div class="table-toolbar"><div class="table-title">Remboursements liés</div></div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>N°</th><th>Méthode</th><th>Montant</th><th>Statut</th><th>Date</th></tr></thead>
                        <tbody>
                            @foreach ($creditNote->refunds as $rf)
                            <tr><td>{{ $rf->refund_number }}</td><td>{{ $rf->method?->label() }}</td><td>{{ fmt_money($rf->amount) }}</td><td><span class="badge {{ $rf->status?->badgeClass() }}">{{ $rf->status?->label() }}</span></td><td>{{ $rf->refund_date?->format('d/m/Y') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @endif
        </div>

        {{-- Résolution --}}
        <div>
            @if ($creditNote->isUsable())
            <section class="card" style="padding:16px; margin-bottom:16px;">
                <div class="table-title" style="margin-bottom:12px;">Imputer sur la facture</div>
                <p style="font-size:12px; color:#666;">Réduit la créance de la facture liée (avant paiement).</p>
                <input class="input input-sm" type="number" step="0.01" wire:model="applyAmount" placeholder="Montant (vide = max)" style="margin-bottom:8px;">
                <button class="btn btn-primary" wire:click="applyToInvoice" @disabled(! $canUse) style="width:100%;">Imputer</button>
            </section>

            <section class="card" style="padding:16px; margin-bottom:16px;">
                <div class="table-title" style="margin-bottom:12px;">Rembourser</div>
                <p style="font-size:12px; color:#666;">Pour un client ayant déjà payé.</p>
                <select class="input input-sm" wire:model="refundMethod" style="margin-bottom:8px;">
                    @foreach ($methods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <input class="input input-sm" type="number" step="0.01" wire:model="refundAmount" placeholder="Montant (vide = max)" style="margin-bottom:8px;">
                <input class="input input-sm" type="text" wire:model="refundReference" placeholder="Référence externe" style="margin-bottom:8px;">
                <button class="btn btn-primary" wire:click="issueRefund" @disabled(! $canRefund) style="width:100%;">Émettre le remboursement</button>
            </section>

            <section class="card" style="padding:16px; margin-bottom:16px;">
                <div class="table-title" style="margin-bottom:12px;">Convertir en crédit client</div>
                <input class="input input-sm" type="number" step="0.01" wire:model="creditAmount" placeholder="Montant (vide = max)" style="margin-bottom:8px;">
                <button class="btn btn-secondary" wire:click="convertToCredit" @disabled(! $canCredit) style="width:100%;">Créditer le portefeuille</button>
            </section>
            @endif

            <section class="card" style="padding:16px;">
                <div class="table-title" style="margin-bottom:8px;">Portefeuille client</div>
                <div style="font-size:20px; font-weight:700;">{{ fmt_money($walletBalance) }}</div>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.customer_credits.index', ['tenant' => $tenantCode, 'client' => $creditNote->client_id]) }}" style="margin-top:8px;">Voir le détail</a>
            </section>
        </div>
    </div>
</div>
