@php
    use App\Support\CommercialPrintPaginator;

    $sortedLines = $receipt->lines
        ->sortBy(fn ($rLine) => (int) ($rLine->purchaseLine?->line_number ?? 0))
        ->values();
    $printPages = CommercialPrintPaginator::pages($sortedLines);
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
                            'docDate' => $receipt->receipt_date->format('d/m/y'),
                            'docLabel' => 'RÉCEPTION N°',
                            'docNumber' => $receipt->receipt_number,
                            'docSubtitle' => 'Commande ' . $purchase->order_number,
                        ])

                        <div class="doc-title-band">Bon de réception</div>

                        @include('inovcom-purchases::print.partials.supplier-zone', ['provider' => $purchase->provider])
                    @else
                        @include('partials.print.commercial-doc-continuation', [
                            'docLabel' => 'RÉCEPTION N°',
                            'docNumber' => $receipt->receipt_number,
                            'clientName' => $purchase->provider?->name,
                            'pageIndex' => $pageIndex,
                            'totalPages' => $totalPrintPages,
                        ])
                    @endif

                    <table class="lines-table">
                        <thead>
                            <tr>
                                <th style="width:6%">N°</th>
                                <th style="width:54%">Référence / Article</th>
                                <th style="width:18%">Qté reçue</th>
                                <th style="width:22%">Prix unitaire</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pageLines as $i => $rLine)
                                @php
                                    $pl = $rLine->purchaseLine;
                                    $lineNo = ($pl?->line_number ?? null) ?: (($lineOffset + $i + 1) * 10);
                                @endphp
                                <tr>
                                    <td class="qty">{{ $lineNo }}</td>
                                    <td class="left"><x-item-label :reference="$pl?->item?->sku" :name="$pl?->item_name" /></td>
                                    <td class="qty">{{ fmt_num($rLine->quantity_received) }}</td>
                                    <td class="num">{{ fmt_money($pl->unit_price ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($isLastPage && $receipt->notes)
                        <div class="doc-note"><strong>Notes :</strong> <span class="doc-note__text">{{ $receipt->notes }}</span></div>
                    @endif

                    @if ($isLastPage)
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
