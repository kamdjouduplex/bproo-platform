<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">{{ $student->full_name }}</h2>
                <p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }} — profil {{ $completion['percent'] }}%</p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.students.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.students.show', ['tenant' => $tenantCode, 'id' => $student->id]) }}">Voir</a>
                    @if($canManage)<button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>@endif
                @elseif($canManage)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.students.manage', ['tenant' => $tenantCode, 'id' => $student->id]) }}">Gérer</a>
                @endif
            </div>
        </div>

        <div style="margin:0 16px 14px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;">
            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:6px;">
                <strong>Complétion du profil</strong>
                <span>{{ $completion['percent'] }}% ({{ $completion['filled'] }}/{{ $completion['total'] }})</span>
            </div>
            <div style="height:8px; background:#e2e8f0; border-radius:999px; overflow:hidden;">
                <div style="height:100%; width:{{ $completion['percent'] }}%; background:#2563eb;"></div>
            </div>
            @if(count($completion['missing']))
                <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Manquant : {{ implode(', ', $completion['missing']) }}</p>
            @endif
        </div>

        <div class="sch-info-grid">
            <div class="sch-info-item" style="grid-column:1 / -1;">
                <span class="sch-info-item__label">Photo de profil</span>
                <div class="sch-info-item__value">
                    @if($isManage && $canManage)
                        @include('school::livewire.partials.student-photo-cropper', [
                            'wireMethod' => 'saveCroppedProfilePhoto',
                            'currentUrl' => $photoUrl,
                            'buttonLabel' => 'Choisir et cadrer la photo',
                        ])
                        @if($photoUrl)
                            <div style="margin-top:10px;">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="clearProfilePhoto" wire:confirm="Retirer la photo de profil ?">
                                    Retirer la photo
                                </button>
                            </div>
                        @endif
                    @elseif($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $student->full_name }}" style="width:112px; height:140px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0;">
                    @else
                        <div style="width:112px; height:140px; border-radius:10px; border:1px dashed #cbd5e1; background:#f8fafc; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:12px; text-align:center; padding:8px;">Aucune photo</div>
                    @endif
                </div>
            </div>
            @foreach(['Student ID' => $student->student_code, 'Prénom' => $student->first_name, 'Nom' => $student->last_name, 'Genre' => $genderLabel ?? '—', 'Naissance' => $student->birth_date?->format('d/m/Y') ?? '—', 'Parent / Tuteur' => $student->parent_full_name ?? '—', 'Téléphone parent' => $student->parent_phone ?? '—', 'Email parent' => $student->parent_email ?? '—', 'Notes' => $student->notes ?? '—', 'Statut' => $student->is_active ? 'Actif' : 'Inactif', 'Créé le' => $student->created_at?->format('d/m/Y H:i') ?? '—', 'Mise à jour' => $student->updated_at?->format('d/m/Y H:i') ?? '—'] as $label => $value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
        </div>
        @if($isManage && $canManage)<div class="sch-actions-panel"><div class="sch-actions-panel__label">Actions</div><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button><button class="btn btn-secondary btn-sm" wire:click="{{ $student->is_active ? 'deactivate' : 'activate' }}">{{ $student->is_active ? 'Désactiver' : 'Activer' }}</button></div>@endif
    </section>

    <section class="card app-table-card" style="margin-top:16px;">
        <div class="sch-list-head"><h2 class="sch-list-head__title">Historique académique</h2></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Année</th><th>Classe</th><th>Section</th><th>Statut</th><th>Inscrit le</th></tr></thead>
                <tbody>
                @forelse($enrollments as $e)
                    <tr>
                        <td>{{ $e->academicYear?->name }}</td>
                        <td>{{ $e->schoolClass?->name ?? '—' }}</td>
                        <td>{{ $e->section ?: ($e->schoolClass?->section ?? '—') }}</td>
                        <td>{{ $e->status }}</td>
                        <td>{{ $e->created_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aucune inscription.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card app-table-card" style="margin-top:16px;">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Solde &amp; ledger</h2>
            <div class="sch-list-head__actions">
                <span class="badge {{ $balance < 0 ? 'badge-danger' : 'badge-success' }}">
                    Solde : {{ number_format($balance, 0, ',', ' ') }}
                </span>
            </div>
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
                        <td class="right">{{ $entry->entry_type === 'credit' ? '+' : '−' }}{{ number_format((float)$entry->amount, 0, ',', ' ') }}</td>
                        <td class="right">{{ number_format((float)$entry->balance_after, 0, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aucun mouvement. Les frais s’imputent à l’inscription ; les paiements validés créditent le solde.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card app-table-card" style="margin-top:16px;">
        <div class="sch-list-head"><h2 class="sch-list-head__title">Historique financier</h2></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Date</th><th>Année</th><th>Type</th><th class="right">Montant</th><th>Statut</th><th>Réf.</th></tr></thead>
                <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td>{{ $p->created_at?->format('d/m/Y') }}</td>
                        <td>{{ $p->academicYear?->name }}</td>
                        <td>{{ $p->payment_type }}</td>
                        <td class="right">{{ number_format((float)$p->amount, 0, ',', ' ') }} {{ $p->currency_code }}</td>
                        <td>{{ $p->status }}</td>
                        <td>{{ $p->reference ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucun paiement.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card app-table-card" style="margin-top:16px;">
        <div class="sch-list-head"><h2 class="sch-list-head__title">Résultats / examens</h2></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Examen</th><th>Matière</th><th class="right">Note</th><th>Validé</th></tr></thead>
                <tbody>
                @forelse($examMarks as $m)
                    <tr>
                        <td>{{ $m->exam?->title }}</td>
                        <td>{{ $m->exam?->subject?->name ?? '—' }}</td>
                        <td class="right">{{ $m->is_absent ? 'Absent' : number_format((float)$m->score, 2) }}</td>
                        <td>{{ $m->validated_at ? $m->validated_at->format('d/m/Y') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Aucune note.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel"><div class="sch-modal sch-modal--wide"><div class="sch-modal__head"><h3 class="sch-modal__title">Modifier l’élève</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
            <div class="sch-modal__body"><div class="form-grid">
                <div><label class="label">Student ID</label><input class="input" wire:model="studentCode">@error('studentCode') <span class="text-error">{{ $message }}</span> @enderror</div>
                <div><label class="label">Genre</label><select class="input" wire:model="gender"><option value="">—</option>@foreach($genders as $g)<option value="{{ $g->value }}">{{ $g->label }}</option>@endforeach</select></div>
                <div><label class="label">Prénom</label><input class="input" wire:model="firstName"></div><div><label class="label">Nom</label><input class="input" wire:model="lastName"></div>
                <div><label class="label">Naissance</label><input class="input" type="date" wire:model="birthDate"></div><div><label class="label">Parent / Tuteur</label><input class="input" wire:model="parentFullName"></div>
                <div><label class="label">Téléphone parent</label><input class="input" wire:model="parentPhone"></div><div><label class="label">Email parent</label><input class="input" type="email" wire:model="parentEmail"></div>
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
            </div></div><div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
        </div></div>
    @endif
</div>
