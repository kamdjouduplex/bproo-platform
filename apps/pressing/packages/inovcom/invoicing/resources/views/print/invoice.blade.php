@php
    use App\Support\CommercialPrintPaginator;
    use App\Support\DocumentTaxCalculator;

    $montantHt = max(0, round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2));
    $quotation = $invoice->quotation;
    $headerDiscountMode = document_discount_header_mode($invoice, $quotation);
    $headerDiscountPercent = document_discount_percent_display($invoice, $quotation);
    $quotationLinesByItem = $quotation
        ? $quotation->lines->keyBy(fn ($line) => (int) ($line->item_id ?? 0))
        : collect();

    $taxSummary = DocumentTaxCalculator::summarizeFromStoredTaxLines(
        $montantHt,
        $invoice->taxLines,
        (float) $invoice->tax_amount
    );

    $withholdingAmount = $taxSummary['subtractive'];
    $tvaAmount = 0.0;
    $otherTaxAmount = 0.0;
    $withholdingRateLabel = null;
    $tvaRateLabel = null;

    $taxRateLabel = function ($taxLine) use ($montantHt) {
        if ($taxLine->tax_rate !== null && (float) $taxLine->tax_rate > 0) {
            return fmt_num((float) $taxLine->tax_rate, 2) . '%';
        }
        $amt = (float) $taxLine->tax_amount;
        if ($montantHt > 0 && $amt > 0) {
            return fmt_num(round($amt / $montantHt * 100, 2), 2) . '%';
        }

        return null;
    };

    foreach ($invoice->taxLines as $tl) {
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

    if ($invoice->taxLines->isEmpty() && (float) $invoice->tax_amount > 0) {
        $tvaAmount = (float) $invoice->tax_amount;
    }

    $montantTtc = $taxSummary['ttc'];
    $netAPayer = (float) $invoice->total > 0 ? (float) $invoice->total : $taxSummary['total'];
    $withholdingAmount = $taxSummary['subtractive'];

    $clientNiu = $invoice->client->niu ?: $invoice->client->tax_id;
    $clientRc = $invoice->client->rccm
        ?: ($invoice->client->metadata['rc'] ?? $invoice->client->metadata['rccm'] ?? null);
    $clientLocation = $invoice->client->bp ?: $invoice->client->address;
    $printPages = CommercialPrintPaginator::pages($invoice->lines);
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
        .brand-block .brand-name { font-weight: 800; font-size: 16px; }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .brand-block { flex: 1; text-align: left; padding-right: 24px; }
        .brand-block .tenant-doc-brand { display: flex; flex-direction: column; align-items: flex-start; }
        .tenant-doc-logo { max-height: 80px; max-width: 260px; object-fit: contain; }
        .brand-ids { margin-top: 6px; font-size: 9.5px; line-height: 1.5; color: #1f2937; }
        .brand-ids b { font-weight: 700; }
        .brand-ids .brand-id-name { font-weight: 800; font-size: 11px; color: #4d7c0f; margin-bottom: 2px; }
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
        .refs-row {
            display: flex;
            gap: 32px;
            margin-bottom: 10px;
            font-size: 11px;
            flex-wrap: wrap;
        }
        .refs-row span { font-weight: 700; }
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
            min-width: 280px;
            font-size: 11px;
        }
        .totals-table td {
            border: 1px solid #111;
            padding: 6px 12px;
        }
        .totals-table .label { font-weight: 700; text-align: left; white-space: nowrap; }
        .totals-table .label-rate { font-weight: 400; }
        .totals-table .value { text-align: right; font-weight: 700; min-width: 110px; }
        .totals-table tr.net-row .label,
        .totals-table tr.net-row .value {
            font-size: 13px;
            font-weight: 800;
        }
        .bottom-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 14px;
            gap: 16px;
        }
        .amount-words {
            flex: 1;
            font-size: 11px;
            line-height: 1.5;
            padding-top: 4px;
        }
        .amount-words strong { font-weight: 700; }
        .payment-line { margin-top: 12px; font-size: 11px; }
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
                        <th>FACTURE</th>
                    </tr>
                    <tr>
                        <td>{{ $invoice->invoice_date->format('d/m/y') }}</td>
                        <td class="doc-number">{{ $invoice->invoice_number }}</td>
                    </tr>
                </table>
                @if($invoice->due_date)
                    <div class="doc-validity">Échéance : {{ $invoice->due_date->format('d/m/Y') }}</div>
                @endif
            </div>
        </div>

        <div class="client-zone">
            <div class="client-box">
                <span class="client-label">Client :</span>
                <strong>{{ $invoice->client->name }}</strong><br>
                @if($invoice->client->phone){{ $invoice->client->phone }}<br>@endif
                @if($clientNiu){{ $clientNiu }}<br>@endif
                @if($clientRc){{ $clientRc }}<br>@endif
                @if($clientLocation){{ $clientLocation }}@endif
            </div>
        </div>

        <div class="refs-row">
            @if($invoice->customer_reference)
                <div><span>N° DEMANDE ACHAT :</span> {{ $invoice->customer_reference }}</div>
            @endif
            @if($invoice->delivery_note_number)
                <div><span>BL N°</span>{{ $invoice->delivery_note_number }}</div>
            @endif
            @if($invoice->quotation_reference ?? ($invoice->quotation->number ?? null))
                <div><span>DEVIS N°</span> {{ $invoice->quotation_reference ?? $invoice->quotation->number }}</div>
            @endif
        </div>
                @else
                    @include('partials.print.commercial-doc-continuation', [
                        'docLabel' => 'FACTURE N°',
                        'docNumber' => $invoice->invoice_number,
                        'clientName' => $invoice->client->name,
                        'pageIndex' => $pageIndex,
                        'totalPages' => $totalPrintPages,
                    ])
                @endif

        <table class="lines-table">
            <thead>
                <tr>
                    <th style="width:6%">N°</th>
                    <th style="width:32%">Référence / Article</th>
                    <th style="width:8%">Qté</th>
                    <th style="width:12%">PU</th>
                    <th style="width:10%">Remise</th>
                    <th style="width:12%">PU NET</th>
                    <th style="width:14%">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pageLines as $i => $line)
                @php
                    $quotationLine = $quotationLinesByItem->get((int) ($line->item_id ?? 0));
                    $discountLabel = format_invoice_line_discount_label($line, $quotationLine);
                    $puNet = (float) ($line->unit_price_net ?? max(0, (float)$line->unit_price - (float) ($line->line_discount ?? 0)));
                    $lineHt = (float) $line->line_total;
                    $lineNo = $line->line_number ?: (($lineOffset + $i + 1) * 10);
                @endphp
                <tr>
                    <td class="qty">{{ $lineNo }}</td>
                    <td><x-item-label :reference="$line->item_sku" :name="$line->item_name" /></td>
                    <td class="qty">{{ fmt_num((float)$line->quantity, 2) }}</td>
                    <td class="num">{{ fmt_num((float)$line->unit_price, 2) }}</td>
                    <td class="num">{{ $discountLabel !== '—' ? $discountLabel : '' }}</td>
                    <td class="num">{{ fmt_num($puNet, 2) }}</td>
                    <td class="num">{{ fmt_money($lineHt) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if ($isLastPage)
        <div class="totals-wrap">
            <table class="totals-table">
                @if ((float) $invoice->discount_amount > 0)
                <tr>
                    <td class="label">
                        REMISE
                        @if ($headerDiscountMode === 'percent' && $headerDiscountPercent > 0)
                            <span class="label-rate">({{ fmt_num($headerDiscountPercent) }} %)</span>
                        @elseif ($headerDiscountMode === 'amount')
                            <span class="label-rate">(montant fixe)</span>
                        @endif
                    </td>
                    <td class="value">−{{ fmt_money((float) $invoice->discount_amount) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">MONTANT HT</td>
                    <td class="value">{{ fmt_money($montantHt) }}</td>
                </tr>
                @if($withholdingAmount > 0)
                <tr>
                    <td class="label">
                        MONTANT IS
                        @if($withholdingRateLabel)
                            <span class="label-rate">({{ $withholdingRateLabel }})</span>
                        @endif
                    </td>
                    <td class="value">−{{ fmt_money($withholdingAmount) }}</td>
                </tr>
                @endif
                @if($tvaAmount > 0)
                <tr>
                    <td class="label">
                        MONTANT TVA
                        @if($tvaRateLabel)
                            <span class="label-rate">({{ $tvaRateLabel }})</span>
                        @endif
                    </td>
                    <td class="value">+{{ fmt_money($tvaAmount) }}</td>
                </tr>
                @endif
                @foreach($invoice->taxLines as $tl)
                    @php
                        $effect = DocumentTaxCalculator::normalizeEffect($tl->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD);
                        $name = mb_strtoupper((string) $tl->tax_name);
                        $isWithholding = $effect === DocumentTaxCalculator::EFFECT_SUBTRACT;
                        $isTva = str_contains($name, 'TVA');
                        $otherRateLabel = $taxRateLabel($tl);
                    @endphp
                    @if((float)$tl->tax_amount > 0 && !$isWithholding && !$isTva)
                    <tr>
                        <td class="label">
                            MONTANT {{ mb_strtoupper($tl->tax_name) }}
                            @if($otherRateLabel)
                                <span class="label-rate">({{ $otherRateLabel }})</span>
                            @endif
                        </td>
                        <td class="value">+{{ fmt_money((float)$tl->tax_amount) }}</td>
                    </tr>
                    @endif
                @endforeach
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

        <div class="bottom-row">
            <div class="amount-words">
                Arrêtez la présente facture à la somme de : <strong>{{ ucfirst($amountInWords) }}</strong>
            </div>
        </div>

        <div class="payment-line">
            Mode de payement : {{ $invoice->payment_mode ?? $settings['payment_modes_default'] ?? 'chèque/Virement/Espèce' }}
        </div>

        @if(!empty($invoice->notes))
            <div class="doc-note"><strong>Note :</strong> <span class="doc-note__text">{{ $invoice->notes }}</span></div>
        @endif

        @if(!empty($invoice->additional_info))
            <div class="doc-note"><span class="doc-note__text">{{ $invoice->additional_info }}</span></div>
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
