<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup')
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
        }
        .page { margin: 0; padding: 0; max-width: 210mm; width: 100%; }
        .brand-block .brand-name { font-weight: 800; font-size: 16px; }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .brand-block { flex: 1; text-align: left; padding-right: 24px; }
        .tenant-doc-logo { max-height: 80px; max-width: 260px; object-fit: contain; }
        .brand-ids { margin-top: 6px; font-size: 9.5px; line-height: 1.5; color: #1f2937; }
        .brand-ids b { font-weight: 700; }
        .doc-box {
            border: 2px solid #111;
            width: 300px;
            font-size: 11px;
        }
        .doc-box table { width: 100%; border-collapse: collapse; }
        .doc-box td, .doc-box th {
            border: 1px solid #111;
            padding: 6px 10px;
            text-align: center;
        }
        .doc-box th { font-weight: 700; background: #f8f8f8; }
        .doc-number { font-size: 16px; font-weight: 800; letter-spacing: 1px; }
        .doc-validity { padding: 4px 8px; font-size: 9px; text-align: center; border-top: 1px solid #111; }
        .doc-title {
            text-align: center;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 3px;
            margin: 6px 0 14px;
            text-transform: uppercase;
        }
        .client-zone {
            display: flex;
            justify-content: flex-end;
            margin: 8px 0 12px;
        }
        .client-box {
            border: 1px solid #111;
            padding: 8px 12px;
            width: 300px;
            font-size: 11px;
            line-height: 1.55;
        }
        .client-box strong { font-size: 12px; }
        .client-box .client-label {
            display: block;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: .5px;
            color: #555;
            margin-bottom: 2px;
        }
        .doc-subtitle {
            text-align: center;
            font-size: 12px;
            line-height: 1.6;
            margin: -8px 0 14px;
            color: #1f2937;
        }
        .doc-subtitle strong { font-weight: 700; }
        .totals-table {
            width: 320px;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 10px;
            margin-top: 8px;
        }
        .totals-table td {
            border: 1px solid #111;
            padding: 5px 10px;
        }
        .totals-table td:last-child { text-align: right; font-weight: 600; }
        .totals-table tr.grand td { font-weight: 800; background: #f8f8f8; }
        .lines-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 10px;
        }
        .lines-table th,
        .lines-table td {
            border: 1px solid #111;
            padding: 6px 8px;
        }
        .lines-table thead th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: center;
        }
        .lines-table td.qty { text-align: center; font-weight: 600; }
        .lines-table td.num { text-align: right; white-space: nowrap; }
        @include('partials.item-label-css')
        @include('partials.print.commercial-document-css')
        .notes-block {
            margin: 10px 0;
            padding: 8px 12px;
            border: 1px dashed #999;
            font-size: 10px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .legal-note { font-size: 10px; margin-top: 8px; line-height: 1.45; }
        .signatures {
            display: flex;
            justify-content: space-between;
            gap: 32px;
            margin-top: 36px;
        }
        .sign-box {
            flex: 1;
            border: 1px solid #111;
            min-height: 110px;
            padding: 8px 12px;
        }
        .sign-box .sign-title {
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1px solid #111;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .sign-box .sign-meta { font-size: 9px; color: #555; line-height: 1.8; }
        .doc-footer .print-stamp { margin-top: 4px; font-size: 8.5px; color: #6b7280; font-weight: 700; }
        .doc-footer .print-stamp strong,
        .doc-footer .print-stamp b { font-weight: 800; }
        .doc-footer .footer-rule {
            height: 3px;
            width: 230px;
            margin-bottom: 6px;
            background: linear-gradient(90deg, #7c3aed 0%, #7c3aed 55%, #65a30d 55%, #65a30d 100%);
            border-radius: 2px;
        }
        .doc-footer .footer-name { font-weight: 800; font-size: 12px; color: #4d7c0f; letter-spacing: .2px; margin-bottom: 2px; }
        .doc-footer .footer-legal b, .doc-footer .footer-contact b { font-weight: 700; }
        .doc-footer .footer-sep { display: inline-block; width: 10px; }
        .no-print { margin-top: 20px; text-align: center; color: #666; font-size: 11px; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    @php
        use App\Support\CommercialPrintPaginator;

        $showPrices = $showPrices ?? false;
        $showDiscounts = $showDiscounts ?? false;
        $printLines = $printData['lines'] ?? [];
        $printPages = CommercialPrintPaginator::pages(
            $printLines,
            CommercialPrintPaginator::DELIVERY_NOTE_FIRST_PAGE_LINES,
            CommercialPrintPaginator::CONTINUATION_PAGE_LINES,
        );
    @endphp

    @foreach ($printPages as $printPage)
        @php
            $pageLines = $printPage['lines'];
            $pageIndex = $printPage['index'];
            $totalPrintPages = $printPage['total'];
            $isLastPage = $pageIndex === $totalPrintPages - 1;
        @endphp
        <div class="page{{ $isLastPage ? ' page--last' : '' }}">
            <div class="print-page-inner{{ $isLastPage ? ' print-page-inner--last' : '' }}">
                <div class="print-page-content">
                @if ($pageIndex === 0)
        <div class="header-top">
            <div class="brand-block">
                @php $logoSrc = $settings['logo_embed_src'] ?? $settings['logo_url'] ?? null; @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $settings['shop_name'] ?? '' }}" class="tenant-doc-logo">
                @else
                    <div class="brand-name">{{ $settings['shop_name'] ?? '' }}</div>
                @endif
                @if(($settings['print_show_header_company_info'] ?? true) && (!empty($settings['shop_tax_id']) || !empty($settings['shop_rccm']) || !empty($settings['shop_cnps']) || !empty($settings['shop_address']) || !empty($settings['shop_bp']) || !empty($settings['shop_phone']) || !empty($settings['shop_email'])))
                    <div class="brand-ids">
                        @if(!empty($settings['shop_tax_id']))<div><b>NIU :</b> {{ $settings['shop_tax_id'] }}</div>@endif
                        @if(!empty($settings['shop_rccm']))<div><b>RCCM :</b> {{ $settings['shop_rccm'] }}</div>@endif
                        @if(!empty($settings['shop_cnps']))<div><b>CNPS :</b> {{ $settings['shop_cnps'] }}</div>@endif
                        @if(!empty($settings['shop_address']))<div><b>Adresse :</b> {{ trim(preg_replace('/\s+/', ' ', $settings['shop_address'])) }}</div>@endif
                        @if(!empty($settings['shop_bp']))<div><b>B.P. :</b> {{ $settings['shop_bp'] }}</div>@endif
                        @if(!empty($settings['shop_phone']))<div><b>Tél :</b> {{ $settings['shop_phone'] }}</div>@endif
                        @if(!empty($settings['shop_email']))<div><b>Mail :</b> {{ $settings['shop_email'] }}</div>@endif
                    </div>
                @endif
            </div>
            <div class="doc-box">
                <table>
                    <tr>
                        <th>DATE</th>
                        <th>BL N°</th>
                    </tr>
                    <tr>
                        <td>{{ $deliveryNote->delivery_date->format('d/m/y') }}</td>
                        <td class="doc-number">{{ $deliveryNote->delivery_number }}</td>
                    </tr>
                </table>
                <div class="doc-validity">Statut : {{ \InovCom\Invoicing\Models\DeliveryNote::statusLabel($deliveryNote->status) }}</div>
            </div>
        </div>

        <div class="doc-title">Bon de livraison</div>

        <div class="doc-subtitle">
            @if (!empty($printData['quotation_number']))
                Bon de livraison suivant Bon de Commande N° <strong>{{ $printData['purchase_order'] ?? $printData['quotation_number'] }}</strong>
            @elseif (!empty($printData['invoice_number']))
                Bon de livraison suivant facture N° <strong>{{ $printData['invoice_number'] }}</strong>
                @if (!empty($printData['purchase_order']))
                    <br>N° demande achat : <strong>{{ $printData['purchase_order'] }}</strong>
                @endif
            @elseif (!empty($printData['purchase_order']))
                Bon de livraison suivant Bon de Commande N° <strong>{{ $printData['purchase_order'] }}</strong>
            @endif
        </div>

        <div class="client-zone">
            <div class="client-box">
                <span class="client-label">Destinataire :</span>
                @if($client ?? null)
                    <strong>{{ $client->name }}</strong><br>
                    @if($client->phone){{ $client->phone }}<br>@endif
                    @if($client->tax_id)NIU : {{ $client->tax_id }}<br>@endif
                    @if($client->address){{ $client->address }}@endif
                @else
                    —
                @endif
            </div>
        </div>
                @else
                    @include('partials.print.commercial-doc-continuation', [
                        'docLabel' => 'BL N°',
                        'docNumber' => $deliveryNote->delivery_number,
                        'clientName' => $client->name ?? null,
                        'pageIndex' => $pageIndex,
                        'totalPages' => $totalPrintPages,
                    ])
                @endif

        <table class="lines-table">
            <thead>
                <tr>
                    <th style="width:{{ $showPrices && $showDiscounts ? '28%' : '38%' }}">Référence / Article</th>
                    <th style="width:8%">Qté</th>
                    @if ($showPrices)
                        <th style="width:12%">PU</th>
                        @if ($showDiscounts)
                            <th style="width:10%">Remise</th>
                            <th style="width:12%">PU net</th>
                        @endif
                        <th style="width:14%">Montant HT</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($pageLines as $line)
                @php
                    $discountLabel = format_line_discount_label([
                        'line_discount_mode' => $line['line_discount_mode'] ?? 'amount',
                        'line_discount_input' => $line['line_discount_input'] ?? null,
                        'line_discount' => $line['line_discount'] ?? $line['line_discount_per_unit'] ?? 0,
                        'unit_price' => $line['unit_price'] ?? 0,
                    ]);
                    $puNet = (float) ($line['unit_price_net'] ?? max(0, (float) ($line['unit_price'] ?? 0) - (float) ($line['line_discount'] ?? 0)));
                @endphp
                <tr>
                    <td><x-item-label :reference="$line['item_sku'] ?? null" :name="$line['item_name'] ?? null" /></td>
                    <td class="qty">{{ fmt_num((float) $line['quantity'], 2) }}</td>
                    @if ($showPrices)
                        <td class="num">{{ fmt_num((float) $line['unit_price'], 2) }}</td>
                        @if ($showDiscounts)
                            <td class="num">{{ $discountLabel !== '—' ? $discountLabel : '' }}</td>
                            <td class="num">{{ fmt_num($puNet, 2) }}</td>
                        @endif
                        <td class="num">{{ fmt_money((float) $line['line_total']) }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>

        @if ($isLastPage)
        @if ($showPrices)
            @php
                $netHt = (float) ($printData['net_ht'] ?? max(0, (float) $printData['subtotal'] - (float) ($printData['discount_amount'] ?? 0)));
                $taxLines = $printData['tax_lines'] ?? [];
                $hasSubtractiveTax = collect($taxLines)->contains(
                    fn ($line) => ($line['tax_effect'] ?? 'add') === 'subtract' && (float) ($line['tax_amount'] ?? 0) > 0
                );
            @endphp
            <table class="totals-table">
                @if ($showDiscounts && (float) ($printData['discount_amount'] ?? 0) > 0)
                    <tr>
                        <td>
                            REMISE
                            @if ((float) ($printData['discount_percent'] ?? 0) > 0)
                                ({{ fmt_num((float) $printData['discount_percent'], 2) }} %)
                            @endif
                        </td>
                        <td>−{{ fmt_money((float) $printData['discount_amount']) }}</td>
                    </tr>
                @endif
                <tr>
                    <td>MONTANT HT</td>
                    <td>{{ fmt_money($netHt) }}</td>
                </tr>
                @foreach ($taxLines as $line)
                    @if ((float) ($line['tax_amount'] ?? 0) > 0)
                        @php $taxSubtract = ($line['tax_effect'] ?? 'add') === 'subtract'; @endphp
                        <tr>
                            <td>
                                MONTANT {{ mb_strtoupper($line['tax_name'] ?? 'Taxe') }}
                                @if (($line['tax_mode'] ?? 'amount') === 'percent' && isset($line['tax_rate']))
                                    ({{ fmt_num((float) $line['tax_rate'], 2) }} %)
                                @endif
                            </td>
                            <td>{{ $taxSubtract ? '−' : '+' }}{{ fmt_money((float) $line['tax_amount']) }}</td>
                        </tr>
                    @endif
                @endforeach
                @if ($hasSubtractiveTax && (float) ($printData['ttc'] ?? 0) > 0)
                    <tr>
                        <td>MONTANT TTC</td>
                        <td>{{ fmt_money((float) $printData['ttc']) }}</td>
                    </tr>
                    <tr class="grand">
                        <td><strong>NET A PAYER</strong></td>
                        <td><strong>{{ fmt_money((float) $printData['total']) }}</strong></td>
                    </tr>
                @else
                    <tr class="grand">
                        <td><strong>MONTANT TTC</strong></td>
                        <td><strong>{{ fmt_money((float) ($printData['ttc'] ?? $printData['total'] ?? $netHt)) }}</strong></td>
                    </tr>
                @endif
            </table>
        @endif

        @if($deliveryNote->notes)
            <div class="notes-block">
                <strong>Observations :</strong>
                <span class="doc-note__text">{{ $deliveryNote->notes }}</span>
            </div>
        @endif

        <div class="legal-note">
            Les marchandises désignées ci-dessus ont été livrées en bon état. Toute réclamation doit être formulée à la réception.
        </div>
                </div>
        <div class="signatures">
            <div class="sign-box">
                <div class="sign-title">Visa du livreur</div>
                <div class="sign-meta">
                    Nom : {{ $deliveryNote->confirmer?->name ?? $deliveryNote->creator?->name ?? '............................' }}<br>
                    Date : {{ optional($deliveryNote->confirmed_at)->format('d/m/Y') ?? '....../....../..........' }}<br>
                    Signature &amp; cachet :
                </div>
            </div>
            <div class="sign-box">
                <div class="sign-title">Visa du réceptionnaire</div>
                <div class="sign-meta">
                    Nom : ............................<br>
                    Date : ....../....../..........<br>
                    Signature (bon pour réception) :
                </div>
            </div>
        </div>
        <div class="signature-space" aria-hidden="true"></div>
        <div class="print-page-footer">
        @include('partials.print.commercial-doc-footer', [
            'settings' => $settings,
            'showStamp' => false,
            'pageIndex' => $pageIndex,
            'totalPages' => $totalPrintPages,
        ])
        </div>
        @else
                </div>
            @include('partials.print.commercial-doc-footer', [
                'settings' => $settings,
                'compact' => true,
                'pageIndex' => $pageIndex,
                'totalPages' => $totalPrintPages,
            ])
        @endif
            </div>
        </div>
    @endforeach

    @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
