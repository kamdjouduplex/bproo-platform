@php
    use App\Support\CommercialPrintPaginator;

    $printPages = CommercialPrintPaginator::pages($order->lines);
    $docSubtitle = $statusLabel;
    if ($order->expected_date) {
        $docSubtitle .= ($docSubtitle ? ' — ' : '') . 'Livraison prévue le ' . $order->expected_date->format('d/m/Y');
    }
    $docSubtitle .= ($docSubtitle ? ' — ' : '') . 'Montants en ' . $order->currency_code;
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
                            'docDate' => $order->order_date->format('d/m/y'),
                            'docLabel' => 'COMMANDE N°',
                            'docNumber' => $order->order_number,
                            'docSubtitle' => $docSubtitle ?: null,
                        ])

                        <div class="doc-title-band">Bon de commande (import)</div>

                        @include('inovcom-purchases::print.partials.supplier-zone', ['provider' => $order->provider])

                        <div class="doc-meta-row">
                            <span><strong>Devise :</strong> {{ $order->currency_code }}</span>
                            <span><strong>Taux :</strong> 1 {{ $order->currency_code }} = {{ fmt_num((float) $order->exchange_rate, 4) }} FCFA</span>
                        </div>
                    @else
                        @include('partials.print.commercial-doc-continuation', [
                            'docLabel' => 'COMMANDE N°',
                            'docNumber' => $order->order_number,
                            'clientName' => $order->provider?->name,
                            'pageIndex' => $pageIndex,
                            'totalPages' => $totalPrintPages,
                        ])
                    @endif

                    <table class="lines-table">
                        <thead>
                            <tr>
                                <th style="width:6%">N°</th>
                                <th style="width:40%">Référence / Article</th>
                                <th style="width:12%">Qté</th>
                                <th style="width:20%">Prix unit. ({{ $order->currency_code }})</th>
                                <th style="width:22%">Total ({{ $order->currency_code }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pageLines as $i => $line)
                                @php
                                    $lineNo = ($line->line_number ?? null) ?: (($lineOffset + $i + 1) * 10);
                                @endphp
                                <tr>
                                    <td class="qty">{{ $lineNo }}</td>
                                    <td class="left"><x-item-label :reference="$line->item?->sku" :name="$line->item_name" /></td>
                                    <td class="qty">{{ fmt_num((float) $line->quantity, 3) }}</td>
                                    <td class="num">{{ fmt_num((float) $line->unit_price_foreign, 4) }}</td>
                                    <td class="num">{{ fmt_num((float) $line->line_total_foreign, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($isLastPage)
                        <div class="totals-wrap">
                            <table class="totals-table">
                                <tr class="net-row">
                                    <td class="label">TOTAL {{ $order->currency_code }}</td>
                                    <td class="value">{{ fmt_num((float) $order->subtotal_foreign, 2) }} {{ $order->currency_code }}</td>
                                </tr>
                            </table>
                        </div>

                        @if ($order->notes)
                            <div class="doc-note"><strong>Notes :</strong> <span class="doc-note__text">{{ $order->notes }}</span></div>
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
