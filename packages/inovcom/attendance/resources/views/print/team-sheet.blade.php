<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('partials.print.document-title')
    <style>
        @include('inovcom-invoicing::print.partials.document-print-styles')

        .page.attendance-print-page {
            padding: 0 !important;
            max-width: 210mm;
            page-break-after: always;
        }
        .page.attendance-print-page:last-of-type { page-break-after: auto; }
        .attendance-sheet {
            padding: 10mm 12mm 8mm !important;
            box-sizing: border-box;
        }
        .attendance-sheet .sheet-title {
            text-align: center;
            margin: 16px 0 8px !important;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .attendance-sheet .meta {
            text-align: center;
            font-size: 11px;
            margin: 0 0 16px !important;
            color: #374151;
            line-height: 1.5;
        }
        .attendance-sheet .perf-box {
            border: 2px solid #111;
            padding: 16px 18px !important;
            margin: 0 0 18px !important;
            text-align: center;
            box-sizing: border-box;
        }
        .attendance-sheet .perf-box__label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.04em;
            margin-bottom: 6px !important;
        }
        .attendance-sheet .perf-box .pct {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.25;
            margin: 6px 0 !important;
        }
        .attendance-sheet .perf-box__status {
            font-weight: 700;
            font-size: 11px;
            margin: 0 0 10px !important;
        }
        .attendance-sheet .perf-box--excellent .pct { color: #166534; }
        .attendance-sheet .perf-box--good .pct { color: #1d4ed8; }
        .attendance-sheet .perf-box--warning .pct { color: #b45309; }
        .attendance-sheet .perf-box--poor .pct { color: #b91c1c; }
        .attendance-sheet .stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 10px !important;
            font-size: 10px;
            flex-wrap: wrap;
            line-height: 1.5;
        }
        .attendance-sheet .cal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            table-layout: fixed;
        }
        .attendance-sheet .cal-table th,
        .attendance-sheet .cal-table td {
            border: 1px solid #111 !important;
            padding: 10px 12px !important;
            text-align: center !important;
            vertical-align: middle !important;
            line-height: 1.4 !important;
        }
        .attendance-sheet .cal-table th {
            background: #f0f0f0 !important;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 11px 12px !important;
        }
        .attendance-sheet .present { color: #166534; font-weight: 700; }
        .attendance-sheet .partial { color: #b45309; font-weight: 700; }
        .attendance-sheet .absent { color: #b91c1c; font-weight: 700; }

        @media print {
            .attendance-sheet { padding: 10mm 12mm 8mm !important; }
            .attendance-sheet .cal-table th,
            .attendance-sheet .cal-table td { padding: 10px 12px !important; }
            .attendance-sheet .perf-box { padding: 16px 18px !important; }
        }
    </style>
</head>
<body>
    @foreach ($teamReport['employees'] as $row)
        @php
            $report = $row['report'];
            $employee = $row['employee'];
            $level = $report['performance_level'];
            $percent = $report['performance_percent'];
        @endphp
        <div class="page attendance-print-page">
            <div class="print-page-inner attendance-sheet">
                @include('inovcom-invoicing::print.partials.document-header', [
                    'settings' => $settings,
                    'docDate' => now()->format('d/m/y'),
                    'docLabel' => 'FICHE',
                    'docNumber' => 'PRÉSENCE',
                    'docSubtitle' => $periodLabel,
                ])

                <div class="sheet-title">Fiche de présence</div>
                <div class="meta">
                    <strong>{{ $row['display_name'] }}</strong>
                    @if ($employee->employee_number ?? null) — N° {{ $employee->employee_number }} @endif
                </div>

                <div class="perf-box perf-box--{{ $level }}">
                    <div class="perf-box__label">Indicateur de performance</div>
                    <div class="pct">{{ fmt_num($percent, 1) }}%</div>
                    <div class="perf-box__status">{{ $report['performance_label'] }}</div>
                    <div class="stats">
                        <span>Jours ouvrés : <strong>{{ $report['expected_days'] }}</strong></span>
                        <span>Présences : <strong>{{ $report['present_days'] }}</strong></span>
                        <span>Jours complets : <strong>{{ $report['complete_days'] ?? 0 }}</strong></span>
                        <span>Absences : <strong>{{ $report['absent_days'] }}</strong></span>
                    </div>
                </div>

                <table class="cal-table">
                    <thead>
                        <tr><th>Date</th><th>Jour</th><th>Arrivée</th><th>Départ</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report['days'] as $day)
                            <tr>
                                <td style="padding:10px 12px !important;">{{ $day['label'] }}</td>
                                <td style="padding:10px 12px !important;">{{ $day['weekday'] }}</td>
                                <td style="padding:10px 12px !important;">{{ $day['arrival'] ?? '—' }}</td>
                                <td style="padding:10px 12px !important;">{{ $day['departure'] ?? '—' }}</td>
                                <td class="{{ $day['present'] ? (($day['complete'] ?? false) ? 'present' : 'partial') : 'absent' }}" style="padding:10px 12px !important;">
                                    @if ($day['present'])
                                        {{ ($day['complete'] ?? false) ? 'Complet' : 'Arrivée seule' }}
                                    @else
                                        Absent
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @include('inovcom-invoicing::print.partials.document-footer', ['settings' => $settings])
            </div>
        </div>
    @endforeach

    @include('partials.print.auto-print', [
        'returnUrl' => $returnUrl ?? null,
        'closeAfterPrint' => true,
    ])
</body>
</html>
