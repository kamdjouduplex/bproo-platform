<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Examens</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvel examen</button>
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Titre de l’épreuve…">
            <select class="input" wire:model.live="filterYearId" style="max-width:180px;">
                <option value="">Toutes années</option>
                @foreach($years as $y)
                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterClassId" style="max-width:160px;">
                <option value="">Toutes classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterSubjectId" style="max-width:160px;">
                <option value="">Toutes matières</option>
                @foreach($subjects as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterStatus" style="max-width:140px;">
                <option value="">Tous statuts</option>
                <option value="draft">Brouillon</option>
                <option value="open">Ouvert</option>
                <option value="closed">Clôturé</option>
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Épreuve</th>
                        <th>Année</th>
                        <th>Classe</th>
                        <th>Matière</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $exam)
                        <tr>
                            <td><strong>{{ $exam->title }}</strong></td>
                            <td>{{ $exam->academicYear?->name ?? '—' }}</td>
                            <td>{{ $exam->schoolClass?->name ?? '—' }}</td>
                            <td>{{ $exam->subject?->name ?? '—' }}</td>
                            <td>{{ $exam->exam_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                @if($exam->status === 'open')
                                    <span class="badge badge-success">Ouvert</span>
                                @elseif($exam->status === 'closed')
                                    <span class="badge badge-secondary">Clôturé</span>
                                @else
                                    <span class="badge badge-secondary">Brouillon</span>
                                @endif
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.exams.show', ['tenant' => $tenantCode, 'id' => $exam->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.exams.manage', ['tenant' => $tenantCode, 'id' => $exam->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucun examen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $exams->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div><h3 class="sch-modal__title">Nouvel examen</h3></div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div class="form-span-2">
                            <label class="label">Titre</label>
                            <input class="input" type="text" wire:model="title" placeholder="Devoir 1 — Trimestre 1">
                            @error('title') <span class="text-error">{{ $message }}</span> @enderror
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
                            <label class="label">Matière</label>
                            <select class="input" wire:model="subjectId">
                                <option value="">—</option>
                                @foreach($subjects as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            @error('subjectId') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Enseignant</label>
                            <select class="input" wire:model="teacherId">
                                <option value="">—</option>
                                @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Date</label>
                            <input class="input" type="date" wire:model="examDate">
                        </div>
                        <div>
                            <label class="label">Note max</label>
                            <input class="input" type="number" step="0.01" wire:model="maxScore">
                            @error('maxScore') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Coefficient</label>
                            <input class="input" type="number" step="0.01" wire:model="coefficient">
                            @error('coefficient') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Statut</label>
                            <select class="input" wire:model="status">
                                <option value="draft">Brouillon</option>
                                <option value="open">Ouvert</option>
                                <option value="closed">Clôturé</option>
                            </select>
                        </div>
                        <div class="form-span-2">
                            <label class="label">Notes</label>
                            <textarea class="input" rows="2" wire:model="notes"></textarea>
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
