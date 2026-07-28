@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Commandes d'achat</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <input class="input input-sm" type="date" wire:model.live="dateFrom" style="width: 150px;">
                <input class="input input-sm" type="date" wire:model.live="dateTo" style="width: 150px;">
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="all">Tous les statuts</option>
                    <option value="draft">Brouillon</option>
                    <option value="confirmed">Confirmée</option>
                    <option value="partial">Réception partielle</option>
                    <option value="received">Réceptionnée</option>
                    <option value="cancelled">Annulée</option>
                </select>
                <form wire:submit.prevent="applySearch" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="N° commande ou fournisseur" style="min-width: 200px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model.live="perPage">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.purchases.create', ['tenant' => $tenantCode]) }}">Nouvelle commande</a>
                @endif
                @if ($canForeignPurchases ?? false)
                    <a class="btn btn-secondary" href="{{ route('tenant.foreign_purchases.index', ['tenant' => $tenantCode]) }}">Achats étrangers</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Date</th>
                        <th>Fournisseur</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th>Réception</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php
                            $statusLabel = \InovCom\Purchases\Services\PurchasesService::statusLabel($order->status);
                            $badge = match($order->status) {
                                'draft' => 'badge-secondary',
                                'confirmed' => 'badge-info',
                                'partial', 'sent' => 'badge-warning',
                                'received' => 'badge-success',
                                'cancelled' => 'badge-error',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <tr>
                            <td>
                                @if ($canView)
                                    <a href="{{ route('tenant.purchases.show', [$order->id, 'tenant' => $tenantCode]) }}">{{ $order->order_number }}</a>
                                @else
                                    {{ $order->order_number }}
                                @endif
                            </td>
                            <td>{{ $order->order_date->format('d/m/Y') }}</td>
                            <td>{{ $order->provider?->name ?? '—' }}</td>
                            <td><strong>{{ fmt_money($order->total) }} FCFA</strong></td>
                            <td><span class="badge {{ $badge }}">{{ $statusLabel }}</span></td>
                            <td>
                                @if ($order->reception_percent >= 100)
                                    <span class="badge badge-success">100%</span>
                                @elseif ($order->reception_percent > 0)
                                    <span class="badge badge-warning">{{ fmt_num($order->reception_percent) }}%</span>
                                @else
                                    <span class="badge badge-secondary">0%</span>
                                @endif
                            </td>
                            <td style="white-space: nowrap;">
                                @if ($canView)
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.purchases.show', [$order->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @endif
                                @if ($canUpdate && $order->isEditable())
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.purchases.edit', [$order->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                @endif
                                @if ($canReceive && $order->canReceive())
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.purchases.receive', [$order->id, 'tenant' => $tenantCode]) }}">Réceptionner</a>
                                @endif
                                @if ($canCancel && $order->canCancel())
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.purchases.show', [$order->id, 'tenant' => $tenantCode, 'cancel' => 1]) }}#actions">Annuler</a>
                                @endif
                                @if ($canView)
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.purchases.print', [$order->id, 'tenant' => $tenantCode, 'type' => 'order']) }}">Imprimer</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($orders->count() === 0)
                        <tr>
                            <td colspan="7">Aucune commande pour cette période.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $orders->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </section>
</div>
