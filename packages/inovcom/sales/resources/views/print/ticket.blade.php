<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? '80mm auto'])
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; font-size: 12px; line-height: 1.35; color: #000; background: #fff; max-width: 80mm; margin: 0 auto; }
        .ticket-inner { padding: 12px 10px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: 700; }
        .mt-1 { margin-top: 6px; }
        .mt-2 { margin-top: 12px; }
        .mb-1 { margin-bottom: 6px; }
        .pb-1 { padding-bottom: 6px; border-bottom: 1px dashed #333; }
        .line { border-top: 1px dashed #333; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { padding: 2px 0; }
        th { text-align: left; }
        .qty { width: 24px; text-align: right; }
        .price, .total { text-align: right; }
        @include('partials.item-label-css')
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="ticket-inner">
    @if(!empty($settings['logo_url']))
        <div class="text-center mb-1"><img src="{{ $settings['logo_url'] }}" alt="{{ $settings['shop_name'] }}" style="max-height:48px; max-width:100%; object-fit:contain;"></div>
    @else
        <div class="text-center bold" style="font-size: 14px;">{{ $settings['shop_name'] }}</div>
    @endif
    @if(($settings['print_show_header_company_info'] ?? true))
        @if(!empty($settings['shop_phone']))<div class="text-center">{{ $settings['shop_phone'] }}</div>@endif
        @if(!empty($settings['shop_address']))<div class="text-center">{{ $settings['shop_address'] }}</div>@endif
        @if(!empty($settings['shop_email']))<div class="text-center">{{ $settings['shop_email'] }}</div>@endif
        @if(!empty($settings['shop_tax_id']))<div class="text-center">N° fiscal: {{ $settings['shop_tax_id'] }}</div>@endif
    @endif

    <div class="line mt-1"></div>
    <div class="text-center mt-1">Reçu / Ticket</div>
    <div class="mt-1"><strong>{{ $sale->sale_number }}</strong> — {{ $sale->sale_date->format('d/m/Y H:i') }}</div>
    <div class="mt-1">Client: {{ $sale->client?->name ?? 'Client occasionnel' }}</div>
    @if (!empty($rxSummary))
        <div class="mt-1">Ordonnance: <strong>{{ $rxSummary['number'] }}</strong> ({{ $rxSummary['status_label'] }})</div>
    @endif
    <div class="line mt-1"></div>

    <table class="mt-1">
        <thead>
            <tr>
                <th>Article</th>
                <th class="qty">Qté</th>
                <th class="price">Prix</th>
                <th class="total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->lines as $line)
            <tr>
                <td><x-item-label :reference="$line->item_sku" :name="$line->item_name" /></td>
                <td class="qty">{{ fmt_num($line->quantity) }}</td>
                <td class="price">{{ fmt_money($line->unit_price) }}</td>
                <td class="total">{{ fmt_money($line->line_total) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="line mt-1"></div>
    <div class="text-right">Sous-total: {{ fmt_money($sale->subtotal) }} {{ $currency === 'XOF' ? 'FCFA' : $currency }}</div>
    @if($sale->discount_amount > 0)<div class="text-right">Remise: -{{ fmt_money($sale->discount_amount) }} {{ $currency === 'XOF' ? 'FCFA' : $currency }}</div>@endif
    <div class="text-right bold mt-1">Total: {{ fmt_money($sale->total) }} {{ $currency === 'XOF' ? 'FCFA' : $currency }}</div>

    @if($sale->payments->count() > 0)
    <div class="mt-1 pb-1">Paiement(s):</div>
    @foreach($sale->payments as $p)
    <div>{{ $p->method_label }}: {{ fmt_money($p->amount) }} {{ $currency === 'XOF' ? 'FCFA' : $currency }}{{ $p->transaction_reference ? ' — ' . $p->transaction_reference : '' }}</div>
    @endforeach
    @endif

    @if (!empty($rxSummary) && !empty($rxSummary['lines']))
    <div class="line mt-1"></div>
    <div class="bold mb-1">Délivrance ordonnance</div>
    @foreach ($rxSummary['lines'] as $rxLine)
    <div style="font-size: 10px; margin-bottom: 4px;">
        {{ $rxLine['item_name'] }}<br>
        Prescrit {{ fmt_num($rxLine['prescribed']) }}
        · Ce ticket {{ fmt_num($rxLine['this_sale']) }}
        · Total délivré {{ fmt_num($rxLine['dispensed']) }}
        · Reste {{ fmt_num($rxLine['remaining']) }}
    </div>
    @endforeach
    @endif

    @if(!empty($settings['invoice_footer']))
    <div class="line mt-2"></div>
    <div class="text-center" style="font-size: 10px;">{{ $settings['invoice_footer'] }}</div>
    @endif

    <div class="text-center mt-2" style="font-size: 10px;">Merci pour votre achat</div>
    </div>

    @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
