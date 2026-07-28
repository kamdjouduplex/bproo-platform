<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <section class="card">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <span class="badge badge-info">{{ \InovCom\Invoicing\Models\DeliveryNote::statusLabel($deliveryNote->status) }}</span>
        </div>

        <h2 class="card-title">{{ $deliveryNote->delivery_number }}</h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
            <div>
                @if ($deliveryNote->invoice_id)
                    <p><strong>Facture :</strong>
                        <a href="{{ route('tenant.invoicing.edit', [$deliveryNote->invoice_id, 'tenant' => $tenantCode]) }}">{{ $deliveryNote->invoice?->invoice_number }}</a>
                    </p>
                @endif
                @if ($deliveryNote->quotation_id)
                    <p><strong>Devis :</strong>
                        <a href="{{ route('tenant.quotations.edit', ['quotation' => $deliveryNote->quotation_id, 'tenant' => $tenantCode]) }}">{{ $deliveryNote->quotation?->number }}</a>
                    </p>
                @endif
                <p><strong>Client :</strong> {{ $deliveryNote->invoice?->client?->name ?? $deliveryNote->client?->name ?? $deliveryNote->quotation?->client?->name }}</p>
                <p><strong>Date livraison :</strong> {{ $deliveryNote->delivery_date->format('d/m/Y') }}</p>
                @if ($deliveryNote->customer_purchase_order)
                    <p><strong>N° demande achat :</strong> {{ $deliveryNote->customer_purchase_order }}</p>
                @elseif ($deliveryNote->quotation?->customer_purchase_order)
                    <p><strong>N° demande achat :</strong> {{ $deliveryNote->quotation->customer_purchase_order }}</p>
                @elseif ($deliveryNote->invoice?->customer_reference)
                    <p><strong>N° demande achat :</strong> {{ $deliveryNote->invoice->customer_reference }}</p>
                @endif
                <p><strong>Impression :</strong>
                    @if ($deliveryNote->show_prices)
                        prix @if($deliveryNote->show_discounts) et remises @endif
                    @else
                        sans prix
                    @endif
                </p>
                <p><strong>Créé par :</strong> {{ $deliveryNote->creator?->name ?? '—' }}</p>
                @if ($deliveryNote->isConfirmed())
                    <p><strong>Validé par :</strong> {{ $deliveryNote->confirmer?->name ?? '—' }}
                        le {{ $deliveryNote->confirmed_at?->format('d/m/Y H:i') }}</p>
                @endif
            </div>
            <div>
                @if ($deliveryNote->notes)
                    <p><strong>Notes :</strong> {{ $deliveryNote->notes }}</p>
                @endif
            </div>
        </div>

        @if ($canEditPrintOptions)
            <div style="margin-bottom:20px;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
                <strong style="font-size:13px;">Impression du BL</strong>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
                    <div class="field">
                        <label class="field-label">N° demande achat</label>
                        <input class="input" type="text" wire:model="customer_purchase_order" placeholder="Ex. DA-2026-014" maxlength="120" @disabled(!$canEdit)>
                    </div>
                    <div style="display:flex;flex-direction:column;justify-content:flex-end;gap:8px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                            <input type="checkbox" wire:model.boolean.live="show_prices"> Afficher les prix
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                            <input type="checkbox" wire:model.boolean.live="show_discounts" @disabled(!$show_prices)> Afficher les remises
                        </label>
                        <p class="field-hint" style="margin:0;">Les cases sont enregistrées automatiquement sur ce BL.</p>
                    </div>
                </div>
                @if ($canEdit)
                    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="savePrintOptions">Enregistrer le n° demande achat</button>
                    </div>
                @endif
            </div>
        @endif

        <h3 style="margin-bottom:12px;">Articles livrés</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th style="width:56px;">N°</th>
                        <th>Référence / Article</th>
                        <th>Qté</th>
                        @if ($show_prices)
                            <th>P.U.</th>
                            @if ($show_discounts)
                                <th>Remise</th>
                            @endif
                            <th>Montant HT</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deliveryNote->lines as $index => $line)
                        @php
                            $row = data_get($printData, "lines.{$index}");
                            $lineNo = (int) data_get($row, 'line_number', 0) ?: (($index + 1) * 10);
                        @endphp
                        <tr>
                            <td style="text-align:center; font-weight:600;">{{ $lineNo }}</td>
                            <td>
                                <x-item-label :reference="$line->item_sku" :name="$line->item_name" />
                            </td>
                            <td>{{ fmt_num($line->quantity) }}</td>
                            @if ($show_prices && $row)
                                @php
                                    $puNet = (float) ($row['unit_price_net'] ?? max(0, (float) ($row['unit_price'] ?? 0) - (float) ($row['line_discount'] ?? 0)));
                                    $puDisplay = $show_discounts ? $puNet : (float) ($row['unit_price'] ?? 0);
                                    $discountLabel = format_line_discount_label([
                                        'line_discount_mode' => $row['line_discount_mode'] ?? 'amount',
                                        'line_discount_input' => $row['line_discount_input'] ?? null,
                                        'line_discount' => $row['line_discount'] ?? $row['line_discount_per_unit'] ?? 0,
                                        'unit_price' => $row['unit_price'] ?? 0,
                                    ]);
                                @endphp
                                <td>{{ fmt_num($puDisplay, 2) }}</td>
                                @if ($show_discounts)
                                    <td>{{ $discountLabel !== '—' ? $discountLabel : '' }}</td>
                                @endif
                                <td>{{ fmt_money((float) $row['line_total']) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($show_prices && $printData)
            @php
                $netHt = (float) ($printData['net_ht'] ?? max(0, (float) $printData['subtotal'] - (float) ($printData['discount_amount'] ?? 0)));
                $taxLines = $printData['tax_lines'] ?? [];
            @endphp
            <div style="padding:12px 0 4px;text-align:right;max-width:360px;margin-left:auto;font-size:14px;">
                @if ($show_discounts && (float) ($printData['discount_amount'] ?? 0) > 0)
                    <div style="color:#b45309;">
                        Remise globale
                        @if ((float) ($printData['discount_percent'] ?? 0) > 0)
                            ({{ fmt_num((float) $printData['discount_percent'], 2) }} %)
                        @endif
                        : <strong>−{{ fmt_money((float) $printData['discount_amount']) }} FCFA</strong>
                    </div>
                @endif
                <div>Montant HT : <strong>{{ fmt_money($netHt) }} FCFA</strong></div>
                @foreach ($taxLines as $line)
                    @if ((float) ($line['tax_amount'] ?? 0) > 0)
                        @php $taxSubtract = ($line['tax_effect'] ?? 'add') === 'subtract'; @endphp
                        <div>
                            Montant {{ $line['tax_name'] ?? 'Taxe' }}
                            @if (($line['tax_mode'] ?? 'amount') === 'percent' && isset($line['tax_rate']))
                                ({{ fmt_num((float) $line['tax_rate'], 2) }} %)
                            @endif
                            :
                            <strong>{{ $taxSubtract ? '−' : '+' }}{{ fmt_money((float) $line['tax_amount']) }} FCFA</strong>
                        </div>
                    @endif
                @endforeach
                @if ((float) ($printData['ttc'] ?? 0) > 0)
                    <div>Montant TTC : <strong>{{ fmt_money((float) $printData['ttc']) }} FCFA</strong></div>
                @endif
                <div style="font-size:1.15em;margin-top:8px;padding-top:8px;border-top:2px solid #e5e7eb;">
                    Net à payer : <strong>{{ fmt_money((float) ($printData['total'] ?? $printData['ttc'] ?? $netHt)) }} FCFA</strong>
                </div>
            </div>
        @endif

        @if ($deliveryNote->isDraft())
            <div style="margin-top:24px;padding:14px;background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;">
                <strong>En attente de validation</strong>
                <p style="font-size:13px;margin-top:6px;color:#92400e;">
                    La validation applique la <strong>sortie de stock</strong> et enregistre les mouvements (type Livraison).
                </p>
            </div>
        @elseif ($deliveryNote->isConfirmed())
            <div style="margin-top:16px;padding:12px;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;">
                <strong>Stock mis à jour</strong> — consultez les mouvements dans le module Stock.
            </div>
        @endif

        <div class="page-actions" style="margin-top:24px;flex-wrap:wrap;gap:8px;">
            <a class="btn btn-primary" href="{{ $printUrl ?? route('tenant.invoicing.deliveries.print', ['deliveryNote' => $deliveryNote->id, 'tenant' => $tenantCode]) }}">Imprimer le BL</a>
            @if ($deliveryNote->invoice_id)
                <a class="btn btn-secondary" href="{{ route('tenant.invoicing.edit', [$deliveryNote->invoice_id, 'tenant' => $tenantCode]) }}">Voir facture</a>
            @endif
            @if ($deliveryNote->isConfirmed() && $deliveryNote->quotation_id && !$deliveryNote->invoice_id && ($canInvoice ?? false))
                <a class="btn btn-primary" href="{{ route('tenant.invoicing.create', ['tenant' => $tenantCode, 'delivery_note' => $deliveryNote->id]) }}">Créer la facture</a>
            @endif
            @if ($canEdit)
                <a class="btn btn-secondary" href="{{ route('tenant.invoicing.deliveries.edit', ['deliveryNote' => $deliveryNote->id, 'tenant' => $tenantCode]) }}">Modifier brouillon</a>
            @endif
            @if ($deliveryNote->isDraft() && $canConfirm)
                <button type="button" class="btn btn-primary" wire:click="confirmDelivery"
                        wire:confirm="Confirmer la livraison ? Le stock sera déduit pour les quantités indiquées.">
                    Valider la livraison
                </button>
            @endif
            @if ($deliveryNote->isDraft() && $canCreate)
                <button type="button" class="btn btn-secondary" wire:click="cancelDraft"
                        wire:confirm="Annuler ce brouillon de livraison ?">Annuler le brouillon</button>
            @endif
        </div>
    </section>
</div>
