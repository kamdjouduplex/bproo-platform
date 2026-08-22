<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Fournisseurs' }}</title>
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
                Fournisseurs
                @if (!empty($settings['shop_address']))<br>{{ $settings['shop_address'] }}@endif
                @if (!empty($settings['shop_phone']))<br>Tél : {{ $settings['shop_phone'] }}@endif
            </div>
        </td>
        <td style="width:38%;">
            <div class="doc-box">
                <table>
                    <tr><th>Date</th><th>Document</th></tr>
                    <tr>
                        <td class="doc-title">{{ $generatedAt->format('d/m/Y') }}</td>
                        <td class="doc-title">Fournisseurs</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="summary">
    <strong>{{ count($rows) }}</strong> fournisseur(s)
    @if (!empty($filterLabel)) · {{ $filterLabel }}@endif
</div>

<table class="data">
    <thead>
        <tr>
            <th style="width:10%;">Code</th>
            <th style="width:20%;">Nom</th>
            <th style="width:12%;">Téléphone</th>
            <th style="width:16%;">Email</th>
            <th style="width:14%;">Ville</th>
            <th style="width:10%;">Pays</th>
            <th style="width:10%;">Type</th>
            <th style="width:8%;">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td><strong>{{ $row['code'] }}</strong></td>
                <td>
                    {{ $row['name'] }}
                    @if (!empty($row['address']))
                        <div class="muted">{{ $row['address'] }}</div>
                    @endif
                </td>
                <td>{{ $row['phone'] }}</td>
                <td>{{ $row['email'] }}</td>
                <td>{{ $row['city'] }}</td>
                <td>{{ $row['country'] }}</td>
                <td>{{ $row['type'] }}</td>
                <td>{{ $row['status'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8">Aucun fournisseur pour ces filtres.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ $shopName }} · Généré le {{ $generatedAt->format('d/m/Y à H:i') }} · Liste fournisseurs
</div>
</body>
</html>
