<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif

    <section class="card">
        <h2 class="card-title">Retour {{ $saleReturn->return_number }}</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
            <div>
                <p><strong>Vente :</strong>
                    <a href="{{ route('tenant.sales.show', [$saleReturn->sale_id, 'tenant' => $tenantCode]) }}">{{ $saleReturn->sale->sale_number }}</a>
                </p>
                <p><strong>Date :</strong> {{ $saleReturn->return_date->format('d/m/Y') }}</p>
                <p><strong>Type :</strong> {{ \InovCom\Sales\Models\SaleReturn::typeLabel($saleReturn->type) }}</p>
                @if ($saleReturn->reason)
                    <p><strong>Motif :</strong> {{ \InovCom\Sales\Models\SaleReturn::reasonLabel($saleReturn->reason) }}</p>
                @endif
            </div>
            <div>
                <p><strong>Sous-total retourné :</strong> {{ fmt_money($saleReturn->subtotal_refund) }} FCFA</p>
                <p><strong>Remise répartie :</strong> {{ fmt_money($saleReturn->discount_refund) }} FCFA</p>
                <p><strong>Remboursement total :</strong> <strong>{{ fmt_money($saleReturn->total_refund) }} FCFA</strong></p>
            </div>
        </div>

        <h3 style="margin-bottom: 12px;">Articles retournés</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Quantité</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($saleReturn->lines as $line)
                        <tr>
                            <td><x-item-label :reference="$line->saleLine?->item_sku" :name="$line->saleLine?->item_name" fallback="—" /></td>
                            <td>{{ fmt_num($line->quantity) }}</td>
                            <td>{{ fmt_money($line->line_refund) }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($saleReturn->refunds->count() > 0)
            <h3 style="margin-top: 24px; margin-bottom: 12px;">Remboursements (répartition)</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Mode</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($saleReturn->refunds as $refund)
                            <tr>
                                <td>{{ $refund->method_label }}</td>
                                <td>{{ fmt_money($refund->amount) }} FCFA</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($saleReturn->notes)
            <p style="margin-top: 16px;"><strong>Notes :</strong> {{ $saleReturn->notes }}</p>
        @endif

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.sales.show', [$saleReturn->sale_id, 'tenant' => $tenantCode]) }}">Voir la vente</a>
            <a class="btn btn-secondary" href="{{ route('tenant.sales.returns.index', ['tenant' => $tenantCode]) }}">Liste des retours</a>
        </div>
    </section>
</div>
