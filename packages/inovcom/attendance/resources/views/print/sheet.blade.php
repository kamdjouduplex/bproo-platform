<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('inovcom-invoicing::print.partials.document-print-styles')

        html, body {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .sheet-title {
            text-align: center;
            margin: 14px 0 6px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .sheet-employee {
            text-align: center;
            font-size: 13px;
            margin-bottom: 14px;
            color: #111;
        }
        .sheet-employee strong { font-size: 14px; }

        .perf-box {
            border: 2px solid #111;
            padding: 12px 14px;
            margin-bottom: 14px;
            text-align: center;
        }
        .perf-box__label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }
        .perf-box .pct {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.1;
        }
        .perf-box--excellent .pct { color: #166534; }
        .perf-box--good .pct { color: #1d4ed8; }
        .perf-box--warning .pct { color: #b45309; }
        .perf-box--poor .pct { color: #b91c1c; }
        .perf-box__level {
            font-weight: 700;
            font-size: 13px;
            margin-top: 2px;
        }
        .perf-box--excellent .perf-box__level { color: #166534; }
        .perf-box--good .perf-box__level { color: #1d4ed8; }
        .perf-box--warning .perf-box__level { color: #b45309; }
        .perf-box--poor .perf-box__level { color: #b91c1c; }

        .stats {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-top: 10px;
            font-size: 11px;
            flex-wrap: wrap;
        }
        .stats span { white-space: nowrap; }

        .cal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 4px;
        }
        .cal-table th,
        .cal-table td {
            border: 1px solid #111;
            padding: 7px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .cal-table th {
            font-weight: 800;
            background: #111;
            color: #fff;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.03em;
        }
        .cal-table tbody tr:nth-child(even) td {
            background: #f5f5f5;
        }
        .cal-table .col-date { width: 18%; text-align: left; padding-left: 10px; }
        .cal-table .col-day { width: 12%; }
        .cal-table .col-time { width: 16%; }
        .cal-table .col-status { width: 22%; font-weight: 700; }

        .status-ok { color: #166534; }
        .status-partial { color: #b45309; }
        .status-absent { color: #b91c1c; }

        .sheet-signatures {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-top: 28px;
            page-break-inside: avoid;
        }
        .sheet-sign {
            flex: 1;
            border-top: 1px solid #111;
            padding-top: 8px;
            font-size: 11px;
            font-weight: 700;
            min-height: 56px;
        }
        .sheet-sign span {
            display: block;
            font-weight: 400;
            font-size: 10px;
            color: #4b5563;
            margin-top: 4px;
        }

        .sheet-note {
            margin-top: 16px;
            font-size: 10px;
            color: #4b5563;
            line-height: 1.4;
        }

        @media print {
            .cal-table th {
                background: #111 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .cal-table tbody tr:nth-child(even) td {
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
@php
    $employee = $report['employee'];
    $level = $report['performance_level'];
    $percent = $report['performance_percent'];
@endphp
<div class="page">
    <div class="print-page-inner print-page-inner--last">
        <div class="print-page-content">
            @include('inovcom-invoicing::print.partials.document-header', [
                'settings' => $settings,
                'docDate' => now()->format('d/m/y'),
                'docLabel' => 'FICHE',
                'docNumber' => 'PRÉSENCE',
                'docSubtitle' => $periodLabel,
            ])

            <div class="sheet-title">Fiche de présence</div>
            <div class="sheet-employee">
                <strong>{{ $displayName }}</strong>
                @if ($employee?->employee_number)
                    — N° {{ $employee->employee_number }}
                @endif
            </div>

            <div class="perf-box perf-box--{{ $level }}">
                <div class="perf-box__label">Indicateur de performance</div>
                <div class="pct">{{ fmt_num($percent, 1) }}%</div>
                <div class="perf-box__level">{{ $report['performance_label'] }}</div>
                <div class="stats">
                    <span>Jours ouvrés : <strong>{{ $report['expected_days'] }}</strong></span>
                    <span>Présences : <strong>{{ $report['present_days'] }}</strong></span>
                    <span>Jours complets : <strong>{{ $report['complete_days'] ?? 0 }}</strong></span>
                    <span>Absences : <strong>{{ $report['absent_days'] }}</strong></span>
                </div>
            </div>

            <table class="cal-table">
                <thead>
                    <tr>
                        <th class="col-date">Date</th>
                        <th class="col-day">Jour</th>
                        <th class="col-time">Arrivée</th>
                        <th class="col-time">Départ</th>
                        <th class="col-status">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['days'] as $day)
                        <tr>
                            <td class="col-date">{{ $day['label'] }}</td>
                            <td class="col-day">{{ $day['weekday'] }}</td>
                            <td class="col-time">{{ $day['arrival'] ?? '—' }}</td>
                            <td class="col-time">{{ $day['departure'] ?? '—' }}</td>
                            <td class="col-status {{ $day['present'] ? (($day['complete'] ?? false) ? 'status-ok' : 'status-partial') : 'status-absent' }}">
                                @if ($day['present'])
                                    {{ ($day['complete'] ?? false) ? 'Complet' : 'Arrivée seule' }}
                                @else
                                    Absent
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Aucun jour sur la période sélectionnée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="sheet-signatures">
                <div class="sheet-sign">
                    Signature employé
                    <span>{{ $displayName }}</span>
                </div>
                <div class="sheet-sign">
                    Visa responsable
                    <span>Date &amp; signature</span>
                </div>
            </div>

            <p class="sheet-note">
                Document généré automatiquement à partir des pointages enregistrés.
                Jours ouvrés pris en compte : lundi à samedi.
            </p>
        </div>

        <div class="print-page-footer">
            @include('inovcom-invoicing::print.partials.document-footer', ['settings' => $settings])
        </div>
    </div>
</div>

@include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
