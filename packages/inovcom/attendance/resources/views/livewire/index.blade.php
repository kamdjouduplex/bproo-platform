@php
    $tenantCode = $tenantCode ?? request()->query('tenant');
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
    @if (($networkConfigured ?? false) && ! ($networkOk ?? true))
        <div class="alert alert-error" style="margin-bottom:16px;" role="alert">
            {{ $networkMessage }}
        </div>
    @endif

    {{-- Pointage du jour (arrivée / départ) --}}
    @if ($canPunch)
        <section class="attendance-hero card {{ $isPresent ? 'attendance-hero--present' : ($departureTime ? 'attendance-hero--complete' : 'attendance-hero--idle') }}">
            <div class="attendance-hero__main">
                <div class="attendance-hero__label">
                    Pointage du jour
                    @if (!empty($connectedUserName))
                        · {{ $connectedUserName }}
                    @endif
                </div>
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
                @if ($networkConfigured ?? false)
                    <p class="attendance-hero__hint" style="margin-top:10px;">
                        Pointage autorisé sur le Wi‑Fi <strong>{{ $wifiName }}</strong>
                    </p>
                @endif
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
                    <p class="attendance-hero__hint">Marquez votre arrivée en début de journée.</p>
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
                    <p class="attendance-hero__hint">Arrivée à <strong>{{ $arrivalTime }}</strong> — pensez à pointer le départ.</p>
                @else
                    <p class="attendance-hero__hint">Journée déjà clôturée. À demain !</p>
                @endif

                @if ($canSettings ?? false)
                    <div style="margin-top:8px;">
                        <a class="btn btn-secondary" href="{{ route('tenant.attendance.settings', ['tenant' => $tenantCode]) }}">
                            Paramètres
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @elseif ($canSettings ?? false)
        <div style="margin-bottom:16px;">
            <a class="btn btn-secondary" href="{{ route('tenant.attendance.settings', ['tenant' => $tenantCode]) }}">
                Paramètres
            </a>
        </div>
    @endif

    {{-- ========== ADMIN : liste employés + % ========== --}}
    @if ($canViewAll)
        <section class="card app-table-card">
            <div class="table-toolbar attendance-toolbar">
                <div class="table-title">Employés — {{ $monthLabel }}</div>
                <div class="attendance-toolbar__filters">
                    <input
                        class="input input-sm"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Rechercher un employé…"
                        aria-label="Rechercher un employé"
                    >
                    <select class="input input-sm" wire:model.live="month" aria-label="Mois">
                        @foreach ($monthOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>N°</th>
                            <th>Présences</th>
                            <th>Absences</th>
                            <th>Taux de présence</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($teamSummary['rows'] ?? []) as $row)
                            @php
                                $level = $row['performance_level'];
                                $pct = $row['performance_percent'];
                                $barColor = match ($level) {
                                    'excellent' => '#16a34a',
                                    'good' => '#2563eb',
                                    'warning' => '#d97706',
                                    default => '#dc2626',
                                };
                                $detailUrl = $row['employee_id']
                                    ? route('tenant.attendance.show', ['tenant' => $tenantCode, 'employeeId' => $row['employee_id'], 'month' => $month])
                                    : route('tenant.attendance.show-user', ['tenant' => $tenantCode, 'userId' => $row['user_id'], 'month' => $month]);
                            @endphp
                            <tr wire:key="person-{{ $row['key'] }}">
                                <td>
                                    <strong>{{ $row['display_name'] }}</strong>
                                    @if (!empty($row['position']))
                                        <div class="attendance-emp-meta">{{ $row['position'] }}</div>
                                    @elseif (! $row['employee_id'] && !empty($row['user']?->email))
                                        <div class="attendance-emp-meta">{{ $row['user']->email }}</div>
                                    @endif
                                </td>
                                <td>{{ $row['employee_number'] ?? '—' }}</td>
                                <td>
                                    <span style="color:#16a34a; font-weight:600;">{{ $row['present_days'] }}</span>
                                    <span class="attendance-emp-meta"> / {{ $row['expected_days'] }} j.</span>
                                </td>
                                <td style="color:#b91c1c; font-weight:600;">{{ $row['absent_days'] }}</td>
                                <td style="min-width:160px;">
                                    <div class="attendance-pct">
                                        <div class="attendance-pct__bar" aria-hidden="true">
                                            <span style="width:{{ min(100, $pct) }}%; background:{{ $barColor }};"></span>
                                        </div>
                                        <div class="attendance-pct__label" style="color:{{ $barColor }};">
                                            {{ fmt_num($pct, 1) }}%
                                            <span class="attendance-emp-meta">· {{ $row['performance_label'] }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a class="btn btn-secondary btn-sm" href="{{ $detailUrl }}">Voir</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center; color:#94a3b8; padding:32px;">
                                    @if (trim($search) !== '')
                                        Aucune personne ne correspond à « {{ $search }} ».
                                    @else
                                        Aucune personne active à afficher.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (!empty($teamSummary['rows']))
                <div class="attendance-footer-note">
                    Base de calcul : jours ouvrés (lun.–sam.) du mois · {{ $teamSummary['expected_days'] ?? 0 }} jour(s) ouvré(s) à ce jour
                </div>
            @endif
        </section>

    {{-- ========== EMPLOYÉ : mon historique ========== --}}
    @else
        <section class="card attendance-intro" style="margin-bottom:16px; padding:16px 18px;">
            <div class="attendance-intro__title">Ma présence</div>
            <p class="attendance-intro__text">
                Consultez votre historique d’arrivées et de départs pour le mois sélectionné,
                et imprimez votre fiche de présence en PDF.
            </p>
        </section>

        @if (! $myEmployee)
            <div class="alert alert-warning" style="margin-bottom:16px;">
                Votre compte n’est pas encore lié à une fiche employé (Paie). Votre présence est quand même suivie via votre compte utilisateur.
            </div>
        @endif

        @if ($myReport)
            <section class="card" style="margin-bottom:16px; padding:16px;">
                <div class="attendance-detail-toolbar" style="margin-bottom:14px;">
                    <div class="table-title" style="margin:0;">{{ $monthLabel }}</div>
                    <div class="attendance-toolbar__filters">
                        <select class="input input-sm" wire:model.live="month" aria-label="Mois">
                            @foreach ($monthOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($printUrl)
                            <a
                                class="btn btn-primary btn-sm"
                                href="{{ $printUrl }}"
                                onclick="window.open(this.href, '_blank'); return false;"
                            >
                                Imprimer la fiche PDF
                            </a>
                        @endif
                    </div>
                </div>

                @include('inovcom-attendance::components.performance-indicator', ['report' => $myReport])
            </section>

            <section class="card app-table-card">
                <div class="table-toolbar">
                    <div class="table-title">Détail jour par jour</div>
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
                            @forelse ($myReport['days'] as $day)
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
            </section>
        @endif
    @endif
</div>
