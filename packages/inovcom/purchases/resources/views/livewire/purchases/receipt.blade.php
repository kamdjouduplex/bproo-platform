@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">
            {{ session('error') }}
        </div>
    @endif

    <section class="card">
        <h2 class="card-title">Réception: {{ $purchase->order_number }}</h2>
        <div style="margin-bottom: 24px; padding: 16px; background: #f5f5f5; border-radius: 4px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <p><strong>Fournisseur:</strong> {{ $purchase->provider?->name ?? 'Non spécifié' }}</p>
                    <p><strong>Date commande:</strong> {{ $purchase->order_date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p><strong>Total commande:</strong> {{ fmt_money($purchase->total) }} FCFA</p>
                    <p><strong>Statut:</strong>
                        <span class="badge badge-info">{{ \InovCom\Purchases\Services\PurchasesService::statusLabel($purchase->status) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <form wire:submit.prevent="receive">
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Date de réception *</label>
                    <input class="input" wire:model="receipt_date" type="date" required>
                </div>
                <div class="field">
                    <label class="field-label">Notes</label>
                    <textarea class="input" wire:model="notes" rows="2" placeholder="Notes sur la réception..."></textarea>
                </div>
            </div>

            <h3 style="margin-top: 24px; margin-bottom: 12px;">Articles à réceptionner</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Qté active</th>
                            <th>Annulée</th>
                            <th>Déjà reçue</th>
                            <th>Reste à recevoir</th>
                            <th>Quantité reçue</th>
                            <th>N° lot</th>
                            <th>Péremption</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase->lines as $line)
                            @if ($line->remaining_quantity <= 0)
                                @continue
                            @endif
                            @php $needsLot = !empty($lineRequiresLot[$line->id]); @endphp
                            <tr>
                                <td><x-item-label :reference="$line->item?->sku" :name="$line->item_name ?? $line->item?->name" /></td>
                                <td>{{ fmt_num($line->active_quantity) }}</td>
                                <td>{{ fmt_num($line->cancelled_quantity) }}</td>
                                <td>{{ fmt_num($line->received_quantity) }}</td>
                                <td><strong>{{ fmt_num($line->remaining_quantity) }}</strong></td>
                                <td>
                                    <input type="number"
                                           class="input input-sm"
                                           wire:model="receivedQuantities.{{ $line->id }}"
                                           min="0"
                                           max="{{ $line->remaining_quantity }}"
                                           step="0.001"
                                           style="width: 100px;">
                                </td>
                                <td>
                                    @if ($needsLot)
                                        <input type="text"
                                               class="input input-sm"
                                               wire:model="batchNumbers.{{ $line->id }}"
                                               placeholder="Lot"
                                               maxlength="100"
                                               style="width: 110px;">
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($needsLot)
                                        <input type="date"
                                               class="input input-sm"
                                               wire:model="expiryDates.{{ $line->id }}"
                                               style="width: 140px;">
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="page-actions" style="margin-top: 24px;">
                <a class="btn btn-secondary" href="{{ route('tenant.purchases.show', [$purchase->id, 'tenant' => $tenantCode]) }}">Retour</a>
                <button type="submit" class="btn btn-primary">Enregistrer la réception</button>
            </div>
        </form>
    </section>
</div>
