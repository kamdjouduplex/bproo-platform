@php
    use App\Support\CommercialPrintPaginator;
    use App\Support\DocumentTaxCalculator;

    $clientNiu = ($quotation->client->niu ?? null) ?: ($quotation->client->tax_id ?? null);
    $clientRc = ($quotation->client->rccm ?? null) ?: ($quotation->client->metadata['rc'] ?? $quotation->client->metadata['rccm'] ?? null);
    $clientLocation = ($quotation->client->bp ?? null) ?: ($quotation->client->address ?? null);

    $headerDiscountMode = ($quotation->discount_mode ?? null) === 'amount'
        ? 'amount'
        : ((float) ($quotation->discount_percent ?? 0) > 0 ? 'percent' : 'amount');
    $hasLineDiscount = $quotation->lines->contains(fn ($l) => line_discount_has_value($l));
    $netHt = max(0, (float) $quotation->subtotal - (float) $quotation->discount_amount);
    $taxSummary = DocumentTaxCalculator::summarizeFromStoredTaxLines(
        $netHt,
        $quotation->taxLines ?? collect(),
        (float) $quotation->tax_amount
    );
    $withholdingAmount = $taxSummary['subtractive'];
    $tvaAmount = 0.0;
    $otherTaxAmount = 0.0;
    $withholdingRateLabel = null;
    $tvaRateLabel = null;

    $taxRateLabel = function ($taxLine) use ($netHt) {
        if ($taxLine->tax_rate !== null && (float) $taxLine->tax_rate > 0) {
            return fmt_num((float) $taxLine->tax_rate, 2) . '%';
        }
        $amt = (float) $taxLine->tax_amount;
        if ($netHt > 0 && $amt > 0) {
            return fmt_num(round($amt / $netHt * 100, 2), 2) . '%';
        }

        return null;
    };

    foreach (($quotation->taxLines ?? collect()) as $tl) {
        $amt = (float) $tl->tax_amount;
        if ($amt <= 0) {
            continue;
        }

        $effect = DocumentTaxCalculator::normalizeEffect($tl->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD);
        if ($effect === DocumentTaxCalculator::EFFECT_SUBTRACT) {
            if ($withholdingRateLabel === null && ($label = $taxRateLabel($tl))) {
                $withholdingRateLabel = $label;
            }
            continue;
        }

        $name = mb_strtoupper((string) $tl->tax_name);
        if (str_contains($name, 'TVA')) {
            $tvaAmount += $amt;
            if ($tvaRateLabel === null && ($label = $taxRateLabel($tl))) {
                $tvaRateLabel = $label;
            }
        } else {
            $otherTaxAmount += $amt;
        }
    }

    $montantTtc = $taxSummary['ttc'];
    $netAPayer = (float) $quotation->total > 0 ? (float) $quotation->total : $taxSummary['total'];
    $printPages = CommercialPrintPaginator::pages($quotation->lines);
@endphp
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
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .brand-block { flex: 1; text-align: left; padding-right: 24px; }
        .brand-block .tenant-doc-brand { display: flex; flex-direction: column; align-items: flex-start; }
        .brand-block .brand-name { font-weight: 800; font-size: 16px; }
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
        .lines-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .lines-table th,
        .lines-table td {
            border: 1px solid #111;
            padding: 5px 6px;
        }
        .lines-table thead th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: center;
        }
        .lines-table td.num { text-align: right; white-space: nowrap; }
        .lines-table td.qty { text-align: center; }
        @include('partials.item-label-css')
        @include('partials.print.commercial-document-css')
        .totals-wrap { display: flex; justify-content: flex-end; margin-top: 4px; }
        .totals-table {
            border-collapse: collapse;
            min-width: 300px;
            font-size: 11px;
        }
        .totals-table td {
            border: 1px solid #111;
            padding: 6px 12px;
        }
        .totals-table .label { font-weight: 700; text-align: left; white-space: nowrap; }
        .totals-table .value { text-align: right; font-weight: 700; min-width: 110px; }
        .totals-table tr.net-row .label,
        .totals-table tr.net-row .value {
            font-size: 13px;
            font-weight: 800;
        }
        .doc-footer .footer-rule {
            height: 3px;
            width: 230px;
            margin-bottom: 6px;
            background: linear-gradient(90deg, #7c3aed 0%, #7c3aed 55%, #65a30d 55%, #65a30d 100%);
            border-radius: 2px;
        }
        .doc-footer .footer-name {
            font-weight: 800;
            font-size: 12px;
            color: #4d7c0f;
            letter-spacing: .2px;
            margin-bottom: 2px;
        }
        .doc-footer .footer-legal,
        .doc-footer .footer-contact { color: #1f2937; }
        .doc-footer .footer-legal b,
        .doc-footer .footer-contact b { font-weight: 700; }
        .doc-footer .footer-sep { display: inline-block; width: 10px; }
        .doc-footer .print-stamp {
            margin-top: 4px;
            font-size: 8.5px;
            color: #6b7280;
            font-weight: 700;
        }
        .doc-footer .print-stamp strong,
        .doc-footer .print-stamp b {
            font-weight: 800;
        }
        .no-print { margin-top: 20px; text-align: center; color: #666; font-size: 11px; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    @foreach ($printPages as $printPage)
        @php
            $pageLines = $printPage['lines'];
            $pageIndex = $printPage['index'];
            $totalPrintPages = $printPage['total'];
            $lineOffset = $printPage['offset'];
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
                        <th>DEVIS N°</th>
                    </tr>
                    <tr>
                        <td>{{ $quotation->quote_date->format('d/m/y') }}</td>
                        <td class="doc-number">{{ $quotation->number }}</td>
                    </tr>
                </table>
                @if ($quotation->valid_until)
                    <div class="doc-validity">Valide jusqu'au {{ $quotation->valid_until->format('d/m/Y') }}</div>
                @endif
            </div>
        </div>

        <div class="client-zone">
            <div class="client-box">
                <span class="client-label">Client :</span>
                <strong>{{ $quotation->client->name }}</strong><br>
                @if($quotation->client->phone){{ $quotation->client->phone }}<br>@endif
                @if($clientNiu)NIU: {{ $clientNiu }}<br>@endif
                @if($clientRc)RCCM: {{ $clientRc }}<br>@endif
                @if($clientLocation){{ $clientLocation }}@endif
            </div>
        </div>

        @if (!empty($quotation->customer_purchase_order))
            <div style="margin-bottom:10px;font-size:11px;">
                <strong>N° demande achat :</strong> {{ $quotation->customer_purchase_order }}
            </div>
        @endif
                @else
                    @include('partials.print.commercial-doc-continuation', [
                        'docLabel' => 'DEVIS N°',
                        'docNumber' => $quotation->number,
                        'clientName' => $quotation->client->name,
                        'pageIndex' => $pageIndex,
                        'totalPages' => $totalPrintPages,
                    ])
                @endif

        <table class="lines-table">
            <thead>
                <tr>
                    <th style="width:6%">N°</th>
                    <th style="width:{{ $hasLineDiscount ? '34%' : '42%' }}">Référence / Article</th>
                    <th style="width:10%">Qté</th>
                    <th style="width:14%">P.U.</th>
                    @if ($hasLineDiscount)
                        <th style="width:12%">Remise</th>
                    @endif
                    <th style="width:14%">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pageLines as $i => $line)
                @php
                    $puNet = $line->unit_price_net !== null
                        ? (float) $line->unit_price_net
                        : max(0, (float) $line->unit_price - (float) ($line->line_discount ?? 0));
                    $lineNo = $line->line_number ?: (($lineOffset + $i + 1) * 10);
                    $discountLabel = format_line_discount_label($line);
                @endphp
                <tr>
                    <td class="qty">{{ $lineNo }}</td>
                    <td><x-item-label :reference="$line->item_sku" :name="$line->item_name" /></td>
                    <td class="qty">{{ fmt_num((float)$line->quantity, 2) }}</td>
                    <td class="num">{{ fmt_num($puNet, 2) }}</td>
                    @if ($hasLineDiscount)
                        <td class="num">{{ $discountLabel !== '—' ? $discountLabel : '' }}</td>
                    @endif
                    <td class="num">{{ fmt_money((float)$line->line_total) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if ($isLastPage)
        <div class="totals-wrap">
            <table class="totals-table">
                @if ((float) $quotation->discount_amount > 0)
                <tr>
                    <td class="label">
                        REMISE
                        @if ($headerDiscountMode === 'percent' && (float) $quotation->discount_percent > 0)
                            <span class="label-rate">({{ fmt_num((float) $quotation->discount_percent) }} %)</span>
                        @elseif ($headerDiscountMode === 'amount')
                            <span class="label-rate">(montant fixe)</span>
                        @endif
                    </td>
                    <td class="value">−{{ fmt_money((float) $quotation->discount_amount) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">MONTANT HT</td>
                    <td class="value">{{ fmt_money($netHt) }}</td>
                </tr>
                @if ($withholdingAmount > 0)
                <tr>
                    <td class="label">
                        MONTANT IS
                        @if ($withholdingRateLabel)
                            <span class="label-rate">({{ $withholdingRateLabel }})</span>
                        @endif
                    </td>
                    <td class="value">−{{ fmt_money($withholdingAmount) }}</td>
                </tr>
                @endif
                @if ($tvaAmount > 0)
                <tr>
                    <td class="label">
                        MONTANT TVA
                        @if ($tvaRateLabel)
                            <span class="label-rate">({{ $tvaRateLabel }})</span>
                        @endif
                    </td>
                    <td class="value">+{{ fmt_money($tvaAmount) }}</td>
                </tr>
                @endif
                @foreach (($quotation->taxLines ?? collect()) as $taxLine)
                    @php
                        $effect = DocumentTaxCalculator::normalizeEffect($taxLine->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD);
                        $name = mb_strtoupper((string) $taxLine->tax_name);
                        $isWithholding = $effect === DocumentTaxCalculator::EFFECT_SUBTRACT;
                        $isTva = str_contains($name, 'TVA');
                    @endphp
                    @if ((float) $taxLine->tax_amount > 0 && !$isWithholding && !$isTva)
                    <tr>
                        <td class="label">
                            MONTANT {{ mb_strtoupper($taxLine->tax_name) }}
                            @if (($taxLine->tax_mode ?? 'amount') === 'percent' && $taxLine->tax_rate !== null)
                                <span class="label-rate">({{ fmt_num((float) $taxLine->tax_rate) }} %)</span>
                            @endif
                        </td>
                        <td class="value">+{{ fmt_money((float) $taxLine->tax_amount) }}</td>
                    </tr>
                    @endif
                @endforeach
                @if (($quotation->taxLines ?? collect())->count() === 0 && (float) $quotation->tax_amount > 0)
                <tr>
                    <td class="label">TVA @if((float) $quotation->tax_rate > 0)({{ fmt_num((float) $quotation->tax_rate) }} %)@endif</td>
                    <td class="value">+{{ fmt_money((float) $quotation->tax_amount) }}</td>
                </tr>
                @endif
                <tr class="net-row">
                    <td class="label">MONTANT TTC</td>
                    <td class="value">{{ fmt_money($montantTtc) }}</td>
                </tr>
                <tr class="net-row">
                    <td class="label"><strong>NET A PAYER</strong></td>
                    <td class="value"><strong>{{ fmt_money($netAPayer) }}</strong></td>
                </tr>
            </table>
        </div>

        @if($quotation->notes)
            <div class="doc-note"><strong>Notes :</strong> <span class="doc-note__text">{{ $quotation->notes }}</span></div>
        @endif
                </div>
        <div class="signature">LA DIRECTION</div>
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
