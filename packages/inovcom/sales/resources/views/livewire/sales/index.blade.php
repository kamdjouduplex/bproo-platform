@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (isset($suspendedSales) && $suspendedSales->isNotEmpty())
        <section class="card" style="margin-bottom: 20px; border-left: 4px solid #3b82f6;">
            <h2 class="card-title">Ventes suspendues</h2>
            <p style="margin-bottom: 12px; color: #555; font-size: 13px;">Reprenez une vente mise de côté en cliquant sur <strong>Reprendre</strong>.</p>
            <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach ($suspendedSales as $suspended)
                    <li style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #eee; gap: 12px; flex-wrap: wrap;">
                        <span style="font-size: 14px;">{{ $suspended->summary }} — {{ $suspended->created_at->format('d/m/Y H:i') }}{{ $suspended->user ? ' · ' . $suspended->user->name : '' }}</span>
                        <span style="display: flex; gap: 8px; flex-shrink: 0;">
                            <a href="{{ route('tenant.sales.create', ['tenant' => $tenantCode, 'resume' => $suspended->id]) }}" class="btn btn-primary btn-sm">Reprendre</a>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="deleteSuspended({{ $suspended->id }})" wire:confirm="Supprimer cette vente suspendue ?">Supprimer</button>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Ventes</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <span style="font-size: 12px; color: #64748b; font-weight: 600;">Période :</span>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('day')">Aujourd'hui</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('week')">Cette semaine</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('month')">Ce mois</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('year')">Cette année</button>
                <span style="font-size: 12px; color: #64748b; margin-left: 4px;">|</span>
                <input class="input input-sm" type="date" wire:model.live="dateFrom" style="width: 140px;" title="Du">
                <input class="input input-sm" type="date" wire:model.live="dateTo" style="width: 140px;" title="Au">
                <form wire:submit.prevent="applySearch" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="N° vente ou nom client" style="min-width: 200px;" aria-label="Rechercher par numéro de vente ou nom du client">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model="perPage">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                @if (\Illuminate\Support\Facades\Route::has('tenant.sales.returns.index'))
                    <a class="btn btn-secondary" href="{{ route('tenant.sales.returns.index', ['tenant' => $tenantCode]) }}">Retours</a>
                @endif
                <a class="btn btn-primary" href="{{ route('tenant.sales.create', ['tenant' => $tenantCode]) }}">Nouvelle vente</a>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° Vente</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Sous-total</th>
                        <th>Remise</th>
                        <th>Total</th>
                        <th>Paiement</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sales as $sale)
                        <tr>
                            <td>{{ $sale->sale_number }}</td>
                            <td>{{ $sale->sale_date->format('d/m/Y') }}</td>
                            <td>{{ $sale->client?->name ?? 'Client occasionnel' }}</td>
                            <td>
                                @if ($sale->price_tier === 'retail')
                                    <span class="badge badge-info">Détail</span>
                                @elseif ($sale->price_tier === 'semi_wholesale')
                                    <span class="badge badge-info">Semi-gros</span>
                                @else
                                    <span class="badge badge-info">Gros</span>
                                @endif
                            </td>
                            <td>{{ fmt_money($sale->subtotal) }} FCFA</td>
                            <td>{{ fmt_money($sale->discount_amount) }} FCFA</td>
                            <td><strong>{{ fmt_money($sale->total) }} FCFA</strong></td>
                            <td>
                                @if ($sale->isFullyPaid())
                                    <span class="badge badge-success">Payé</span>
                                @elseif ($sale->hasCreditPayment() && $sale->total_paid > 0.01)
                                    <span class="badge badge-warning">Partiel / Crédit</span>
                                @elseif ($sale->hasCreditPayment())
                                    <span class="badge badge-warning">À crédit</span>
                                @else
                                    <span class="badge badge-danger">Impayé</span>
                                @endif
                            </td>
                            <td>
                                @if ($sale->payments->count() > 0)
                                    @foreach ($sale->payments as $payment)
                                        <span class="badge badge-secondary">{{ $payment->method_label }} {{ fmt_money($payment->amount) }}</span>
                                    @endforeach
                                @else
                                    <span class="badge badge-warning">Non payé</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-secondary" href="{{ route('tenant.sales.show', [$sale->id, 'tenant' => $tenantCode]) }}">Voir</a>
                            </td>
                        </tr>
                    @endforeach
                    @if ($sales->count() === 0)
                        <tr>
                            <td colspan="10">Aucune vente pour cette période.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $sales->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </section>
</div>
