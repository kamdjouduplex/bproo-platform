<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Feuille de comptage' }}</title>
    <style>
        @page { margin: 14px 16px 22px 16px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9px; margin: 0; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header td { vertical-align: top; border: none; padding: 0; }
        .brand-name { font-size: 13px; font-weight: bold; color: #0f172a; margin: 0 0 2px; }
        .brand-meta { font-size: 8px; color: #64748b; line-height: 1.35; }
        .doc-box { border: 1.5px solid #111; }
        .doc-box table { width: 100%; border-collapse: collapse; }
        .doc-box th, .doc-box td { border: 1px solid #111; padding: 4px 6px; text-align: center; }
        .doc-box th {
            background-color: #0f766e;
            color: #ffffff;
            font-size: 7.5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .doc-title { font-size: 11px; font-weight: bold; }
        .summary { margin: 0 0 8px; font-size: 8.5px; color: #334155; }
        .meta-line { margin: 0 0 8px; font-size: 8px; color: #64748b; }
        .sign { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
        .sign td { border: 1px solid #cbd5e1; padding: 8px 6px; width: 33%; vertical-align: top; height: 42px; }
        .sign .lbl { font-size: 7.5px; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        table.data th, table.data td { padding: 5px 4px; text-align: left; vertical-align: middle; border: 1px solid #cbd5e1; }
        table.data th { background-color: #0f766e; color: #ffffff; font-size: 7.5px; text-transform: uppercase; }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #64748b; font-size: 7.5px; }
        .footer { margin-top: 8px; font-size: 7.5px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
@php
    $settings = $settings ?? [];
    $shopName = $settings['shop_name'] ?? ($shopName ?? 'Bproo Pharma');
    $generatedAt = $generatedAt ?? now();
    $rows = $rows ?? [];
    $showExpected = (bool) ($showExpected ?? true);
    $includeCounted = (bool) ($includeCounted ?? false);
    $hasLocations = (bool) ($hasLocations ?? false);
    $colspan = 5 + ($hasLocations ? 1 : 0) + ($showExpected ? 1 : 0) + 1 + ($includeCounted ? 1 : 0) + 1;
@endphp

<table class="header">
    <tr>
        <td style="width:60%;padding-right:10px;">
            <div class="brand-name">{{ $shopName }}</div>
            <div class="brand-meta">
                Feuille de comptage inventaire
                @if (!empty($settings['shop_address']))<br>{{ $settings['shop_address'] }}@endif
            </div>
        </td>
        <td style="width:40%;">
            <div class="doc-box">
                <table>
                    <tr><th>Date</th><th>Document</th></tr>
                    <tr>
                        <td class="doc-title">{{ $generatedAt->format('d/m/Y') }}</td>
                        <td class="doc-title">Inventaire papier</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="summary">
    <strong>{{ $title }}</strong>
    @if (!empty($subtitle)) · {{ $subtitle }}@endif
    · <strong>{{ count($rows) }}</strong> article(s)
    @if (! $showExpected) · Comptage à l’aveugle (stock système masqué)@endif
</div>

<table class="sign">
    <tr>
        <td><div class="lbl">Zone / rayon</div></td>
        <td><div class="lbl">Compteur</div></td>
        <td><div class="lbl">Contrôle / visa</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width:5%;" class="center">#</th>
            <th style="width:12%;">Réf.</th>
            <th style="width:{{ $hasLocations ? 24 : 30 }}%;">Désignation</th>
            @if ($hasLocations)
                <th style="width:10%;">Empl.</th>
            @endif
            <th style="width:7%;" class="center">Unité</th>
            @if ($showExpected)
                <th style="width:10%;" class="right">Stock sys.</th>
            @endif
            <th style="width:12%;" class="center">Qté comptée</th>
            @if ($includeCounted)
                <th style="width:8%;" class="right">Écart</th>
            @endif
            <th style="width:{{ $includeCounted ? 12 : 18 }}%;">Notes</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="center">{{ $row['index'] }}</td>
                <td>
                    <strong>{{ $row['sku'] !== '' ? $row['sku'] : '—' }}</strong>
                    @if (!empty($row['barcode']))
                        <div class="muted">{{ $row['barcode'] }}</div>
                    @endif
                </td>
                <td>{{ $row['name'] }}</td>
                @if ($hasLocations)
                    <td>{{ $row['location'] !== '' ? $row['location'] : '—' }}</td>
                @endif
                <td class="center">{{ $row['unit'] }}</td>
                @if ($showExpected)
                    <td class="right">{{ $row['expected_quantity'] !== null ? number_format((float) $row['expected_quantity'], 2, ',', ' ') : '—' }}</td>
                @endif
                <td class="center">
                    @if ($row['counted_quantity'] !== null)
                        {{ number_format((float) $row['counted_quantity'], 2, ',', ' ') }}
                    @endif
                </td>
                @if ($includeCounted)
                    <td class="right">
                        @if ($row['difference'] !== null && (float) $row['difference'] != 0)
                            {{ ((float) $row['difference'] > 0 ? '+' : '') . number_format((float) $row['difference'], 2, ',', ' ') }}
                        @else
                            —
                        @endif
                    </td>
                @endif
                <td>{{ $row['notes'] !== '' ? $row['notes'] : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $colspan }}">Aucun article à compter.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="meta-line">
    Remplir la colonne « Qté comptée » à la main, puis saisir les quantités dans l’inventaire numérique.
</div>
<div class="footer">
    {{ $shopName }} · Généré le {{ $generatedAt->format('d/m/Y à H:i') }}
</div>
</body>
</html>
