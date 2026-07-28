@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    <section class="card" style="margin-bottom:16px;">
        <div class="table-toolbar">
            <div class="table-title">Fiche de présence</div>
            <form style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                @if ($canViewAll)
                    <select class="input input-sm" wire:model.live="employeeFilter">
                        <option value="">Choisir un employé</option>
                        <option value="all">Tous les employés (vue équipe)</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} — {{ $emp->employee_number }}</option>
                        @endforeach
                    </select>
                @else
                    <span class="input input-sm" style="display:inline-flex;align-items:center;background:#f8fafc;color:#0f172a;font-weight:600;">
                        {{ auth('tenant')->user()?->name ?? __('Moi') }}
                    </span>
                @endif
                <input class="input input-sm" type="date" wire:model.live="dateFrom">
                <input class="input input-sm" type="date" wire:model.live="dateTo">
                @if (($report || $teamReport) && ! str_starts_with((string) ($employeeFilter ?? ''), 'user:'))
                    <a class="btn btn-primary btn-sm" href="{{ $this->printUrl() }}">
                        {{ ($employeeFilter ?? '') === 'all' ? 'Imprimer toutes les fiches' : 'Imprimer la fiche' }}
                    </a>
                @endif
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.attendance.index', ['tenant' => $tenantCode]) }}">Retour</a>
            </form>
        </div>
    </section>

    @if ($teamReport)
        <section class="card" style="margin-bottom:16px;">
            <div class="card-title">Vue équipe — {{ count($teamReport['employees']) }} employé(s)</div>
            <p style="font-size:12px; color:#6b7280; margin-bottom:12px;">
                Période : {{ $teamReport['from']->format('d/m/Y') }} — {{ $teamReport['to']->format('d/m/Y') }}
            </p>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>N°</th>
                            <th>Jours ouvrés</th>
                            <th>Présences</th>
                            <th>Jours complets</th>
                            <th>Absences</th>
                            <th>Taux</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamReport['employees'] as $row)
                            @php $r = $row['report']; @endphp
                            <tr>
                                <td><strong>{{ $row['display_name'] }}</strong></td>
                                <td>{{ $row['employee']->employee_number ?? '—' }}</td>
                                <td>{{ $r['expected_days'] }}</td>
                                <td style="color:#16a34a; font-weight:600;">{{ $r['present_days'] }}</td>
                                <td>{{ $r['complete_days'] ?? 0 }}</td>
                                <td style="color:#b91c1c;">{{ $r['absent_days'] }}</td>
                                <td>{{ fmt_num($r['performance_percent'], 1) }}%</td>
                                <td>
                                    <a class="btn btn-secondary btn-sm"
                                       href="{{ route('tenant.attendance.sheet', ['tenant' => $tenantCode, 'employee_id' => $row['employee']->id]) }}">
                                        Détail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @foreach ($teamReport['employees'] as $row)
            @php $r = $row['report']; @endphp
            <section class="card app-table-card" style="margin-bottom:16px;">
                <div class="table-title" style="padding:12px 16px 0;">
                    {{ $row['display_name'] }}
                    @if ($row['employee']->employee_number)
                        <span style="font-weight:400; color:#6b7280;">— {{ $row['employee']->employee_number }}</span>
                    @endif
                </div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Jour</th><th>Arrivée</th><th>Départ</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($r['days'] as $day)
                                <tr>
                                    <td>{{ $day['label'] }}</td>
                                    <td>{{ $day['weekday'] }}</td>
                                    <td>{{ $day['arrival'] ?? '—' }}</td>
                                    <td>{{ $day['departure'] ?? '—' }}</td>
                                    <td>
                                        @if ($day['present'])
                                            @if ($day['complete'] ?? false)
                                                <span class="badge badge-success">Complet</span>
                                            @else
                                                <span class="badge badge-warning">Arrivée seule</span>
                                            @endif
                                        @else
                                            <span class="badge badge-error">Absent</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @elseif ($report)
        @php
            $displayName = app(\InovCom\Attendance\Services\AttendanceService::class)->displayName($report['employee'], $report['user']);
        @endphp
        <section class="card" style="margin-bottom:16px;">
            <div class="card-title">{{ $displayName }}</div>
            <p style="font-size:12px; color:#6b7280; margin-bottom:12px;">
                Période : {{ $report['from']->format('d/m/Y') }} — {{ $report['to']->format('d/m/Y') }}
            </p>
            @include('inovcom-attendance::components.performance-indicator', ['report' => $report])
        </section>

        <section class="card app-table-card">
            <div class="table-title" style="padding:12px 16px 0;">Calendrier de présence (lun.–sam., dimanche exclu)</div>
            <div class="table-scroll">
                <table>
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
                                <td>
                                    @if ($day['present'])
                                        @if ($day['complete'] ?? false)
                                            <span class="badge badge-success">Complet</span>
                                        @else
                                            <span class="badge badge-warning">Arrivée seule</span>
                                        @endif
                                    @else
                                        <span class="badge badge-error">Absent</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($employees->isEmpty())
        <section class="card">
            <p style="padding:24px; color:#6b7280;">Aucun employé actif. Activez le module Paie et créez des employés, en les liant à un compte utilisateur.</p>
        </section>
    @else
        <section class="card">
            <p style="padding:24px; color:#6b7280;">Sélectionnez un employé ou la vue équipe pour afficher les fiches de présence.</p>
        </section>
    @endif
</div>
