<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Enseignants</h2>
            <div class="sch-list-head__actions">
                @if($canManage)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvel enseignant</button>
                @endif
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher ID, nom, téléphone, email…">
            <select class="input" wire:model.live="filterActive" style="max-width:160px;">
                <option value="">Tous les statuts</option>
                <option value="1">Actifs</option>
                <option value="0">Inactifs</option>
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>Matières</th>
                        <th>Dossier</th>
                        <th>Actif</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teachers as $t)
                        <tr>
                            <td><code>{{ $t->teacher_code ?? '—' }}</code></td>
                            <td>
                                <strong>{{ $t->full_name }}</strong>
                                @if($t->email)
                                    <div style="font-size:12px; color:#64748b;">{{ $t->email }}</div>
                                @endif
                            </td>
                            <td>{{ $t->phone ?? '—' }}</td>
                            <td>{{ $t->subjects->pluck('name')->join(', ') ?: '—' }}</td>
                            <td>
                                @if($t->isValidated())
                                    <span class="badge badge-success">Validé</span>
                                @else
                                    <span class="badge badge-secondary">À remplir</span>
                                @endif
                            </td>
                            <td>
                                @if($t->is_active)
                                    <span class="badge badge-success">Oui</span>
                                @else
                                    <span class="badge badge-secondary">Non</span>
                                @endif
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.teachers.show', ['tenant' => $tenantCode, 'id' => $t->id]) }}">Voir</a>
                                    @if($canManage)
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.teachers.manage', ['tenant' => $tenantCode, 'id' => $t->id]) }}">Gérer</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucun enseignant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $teachers->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">Nouvel enseignant</h3>
                        <p class="sch-modal__hint">Choisissez l’utilisateur : nom, téléphone et email sont repris. Complétez ensuite le dossier scolaire. Login, rôle et salaire se gèrent ailleurs.</p>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    @if($errors->any())
                        <div class="form-validation-summary" role="alert" style="margin:0 0 12px; padding:10px 12px; border:1px solid #fecaca; background:#fef2f2; border-radius:8px; color:#991b1b; font-size:13px;">
                            <p style="margin:0 0 6px; font-weight:600;">Veuillez corriger les erreurs suivantes :</p>
                            <ul style="margin:0; padding-left:18px;">
                                @foreach($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="form-grid">
                        @include('school::livewire.partials.teacher-form-fields', [
                            'showUserPicker' => true,
                            'showLock' => false,
                            'showActive' => true,
                            'userRequired' => true,
                            'photoUrl' => null,
                        ])
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Enregistrer</span>
                        <span wire:loading wire:target="save">Enregistrement…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
