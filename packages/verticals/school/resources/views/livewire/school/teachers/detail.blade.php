<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div style="display:flex; gap:14px; align-items:center;">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="" style="width:56px; height:70px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0;">
                @endif
                <div>
                    <h2 class="sch-detail-toolbar__title">{{ $teacher->full_name }}</h2>
                    <p class="sch-detail-toolbar__hint">
                        {{ $teacher->teacher_code ?? '—' }}
                        · {{ $teacher->isValidated() ? 'Dossier validé' : 'Dossier à remplir' }}
                        · {{ $teacher->is_active ? 'Actif' : 'Désactivé' }}
                    </p>
                </div>
            </div>
            <div class="sch-detail-toolbar__actions">
                @if($canViewList)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.teachers.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                @endif
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.teachers.show', ['tenant' => $tenantCode, 'id' => $teacher->id]) }}">Voir</a>
                    <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @elseif($canEdit)
                    <button class="btn btn-primary btn-sm" wire:click="edit">Remplir / valider</button>
                @elseif($isOwn && $teacher->isValidated())
                    <span class="badge badge-success">Validé — contactez l’ADM pour une correction</span>
                @endif
            </div>
        </div>

        @if($isOwn && $teacher->isValidated())
            <p class="sch-modal__hint" style="padding:0 20px 12px;">Ce dossier a été validé. En cas d’erreur, contactez l’administration.</p>
        @elseif($isOwn && ! $teacher->isValidated())
            <p class="sch-modal__hint" style="padding:0 20px 12px;">Remplissez une seule fois, puis validez. Après validation, vous ne pourrez plus modifier.</p>
        @endif

        <div class="sch-info-grid">
            @foreach([
                'ID' => $teacher->teacher_code ?? '—',
                'Nom' => $teacher->last_name ?: '—',
                'Prénom' => $teacher->first_name ?: '—',
                'Sexe' => $genderLabel,
                'Téléphone' => $teacher->phone ?? '—',
                'Email' => $teacher->email ?? '—',
                'Adresse' => $teacher->address ?? '—',
                'Niveau d’étude' => $levelLabel,
                'Diplôme' => trim($diplomaLabel.($teacher->diploma_label ? ' — '.$teacher->diploma_label : '')) ?: '—',
                'Étude en cours' => $teacher->studies_in_progress ?: '—',
                'Matières' => $teacher->subjects->pluck('name')->join(', ') ?: '—',
                'Section' => $sectionLabel,
                'Horaire' => $teacher->schedule_note ?? '—',
                'Rémunération' => $teacher->remuneration_amount !== null ? number_format((float) $teacher->remuneration_amount, 0, ',', ' ') : '—',
                'Compte' => $teacher->user_id ? 'Accès créé' : 'Pas d’accès',
                'Statut compte' => $teacher->is_active ? 'Actif' : 'Désactivé',
                'Dossier' => $teacher->isValidated() ? ('Validé le '.($teacher->validated_at?->format('d/m/Y H:i') ?? '—')) : 'À remplir',
            ] as $label => $value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
        </div>
        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions ADM</div>
                <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                <button class="btn btn-secondary btn-sm" wire:click="{{ $teacher->is_active ? 'deactivate' : 'activate' }}">
                    {{ $teacher->is_active ? 'Désactiver le compte' : 'Réactiver le compte' }}
                </button>
            </div>
        @endif
    </section>
    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">{{ $isManage ? 'Modifier l’enseignant' : 'Remplir mon dossier' }}</h3>
                        @if(! $isManage)
                            <p class="sch-modal__hint">Une fois enregistré, le dossier est verrouillé. Seule l’ADM pourra le corriger.</p>
                        @endif
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        @include('school::livewire.partials.teacher-form-fields', [
                            'showAccess' => $isManage && ! $teacher->user_id,
                            'showLock' => $isManage,
                            'showActive' => $isManage,
                            'photoUrl' => $photoUrl,
                        ])
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="save">{{ $isManage ? 'Enregistrer' : 'Valider le dossier' }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
