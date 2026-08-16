<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Élèves</h2>
            <div class="sch-list-head__actions">
                @if($canManage ?? true)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvel élève</button>
                @endif
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="ID, nom, parent, téléphone…">
            <select class="input" wire:model.live="filterGender" style="max-width:160px;">
                <option value="">Tous genres</option>
                @foreach($genders as $g)
                    <option value="{{ $g->value }}">{{ $g->label }}</option>
                @endforeach
            </select>
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
                        <th style="width:56px;"></th>
                        <th>Student ID</th>
                        <th>Élève</th>
                        <th>Parent</th>
                        <th>Téléphone</th>
                        <th>Profil</th>
                        <th>Actif</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $s)
                        @php
                            $pct = $s->profileCompletion()['percent'];
                            $thumb = $s->photoUrl($tenantCode);
                        @endphp
                        <tr>
                            <td>
                                @if($thumb)
                                    <img src="{{ $thumb }}" alt="" style="width:36px; height:36px; object-fit:cover; border-radius:999px; border:1px solid #e2e8f0;">
                                @else
                                    <div style="width:36px; height:36px; border-radius:999px; background:#e2e8f0; color:#64748b; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600;">
                                        {{ mb_strtoupper(mb_substr($s->first_name, 0, 1).mb_substr($s->last_name, 0, 1)) }}
                                    </div>
                                @endif
                            </td>
                            <td><strong>{{ $s->student_code }}</strong></td>
                            <td>{{ $s->first_name }} {{ $s->last_name }}</td>
                            <td>{{ $s->parent_full_name ?? '—' }}</td>
                            <td>{{ $s->parent_phone ?? '—' }}</td>
                            <td>{{ $pct }}%</td>
                            <td>
                                @if($s->is_active)
                                    <span class="badge badge-success">Oui</span>
                                @else
                                    <span class="badge badge-secondary">Non</span>
                                @endif
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.students.show', ['tenant' => $tenantCode, 'id' => $s->id]) }}">Voir</a>
                                    @if($canManage ?? true)
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.students.manage', ['tenant' => $tenantCode, 'id' => $s->id]) }}">Gérer</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Aucun élève.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $students->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">Nouvel élève</h3>
                        <p class="sch-modal__hint">Profil permanent — l’inscription se fait chaque année séparément.</p>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Student ID</label>
                            <input class="input" wire:model="studentCode" type="text" placeholder="SCH-2026-0001">
                            @error('studentCode') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Genre</label>
                            <select class="input" wire:model="gender">
                                <option value="">—</option>
                                @foreach ($genders as $opt)
                                    <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Prénom</label>
                            <input class="input" wire:model="firstName" type="text">
                            @error('firstName') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Nom</label>
                            <input class="input" wire:model="lastName" type="text">
                            @error('lastName') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Naissance</label>
                            <input class="input" wire:model="birthDate" type="date">
                        </div>
                        <div>
                            <label class="label">Parent / Tuteur</label>
                            <input class="input" wire:model="parentFullName" type="text">
                        </div>
                        <div>
                            <label class="label">Téléphone parent</label>
                            <input class="input" wire:model="parentPhone" type="text">
                        </div>
                        <div>
                            <label class="label">Email parent</label>
                            <input class="input" wire:model="parentEmail" type="email">
                            @error('parentEmail') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        @include('school::livewire.partials.student-photo-cropper', [
                            'wireMethod' => 'setCroppedPhoto',
                            'currentUrl' => $croppedPreview ?? null,
                            'buttonLabel' => 'Choisir et cadrer la photo',
                        ])
                        @if(!empty($croppedPreview))
                            <div class="form-span-2" style="font-size:12px; color:#166534;">✓ Photo cadrée prête — cliquez Enregistrer.</div>
                        @endif
                        <div>
                            <label class="label">Notes</label>
                            <input class="input" wire:model="notes" type="text">
                        </div>
                        <div class="form-span-2">
                            <label class="label" style="margin:0;">
                                <input type="checkbox" wire:model="isActive"> Actif
                            </label>
                        </div>
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                </div>
            </div>
        </div>
    @endif

</div>
