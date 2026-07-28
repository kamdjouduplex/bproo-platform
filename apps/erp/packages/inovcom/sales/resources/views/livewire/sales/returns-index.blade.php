<div class="page-body">
    <section class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <h2 class="card-title" style="margin: 0;">Retours produits</h2>
            <a class="btn btn-secondary" href="{{ route('tenant.sales.index', ['tenant' => $tenantCode]) }}">Ventes</a>
        </div>

        <input class="input" wire:model.live.debounce.300ms="search" placeholder="N° retour ou n° vente…" style="max-width: 320px; margin-bottom: 16px;">

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° retour</th>
                        <th>Date</th>
                        <th>Vente</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Montant</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($returns as $ret)
                        <tr>
                            <td><strong>{{ $ret->return_number }}</strong></td>
                            <td>{{ $ret->return_date->format('d/m/Y') }}</td>
                            <td>{{ $ret->sale?->sale_number }}</td>
                            <td>{{ $ret->sale?->client?->name ?? '—' }}</td>
                            <td>{{ \InovCom\Sales\Models\SaleReturn::typeLabel($ret->type) }}</td>
                            <td>{{ fmt_money($ret->total_refund) }} FCFA</td>
                            <td>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.sales.returns.show', ['saleReturn' => $ret->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @if ($ret->sale_id)
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.sales.show', [$ret->sale_id, 'tenant' => $tenantCode]) }}">Vente</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: #6b7280;">Aucun retour enregistré.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 16px;">{{ $returns->links() }}</div>
    </section>
</div>
