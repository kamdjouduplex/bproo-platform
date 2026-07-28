<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture — {{ $order->number }}</title>
    <style>
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? 'A4'])
        body { font-family: Arial, sans-serif; font-size: 13px; }
        .wrap { max-width: 800px; margin: 0 auto; padding: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8fafc; }
        .totals { margin-top: 16px; width: 280px; margin-left: auto; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
@include('partials.print.auto-print')
<div class="wrap">
    @include('partials.print.document-header', [
        'settings' => $settings,
        'docDate' => $order->received_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
        'docLabel' => 'FACTURE N°',
        'docNumber' => $order->number,
        'docSubtitle' => 'Facture pressing',
    ])
    <h1>Facture pressing</h1>
    <p>{{ $order->number }} · {{ $order->received_at?->format('d/m/Y') }}</p>
    <p><strong>Client :</strong> {{ $order->client?->full_name }}<br>
       WhatsApp : {{ $order->client?->whatsapp }}<br>
       Adresse : {{ $order->client?->address }}</p>

    <table>
        <thead>
            <tr><th>#</th><th>Désignation</th><th>Qté</th><th>P.U.</th><th>Total</th></tr>
        </thead>
        <tbody>
            @foreach ($order->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->articleType?->name }} {{ collect([$item->color,$item->brand,$item->size])->filter()->implode(' / ') }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format((float) $item->unit_price, 0, ',', ' ') }}</td>
                    <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Sous-total</td><td style="text-align:right;">{{ number_format((float) $order->subtotal, 0, ',', ' ') }}</td></tr>
        <tr><td>Remise</td><td style="text-align:right;">{{ number_format((float) $order->discount_amount, 0, ',', ' ') }}</td></tr>
        <tr><td>TVA</td><td style="text-align:right;">{{ number_format((float) $order->tax_amount, 0, ',', ' ') }}</td></tr>
        <tr><td><strong>Total</strong></td><td style="text-align:right;"><strong>{{ number_format((float) $order->total, 0, ',', ' ') }} {{ $currency }}</strong></td></tr>
        <tr><td>Payé</td><td style="text-align:right;">{{ number_format((float) $order->amount_paid, 0, ',', ' ') }}</td></tr>
        <tr><td>Reste</td><td style="text-align:right;">{{ number_format((float) $order->balance, 0, ',', ' ') }}</td></tr>
    </table>
</div>
</body>
</html>
