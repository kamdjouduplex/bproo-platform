@php
    use App\Support\CommercialPrintPaginator;

    $printPages = CommercialPrintPaginator::pages($purchase->lines);
    $docSubtitle = $statusLabel;
    if ($purchase->expected_date) {
        $docSubtitle .= ($docSubtitle ? ' — ' : '') . 'Livraison prévue le ' . $purchase->expected_date->format('d/m/Y');
    }
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('inovcom-purchases::print.partials.purchase-print-styles')
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
                        @include('partials.print.document-header', [
                            'settings' => $settings,
                            'docDate' => $purchase->order_date->format('d/m/y'),
                            'docLabel' => 'COMMANDE N°',
                            'docNumber' => $purchase->order_number,
                            'docSubtitle' => $docSubtitle ?: null,
                        ])

                        <div class="doc-title-band">Bon de commande</div>

                        @include('inovcom-purchases::print.partials.supplier-zone', ['provider' => $purchase->provider])
                    @else
                        @include('partials.print.commercial-doc-continuation', [
                            'docLabel' => 'COMMANDE N°',
                            'docNumber' => $purchase->order_number,
                            'clientName' => $purchase->provider?->name,
                            'pageIndex' => $pageIndex,
                            'totalPages' => $totalPrintPages,
                        ])
                    @endif

                    @php $hasVat = (bool) ($purchase->has_vat ?? false); @endphp
                    <table class="lines-table">
                        <thead>
                            <tr>
                                <th style="width:6%">N°</th>
                                <th style="width:38%">Référence / Article</th>
                                <th style="width:12%">Qté commandée</th>
                                <th style="width:12%">Qté annulée</th>
                                <th style="width:14%">{{ $hasVat ? 'Prix HT' : 'Prix unitaire' }}</th>
                                <th style="width:18%">{{ $hasVat ? 'Total HT' : 'Total' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pageLines as $i => $line)
                                @php
                                    $lineNo = ($line->line_number ?? null) ?: (($lineOffset + $i + 1) * 10);
                                    $unitHt = $line->unit_price_ht ?? $line->unit_price;
                                    $lineHt = $line->line_total_ht ?? $line->line_total;
                                @endphp
                                <tr>
                                    <td class="qty">{{ $lineNo }}</td>
                                    <td class="left"><x-item-label :reference="$line->item?->sku" :name="$line->item_name" /></td>
                                    <td class="qty">{{ fmt_num($line->quantity) }}</td>
                                    <td class="qty">{{ fmt_num($line->cancelled_quantity) }}</td>
                                    <td class="num">{{ fmt_money($unitHt) }}</td>
                                    <td class="num">{{ fmt_money($lineHt) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($isLastPage)
                        <div class="totals-wrap">
                            <table class="totals-table">
                                @if ($hasVat)
                                    <tr>
                                        <td class="label">MONTANT HT</td>
                                        <td class="value">{{ fmt_money($purchase->total_ht ?? $purchase->subtotal) }} FCFA</td>
                                    </tr>
                                    <tr>
                                        <td class="label">TVA {{ fmt_num((float) ($purchase->vat_rate ?? 0), 2) }} %</td>
                                        <td class="value">{{ fmt_money($purchase->vat_amount ?? 0) }} FCFA</td>
                                    </tr>
                                    <tr class="net-row">
                                        <td class="label">TOTAL TTC</td>
                                        <td class="value">{{ fmt_money($purchase->total_ttc ?? $purchase->total) }} FCFA</td>
                                    </tr>
                                @else
                                    <tr class="net-row">
                                        <td class="label">TOTAL</td>
                                        <td class="value">{{ fmt_money($purchase->total) }} FCFA</td>
                                    </tr>
                                @endif
                            </table>
                        </div>

                        @if ($purchase->notes)
                            <div class="doc-note"><strong>Notes :</strong> <span class="doc-note__text">{{ $purchase->notes }}</span></div>
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
