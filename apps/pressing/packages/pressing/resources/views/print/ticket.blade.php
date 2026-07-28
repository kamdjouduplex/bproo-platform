<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ticket — {{ $order->number }}</title>
    <style>
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? '80mm auto'])
        body { font-family: ui-monospace, monospace; font-size: 12px; width: 72mm; margin: 0 auto; }
        .c { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; }
        td { padding: 2px 0; vertical-align: top; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
@include('partials.print.auto-print')
<div class="c"><strong>{{ $settings['shop_name'] ?? 'Pressing' }}</strong></div>
<div class="c">{{ $order->agence?->name }}</div>
<div class="line"></div>
<div><strong>{{ $order->number }}</strong></div>
<div>{{ $order->received_at?->format('d/m/Y H:i') }}</div>
<div>{{ $order->client?->full_name }}</div>
<div class="line"></div>
<table>
@foreach ($order->items as $item)
    <tr>
        <td>{{ $item->quantity }}x {{ $item->articleType?->name }}</td>
        <td style="text-align:right;">{{ number_format((float) $item->line_total, 0, ',', ' ') }}</td>
    </tr>
@endforeach
</table>
<div class="line"></div>
<div>Total: <strong>{{ number_format((float) $order->total, 0, ',', ' ') }}</strong></div>
<div>Payé: {{ number_format((float) $order->amount_paid, 0, ',', ' ') }}</div>
<div>Reste: {{ number_format((float) $order->balance, 0, ',', ' ') }}</div>
<div class="c" style="margin-top:10px;">
    <img src="{{ $qrImageUrl }}" width="110" height="110" alt="QR">
</div>
</body>
</html>
