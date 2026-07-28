<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu dépôt — {{ $order->number }}</title>
    <style>
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? 'A4'])
        body { font-family: Arial, sans-serif; font-size: 13px; color: #111; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 16px; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #ddd; padding: 6px 4px; text-align: left; }
        .meta { color: #555; margin: 2px 0; }
        .qr { text-align: center; margin-top: 16px; }
        .totals { margin-top: 12px; text-align: right; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
@include('partials.print.auto-print')
<div class="wrap">
    @include('partials.print.document-header', [
        'settings' => $settings,
        'docDate' => $order->received_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
        'docLabel' => 'REÇU N°',
        'docNumber' => $order->number,
        'docSubtitle' => 'Reçu de dépôt pressing',
    ])
    <h1>Reçu de dépôt</h1>
    <p class="meta"><strong>{{ $order->number }}</strong> · {{ $order->received_at?->format('d/m/Y H:i') }}</p>
    <p class="meta">Client : {{ $order->client?->full_name }} · WhatsApp {{ $order->client?->whatsapp }}</p>
    <p class="meta">Agence : {{ $order->agence?->name }}</p>
    @if ($order->billing_mode === 'weight_global')
        <p class="meta">Facturation : {{ number_format((float) $order->total_weight_kg, 3, ',', ' ') }} kg × {{ number_format((float) $order->weight_unit_price, 0, ',', ' ') }} FCFA/kg (tout cou)</p>
    @elseif ($order->billing_mode === 'weight_by_type')
        <p class="meta">Facturation : au kilo par type · {{ number_format((float) $order->total_weight_kg, 3, ',', ' ') }} kg total</p>
    @elseif ($order->billing_mode === 'mixed')
        <p class="meta">Facturation mixte (prix fixe + kilo)@if((float) $order->total_weight_kg > 0) · {{ number_format((float) $order->total_weight_kg, 3, ',', ' ') }} kg (lignes kilo)@endif</p>
    @endif
    @if ($order->due_at)
        <p class="meta">Prévu pour : {{ $order->due_at->format('d/m/Y H:i') }}</p>
    @endif

    @if ($order->relationLoaded('constitutionLines') ? $order->constitutionLines->isNotEmpty() : $order->constitutionLines()->exists())
        <p class="meta" style="margin-top:10px;"><strong>Contenu du lot :</strong></p>
        <ul style="margin:4px 0 12px;padding-left:18px;">
            @foreach ($order->constitutionLines as $line)
                <li>{{ $line->label() }}</li>
            @endforeach
        </ul>
    @endif

    <table>
        <thead>
            <tr><th>Article</th><th>Qté / Poids</th><th>Détails</th><th>Montant</th></tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                @php
                    $itemPerKg = in_array($item->pricing_mode, ['per_kg', 'weight_by_type'], true)
                        || ($order->billing_mode === 'weight_by_type' && $item->weight_kg);
                @endphp
                <tr>
                    <td>{{ $item->articleType?->name }}</td>
                    <td>
                        @if ($itemPerKg && $item->weight_kg)
                            {{ number_format((float) $item->weight_kg, 3, ',', ' ') }} kg
                        @else
                            {{ $item->quantity }}
                        @endif
                    </td>
                    <td>{{ collect([$item->color, $item->brand, $item->size])->filter()->implode(' · ') }}</td>
                    <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div>Sous-total : {{ number_format((float) $order->subtotal, 0, ',', ' ') }} {{ $currency }}</div>
        @if ($order->discount_amount > 0)
            <div>Remise : −{{ number_format((float) $order->discount_amount, 0, ',', ' ') }}</div>
        @endif
        @if ($order->tax_amount > 0)
            <div>TVA : {{ number_format((float) $order->tax_amount, 0, ',', ' ') }}</div>
        @endif
        <div><strong>Total : {{ number_format((float) $order->total, 0, ',', ' ') }} {{ $currency }}</strong></div>
        <div>Payé : {{ number_format((float) $order->amount_paid, 0, ',', ' ') }} · Reste : {{ number_format((float) $order->balance, 0, ',', ' ') }}</div>
    </div>

    <div class="qr">
        <img src="{{ $qrImageUrl }}" alt="QR Code" width="140" height="140">
        <div style="font-size:11px;margin-top:6px;">Scannez pour suivre la commande</div>
    </div>
</div>
</body>
</html>
