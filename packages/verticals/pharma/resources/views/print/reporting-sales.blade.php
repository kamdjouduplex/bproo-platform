<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('partials.print.document-title')
    <style>
        @include('inovcom-invoicing::print.partials.document-print-styles')
        .pa-print table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .pa-print th, .pa-print td { border: 1px solid #111; padding: 6px 8px; }
        .pa-print th { background: #f1f5f9; text-transform: uppercase; font-size: 9px; }
        .pa-print .is-num { text-align: right; }
        .sheet-title { text-align: center; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin: 12px 0; }
    </style>
</head>
<body>
<div class="page">
    <div class="print-page-inner">
        @include('inovcom-invoicing::print.partials.document-header', [
            'settings' => $settings,
            'docDate' => now()->format('d/m/y'),
            'docLabel' => 'RAPPORT',
            'docNumber' => $docNumber ?? 'VENTES',
            'docSubtitle' => $title,
        ])
        <div class="sheet-title">{{ $title }}</div>
        <div class="pa-print">
            <table>
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php
                        $numStart = $numStart ?? 2;
                        $numEnd = $numEnd ?? 5;
                    @endphp
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($row as $i => $cell)
                                <td class="{{ $i >= $numStart && $i <= $numEnd ? 'is-num' : '' }}">{{ is_numeric($cell) ? fmt_num($cell, 2) : $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('inovcom-invoicing::print.partials.document-footer', ['settings' => $settings])
    </div>
</div>
@include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null, 'closeAfterPrint' => true])
</body>
</html>
