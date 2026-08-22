<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Fiche de présence' }}</title>
    <style>
        @page { margin: 16px 18px 24px 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9px; margin: 0; }
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
        th, td { padding: 4px 5px; text-align: left; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        th { background: #0f766e; color: #fff; font-size: 7.5px; text-transform: uppercase; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .center { text-align: center; }
        .present { color: #166534; font-weight: bold; }
        .partial { color: #b45309; font-weight: bold; }
        .absent { color: #b91c1c; font-weight: bold; }
        .footer { margin-top: 8px; font-size: 8px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
@php
    $settings = $settings ?? [];
    $shopName = $settings['shop_name'] ?? ($shopName ?? 'Bproo Pharma');
    $generatedAt = $generatedAt ?? now();
    $rows = $rows ?? [];
    $employeeMeta = $employeeMeta ?? '';
@endphp

<table class="header">
    <tr>
        <td style="width:62%;padding-right:12px;">
            <div class="brand-name">{{ $shopName }}</div>
            <div class="brand-meta">
                Fiche de présence
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
                        <td class="doc-title">Présence</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="summary">
    <strong>{{ $displayName ?? '—' }}</strong>
    @if ($employeeMeta !== '') · {{ $employeeMeta }}@endif
    @if (!empty($monthLabel)) · {{ $monthLabel }}@endif
    @if (!empty($periodLabel)) · {{ $periodLabel }}@endif
</div>

<div class="summary">
    Performance : <strong>{{ number_format((float) ($performancePercent ?? 0), 1, ',', ' ') }}%</strong>
    @if (!empty($performanceLabel)) ({{ $performanceLabel }})@endif
    · Ouvrés : <strong>{{ (int) ($expectedDays ?? 0) }}</strong>
    · Présences : <strong>{{ (int) ($presentDays ?? 0) }}</strong>
    · Complets : <strong>{{ (int) ($completeDays ?? 0) }}</strong>
    · Absences : <strong>{{ (int) ($absentDays ?? 0) }}</strong>
</div>

<table class="data">
    <thead>
        <tr>
            <th style="width:18%;">Date</th>
            <th style="width:14%;">Jour</th>
            <th class="center" style="width:18%;">Arrivée</th>
            <th class="center" style="width:18%;">Départ</th>
            <th class="center" style="width:32%;">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['weekday'] }}</td>
                <td class="center">{{ $row['arrival'] }}</td>
                <td class="center">{{ $row['departure'] }}</td>
                <td class="center {{ $row['status_class'] }}">{{ $row['status'] }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Aucun jour à afficher pour cette période.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ $shopName }} · Généré le {{ $generatedAt->format('d/m/Y à H:i') }} · Fiche de présence
</div>
</body>
</html>
