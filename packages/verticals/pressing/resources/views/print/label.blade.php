<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Étiquette — {{ $order->number }}</title>
    <style>
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? '80mm auto'])
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            width: 70mm;
            margin: 0 auto;
            padding: 4mm 2mm;
            text-align: center;
            color: #000;
            background: #fff;
        }
        .num { font-size: 15px; font-weight: 700; letter-spacing: 0.02em; }
        .meta { margin-top: 2px; line-height: 1.35; }
        .qr { margin: 6px 0 2px; }
        .qr img { display: block; margin: 0 auto; width: 32mm; height: 32mm; }
        @media print {
            body { padding: 2mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@include('partials.print.auto-print')
<div class="num">{{ $order->number }}</div>
<div class="meta">{{ $order->client?->full_name }}</div>
<div class="meta">{{ $order->items->sum('quantity') }} article(s)</div>
<div class="qr"><img src="{{ $qrImageUrl }}" width="120" height="120" alt="QR"></div>
</body>
</html>
