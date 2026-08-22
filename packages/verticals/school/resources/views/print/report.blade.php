<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup')
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 10px; color: #111; background: #fff; padding: 10mm; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 14px; }
        .toolbar a, .toolbar button { font-size: 13px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f2744; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .meta { color: #64748b; margin-bottom: 10px; }
        .kpis { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .kpi { border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; min-width: 110px; }
        .kpi span { display: block; font-size: 8px; text-transform: uppercase; color: #64748b; }
        .kpi strong { font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; }
        th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }
        tfoot td { font-weight: 700; background: #f8fafc; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $returnUrl }}">Retour</a>
        <button type="button" class="primary" onclick="window.print()">Imprimer / PDF</button>
    </div>
    <h1>{{ $shopName }} — {{ $report['title'] }}</h1>
    <div class="meta">{{ $report['summary'] }} · {{ now()->format('d/m/Y H:i') }}</div>
    @if(!empty($report['kpis']))
        <div class="kpis">
            @foreach($report['kpis'] as $kpi)
                <div class="kpi"><span>{{ $kpi['label'] }}</span><strong>{{ $kpi['value'] }}</strong></div>
            @endforeach
        </div>
    @endif
    <table>
        <thead>
            <tr>
                @foreach($report['headers'] as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ max(1, count($report['headers'])) }}">Aucune ligne.</td></tr>
            @endforelse
        </tbody>
        @if(!empty($report['totals']) && count($report['rows']))
            <tfoot>
                <tr>
                    @foreach($report['totals'] as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
