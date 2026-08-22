<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Ventes' }}</title>
    <style>
        @page { margin: 18px 20px 26px 20px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9px; margin: 0; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .header td { vertical-align: top; border: none; padding: 0; }
        .brand-name { font-size: 14px; font-weight: bold; color: #0f172a; margin: 0 0 3px; }
        .brand-meta { font-size: 8px; color: #64748b; line-height: 1.4; }
        .doc-box { border: 1.5px solid #111; }
        .doc-box table { width: 100%; border-collapse: collapse; }
        .doc-box th, .doc-box td { border: 1px solid #111; padding: 5px 7px; text-align: center; }
        .doc-box th { background-color: #0f766e; color: #ffffff; font-size: 8px; text-transform: uppercase; font-weight: bold; }
        .doc-title { font-size: 12px; font-weight: bold; }
        .summary { margin: 0 0 10px; font-size: 9px; color: #334155; }
        .summary strong { color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { padding: 4px 5px; text-align: left; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        th { background: #0f766e; color: #fff; font-size: 7.5px; text-transform: uppercase; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .muted { color: #64748b; font-size: 7.5px; }
        .footer { margin-top: 10px; font-size: 8px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
@php
    $settings = $settings ?? [];
    $shopName = $settings['shop_name'] ?? ($shopName ?? 'Bproo Pharma');
    $generatedAt = $generatedAt ?? now();
    $rows = $rows ?? [];
@endphp

<table class="header">
    <tr>
        <td style="width:62%;padding-right:12px;">
            <div class="brand-name">{{ $shopName }}</div>
            <div class="brand-meta">
                @if (!empty($settings['shop_address'])){{ $settings['shop_address'] }}<br>@endif
                @if (!empty($settings['shop_phone']))Tél : {{ $settings['shop_phone'] }}@endif
            </div>
        </td>
        <td style="width:38%;">
            <div class="doc-box">
                <table>
                    <tr><th>Date</th><th>Document</th></tr>
                    <tr>
                        <td class="doc-title">{{ $generatedAt->format('d/m/Y') }}</td>
                        <td class="doc-title">Liste des ventes</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="summary">
    <strong>{{ count($rows) }}</strong> vente(s)
    @if (!empty($filterLabel)) · {{ $filterLabel }}@endif
    · Total exporté : <strong>{{ number_format((float) ($totalAmount ?? 0), 0, ',', ' ') }}</strong>
</div>

<table class="data">
    <thead>
        <tr>
            <th style="width:11%;">N° vente</th>
            <th style="width:8%;">Date</th>
            <th style="width:16%;">Client</th>
            <th style="width:8%;">Type</th>
            <th class="right" style="width:9%;">Sous-total</th>
            <th class="right" style="width:8%;">Remise</th>
            <th class="right" style="width:9%;">Total</th>
            <th style="width:7%;">Devise</th>
            <th style="width:10%;">Paiement</th>
            <th style="width:14%;">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td><strong>{{ $row['sale_number'] }}</strong></td>
                <td>{{ $row['sale_date'] }}</td>
                <td>
                    {{ $row['client_name'] }}
                    @if (!empty($row['seller_name']) && $row['seller_name'] !== '—')
                        <div class="muted">{{ $row['seller_name'] }}</div>
                    @endif
                </td>
                <td>{{ $row['price_tier_label'] }}</td>
                <td class="right">{{ number_format((float) $row['subtotal'], 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $row['discount_amount'], 0, ',', ' ') }}</td>
                <td class="right"><strong>{{ number_format((float) $row['total'], 0, ',', ' ') }}</strong></td>
                <td>{{ $row['currency_label'] }}</td>
                <td>{{ $row['payment_label'] }}</td>
                <td>{{ $row['status_label'] }}</td>
            </tr>
        @empty
            <tr><td colspan="10">Aucune vente pour ces filtres.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ $shopName }} · Généré le {{ $generatedAt->format('d/m/Y à H:i') }} · Archive administration
</div>
</body>
</html>
