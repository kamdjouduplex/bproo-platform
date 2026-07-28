<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    <section class="card" style="margin-bottom: 16px;">
        <h2 class="card-title">Retour produit — {{ $sale->sale_number }}</h2>
        <p style="color: #6b7280; font-size: 14px; margin-bottom: 12px;">
            Client : <strong>{{ $sale->client?->name ?? 'Client occasionnel' }}</strong>
            · Total vente : <strong>{{ fmt_money($sale->total) }} FCFA</strong>
            @if ($sale->totalReturned() > 0)
                · Déjà retourné : <strong>{{ fmt_money($sale->totalReturned()) }} FCFA</strong>
            @endif
        </p>

        @if (count($lineRows) === 0)
            <p class="alert alert-error">Tous les articles de cette vente ont déjà été retournés.</p>
            <a class="btn btn-secondary" href="{{ route('tenant.sales.show', [$sale->id, 'tenant' => $tenantCode]) }}">Retour à la vente</a>
        @else
            <div class="page-actions" style="margin-bottom: 16px;">
                <button type="button" class="btn btn-secondary" wire:click="selectFullReturn">Retour intégral</button>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Vendu</th>
                            <th>Restant</th>
                            <th>Qté à retourner</th>
                            <th>Prix unit.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lineRows as $index => $row)
                            <tr wire:key="return-line-{{ $row['sale_line_id'] }}">
                                <td>
                                    <x-item-label :reference="$row['item_sku'] ?? null" :name="$row['item_name'] ?? null" />
                                </td>
                                <td>{{ fmt_num($row['sold_qty']) }}@if($row['unit_name']) {{ $row['unit_name'] }}@endif</td>
                                <td>{{ fmt_num($row['returnable_qty']) }}</td>
                                <td style="max-width: 140px;">
                                    <input class="input"
                                           type="number"
                                           step="0.001"
                                           min="0"
                                           max="{{ $row['returnable_qty'] }}"
                                           wire:model.live="lineRows.{{ $index }}.quantity">
                                </td>
                                <td>{{ fmt_money($row['unit_price']) }} FCFA</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-grid" style="margin-top: 20px;">
                <div class="form-group">
                    <label class="field-label">Motif du retour</label>
                    <select class="input" wire:model="reason">
                        <option value="">— Sélectionner —</option>
                        <option value="defect">Produit défectueux</option>
                        <option value="wrong_item">Erreur de livraison</option>
                        <option value="client_request">Demande client</option>
                        <option value="expired">Périmé / non conforme</option>
                        <option value="bad_reference">Mauvaise référence</option>
                        <option value="poor_quality">Qualité insatisfaisante</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="field-label">Notes</label>
                    <textarea class="input" wire:model="notes" rows="2" placeholder="Commentaire interne…"></textarea>
                </div>
            </div>

            <div style="margin-top: 16px; padding: 12px 16px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px;">
                <strong>Remboursement estimé :</strong> {{ fmt_money($estimatedRefund) }} FCFA
                <div style="font-size: 12px; color: #166534; margin-top: 4px;">
                    Le stock sera réintégré et le remboursement réparti proportionnellement aux modes de paiement de la vente (caisse, mobile money, crédit client).
                </div>
            </div>

            <div class="page-actions" style="margin-top: 20px;">
                <button type="button" class="btn btn-primary" wire:click="confirmReturn" wire:confirm="Confirmer ce retour ? Le stock et la caisse seront mis à jour.">
                    Valider le retour
                </button>
                <a class="btn btn-secondary" href="{{ route('tenant.sales.show', [$sale->id, 'tenant' => $tenantCode]) }}">Annuler</a>
            </div>
        @endif
    </section>

    @if ($sale->confirmedReturns->count() > 0)
        <section class="card">
            <h3 class="form-section-title">Retours précédents</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N° retour</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->confirmedReturns as $ret)
                            <tr>
                                <td>{{ $ret->return_number }}</td>
                                <td>{{ $ret->return_date->format('d/m/Y') }}</td>
                                <td>{{ \InovCom\Sales\Models\SaleReturn::typeLabel($ret->type) }}</td>
                                <td>{{ fmt_money($ret->total_refund) }} FCFA</td>
                                <td>
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.sales.returns.show', ['saleReturn' => $ret->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
