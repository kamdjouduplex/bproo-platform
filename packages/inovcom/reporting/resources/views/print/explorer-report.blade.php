<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $printTitle ?? ($title ?? 'Rapport') }}</title>
    @if (!($forPdf ?? false))
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? 'A4'])
    @endif
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, "Segoe UI", Arial, sans-serif;
            font-size: 10px;
            color: #111827;
            margin: 0;
            padding: {{ ($forPdf ?? false) ? '12mm' : '0' }};
            line-height: 1.35;
        }
        .report-page {
            padding: {{ ($forPdf ?? false) ? '0' : '10mm' }};
            max-width: 100%;
            margin: 0 auto;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .brand { font-size: 16px; font-weight: 700; }
        .brand-sub { color: #4b5563; margin-top: 2px; font-size: 10px; }
        .meta-block { text-align: right; color: #374151; font-size: 10px; }
        .meta-block strong { color: #111827; }
        .totals {
            margin: 0 0 12px;
            font-size: 10px;
            color: #374151;
        }
        .totals span { margin-right: 14px; }
        h1 {
            font-size: 13px;
            margin: 0 0 4px;
            color: #111827;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 6px; vertical-align: top; text-align: left; }
        thead th {
            background: #111827;
            color: #fff;
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: none;
        }
        tbody td {
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        .right { text-align: right; }
        .empty { padding: 16px 0; color: #6b7280; }
        .no-print { margin-top: 16px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
@if (!($forPdf ?? false) && ($autoPrint ?? false))
    @include('partials.print.auto-print')
@endif

<div class="report-page">
    <div class="doc-header">
        <div>
            <div class="brand">{{ $shopName ?? 'Bproo Pharma' }}</div>
            <div class="brand-sub">Rapport — {{ $title ?? 'Explorateur' }}</div>
        </div>
        <div class="meta-block">
            @if (!empty($periodLabel))
                <div><strong>Période :</strong> {{ $periodLabel }}</div>
            @endif
            <div><strong>Généré le :</strong> {{ ($generatedAt ?? now())->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <h1>{{ $title ?? 'Rapport' }}</h1>

    @if (!empty($meta))
        <div class="totals">
            @if (!empty($meta['count']))
                <span><strong>{{ $meta['count'] }}</strong> ligne(s)</span>
            @endif
            @if (isset($meta['total']))
                <span>Total : <strong>{{ is_numeric($meta['total']) ? number_format((float) $meta['total'], 0, ',', ' ') : $meta['total'] }}</strong></span>
            @endif
            @if (isset($meta['total_ttc']))
                <span>TTC : <strong>{{ is_numeric($meta['total_ttc']) ? number_format((float) $meta['total_ttc'], 0, ',', ' ') : $meta['total_ttc'] }}</strong></span>
            @endif
            @if (isset($meta['tva_total']))
                <span>TVA : <strong>{{ is_numeric($meta['tva_total']) ? number_format((float) $meta['tva_total'], 0, ',', ' ') : $meta['tva_total'] }}</strong></span>
            @endif
        </div>
    @endif

    @if (count($rows ?? []) === 0)
        <p class="empty">Aucune donnée pour ce rapport.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="{{ is_numeric($cell) ? 'right' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!($forPdf ?? false) && !empty($returnUrl))
        <div class="no-print">
            <a href="{{ $returnUrl }}">← Retour aux rapports</a>
        </div>
    @endif
</div>
</body>
</html>
