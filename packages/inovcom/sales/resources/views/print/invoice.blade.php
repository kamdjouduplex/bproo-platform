@php
    $currencyLabel = ($currency ?? 'XOF') === 'XOF' ? 'FCFA' : ($currency ?? 'XOF');
    $saleDateLabel = $sale->sale_date?->format('d/m/y') ?? now()->format('d/m/y');
    $client = $sale->client;
    $clientNiu = $client?->niu ?: $client?->tax_id;
    $clientRc = $client?->rccm ?: ($client?->metadata['rc'] ?? $client?->metadata['rccm'] ?? null);
    $clientLocation = $client?->bp ?: $client?->address;
    $priceTierLabel = match ($sale->price_tier ?? null) {
        'retail' => 'Détail',
        'semi_wholesale' => 'Semi-gros',
        'wholesale' => 'Gros',
        default => $sale->price_tier ? (string) $sale->price_tier : '—',
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.document-base-styles')
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
        .lines-table td.left { text-align: left; }
        @include('partials.item-label-css')
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
        .totals-table .label { font-weight: 700; text-align: left; }
        .totals-table .value { text-align: right; font-weight: 700; min-width: 110px; }
        .totals-table tr.net-row .label,
        .totals-table tr.net-row .value { font-size: 13px; font-weight: 800; }
        .doc-meta-line {
            margin: 0 0 10px;
            font-size: 11px;
        }
        .payments-block { margin-top: 14px; }
        .payments-block h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #555;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="page page--last">
        <div class="print-page-inner print-page-inner--last">
            <div class="print-page-content">
                @include('partials.print.document-header', [
                    'settings' => $settings,
                    'docDate' => $saleDateLabel,
                    'docLabel' => 'FACTURE N°',
                    'docNumber' => $sale->sale_number,
                    'docSubtitle' => 'Vente Direct — ' . $priceTierLabel,
                ])

                <div class="client-zone">
                    <div class="client-box">
                        <span class="client-label">Client :</span>
                        <strong>{{ $client?->name ?? 'Client occasionnel' }}</strong><br>
                        @if ($client?->phone){{ $client->phone }}<br>@endif
                        @if ($clientNiu)NIU: {{ $clientNiu }}<br>@endif
                        @if ($clientRc)RCCM: {{ $clientRc }}<br>@endif
                        @if ($clientLocation){{ $clientLocation }}@endif
                        @if (!empty($rxSummary))
                            <br><br>
                            <span class="client-label">Ordonnance :</span>
                            <strong>{{ $rxSummary['number'] }}</strong> — {{ $rxSummary['status_label'] }}
                        @endif
                    </div>
                </div>

                <table class="lines-table">
                    <thead>
                        <tr>
                            <th style="width:6%">N°</th>
                            <th style="width:44%">Référence / Article</th>
                            <th style="width:12%">Qté</th>
                            <th style="width:18%">Prix unitaire</th>
                            <th style="width:20%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->lines as $i => $line)
                            <tr>
                                <td class="qty">{{ ($i + 1) * 10 }}</td>
                                <td class="left"><x-item-label :reference="$line->item_sku" :name="$line->item_name" /></td>
                                <td class="qty">{{ fmt_num((float) $line->quantity) }}</td>
                                <td class="num">{{ fmt_money((float) $line->unit_price) }}</td>
                                <td class="num">{{ fmt_money((float) $line->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="totals-wrap">
                    <table class="totals-table">
                        <tr>
                            <td class="label">Sous-total</td>
                            <td class="value">{{ fmt_money((float) $sale->subtotal) }} {{ $currencyLabel }}</td>
                        </tr>
                        @if ((float) $sale->discount_amount > 0)
                            <tr>
                                <td class="label">Remise</td>
                                <td class="value">−{{ fmt_money((float) $sale->discount_amount) }} {{ $currencyLabel }}</td>
                            </tr>
                        @endif
                        <tr class="net-row">
                            <td class="label">TOTAL</td>
                            <td class="value">{{ fmt_money((float) $sale->total) }} {{ $currencyLabel }}</td>
                        </tr>
                    </table>
                </div>

                @if ($sale->payments->count() > 0)
                    <div class="payments-block">
                        <h3>Paiements</h3>
                        <table class="lines-table">
                            <thead>
                                <tr>
                                    <th>Méthode</th>
                                    <th>Référence</th>
                                    <th style="width:28%">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sale->payments as $p)
                                    <tr>
                                        <td class="left">{{ $p->method_label }}</td>
                                        <td class="left">{{ $p->transaction_reference ?: '—' }}</td>
                                        <td class="num">{{ fmt_money((float) $p->amount) }} {{ $currencyLabel }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (!empty($rxSummary) && !empty($rxSummary['lines']))
                    <div class="payments-block" style="margin-top: 14px;">
                        <h3>Délivrance ordonnance {{ $rxSummary['number'] }}</h3>
                        <table class="lines-table">
                            <thead>
                                <tr>
                                    <th>Médicament</th>
                                    <th style="width:14%">Prescrit</th>
                                    <th style="width:14%">Ce ticket</th>
                                    <th style="width:14%">Total délivré</th>
                                    <th style="width:14%">Reste</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rxSummary['lines'] as $rxLine)
                                    <tr>
                                        <td class="left">{{ $rxLine['item_name'] }}</td>
                                        <td class="qty">{{ fmt_num($rxLine['prescribed']) }}</td>
                                        <td class="qty">{{ fmt_num($rxLine['this_sale']) }}</td>
                                        <td class="qty">{{ fmt_num($rxLine['dispensed']) }}</td>
                                        <td class="qty"><strong>{{ fmt_num($rxLine['remaining']) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($sale->notes)
                    <div class="doc-note"><strong>Notes :</strong> <span class="doc-note__text">{{ $sale->notes }}</span></div>
                @endif
            </div>

            <div class="signature">LA DIRECTION</div>
            <div class="signature-space" aria-hidden="true"></div>
            <div class="print-page-footer">
                @include('partials.print.commercial-doc-footer', [
                    'settings' => $settings,
                    'showStamp' => false,
                ])
            </div>
        </div>
    </div>

    @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
