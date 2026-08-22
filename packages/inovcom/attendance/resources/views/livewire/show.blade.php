@php
    $tenantCode = $tenantCode ?? request()->query('tenant');
    $employee = $report['employee'] ?? null;
@endphp

<div class="page-body attendance-page">
    <section class="card attendance-detail-header" style="margin-bottom:16px; padding:16px 18px;">
        <div class="attendance-detail-toolbar">
            <div>
                <a
                    class="attendance-back"
                    href="{{ route('tenant.attendance.index', ['tenant' => $tenantCode, 'month' => $month]) }}"
                >
                    ← Retour à la liste
                </a>
                <h1 class="attendance-detail-name">{{ $displayName }}</h1>
                @if ($employee?->employee_number || $employee?->position)
                    <p class="attendance-emp-meta" style="margin:4px 0 0;">
                        @if ($employee?->employee_number) N° {{ $employee->employee_number }} @endif
                        @if ($employee?->employee_number && $employee?->position) · @endif
                        @if ($employee?->position) {{ $employee->position }} @endif
                    </p>
                @endif
            </div>
            <div class="attendance-toolbar__filters">
                <select class="input input-sm" wire:model.live="month" aria-label="Mois">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-export-btn format="excel" class="btn-sm" wire:click="exportExcel">Feuille Excel</x-export-btn>
                <x-export-btn format="pdf" class="btn-sm" wire:click="exportPdf">Feuille PDF</x-export-btn>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:16px; padding:16px;">
        <div class="card-title" style="margin-bottom:12px;">Indicateur — {{ $monthLabel }}</div>
        @include('inovcom-attendance::components.performance-indicator', ['report' => $report])
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Historique du mois</div>
            <div class="attendance-emp-meta">
                {{ $dateFrom->format('d/m/Y') }} — {{ $dateTo->format('d/m/Y') }}
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Jour</th>
                        <th>Arrivée</th>
                        <th>Départ</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['days'] as $day)
                        <tr>
                            <td>{{ $day['label'] }}</td>
                            <td>{{ $day['weekday'] }}</td>
                            <td class="{{ $day['arrival'] ? 'is-in-time' : '' }}">{{ $day['arrival'] ?? '—' }}</td>
                            <td class="{{ $day['departure'] ? 'is-out-time' : '' }}">{{ $day['departure'] ?? '—' }}</td>
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
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center; color:#94a3b8; padding:28px;">
                                Aucun jour à afficher pour ce mois.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="attendance-footer-note">
            Présence = arrivée pointée · Complet = arrivée + départ · Jours ouvrés lun.–sam.
        </div>
    </section>
</div>
