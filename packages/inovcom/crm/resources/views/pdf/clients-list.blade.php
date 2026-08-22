<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Clients' }}</title>
    <style>
        @page { margin: 16px 18px 24px 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8.5px; margin: 0; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header td { vertical-align: top; border: none; padding: 0; }
        .brand-name { font-size: 13px; font-weight: bold; color: #0f172a; margin: 0 0 3px; }
        .brand-meta { font-size: 8px; color: #64748b; line-height: 1.4; }
        .doc-box { border: 1.5px solid #111; }
        .doc-box table { width: 100%; border-collapse: collapse; }
        .doc-box th, .doc-box td { border: 1px solid #111; padding: 5px 7px; text-align: center; }
        .doc-box th { background-color: #0f766e; color: #ffffff; font-size: 8px; text-transform: uppercase; font-weight: bold; }
        .doc-title { font-size: 11px; font-weight: bold; }
        .summary { margin: 0 0 8px; font-size: 9px; color: #334155; }
        .summary strong { color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { padding: 3px 4px; text-align: left; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        th { background: #0f766e; color: #fff; font-size: 7px; text-transform: uppercase; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .muted { color: #64748b; font-size: 7.5px; }
        .footer { margin-top: 8px; font-size: 8px; color: #6b7280; text-align: right; }
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
                        <td class="doc-title">Clients</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="summary">
    <strong>{{ count($rows) }}</strong> client(s)
    @if (!empty($filterLabel)) · {{ $filterLabel }}@endif
    · Encours total : <strong>{{ number_format((float) ($totalOutstanding ?? 0), 0, ',', ' ') }} {{ currency_label($settings['currency'] ?? null) }}</strong>
</div>

<table class="data">
    <thead>
        <tr>
            <th style="width:9%;">Code</th>
            <th style="width:18%;">Nom</th>
            <th style="width:8%;">Type</th>
            <th style="width:10%;">Téléphone</th>
            <th style="width:12%;">Email</th>
            <th style="width:9%;">Catégorie</th>
            <th style="width:8%;">Zone</th>
            <th style="width:7%;">Palier</th>
            <th class="right" style="width:9%;">Limite</th>
            <th class="right" style="width:9%;">Encours</th>
            <th style="width:7%;">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td><strong>{{ $row['code'] }}</strong></td>
                <td>
                    {{ $row['name'] }}
                    @if (!empty($row['niu']))
                        <div class="muted">NIU {{ $row['niu'] }}</div>
                    @endif
                </td>
                <td>{{ $row['type'] }}</td>
                <td>{{ $row['phone'] }}</td>
                <td>{{ $row['email'] }}</td>
                <td>{{ $row['category'] }}</td>
                <td>{{ $row['zone'] }}</td>
                <td>{{ $row['price_tier'] }}</td>
                <td class="right">{{ $row['credit_limit'] }}</td>
                <td class="right"><strong>{{ $row['outstanding'] }}</strong></td>
                <td>{{ $row['status'] }}</td>
            </tr>
        @empty
            <tr><td colspan="11">Aucun client pour ces filtres.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ $shopName }} · Généré le {{ $generatedAt->format('d/m/Y à H:i') }} · Liste clients
</div>
</body>
</html>
