@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="margin-bottom:16px;">
        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.index', ['tenant' => $tenantCode]) }}">&larr; Retours</a>
    </div>

    @if (! $invoice)
        <section class="card app-table-card">
            <div class="table-toolbar">
                <div class="table-title">Choisir la facture d'origine</div>
                <input class="input input-sm" type="text" wire:model.live.debounce.300ms="invoiceSearch" placeholder="N° facture ou client" style="min-width:240px;">
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>N° facture</th><th>Client</th><th>Date</th><th>Total</th><th>Solde</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $inv)
                        @php $badge = match($inv->status) {
                            'paid' => 'badge-success',
                            'partial' => 'badge-info',
                            'cancelled' => 'badge-error',
                            'issued' => 'badge-warning',
                            'superseded' => 'badge-secondary',
                            default => 'badge-secondary',
                        }; @endphp
                        <tr>
                            <td><strong>{{ $inv->invoice_number }}</strong></td>
                            <td>{{ $inv->client?->name }}</td>
                            <td>{{ $inv->invoice_date?->format('d/m/Y') }}</td>
                            <td>{{ fmt_money($inv->total) }}</td>
                            <td>{{ fmt_money($inv->balance) }}</td>
                            <td>
                                <span class="badge {{ $badge }}">{{ \InovCom\Invoicing\Models\Invoice::statusLabel($inv->status) }}</span>
                                @if ($inv->isOverdue())<div style="font-size:10px; color:#b91c1c; font-weight:600; margin-top:3px;">{{ $inv->daysOverdue() }} j de retard</div>@endif
                            </td>
                            <td><button class="btn btn-primary btn-sm" wire:click="selectInvoice({{ $inv->id }})">Sélectionner</button></td>
                        </tr>
                        @endforeach
                        @if ($invoices->count() === 0)<tr><td colspan="7">Aucune facture éligible.</td></tr>@endif
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="card" style="padding:16px; margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>
                    <div style="font-weight:700; font-size:16px;">Facture {{ $invoice->invoice_number }}</div>
                    <div style="color:#666;">Client : {{ $invoice->client?->name }} — Solde : {{ fmt_money($invoice->balance) }}</div>
                </div>
                <button class="btn btn-secondary btn-sm" wire:click="clearInvoice">Changer de facture</button>
            </div>
        </section>

        <section class="card app-table-card">
            <div class="table-toolbar"><div class="table-title">Articles à retourner</div></div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Article</th><th>Retournable</th><th>Quantité retournée</th><th>Motif (ligne)</th></tr></thead>
                    <tbody>
                        @foreach ($lines as $lineId => $row)
                        <tr @if($row['returnable'] <= 0) style="opacity:.5;" @endif>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ fmt_num($row['returnable']) }}</td>
                            <td style="max-width:150px;">
                                <input class="input input-sm" type="number" min="0" step="0.001" max="{{ $row['returnable'] }}"
                                    wire:model="lines.{{ $lineId }}.quantity" @if($row['returnable'] <= 0) disabled @endif>
                            </td>
                            <td style="max-width:200px;">
                                <select class="input input-sm" wire:model="lines.{{ $lineId }}.reason_id">
                                    <option value="">— motif global —</option>
                                    @foreach ($reasons as $reason)<option value="{{ $reason->id }}">{{ $reason->label }}</option>@endforeach
                                </select>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="padding:16px; display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px;">
                <div>
                    <label class="form-label">Date du retour</label>
                    <input class="input" type="date" wire:model="returnDate">
                </div>
                <div>
                    <label class="form-label">Motif global</label>
                    <select class="input" wire:model="reasonId">
                        <option value="">— sélectionner —</option>
                        @foreach ($reasons as $reason)<option value="{{ $reason->id }}">{{ $reason->label }}</option>@endforeach
                    </select>
                </div>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Notes</label>
                    <textarea class="input" rows="2" wire:model="notes" placeholder="Observations, contexte du retour..."></textarea>
                </div>
            </div>

            <div style="padding:0 16px 16px; display:flex; gap:8px;">
                <button class="btn btn-primary" wire:click="save">Créer le retour (brouillon)</button>
                <a class="btn btn-secondary" href="{{ route('tenant.returns.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            </div>
        </section>
    @endif
</div>
