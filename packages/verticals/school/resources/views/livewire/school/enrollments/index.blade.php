<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Inscriptions</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle inscription</button>
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Élève (ID ou nom)…">
            <select class="input" wire:model.live="filterYearId" style="max-width:200px;">
                <option value="">Toutes années</option>
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
            <select class="input" wire:model.live="filterStatus" style="max-width:160px;">
                <option value="">Tous statuts</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label }}</option>
                @endforeach
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Année</th>
                        <th>Classe</th>
                        <th>Section</th>
                        <th>Statut</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($enrollments as $e)
                        <tr>
                            <td>
                                <strong>{{ $e->student?->student_code }}</strong>
                                — {{ $e->student?->first_name }} {{ $e->student?->last_name }}
                            </td>
                            <td>{{ $e->academicYear?->name ?? '—' }}</td>
                            <td>{{ $e->schoolClass?->name ?? '—' }}</td>
                            <td>{{ $e->section ?? '—' }}</td>
                            <td>{{ $statusLabels[$e->status] ?? $e->status }}</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.enrollments.show', ['tenant' => $tenantCode, 'id' => $e->id]) }}">Voir</a>
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.enrollments.print', ['tenant' => $tenantCode, 'enrollment' => $e->id]) }}" target="_blank">Fiche</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.enrollments.manage', ['tenant' => $tenantCode, 'id' => $e->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucune inscription.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $enrollments->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">Nouvelle inscription</h3>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div class="form-span-2">
                            @include('school::livewire.partials.searchable-student')
                        </div>
                        <div>
                            <label class="label">Année académique</label>
                            <select class="input" wire:model="academicYearId">
                                <option value="">—</option>
                                @foreach($years as $y)
                                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                                @endforeach
                            </select>
                            @error('academicYearId') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
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
                            <label class="label">Section</label>
                            <select class="input" wire:model="section">
                                <option value="">—</option>
                                @foreach($sections as $s)
                                    <option value="{{ $s->value }}">{{ $s->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Statut</label>
                            <select class="input" wire:model="status">
                                @foreach($statuses as $st)
                                    <option value="{{ $st->value }}">{{ $st->label }}</option>
                                @endforeach
                            </select>
                            @error('status') <span class="text-error">{{ $message }}</span> @enderror
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
