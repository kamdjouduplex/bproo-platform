<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Pertes' }}</title>
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
                        <td class="doc-title">Pertes</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="summary">
    <strong>{{ count($rows) }}</strong> perte(s)
    @if (!empty($filterLabel)) · {{ $filterLabel }}@endif
    · Valeur confirmée : <strong>{{ number_format((float) ($totalValue ?? 0), 0, ',', ' ') }} {{ currency_label($settings['currency'] ?? null) }}</strong>
</div>

<table class="data">
    <thead>
        <tr>
            <th style="width:12%;">Référence</th>
            <th style="width:9%;">Date</th>
            <th style="width:22%;">Article</th>
            <th style="width:14%;">Raison</th>
            <th class="right" style="width:10%;">Qté</th>
            <th class="right" style="width:12%;">Valeur</th>
            <th style="width:10%;">Statut</th>
            <th style="width:11%;">Créé par</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td><strong>{{ $row['reference'] }}</strong></td>
                <td>{{ $row['loss_date'] }}</td>
                <td>
                    {{ $row['item_name'] }}
                    @if (!empty($row['item_sku']))
                        <div class="muted">{{ $row['item_sku'] }}</div>
                    @endif
                </td>
                <td>{{ $row['reason_name'] }}</td>
                <td class="right">{{ number_format((float) $row['quantity'], 2, ',', ' ') }} {{ $row['unit'] }}</td>
                <td class="right"><strong>{{ number_format((float) $row['value'], 0, ',', ' ') }}</strong></td>
                <td>{{ $row['status_label'] }}</td>
                <td>{{ $row['creator_name'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8">Aucune perte pour ces filtres.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ $shopName }} · Généré le {{ $generatedAt->format('d/m/Y à H:i') }} · Archive administration
</div>
</body>
</html>
