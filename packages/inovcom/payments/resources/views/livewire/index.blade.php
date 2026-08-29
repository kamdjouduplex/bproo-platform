@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif

    <div class="card" style="margin-bottom: 16px; padding: 12px;">
        <strong>Encours factures impayées :</strong> {{ fmt_money($totalOutstanding) }} FCFA
    </div>

    <section class="card app-table-card" style="margin-bottom: 24px;">
        <div class="table-toolbar">
            <div class="table-title">Factures — statut paiement</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input class="input input-sm" wire:model.live.debounce.300ms="search" placeholder="N° ou client" aria-label="Rechercher facture ou client">
                <select class="input input-sm" wire:model.live="invoiceStatusFilter" aria-label="Filtrer par statut de paiement">
                    <option value="unpaid">Impayées / partielles</option>
                    <option value="paid">Soldées</option>
                    <option value="all">Toutes émises</option>
                </select>
                @if ($canManageWithholdings ?? false)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoice_payments.withholding_types', ['tenant' => $tenantCode]) }}">Types de retenues</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>N°</th><th>Client</th><th>Total</th><th>Payé</th><th>Solde</th><th>%</th><th>Statut</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $inv)
                    <tr>
                        <td>{{ $inv->invoice_number }}</td>
                        <td>{{ $inv->client->name }}</td>
                        <td>{{ fmt_money($inv->total) }}</td>
                        <td>{{ fmt_money($inv->amount_paid) }}</td>
                        <td><strong>{{ fmt_money($inv->balance) }}</strong></td>
                        <td>{{ fmt_money($inv->paymentProgressPercent(), 0) }}%</td>
                        <td><span class="badge badge-info">{{ \InovCom\Invoicing\Models\Invoice::statusLabel($inv->status) }}</span></td>
                        <td>
                            @if ($canReceive && $inv->canReceivePayment())
                                <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoice_payments.pay', [$inv->id, 'tenant' => $tenantCode]) }}">Encaisser</a>
                            @endif
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.edit', [$inv->id, 'tenant' => $tenantCode]) }}">Détail</a>
                        </td>
                    </tr>
                    @endforeach
                    @if ($invoices->isEmpty())
                        <tr>
                            <td colspan="8" style="text-align:center;color:#6b7280;padding:16px;">
                                Aucune facture pour ce filtre.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="padding:12px;">{{ $invoices->links() }}</div>
    </section>

    <section class="card app-table-card">
        <div class="table-title" style="padding:12px;">Derniers paiements</div>
        <table>
            <thead><tr><th>Réf.</th><th>Facture</th><th>Client</th><th>Date</th><th>Montant</th><th>Méthode</th></tr></thead>
            <tbody>
                @foreach ($recentPayments as $p)
                <tr>
                    <td>{{ $p->reference }}</td>
                    <td>{{ $p->invoice->invoice_number }}</td>
                    <td>{{ $p->invoice->client->name }}</td>
                    <td>{{ $p->payment_date->format('d/m/Y') }}</td>
                    <td>{{ fmt_money($p->amount) }}</td>
                    <td>{{ \InovCom\InvoicePayments\Models\InvoicePayment::methodLabel($p->payment_method) }}</td>
                </tr>
                @endforeach
                @if ($recentPayments->isEmpty())<tr><td colspan="6">Aucun paiement.</td></tr>@endif
            </tbody>
        </table>
    </section>
</div>
