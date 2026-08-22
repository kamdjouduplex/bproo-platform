<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <div>
                <h2 class="sch-list-head__title">Cours</h2>
                <p class="sch-modal__hint" style="margin:4px 0 0;">Attribuez une matière et un enseignant à chaque classe, puis placez-les dans l’emploi du temps.</p>
            </div>
            <div class="sch-list-head__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.timetable.index', array_filter(['tenant' => $tenantCode, 'class' => $filterClassId])) }}">Emploi du temps</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.rooms.index', ['tenant' => $tenantCode]) }}">Salles</a>
                @if($canManage)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouveau cours</button>
                @endif
            </div>
        </div>

        <div class="sch-filters" style="flex-wrap:wrap;">
            <select class="input" wire:model.live="yearId" style="max-width:200px;">
                @foreach($years as $y)
                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterClassId" style="max-width:180px;">
                <option value="">Toutes classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterTeacherId" style="max-width:220px;">
                <option value="">Tous les enseignants</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                @endforeach
            </select>
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Matière, enseignant, classe…">
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Classe</th>
                        <th>Matière</th>
                        <th>Enseignant</th>
                        <th>Séances / sem.</th>
                        <th>Statut</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                        <tr>
                            <td><strong>{{ $course->schoolClass?->name }}</strong></td>
                            <td>
                                <span style="display:inline-block;width:10px;height:10px;border-radius:999px;background:{{ $course->displayColor() }};margin-right:6px;vertical-align:middle;"></span>
                                {{ $course->subject?->name }}
                            </td>
                            <td>{{ $course->teacher?->full_name }}</td>
                            <td>{{ $course->slots->count() }}</td>
                            <td>
                                <span class="badge {{ $course->is_active ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $course->is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.timetable.index', ['tenant' => $tenantCode, 'class' => $course->class_id]) }}">EDT</a>
                                    @if($canManage)
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $course->id }})">Modifier</button>
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $course->id }})" wire:confirm="Retirer ce cours et ses créneaux ?">Retirer</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucun cours. Créez d’abord les attributions matière / enseignant / classe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $courses->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">{{ $editingId ? 'Modifier le cours' : 'Nouveau cours' }}</h3>
                        <p class="sch-modal__hint">Une matière = un enseignant, pour une classe et une année.</p>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Classe</label>
                            <select class="input" wire:model="classId">
                                <option value="">—</option>
                                @foreach($classes as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('classId') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Matière</label>
                            <select class="input" wire:model="subjectId">
                                <option value="">—</option>
                                @foreach($subjects as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            @error('subjectId') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-span-2">
                            <label class="label">Enseignant</label>
                            <select class="input" wire:model="teacherId">
                                <option value="">—</option>
                                @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                                @endforeach
                            </select>
                            @error('teacherId') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-span-2">
                            <label class="label" style="margin:0;"><input type="checkbox" wire:model="isActive"> Actif</label>
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
