@php
    $employee = $report['employee'];
    $level = $report['performance_level'];
    $percent = $report['performance_percent'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('partials.print.document-title')
    <style>
        @include('inovcom-invoicing::print.partials.document-print-styles')
        body { padding-bottom: 90px; }
        .page { padding-bottom: 0; }
        .sheet-title { text-align: center; margin: 8px 0 10px; font-size: 12px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .meta { text-align: center; font-size: 10px; margin-bottom: 10px; color: #4b5563; }
        .perf-box { border: 2px solid #111; padding: 8px 10px; margin-bottom: 10px; text-align: center; }
        .perf-box .pct { font-size: 22px; font-weight: 800; }
        .perf-box--excellent .pct { color: #166534; }
        .perf-box--good .pct { color: #1d4ed8; }
        .perf-box--warning .pct { color: #b45309; }
        .perf-box--poor .pct { color: #b91c1c; }
        .stats { display: flex; justify-content: center; gap: 16px; margin-top: 6px; font-size: 9px; flex-wrap: wrap; }
        .cal-table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
        .cal-table th, .cal-table td { border: 1px solid #111; padding: 3px 5px; text-align: center; }
        .cal-table th { background: #f0f0f0; }
        .present { color: #166534; font-weight: 700; }
        .partial { color: #b45309; font-weight: 700; }
        .absent { color: #b91c1c; font-weight: 700; }
        .no-print { margin-top: 20px; text-align: center; font-size: 11px; }
    </style>
</head>
<body>
    <div class="page">
        @include('inovcom-invoicing::print.partials.document-header', [
            'settings' => $settings,
            'docDate' => now()->format('d/m/y'),
            'docLabel' => 'FICHE',
            'docNumber' => 'PRÉSENCE',
            'docSubtitle' => $periodLabel,
        ])

        <div class="sheet-title">Fiche de présence</div>
        <div class="meta">
            <strong>{{ $displayName }}</strong>
            @if ($employee?->employee_number) — N° {{ $employee->employee_number }} @endif
        </div>

        <div class="perf-box perf-box--{{ $level }}">
            <div style="font-size:10px; text-transform:uppercase; font-weight:700;">Indicateur de performance</div>
            <div class="pct">{{ fmt_num($percent, 1) }}%</div>
            <div style="font-weight:700;">{{ $report['performance_label'] }}</div>
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
                        <td>{{ $day['label'] }}</td>
                        <td>{{ $day['weekday'] }}</td>
                        <td>{{ $day['arrival'] ?? '—' }}</td>
                        <td>{{ $day['departure'] ?? '—' }}</td>
                        <td class="{{ $day['present'] ? (($day['complete'] ?? false) ? 'present' : 'partial') : 'absent' }}">
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
    </div>

    @include('inovcom-invoicing::print.partials.document-footer', ['settings' => $settings])

    @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
