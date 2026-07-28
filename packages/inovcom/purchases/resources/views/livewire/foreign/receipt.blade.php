@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    <section class="card">
        <h2 class="card-title">Réception : {{ $order->order_number }}</h2>
        <div style="margin-bottom: 24px; padding: 16px; background: #f5f5f5; border-radius: 4px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <p><strong>Fournisseur :</strong> {{ $order->provider?->name ?? '—' }}</p>
                    <p><strong>Date commande :</strong> {{ $order->order_date->format('d/m/Y') }}</p>
                    <p><strong>Devise :</strong> {{ $order->currency_code }}</p>
                </div>
                <div>
                    <p><strong>Total commande :</strong> {{ fmt_money((float) $order->subtotal_foreign) }} {{ $order->currency_code }}</p>
                    <p><strong>Statut :</strong>
                        <span class="badge badge-info">{{ \InovCom\Purchases\Services\ForeignPurchasesService::statusLabel($order->status) }}</span>
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
                            <th>Qté commandée</th>
                            <th>Déjà reçue</th>
                            <th>Reste à recevoir</th>
                            <th>Quantité reçue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->lines as $line)
                            @if ($line->remaining_quantity <= 0)
                                @continue
                            @endif
                            <tr>
                                <td><x-item-label :reference="$line->item?->sku" :name="$line->item_name ?? $line->item?->name" /></td>
                                <td>{{ fmt_num($line->active_quantity, 3) }}</td>
                                <td>{{ fmt_num($line->received_quantity, 3) }}</td>
                                <td><strong>{{ fmt_num($line->remaining_quantity, 3) }}</strong></td>
                                <td>
                                    <input type="number"
                                           class="input input-sm"
                                           wire:model="receivedQuantities.{{ $line->id }}"
                                           min="0"
                                           max="{{ $line->remaining_quantity }}"
                                           step="0.001"
                                           style="width: 100px;">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="page-actions" style="margin-top: 24px;">
                <a class="btn btn-secondary" href="{{ route('tenant.foreign_purchases.show', [$order->id, 'tenant' => $tenantCode]) }}">Retour</a>
                <button type="submit" class="btn btn-primary">Enregistrer la réception</button>
            </div>
        </form>
    </section>
</div>
