@php
    $tenantCode = $tenantCode ?? request()->query('tenant');
    $service = app(\InovCom\Attendance\Services\AttendanceService::class);
    $punchesToday = $todayStatus['punches_today'] ?? collect();
@endphp

<div class="page-body attendance-page">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif
    @if ($punchFlashMessage && ! session()->has('success') && ! session()->has('error'))
        <div class="alert alert-{{ $punchFlashType === 'success' ? 'success' : 'error' }}" style="margin-bottom:16px;">
            {{ $punchFlashMessage }}
        </div>
    @endif

    <section class="attendance-hero card {{ $isPresent ? 'attendance-hero--present' : ($departureTime ? 'attendance-hero--complete' : 'attendance-hero--idle') }}">
        <div class="attendance-hero__main">
            <div class="attendance-hero__label">Pointage du jour</div>
            <div class="attendance-hero__date">{{ $todayLabel ?? now()->format('d/m/Y') }} · {{ $clock }}</div>
            <div class="attendance-hero__status-pill">
                @if ($isPresent)
                    <span class="attendance-pill attendance-pill--present">Présent</span>
                @elseif ($arrivalTime && $departureTime)
                    <span class="attendance-pill attendance-pill--done">Journée clôturée</span>
                @else
                    <span class="attendance-pill attendance-pill--idle">En attente d’arrivée</span>
                @endif
            </div>
        </div>

        <div class="attendance-hero__metrics">
            <div class="attendance-metric">
                <span class="attendance-metric__label">Arrivée</span>
                <span class="attendance-metric__value {{ $arrivalTime ? 'is-set is-in' : '' }}">{{ $arrivalTime ?? '—' }}</span>
            </div>
            <div class="attendance-metric__arrow" aria-hidden="true">→</div>
            <div class="attendance-metric">
                <span class="attendance-metric__label">Départ</span>
                <span class="attendance-metric__value {{ $departureTime ? 'is-set is-out' : '' }}">{{ $departureTime ?? '—' }}</span>
            </div>
        </div>

        <div class="attendance-hero__actions">
            @if ($canPunch)
                @if ($canPunchIn)
                    <button
                        type="button"
                        class="btn btn-primary attendance-cta"
                        wire:click="punchIn"
                        wire:loading.attr="disabled"
                        wire:target="punchIn,punchOut"
                    >
                        <span wire:loading.remove wire:target="punchIn">
                            {{ $arrivalTime ? 'Nouvelle arrivée' : 'Pointer l’arrivée' }}
                        </span>
                        <span wire:loading wire:target="punchIn">Enregistrement…</span>
                    </button>
                    <p class="attendance-hero__hint">
                        Même action que le bouton <strong>Arrivée</strong> dans la barre du haut — l’état se synchronise partout.
                    </p>
                @elseif ($canPunchOut)
                    <button
                        type="button"
                        class="btn btn-primary attendance-cta attendance-cta--out"
                        wire:click="punchOut"
                        wire:loading.attr="disabled"
                        wire:target="punchIn,punchOut"
                    >
                        <span wire:loading.remove wire:target="punchOut">Pointer le départ</span>
                        <span wire:loading wire:target="punchOut">Enregistrement…</span>
                    </button>
                    <p class="attendance-hero__hint">
                        Arrivée enregistrée à <strong>{{ $arrivalTime }}</strong>. Utilisez aussi <strong>Départ</strong> dans la barre du haut.
                    </p>
                @endif
            @else
                <p class="attendance-hero__hint">Vous n’avez pas la permission de pointer.</p>
            @endif

            @if ($canSheet)
                <a class="btn btn-secondary" href="{{ route('tenant.attendance.sheet', ['tenant' => $tenantCode]) }}">
                    Fiche de présence
                </a>
            @endif
        </div>
    </section>

    @if ($punchesToday->isNotEmpty())
        <section class="card attendance-timeline-card" style="margin-bottom:16px;">
            <div class="card-title" style="margin-bottom:12px;">Timeline aujourd’hui</div>
            <ol class="attendance-timeline">
                @foreach ($punchesToday as $p)
                    @php
                        $isOut = ($p->punch_type ?? 'in') === 'out';
                    @endphp
                    <li class="attendance-timeline__item {{ $isOut ? 'is-out' : 'is-in' }}">
                        <span class="attendance-timeline__dot"></span>
                        <span class="attendance-timeline__type">{{ $service->punchTypeLabel($p->punch_type ?? 'in') }}</span>
                        <span class="attendance-timeline__time">{{ $p->punched_at->format('H:i:s') }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($myReport && ($myReport['expected_days'] ?? 0) === 0 && ($myReport['punches'] ?? collect())->isNotEmpty())
        <div class="alert alert-warning" style="margin-bottom:16px;">
            Des pointages existent sur la période, mais aucun jour ouvré (lun.–sam.) n’est encore comptabilisé.
            Vérifiez que votre fiche employé est liée à votre compte dans Paie → Employés.
        </div>
    @endif

    @if ($myReport)
        <section class="card" style="margin-bottom:16px; padding:16px;">
            <div class="card-title" style="margin-bottom:12px;">Ma performance (période filtrée)</div>
            @include('inovcom-attendance::components.performance-indicator', ['report' => $myReport])
        </section>
    @endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Historique des pointages</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <input class="input input-sm" type="date" wire:model.live="dateFrom" title="Du" aria-label="Date de début">
                <input class="input input-sm" type="date" wire:model.live="dateTo" title="Au" aria-label="Date de fin">
                @if ($canViewAll && $employees->isNotEmpty())
                    <select class="input input-sm" wire:model.live="employeeFilter" aria-label="Employé">
                        <option value="">Tous employés</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_number }})</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Heure</th>
                        <th>Employé lié</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($punches as $p)
                        @php
                            $typeLabel = $service->punchTypeLabel($p->punch_type ?? 'in');
                            $isOut = ($p->punch_type ?? 'in') === 'out';
                        @endphp
                        <tr wire:key="punch-{{ $p->id }}">
                            <td>{{ $p->attendance_date->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge {{ $isOut ? 'badge-neutral' : 'badge-success' }}">{{ $typeLabel }}</span>
                            </td>
                            <td class="prospect-money">{{ $p->punched_at->format('H:i:s') }}</td>
                            <td>{{ $p->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:#94a3b8;padding:28px;">
                                Aucun pointage sur cette période.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($punches, 'links'))
            <div style="padding:12px;">{{ $punches->links() }}</div>
        @endif
    </section>
</div>
