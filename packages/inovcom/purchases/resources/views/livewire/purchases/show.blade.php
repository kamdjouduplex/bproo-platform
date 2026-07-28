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

    <div id="actions" class="page-actions" style="margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
        <a class="btn btn-secondary" href="{{ route('tenant.purchases.index', ['tenant' => $tenantCode]) }}">← Liste</a>
        @if ($canReceive)
            <a class="btn btn-primary" href="{{ route('tenant.purchases.receive', [$purchase->id, 'tenant' => $tenantCode]) }}">Réceptionner</a>
        @endif
        @if ($canCancel)
            <button type="button" class="btn btn-secondary" wire:click="openCancelModal">Annuler (partiel / total)</button>
        @endif
        @if ($canConfirm)
            <button type="button" class="btn btn-primary" wire:click="confirmOrder" wire:confirm="Confirmer cet achat ? La commande ne pourra plus être modifiée.">
                Confirmer un achat
            </button>
        @endif
        @if ($canEdit)
            <a class="btn btn-secondary" href="{{ route('tenant.purchases.edit', [$purchase->id, 'tenant' => $tenantCode]) }}">Modifier</a>
        @endif
        @if ($canPrint)
            <a class="btn btn-secondary" href="{{ route('tenant.purchases.print', [$purchase->id, 'tenant' => $tenantCode, 'type' => 'order']) }}">Imprimer bon d'achat</a>
        @endif
    </div>

    @if ($purchase->status === 'draft' && $canReceive)
        <div class="alert alert-info" style="margin-bottom: 16px;">
            Commande en brouillon : la réception la confirmera automatiquement avant d'enregistrer les quantités reçues.
        </div>
    @endif

    <section class="card" style="margin-bottom: 16px;">
        <h2 class="card-title">{{ $purchase->order_number }}</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div>
                <p><strong>Statut :</strong>
                    @php
                        $badge = match($purchase->status) {
                            'draft' => 'badge-secondary',
                            'confirmed' => 'badge-info',
                            'partial', 'sent' => 'badge-warning',
                            'received' => 'badge-success',
                            'cancelled' => 'badge-error',
                            default => 'badge-secondary',
                        };
                    @endphp
                    <span class="badge {{ $badge }}">{{ $statusLabel }}</span>
                </p>
                <p><strong>Date :</strong> {{ $purchase->order_date->format('d/m/Y') }}</p>
                @if ($purchase->expected_date)
                    <p><strong>Livraison prévue :</strong> {{ $purchase->expected_date->format('d/m/Y') }}</p>
                @endif
            </div>
            <div>
                <p><strong>Fournisseur :</strong> {{ $purchase->provider?->name ?? '—' }}</p>
                <p><strong>Total :</strong> {{ fmt_money($purchase->total) }} FCFA</p>
                <p><strong>Réception :</strong> {{ fmt_num($purchase->reception_percent) }} %</p>
            </div>
            <div>
                @if ($purchase->confirmed_at)
                    <p><strong>Confirmée le :</strong> {{ $purchase->confirmed_at->format('d/m/Y H:i') }}</p>
                @endif
                @if ($purchase->cancelled_at)
                    <p><strong>Annulée le :</strong> {{ $purchase->cancelled_at->format('d/m/Y H:i') }}</p>
                @endif
                @if ($purchase->cancellation_reason)
                    <p><strong>Motif :</strong> {{ $purchase->cancellation_reason }}</p>
                @endif
            </div>
        </div>
        @if ($purchase->notes)
            <p style="margin-top: 12px;"><strong>Notes :</strong> {{ $purchase->notes }}</p>
        @endif
    </section>

    <section class="card app-table-card" style="margin-bottom: 16px;">
        <div class="table-title">Lignes de commande</div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Qté commandée</th>
                        <th>Annulée</th>
                        <th>Active</th>
                        <th>Reçue</th>
                        <th>Reste</th>
                        <th>Coût achat</th>
                        <th>Total ligne</th>
                        <th>Dernier coût d'achat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchase->lines as $line)
                        @php
                            $latest = $line->item_id ? $priceHistory->latestForItem((int) $line->item_id, $purchase->provider_id) : null;
                        @endphp
                        <tr>
                            <td><x-item-label :reference="$line->item_sku ?? $line->item?->sku" :name="$line->item_name ?? $line->item?->name" /></td>
                            <td>{{ fmt_num($line->quantity) }}</td>
                            <td>{{ fmt_num($line->cancelled_quantity) }}</td>
                            <td>{{ fmt_num($line->active_quantity) }}</td>
                            <td>{{ fmt_num($line->received_quantity) }}</td>
                            <td><strong>{{ fmt_num($line->remaining_quantity) }}</strong></td>
                            <td>{{ fmt_money($line->unit_price) }}</td>
                            <td>{{ fmt_money($line->line_total) }}</td>
                            <td>
                                @if ($latest)
                                    <strong style="color: var(--color-primary, #2563eb);">{{ fmt_money($latest->unit_price) }}</strong>
                                    <span style="font-size: 11px; color: #666;">({{ $latest->recorded_at->format('d/m/Y') }})</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($purchase->receipts->isNotEmpty())
        <section class="card app-table-card">
            <div class="table-title">Bons de réception</div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase->receipts as $receipt)
                            <tr>
                                <td>{{ $receipt->receipt_number }}</td>
                                <td>{{ $receipt->receipt_date->format('d/m/Y') }}</td>
                                <td>{{ $receipt->status === 'complete' ? 'Complet' : 'Partiel' }}</td>
                                <td>
                                    @if ($canPrint)
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.purchases.print', [$purchase->id, 'tenant' => $tenantCode, 'type' => 'receipt', 'receipt_id' => $receipt->id]) }}">Imprimer</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($showCancelModal)
        <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 16px;">
            <div class="card" style="max-width: 720px; width: 100%; max-height: 90vh; overflow-y: auto;">
                <h2 class="card-title">Annulation</h2>
                <p style="margin-bottom: 12px; color: #666;">Saisissez les quantités à annuler par ligne, ou annulez la commande entièrement.</p>

                <div class="field" style="margin-bottom: 12px;">
                    <label class="field-label">Motif (optionnel)</label>
                    <textarea class="input" wire:model="cancelReason" rows="2"></textarea>
                </div>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                    <input type="checkbox" wire:model="reverseStock">
                    Retirer du stock les quantités déjà réceptionnées
                </label>

                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th>Max annulable</th>
                                <th>Qté à annuler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchase->lines as $line)
                                @php $maxCancel = max(0, (float) $line->quantity - (float) $line->cancelled_quantity); @endphp
                                <tr>
                                    <td><x-item-label :reference="$line->item_sku ?? $line->item?->sku" :name="$line->item_name ?? $line->item?->name" /></td>
                                    <td>{{ fmt_num($maxCancel) }}</td>
                                    <td>
                                        <input type="number" class="input input-sm" style="width: 100px;"
                                               wire:model="cancelQuantities.{{ $line->id }}"
                                               min="0" max="{{ $maxCancel }}" step="0.001">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="page-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" wire:click="closeCancelModal">Fermer</button>
                    <button type="button" class="btn btn-primary" wire:click="cancelPartial" wire:confirm="Appliquer l'annulation partielle ?">Annuler les quantités saisies</button>
                    <button type="button" class="btn btn-secondary" wire:click="cancelEntire" wire:confirm="Annuler toute la commande ?">Tout annuler</button>
                </div>
            </div>
        </div>
    @endif
</div>
