@php
    $canViewDocuments = $canViewDocuments ?? false;
    $studentDocuments = $studentDocuments ?? collect();
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $className = $currentEnrollment?->schoolClass?->name;
    $section = $currentEnrollment?->section ?: ($currentEnrollment?->schoolClass?->section ?? null);
    $yearName = $currentEnrollment?->academicYear?->name;
    $classLine = collect([$className, $section ? 'Section '.$section : null, $yearName])->filter()->implode(' · ');
    $enrollmentStatus = $currentEnrollment
        ? ($enrollmentStatusLabels[$currentEnrollment->status] ?? $currentEnrollment->status)
        : null;
    $tuitionLabels = [
        'paid' => 'Soldé',
        'partial' => 'Acompte versé',
        'unpaid' => 'Impayé',
        'none' => 'Aucun frais',
        'unknown' => '—',
    ];
    $tuitionLabel = $tuitionLabels[$tuition['status'] ?? 'none'] ?? '—';
    $bornWord = ($student->gender === 'F') ? 'Née' : 'Né';
    $metaBits = collect([
        $genderLabel,
        $age ? $age.' ans' : null,
        $student->birth_date ? $bornWord.' le '.$student->birth_date->format('d/m/Y') : null,
    ])->filter();
    $completionTone = $completion['percent'] >= 80 ? 'ok' : ($completion['percent'] >= 50 ? 'mid' : 'low');
    $tuitionTone = match ($tuition['status'] ?? 'none') {
        'paid' => 'ok',
        'partial' => 'mid',
        'unpaid' => 'bad',
        default => 'muted',
    };
    $attTone = $attendanceStats['rate'] === null
        ? 'muted'
        : ($attendanceStats['rate'] >= 90 ? 'ok' : ($attendanceStats['rate'] >= 75 ? 'mid' : 'bad'));
@endphp

<div class="page-body sch-detail-page sch-dossier">
    @include('school::livewire.partials.detail-styles')
    <style>
        .sch-dossier { gap: 18px; }
        .sch-dossier .app-table-card { margin-top: 0; }
        .sch-dossier > .card.sch-dossier-hero { padding: 20px 22px; }
        .sch-dossier > .card.sch-panel { padding: 0; }
        .sch-dossier-hero {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 22px;
            align-items: start;
            background:
                radial-gradient(1200px 180px at 0% 0%, rgba(63,167,150,.10), transparent 55%),
                #fff;
        }
        .sch-dossier-photo { display: flex; flex-direction: column; gap: 8px; width: 132px; }
        .sch-dossier-avatar {
            width: 132px; height: 166px;
            border-radius: 14px;
            object-fit: cover;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(165deg, #2d8a7c, #3fa796);
            color: #fff;
            font-size: 2.1rem; font-weight: 700; letter-spacing: .04em;
            border: 3px solid #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .12);
        }
        .sch-dossier-photo-edit { font-size: 12px; color: #475569; }
        .sch-dossier-photo-edit summary {
            cursor: pointer; color: #3fa796; font-weight: 600; list-style: none;
        }
        .sch-dossier-photo-edit summary::-webkit-details-marker { display: none; }
        .sch-dossier-photo-edit[open] summary { margin-bottom: 8px; }
        .sch-dossier-identity { min-width: 0; }
        .sch-dossier-kicker {
            margin: 0 0 4px;
            font-size: 11px; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: #3fa796;
        }
        .sch-dossier-name {
            margin: 0;
            font-size: 1.55rem; font-weight: 800; color: #0f172a; line-height: 1.2;
        }
        .sch-dossier-code {
            margin: 6px 0 0;
            font-size: 13px; color: #64748b; font-variant-numeric: tabular-nums;
        }
        .sch-dossier-code strong { color: #0f172a; font-weight: 700; }
        .sch-dossier-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .sch-dossier-meta { margin: 10px 0 0; font-size: 13px; color: #475569; }
        .sch-dossier-class { margin: 4px 0 0; font-size: 14px; font-weight: 600; color: #0f172a; }
        .sch-dossier-hero__top {
            display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-start;
        }
        .sch-dossier-hero__actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .sch-dossier-complete { margin-top: 14px; max-width: 420px; }
        .sch-dossier-complete__row {
            display: flex; justify-content: space-between; gap: 8px;
            font-size: 12px; color: #64748b; margin-bottom: 6px;
        }
        .sch-dossier-complete__bar {
            height: 7px; background: #e2e8f0; border-radius: 999px; overflow: hidden;
        }
        .sch-dossier-complete__fill { height: 100%; border-radius: 999px; background: #3fa796; }
        .sch-dossier-complete__fill--mid { background: #d97706; }
        .sch-dossier-complete__fill--low { background: #dc2626; }
        .sch-dossier-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .sch-chip {
            display: inline-flex; align-items: center;
            padding: 2px 8px; border-radius: 999px;
            background: #f1f5f9; color: #64748b; font-size: 11px;
        }
        .sch-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }
        .sch-kpi {
            background: #fff; border: 1px solid #e8eef5; border-radius: 14px;
            padding: 14px 16px; min-width: 0;
        }
        .sch-kpi__label {
            font-size: 11px; font-weight: 700; letter-spacing: .06em;
            text-transform: uppercase; color: #94a3b8; margin: 0 0 6px;
        }
        .sch-kpi__value {
            margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sch-kpi__hint { margin: 4px 0 0; font-size: 12px; color: #64748b; }
        .sch-kpi--ok .sch-kpi__value { color: #047857; }
        .sch-kpi--mid .sch-kpi__value { color: #b45309; }
        .sch-kpi--bad .sch-kpi__value { color: #b91c1c; }
        .sch-kpi--muted .sch-kpi__value { color: #64748b; }
        .sch-dossier-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .sch-panel { padding: 0; overflow: hidden; }
        .sch-panel__head {
            display: flex; justify-content: space-between; align-items: center;
            gap: 8px; padding: 14px 18px 0;
        }
        .sch-panel__title {
            margin: 0; font-size: .95rem; font-weight: 700; color: #0f172a;
        }
        .sch-dl {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 10px 16px;
            padding: 14px 18px 18px;
            margin: 0;
        }
        .sch-dl dt { font-size: 12px; color: #64748b; margin: 0; padding-top: 1px; }
        .sch-dl dd { margin: 0; font-size: 14px; font-weight: 600; color: #0f172a; word-break: break-word; }
        .sch-dl dd a { color: #0f766e; text-decoration: none; }
        .sch-dl dd a:hover { text-decoration: underline; }
        .sch-dl__muted { font-weight: 500; color: #94a3b8; }
        .sch-tabs {
            display: flex; gap: 4px; padding: 10px 12px 0;
            border-bottom: 1px solid #eef2f7;
            overflow-x: auto;
        }
        .sch-tab {
            border: 0; background: transparent; cursor: pointer;
            padding: 10px 14px; font-size: 13px; font-weight: 600;
            color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -1px;
            white-space: nowrap;
        }
        .sch-tab:hover { color: #0f172a; }
        .sch-tab.is-active { color: #0f172a; border-bottom-color: #3fa796; }
        .sch-tab__count {
            display: inline-flex; min-width: 18px; height: 18px; padding: 0 5px;
            align-items: center; justify-content: center;
            border-radius: 999px; background: #f1f5f9; color: #475569;
            font-size: 11px; margin-left: 6px;
        }
        .sch-tab.is-active .sch-tab__count { background: #d1fae5; color: #047857; }
        .sch-empty {
            padding: 36px 20px; text-align: center; color: #64748b;
        }
        .sch-empty p { margin: 0 0 12px; }
        .sch-money { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sch-money--in { color: #047857; }
        .sch-money--out { color: #b91c1c; }
        @media (max-width: 900px) {
            .sch-kpis, .sch-dossier-cols { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 720px) {
            .sch-dossier-hero, .sch-kpis, .sch-dossier-cols, .sch-dl { grid-template-columns: 1fr; }
            .sch-dossier-photo { width: 104px; }
            .sch-dossier-avatar { width: 104px; height: 130px; font-size: 1.6rem; }
            .sch-dossier-name { font-size: 1.25rem; }
        }
    </style>

    <section class="card app-table-card sch-dossier-hero">
        <div class="sch-dossier-photo">
            @if($photoUrl)
                <img class="sch-dossier-avatar" src="{{ $photoUrl }}" alt="{{ $student->full_name }}">
            @else
                <div class="sch-dossier-avatar" aria-hidden="true">{{ $initials ?: 'É' }}</div>
            @endif
            @if($isManage && $canManage)
                <details class="sch-dossier-photo-edit">
                    <summary>Changer la photo</summary>
                    @include('school::livewire.partials.student-photo-cropper', [
                        'wireMethod' => 'saveCroppedProfilePhoto',
                        'currentUrl' => $photoUrl,
                        'buttonLabel' => 'Choisir et cadrer',
                    ])
                    @if($photoUrl)
                        <div style="margin-top:8px;">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="clearProfilePhoto" wire:confirm="Retirer la photo de profil ?">
                                Retirer
                            </button>
                        </div>
                    @endif
                </details>
            @endif
        </div>

        <div class="sch-dossier-identity">
            <div class="sch-dossier-hero__top">
                <div>
                    <p class="sch-dossier-kicker">Dossier élève</p>
                    <h2 class="sch-dossier-name">{{ $student->full_name }}</h2>
                    <p class="sch-dossier-code">
                        Matricule
                        <strong>{{ $student->student_code ?: '—' }}</strong>
                        <span style="margin:0 8px; color:#cbd5e1;">·</span>
                        NISU
                        <strong>{{ $student->nisu ?: '—' }}</strong>
                    </p>
                    @if($classLine)
                        <p class="sch-dossier-class">{{ $classLine }}</p>
                    @else
                        <p class="sch-dossier-class" style="font-weight:500;color:#94a3b8;">Aucune inscription en cours</p>
                    @endif
                    @if($metaBits->isNotEmpty())
                        <p class="sch-dossier-meta">{{ $metaBits->implode(' · ') }}</p>
                    @endif
                    <div class="sch-dossier-badges">
                        <span class="badge {{ $student->is_active ? 'badge-success' : 'badge-neutral' }}">
                            {{ $student->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                        @if($enrollmentStatus)
                            <span class="badge {{ $currentEnrollment?->status === 'enrolled' ? 'badge-info' : 'badge-neutral' }}">
                                {{ $enrollmentStatus }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="sch-dossier-hero__actions">
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.students.index', ['tenant' => $tenantCode]) }}">Retour</a>
                    @if($hasEnrollmentPrint && $currentEnrollment && $canViewEnrollments)
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.enrollments.print', ['tenant' => $tenantCode, 'enrollment' => $currentEnrollment->id]) }}" target="_blank">Fiche d’inscription</a>
                    @endif
                    @if($hasIdCardPrint && $latestIdCard && $canViewIdCards)
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.id_cards.print', ['tenant' => $tenantCode, 'id' => $latestIdCard->id]) }}" target="_blank">Carte ID</a>
                    @endif
                    @if($isManage)
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.students.show', ['tenant' => $tenantCode, 'id' => $student->id]) }}">Voir</a>
                        @if($canManage)
                            <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                            <button class="btn btn-secondary btn-sm" wire:click="{{ $student->is_active ? 'deactivate' : 'activate' }}">
                                {{ $student->is_active ? 'Désactiver' : 'Activer' }}
                            </button>
                        @endif
                    @elseif($canManage)
                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.students.manage', ['tenant' => $tenantCode, 'id' => $student->id]) }}">Gérer</a>
                    @endif
                </div>
            </div>

            <div class="sch-dossier-complete">
                <div class="sch-dossier-complete__row">
                    <span>Profil complété</span>
                    <span>{{ $completion['percent'] }}% · {{ $completion['filled'] }}/{{ $completion['total'] }}</span>
                </div>
                <div class="sch-dossier-complete__bar">
                    <div class="sch-dossier-complete__fill {{ $completionTone !== 'ok' ? 'sch-dossier-complete__fill--'.$completionTone : '' }}" style="width: {{ $completion['percent'] }}%;"></div>
                </div>
                @if(count($completion['missing']))
                    <div class="sch-dossier-chips">
                        @foreach($completion['missing'] as $missing)
                            <span class="sch-chip">Manque : {{ $missing }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="sch-kpis">
        <article class="sch-kpi">
            <p class="sch-kpi__label">Classe</p>
            <p class="sch-kpi__value">{{ $className ?: '—' }}</p>
            <p class="sch-kpi__hint">{{ $yearName ?: 'Pas encore inscrit' }}{{ $section ? ' · '.$section : '' }}</p>
        </article>
        <article class="sch-kpi">
            <p class="sch-kpi__label">Responsable</p>
            <p class="sch-kpi__value">{{ $student->parent_full_name ?: '—' }}</p>
            <p class="sch-kpi__hint">
                {{ $relationshipLabel ?: 'Parent / tuteur' }}
                @if($student->parent_phone)
                    · {{ $student->parent_phone }}
                @endif
            </p>
        </article>
        <article class="sch-kpi sch-kpi--{{ $tuitionTone }}">
            <p class="sch-kpi__label">Scolarité</p>
            <p class="sch-kpi__value">
                @if(($tuition['status'] ?? 'none') === 'none')
                    —
                @elseif(($tuition['due'] ?? 0) > 0)
                    {{ $fmt($tuition['due']) }} dû
                @else
                    Soldé
                @endif
            </p>
            <p class="sch-kpi__hint">
                {{ $tuitionLabel }}
                @if(($tuition['charged'] ?? 0) > 0)
                    · {{ $fmt($tuition['paid']) }} / {{ $fmt($tuition['charged']) }}
                @endif
            </p>
        </article>
        <article class="sch-kpi sch-kpi--{{ $attTone }}">
            <p class="sch-kpi__label">Assiduité</p>
            <p class="sch-kpi__value">{{ $attendanceStats['rate'] !== null ? $attendanceStats['rate'].' %' : '—' }}</p>
            <p class="sch-kpi__hint">
                @if($attendanceStats['total'] > 0)
                    {{ $attendanceStats['present'] }} présent{{ $attendanceStats['present'] > 1 ? 's' : '' }}
                    · {{ $attendanceStats['absent'] }} absent{{ $attendanceStats['absent'] > 1 ? 's' : '' }}
                    @if($attendanceStats['late']) · {{ $attendanceStats['late'] }} retard{{ $attendanceStats['late'] > 1 ? 's' : '' }}@endif
                @else
                    Aucun appel enregistré
                @endif
            </p>
        </article>
    </div>

    <div class="sch-dossier-cols">
        <section class="card app-table-card sch-panel">
            <div class="sch-panel__head">
                <h3 class="sch-panel__title">Identité</h3>
            </div>
            <dl class="sch-dl">
                <dt>NISU</dt>
                <dd class="{{ $student->nisu ? '' : 'sch-dl__muted' }}">{{ $student->nisu ?: 'Non renseigné' }}</dd>
                <dt>Prénom</dt>
                <dd>{{ $student->first_name ?: '—' }}</dd>
                <dt>Nom</dt>
                <dd>{{ $student->last_name ?: '—' }}</dd>
                <dt>Genre</dt>
                <dd>{{ $genderLabel ?: '—' }}</dd>
                <dt>Naissance</dt>
                <dd>
                    @if($student->birth_date)
                        {{ $student->birth_date->format('d/m/Y') }}{{ $age ? ' ('.$age.' ans)' : '' }}
                    @else
                        <span class="sch-dl__muted">—</span>
                    @endif
                </dd>
                <dt>Lieu</dt>
                <dd class="{{ $student->birth_place ? '' : 'sch-dl__muted' }}">{{ $student->birth_place ?: '—' }}</dd>
                <dt>Adresse</dt>
                <dd class="{{ $student->address ? '' : 'sch-dl__muted' }}">{{ $student->address ?: '—' }}</dd>
                <dt>Établ. précédent</dt>
                <dd class="{{ $student->previous_school ? '' : 'sch-dl__muted' }}">{{ $student->previous_school ?: '—' }}</dd>
                <dt>Inscrit le</dt>
                <dd>{{ $student->created_at?->format('d/m/Y') ?? '—' }}</dd>
            </dl>
        </section>

        <section class="card app-table-card sch-panel">
            <div class="sch-panel__head">
                <h3 class="sch-panel__title">Famille &amp; contacts</h3>
            </div>
            <dl class="sch-dl">
                <dt>Responsable</dt>
                <dd class="{{ $student->parent_full_name ? '' : 'sch-dl__muted' }}">{{ $student->parent_full_name ?: '—' }}</dd>
                <dt>Lien</dt>
                <dd class="{{ $relationshipLabel ? '' : 'sch-dl__muted' }}">{{ $relationshipLabel ?: '—' }}</dd>
                <dt>Téléphone</dt>
                <dd>
                    @if($student->parent_phone)
                        <a href="tel:{{ $student->parent_phone }}">{{ $student->parent_phone }}</a>
                    @else
                        <span class="sch-dl__muted">—</span>
                    @endif
                </dd>
                <dt>Email</dt>
                <dd>
                    @if($student->parent_email)
                        <a href="mailto:{{ $student->parent_email }}">{{ $student->parent_email }}</a>
                    @else
                        <span class="sch-dl__muted">—</span>
                    @endif
                </dd>
                <dt>Urgence</dt>
                <dd>
                    @if($student->emergency_contact_name || $student->emergency_contact_phone)
                        {{ $student->emergency_contact_name ?: 'Contact' }}
                        @if($student->emergency_contact_phone)
                            · <a href="tel:{{ $student->emergency_contact_phone }}">{{ $student->emergency_contact_phone }}</a>
                        @endif
                    @else
                        <span class="sch-dl__muted">—</span>
                    @endif
                </dd>
                <dt>Notes</dt>
                <dd class="{{ $student->notes ? '' : 'sch-dl__muted' }}">{{ $student->notes ?: 'Aucune note' }}</dd>
            </dl>
        </section>
    </div>

    <section class="card app-table-card">
        <div class="sch-tabs" role="tablist">
            <button type="button" class="sch-tab {{ $dossierTab === 'scolarite' ? 'is-active' : '' }}" wire:click="$set('dossierTab', 'scolarite')">
                Scolarité<span class="sch-tab__count">{{ $enrollments->count() }}</span>
            </button>
            <button type="button" class="sch-tab {{ $dossierTab === 'finances' ? 'is-active' : '' }}" wire:click="$set('dossierTab', 'finances')">
                Finances<span class="sch-tab__count">{{ $payments->count() }}</span>
            </button>
            <button type="button" class="sch-tab {{ $dossierTab === 'notes' ? 'is-active' : '' }}" wire:click="$set('dossierTab', 'notes')">
                Notes<span class="sch-tab__count">{{ $examMarks->count() }}</span>
            </button>
            <button type="button" class="sch-tab {{ $dossierTab === 'presences' ? 'is-active' : '' }}" wire:click="$set('dossierTab', 'presences')">
                Présences<span class="sch-tab__count">{{ $attendanceStats['total'] }}</span>
            </button>
            @if($canViewDocuments)
                <button type="button" class="sch-tab {{ $dossierTab === 'pieces' ? 'is-active' : '' }}" wire:click="$set('dossierTab', 'pieces')">
                    Pièces<span class="sch-tab__count">{{ $studentDocuments->count() }}</span>
                </button>
            @endif
        </div>

        @if($dossierTab === 'scolarite')
            <div class="sch-list-head">
                <h2 class="sch-list-head__title">Inscriptions</h2>
                @if($canViewEnrollments)
                    <div class="sch-list-head__actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.enrollments.index', ['tenant' => $tenantCode]) }}">Toutes les inscriptions</a>
                    </div>
                @endif
            </div>
            @if($enrollments->isEmpty())
                <div class="sch-empty">
                    <p>Cet élève n’a pas encore d’inscription.</p>
                    @if($canViewEnrollments)
                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.enrollments.index', ['tenant' => $tenantCode]) }}">Inscrire l’élève</a>
                    @endif
                </div>
            @else
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Année</th><th>Classe</th><th>Section</th><th>Statut</th><th>Inscrit le</th><th></th></tr></thead>
                        <tbody>
                        @foreach($enrollments as $e)
                            <tr>
                                <td>{{ $e->academicYear?->name ?? '—' }}</td>
                                <td>{{ $e->schoolClass?->name ?? '—' }}</td>
                                <td>{{ $e->section ?: ($e->schoolClass?->section ?? '—') }}</td>
                                <td>
                                    <span class="badge {{ $e->status === 'enrolled' ? 'badge-success' : 'badge-neutral' }}">
                                        {{ $enrollmentStatusLabels[$e->status] ?? $e->status }}
                                    </span>
                                </td>
                                <td>{{ $e->created_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="sch-row-actions">
                                    @if($canViewEnrollments)
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.enrollments.show', ['tenant' => $tenantCode, 'id' => $e->id]) }}">Voir</a>
                                    @endif
                                    @if($hasEnrollmentPrint && $canViewEnrollments)
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.enrollments.print', ['tenant' => $tenantCode, 'enrollment' => $e->id]) }}" target="_blank">PDF</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @elseif($dossierTab === 'finances')
            <div class="sch-list-head">
                <h2 class="sch-list-head__title">Compte élève</h2>
                <div class="sch-list-head__actions">
                    <span class="badge {{ $balance < 0 ? 'badge-error' : 'badge-success' }}">
                        Solde : {{ $fmt($balance) }}
                    </span>
                    @if($canViewPayments)
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.payments.index', ['tenant' => $tenantCode]) }}">Paiements</a>
                    @endif
                </div>
            </div>
            <div style="padding:0 16px 8px; font-size:13px; color:#64748b;">
                @if(($tuition['status'] ?? 'none') !== 'none' && $yearName)
                    Année {{ $yearName }} :
                    {{ $fmt($tuition['paid']) }} payé sur {{ $fmt($tuition['charged']) }}
                    @if(($tuition['due'] ?? 0) > 0)
                        — reste {{ $fmt($tuition['due']) }}.
                    @else
                        — scolarité soldée.
                    @endif
                @else
                    Les frais s’imputent à l’inscription ; les paiements validés créditent le solde.
                @endif
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Date</th><th>Libellé</th><th>Type</th><th class="right">Montant</th><th class="right">Solde après</th></tr></thead>
                    <tbody>
                    @forelse($ledger as $entry)
                        <tr>
                            <td>{{ $entry->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $entry->label }}</td>
                            <td>{{ $entry->entry_type === 'credit' ? 'Crédit' : 'Débit' }}</td>
                            <td class="right sch-money {{ $entry->entry_type === 'credit' ? 'sch-money--in' : 'sch-money--out' }}">
                                {{ $entry->entry_type === 'credit' ? '+' : '−' }}{{ $fmt($entry->amount) }}
                            </td>
                            <td class="right sch-money">{{ $fmt($entry->balance_after) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucun mouvement de compte.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="sch-list-head" style="padding-top:8px;">
                <h2 class="sch-list-head__title">Paiements</h2>
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Date</th><th>Année</th><th>Type</th><th class="right">Montant</th><th>Statut</th><th>Réf.</th></tr></thead>
                    <tbody>
                    @forelse($payments as $p)
                        @php
                            $payStatus = \School\Support\SchoolPaymentCatalog::statusLabel((string) $p->status);
                            $payBadge = match ($p->status) {
                                'verified' => 'badge-success',
                                'rejected' => 'badge-error',
                                'pending' => 'badge-warning',
                                default => 'badge-neutral',
                            };
                        @endphp
                        <tr>
                            <td>{{ $p->created_at?->format('d/m/Y') }}</td>
                            <td>{{ $p->academicYear?->name ?? '—' }}</td>
                            <td>{{ $p->typeLabel() }}</td>
                            <td class="right sch-money">{{ $fmt($p->amount) }} {{ $p->currency_code }}</td>
                            <td><span class="badge {{ $payBadge }}">{{ $payStatus }}</span></td>
                            <td>
                                @if($canViewPayments)
                                    <a href="{{ route('tenant.school.payments.show', ['tenant' => $tenantCode, 'id' => $p->id]) }}">{{ $p->reference ?: '#'.$p->id }}</a>
                                @else
                                    {{ $p->reference ?? '—' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucun paiement enregistré.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($dossierTab === 'notes')
            <div class="sch-list-head">
                <h2 class="sch-list-head__title">Résultats d’examens</h2>
            </div>
            @if($examMarks->isEmpty())
                <div class="sch-empty">
                    <p>Aucune note n’a encore été saisie pour cet élève.</p>
                </div>
            @else
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Examen</th><th>Matière</th><th>Date</th><th class="right">Note</th><th>Validé</th></tr></thead>
                        <tbody>
                        @foreach($examMarks as $m)
                            <tr>
                                <td>
                                    {{ $m->exam?->title ?? '—' }}
                                    @if($m->exam?->kind || $m->exam?->period)
                                        <div style="font-size:12px; color:#64748b;">
                                            {{ trim(implode(' · ', array_filter([$m->exam?->kindLabel(), $m->exam?->periodLabel()]))) }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $m->exam?->subject?->name ?? '—' }}</td>
                                <td>{{ $m->exam?->exam_date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="right sch-money">
                                    @if($m->is_absent)
                                        <span class="badge badge-warning">Absent</span>
                                    @else
                                        {{ rtrim(rtrim(number_format((float) $m->score, 2, ',', ' '), '0'), ',') }}
                                        @if($m->exam?->max_score)
                                            <span style="color:#94a3b8;font-weight:500;"> / {{ rtrim(rtrim(number_format((float) $m->exam->max_score, 2, ',', ' '), '0'), ',') }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td>{{ $m->validated_at?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @elseif($dossierTab === 'presences')
            <div class="sch-list-head">
                <h2 class="sch-list-head__title">Présences récentes</h2>
                @if($canViewAttendance)
                    <div class="sch-list-head__actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.attendance.index', ['tenant' => $tenantCode]) }}">Faire l’appel</a>
                    </div>
                @endif
            </div>
            @if($attendance->isEmpty())
                <div class="sch-empty">
                    <p>Aucun appel n’a encore été enregistré pour cet élève.</p>
                </div>
            @else
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Date</th><th>Classe</th><th>Cours</th><th>Statut</th><th>Remarque</th></tr></thead>
                        <tbody>
                        @foreach($attendance as $row)
                            @php
                                $attBadge = match ($row->status) {
                                    'present' => 'badge-success',
                                    'absent' => 'badge-error',
                                    'late' => 'badge-warning',
                                    'excused' => 'badge-info',
                                    default => 'badge-neutral',
                                };
                            @endphp
                            <tr>
                                <td>{{ $row->attendance_date?->format('d/m/Y') }}</td>
                                <td>{{ $row->schoolClass?->name ?? '—' }}</td>
                                <td>{{ $row->course?->subject?->name ?? 'Appel général' }}</td>
                                <td>
                                    <span class="badge {{ $attBadge }}">
                                        {{ \School\Models\SchoolAttendanceRecord::statuses()[$row->status] ?? $row->status }}
                                    </span>
                                </td>
                                <td>{{ $row->remark ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @elseif($dossierTab === 'pieces' && $canViewDocuments)
            <div class="sch-list-head">
                <h2 class="sch-list-head__title">Pièces du dossier</h2>
                @if($hasDocumentsIndex)
                    <div class="sch-list-head__actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.documents.index', ['tenant' => $tenantCode, 'student' => $student->id]) }}">Tous les dossiers</a>
                    </div>
                @endif
            </div>
            @include('school::livewire.partials.student-documents')
        @endif
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <h3 class="sch-modal__title">Modifier l’élève</h3>
                    <button class="sch-modal__close" wire:click="cancel" type="button">&times;</button>
                </div>
                <div class="sch-modal__body">
                    @include('school::livewire.partials.student-form-fields')
                    @include('school::livewire.partials.student-photo-cropper', [
                        'wireMethod' => 'setCroppedPhoto',
                        'currentUrl' => $photoUrl,
                        'buttonLabel' => 'Choisir et cadrer la photo',
                    ])
                    @if($croppedPhotoData)
                        <div class="form-span-2" style="font-size:12px; color:#166534;">✓ Nouvelle photo cadrée prête — sera enregistrée avec le formulaire.</div>
                    @endif
                    <div class="form-span-2">
                        <label class="label" style="margin:0;"><input type="checkbox" wire:model="removePhoto"> Retirer la photo actuelle</label>
                    </div>
                    <div><label class="label">Notes</label><input class="input" wire:model="notes"></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Actif</label></div>
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button class="btn btn-secondary" wire:click="cancel" type="button">Annuler</button>
                    <button class="btn btn-primary" wire:click="save" type="button">Enregistrer</button>
                </div>
            </div>
        </div>
    @endif
</div>
