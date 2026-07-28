@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Factures</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <input class="input input-sm" type="text" wire:model.live.debounce.300ms="search" placeholder="N° facture, demande ou client" style="min-width: 220px;">
                <select class="input input-sm" wire:model="statusFilter">
                    <option value="all">Tous statuts</option>
                    <option value="draft">Brouillon</option>
                    <option value="issued">Émise</option>
                    <option value="partial">Partiellement payée</option>
                    <option value="paid">Payée</option>
                    <option value="cancelled">Annulée</option>
                </select>
                <select class="input input-sm" wire:model="declarationFilter">
                    <option value="all">Tous types</option>
                    <option value="declared">Avec déclaration</option>
                    <option value="non_declared">Sans déclaration</option>
                </select>
                <select class="input input-sm" wire:model="clientFilter">
                    <option value="">Tous clients</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
                <button type="button" class="btn btn-secondary" wire:click="resetFilters">Réinitialiser</button>
                @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.deliveries.index'))
                    <a class="btn btn-secondary" href="{{ route('tenant.invoicing.deliveries.index', ['tenant' => $tenantCode]) }}">Livraisons</a>
                @endif
                @if (($canCollection ?? false) && \Illuminate\Support\Facades\Route::has('tenant.invoicing.collection_reminders.index'))
                    <a class="btn btn-secondary" href="{{ route('tenant.invoicing.collection_reminders.index', ['tenant' => $tenantCode]) }}">Fiches de relance</a>
                @endif
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.invoicing.create', ['tenant' => $tenantCode]) }}">Nouvelle facture</a>
                    <a class="btn btn-secondary" href="{{ route('tenant.invoicing.deliveries.index', ['tenant' => $tenantCode, 'status' => 'confirmed']) }}">Facturer un BL</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° facture</th>
                        <th>Type</th>
                        <th>Client</th>
                        <th>N° de Demande</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Payé</th>
                        <th>Solde</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $inv)
                    <tr @class(['invoice-row--overdue' => $inv->isOverdue()])>
                        <td>
                            <div class="invoice-number-cell">
                                <strong>{{ $inv->invoice_number }}</strong>
                                @if ($inv->isOverdue())
                                    <span class="invoice-overdue-pill" title="Échue depuis {{ $inv->daysOverdue() }} jour(s)">
                                        <span class="invoice-overdue-pill__dot" aria-hidden="true"></span>
                                        Échu
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td><span class="badge {{ $inv->declaration_type === 'declared' ? 'badge-info' : 'badge-secondary' }}">{{ \InovCom\Invoicing\Models\Invoice::declarationLabel($inv->declaration_type) }}</span></td>
                        <td>
                            @php $clientName = $inv->client->name ?? '—'; @endphp
                            <span title="{{ $clientName }}">{{ \Illuminate\Support\Str::limit($clientName, 30) }}</span>
                        </td>
                        <td>
                            @if (filled($inv->customer_reference))
                                <code style="font-size:12px;">{{ $inv->customer_reference }}</code>
                            @else
                                <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                        <td>{{ $inv->invoice_date->format('d/m/Y') }}</td>
                        <td>{{ fmt_money($inv->total) }}</td>
                        <td>{{ fmt_money($inv->amount_paid) }}</td>
                        <td><strong>{{ fmt_money($inv->balance) }}</strong></td>
                        <td>
                            @php $badge = match($inv->status) {
                                'paid' => 'badge-success',
                                'partial' => 'badge-info',
                                'cancelled' => 'badge-error',
                                'issued' => 'badge-warning',
                                default => 'badge-secondary',
                            }; @endphp
                            <span class="badge {{ $badge }}">{{ \InovCom\Invoicing\Models\Invoice::statusLabel($inv->status) }}</span>
                            @if ($inv->isOverdue())
                                <div style="font-size:10px; color:#b91c1c; font-weight:600; margin-top:3px;">{{ $inv->daysOverdue() }} j de retard</div>
                            @endif
                            <div style="font-size:11px; color:#666;">{{ fmt_money($inv->paymentProgressPercent()) }}% payé</div>
                        </td>
                        <td style="display:flex; gap:4px; flex-wrap:wrap;">
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.edit', [$inv->id, 'tenant' => $tenantCode]) }}">Voir</a>
                            @if (!in_array($inv->status, ['draft', 'cancelled']))
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.print', [$inv->id, 'tenant' => $tenantCode]) }}">Imprimer</a>
                            @endif
                            @if ($inv->status === 'draft' && $canIssue)
                                <button class="btn btn-primary btn-sm" wire:click="issue({{ $inv->id }})">Émettre</button>
                            @endif
                            @if ($canPay && $inv->canReceivePayment())
                                <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoice_payments.pay', [$inv->id, 'tenant' => $tenantCode]) }}">Payer</a>
                            @endif
                            @if ($inv->status === 'draft' && $canUpdate)
                                <button class="btn btn-secondary btn-sm" wire:click="deleteDraft({{ $inv->id }})"
                                        wire:confirm="Supprimer définitivement ce brouillon ?">Supprimer</button>
                            @endif
                            @if ($canCancel && !in_array($inv->status, ['paid', 'cancelled', 'draft']) && (float) $inv->amount_paid <= 0)
                                <button class="btn btn-secondary btn-sm" wire:click="cancel({{ $inv->id }})" wire:confirm="Annuler cette facture ?">Annuler</button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @if ($invoices->count() === 0)<tr><td colspan="10">Aucune facture.</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div style="padding: 12px;">{{ $invoices->links() }}</div>
    </section>
</div>
