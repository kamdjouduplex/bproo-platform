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

    <div class="page-actions" style="margin-bottom: 16px; flex-wrap: wrap; gap: 8px; align-items: center;">
        <a class="btn btn-secondary" href="{{ route('tenant.foreign_purchases.index', ['tenant' => $tenantCode]) }}">← Liste</a>
        @if ($canReceive)
            <a class="btn btn-primary" href="{{ route('tenant.foreign_purchases.receive', [$order->id, 'tenant' => $tenantCode]) }}">Réceptionner</a>
        @endif
        @if ($canConfirm)
            <button type="button" class="btn btn-primary" wire:click="confirmOrder" wire:confirm="Confirmer cet achat étranger ? La commande ne pourra plus être modifiée.">
                Confirmer
            </button>
        @endif
        <div style="display: inline-flex; gap: 8px; flex-wrap: wrap;">
            @if ($canModify)
                <a class="btn btn-secondary" href="{{ route('tenant.foreign_purchases.edit', [$order->id, 'tenant' => $tenantCode]) }}">Modifier</a>
            @endif
            @if ($canPrint)
                <a class="btn btn-secondary" href="{{ route('tenant.foreign_purchases.print', [$order->id, 'tenant' => $tenantCode]) }}">Imprimer</a>
            @endif
        </div>
    </div>

    @if ($isDraft && $canReceive)
        <div class="alert alert-info" style="margin-bottom: 16px;">
            Commande en brouillon : la réception la confirmera automatiquement avant d'enregistrer les quantités reçues et de mettre à jour le stock.
        </div>
    @endif

    @if (!$isDraft && !$canModify)
        <div class="alert alert-info" style="margin-bottom: 16px;">
            Cette commande est confirmée : le formulaire de modification est en lecture seule.
        </div>
    @endif

    <section class="card" style="margin-bottom: 16px;">
        <h2 class="card-title">{{ $order->order_number }}</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <div>
                <p><strong>Statut :</strong>
                    @php
                        $badge = match($order->status) {
                            'draft' => 'badge-secondary',
                            'confirmed' => 'badge-info',
                            'partial' => 'badge-warning',
                            'received' => 'badge-success',
                            default => 'badge-secondary',
                        };
                    @endphp
                    <span class="badge {{ $badge }}">{{ $statusLabel }}</span>
                </p>
                <p><strong>Date commande :</strong> {{ $order->order_date->format('d/m/Y') }}</p>
                @if ($order->expected_date)
                    <p><strong>Date prévue :</strong> {{ $order->expected_date->format('d/m/Y') }}</p>
                @endif
                @if ($order->confirmed_at)
                    <p><strong>Confirmée le :</strong> {{ $order->confirmed_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
            <div>
                <p><strong>Fournisseur :</strong> {{ $order->provider?->name ?? '—' }}</p>
                <p><strong>Devise :</strong> {{ $order->currency_code }}</p>
                <p><strong>Taux :</strong> 1 {{ $order->currency_code }} = {{ fmt_num((float) $order->exchange_rate, 4) }} FCFA</p>
                <p><strong>Réception :</strong> {{ fmt_num($order->reception_percent) }} %</p>
            </div>
            <div>
                <p><strong>Total commande :</strong> {{ fmt_money((float) $order->subtotal_foreign) }} {{ $order->currency_code }}</p>
                <p style="font-size: 12px; color: #6b7280;">
                    <strong>Équiv. FCFA (indicatif) :</strong> {{ fmt_money((float) $order->subtotal_local) }} FCFA
                </p>
                @if ($order->creator)
                    <p><strong>Créé par :</strong> {{ $order->creator->name ?? $order->creator->email }}</p>
                @endif
            </div>
        </div>
        @if ($order->notes)
            <p style="margin-top: 12px;"><strong>Notes :</strong> {{ $order->notes }}</p>
        @endif
    </section>

    <section class="card app-table-card" style="margin-bottom: 16px;">
        <div class="table-title">Lignes de commande</div>
        <p class="field-hint" style="margin-bottom: 12px;">Les montants FCFA sont un guide interne — seule la devise de la commande figure sur le document imprimé.</p>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Qté commandée</th>
                        <th>Reçue</th>
                        <th>Reste</th>
                        <th>Coût unit. ({{ $order->currency_code }})</th>
                        <th>Total ({{ $order->currency_code }})</th>
                        <th>Coût FCFA <span style="font-weight:400; color:#6b7280;">(indicatif)</span></th>
                        <th>Dernier coût d'achat ({{ $order->currency_code }})</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        @php
                            $latestForeign = $line->item_id
                                ? $priceHistory->latestForeignForItem((int) $line->item_id, $order->currency_code, $order->provider_id)
                                : null;
                        @endphp
                        <tr>
                            <td><x-item-label :reference="$line->item?->sku" :name="$line->item_name" /></td>
                            <td>{{ fmt_num((float) $line->quantity, 3) }}</td>
                            <td>{{ fmt_num((float) $line->received_quantity, 3) }}</td>
                            <td><strong>{{ fmt_num($line->remaining_quantity, 3) }}</strong></td>
                            <td>{{ fmt_num((float) $line->unit_price_foreign, 4) }}</td>
                            <td>{{ fmt_money((float) $line->line_total_foreign) }}</td>
                            <td>{{ fmt_money((float) $line->unit_price_local) }}</td>
                            <td>
                                @if ($latestForeign)
                                    <strong style="color: var(--color-primary, #2563eb);">{{ fmt_num((float) $latestForeign->unit_price_foreign, 4) }}</strong>
                                    <span style="font-size: 11px; color: #666;">({{ $latestForeign->recorded_at->format('d/m/Y') }})</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($order->lines->isEmpty())
                        <tr><td colspan="8">Aucune ligne.</td></tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align: right;"><strong>Totaux</strong></td>
                        <td><strong>{{ fmt_money((float) $order->subtotal_foreign) }} {{ $order->currency_code }}</strong></td>
                        <td colspan="2"><strong>{{ fmt_money((float) $order->subtotal_local) }} FCFA</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    @if ($order->receipts->isNotEmpty())
        <section class="card app-table-card">
            <div class="table-title">Bons de réception</div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Réceptionné par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->receipts as $receipt)
                            <tr>
                                <td>{{ $receipt->receipt_number }}</td>
                                <td>{{ $receipt->receipt_date->format('d/m/Y') }}</td>
                                <td>{{ $receipt->status === 'complete' ? 'Complet' : 'Partiel' }}</td>
                                <td>{{ $receipt->receiver?->name ?? $receipt->receiver?->email ?? '—' }}</td>
                                <td>
                                    @if ($canPrint)
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.foreign_purchases.print', [$order->id, 'tenant' => $tenantCode, 'type' => 'receipt', 'receipt_id' => $receipt->id]) }}">Imprimer</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
