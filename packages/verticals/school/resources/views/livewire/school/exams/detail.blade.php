<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">{{ $exam->title }}</h2>
                <p class="sch-detail-toolbar__hint">
                    {{ $exam->kindLabel() ?: 'Épreuve' }}{{ $exam->periodLabel() ? ' · '.$exam->periodLabel() : '' }}
                    · {{ $isManage ? 'Page de gestion' : 'Page de consultation' }}
                    · <span class="badge {{ $exam->status === 'open' ? 'badge-success' : 'badge-secondary' }}">{{ $statusLabels[$exam->status] ?? $exam->status }}</span>
                </p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.exams.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.exams.show', ['tenant' => $tenantCode, 'id' => $exam->id]) }}">Voir</a>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.exams.manage', ['tenant' => $tenantCode, 'id' => $exam->id]) }}">Gérer</a>
                @endif
            </div>
        </div>

        <div class="sch-info-grid">
            <div class="sch-info-item">
                <span class="sch-info-item__label">Titre</span>
                <div class="sch-info-item__value">{{ $exam->title }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Type</span>
                <div class="sch-info-item__value">{{ $exam->kindLabel() ?: '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Période</span>
                <div class="sch-info-item__value">{{ $exam->periodLabel() ?: '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Année</span>
                <div class="sch-info-item__value">{{ $exam->academicYear?->name ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Classe</span>
                <div class="sch-info-item__value">{{ $exam->schoolClass?->name ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Matière</span>
                <div class="sch-info-item__value">{{ $exam->subject?->name ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Enseignant</span>
                <div class="sch-info-item__value">{{ $exam->teacher?->full_name ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Date</span>
                <div class="sch-info-item__value">{{ $exam->exam_date?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Note max</span>
                <div class="sch-info-item__value">{{ rtrim(rtrim(number_format((float) $exam->max_score, 2, ',', ' '), '0'), ',') }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Coefficient</span>
                <div class="sch-info-item__value">{{ rtrim(rtrim(number_format((float) $exam->coefficient, 2, ',', ' '), '0'), ',') }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Statut</span>
                <div class="sch-info-item__value">{{ $statusLabels[$exam->status] ?? $exam->status }}</div>
            </div>
            <div class="sch-info-item sch-info-item--wide">
                <span class="sch-info-item__label">Notes / consignes</span>
                <div class="sch-info-item__value">{{ $exam->notes ?? '—' }}</div>
            </div>
        </div>

        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions examen</div>
                <button type="button" class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="syncRoster">Charger les élèves inscrits</button>
                @if($exam->status !== 'open')
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="openExam">Ouvrir la saisie</button>
                @endif
                @if($exam->status !== 'closed')
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeExam" wire:confirm="Clôturer cet examen ?">Clôturer</button>
                @endif
                <button type="button" class="btn btn-secondary btn-sm" wire:click="validateMarks">Valider les notes</button>
            </div>
        @endif
    </section>

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h3 class="sch-detail-toolbar__title" style="font-size:1rem;">Feuille de notes</h3>
                <p class="sch-detail-toolbar__hint">Barème : 0 – {{ rtrim(rtrim(number_format((float) $exam->max_score, 2, ',', ' '), '0'), ',') }}</p>
            </div>
            @if($isManage && $exam->status !== 'closed')
                <div class="sch-detail-toolbar__actions">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="saveMarks">Enregistrer les notes</button>
                </div>
            @endif
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th style="width:120px;">Note</th>
                        <th style="width:90px;">Absent</th>
                        <th>Remarque</th>
                        <th style="width:140px;">Validée</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($markRows as $studentId => $row)
                        <tr wire:key="mark-{{ $studentId }}">
                            <td><strong>{{ $row['label'] }}</strong></td>
                            <td>
                                @if($isManage && $exam->status !== 'closed')
                                    <input class="input" type="number" step="0.01" min="0"
                                           wire:model="markRows.{{ $studentId }}.score"
                                           @disabled($row['is_absent'])>
                                    @error('markRows.'.$studentId.'.score') <span class="text-error">{{ $message }}</span> @enderror
                                @else
                                    {{ $row['is_absent'] ? '—' : ($row['score'] !== '' ? $row['score'] : '—') }}
                                @endif
                            </td>
                            <td>
                                @if($isManage && $exam->status !== 'closed')
                                    <input type="checkbox" wire:model.live="markRows.{{ $studentId }}.is_absent">
                                @else
                                    {{ $row['is_absent'] ? 'Oui' : 'Non' }}
                                @endif
                            </td>
                            <td>
                                @if($isManage && $exam->status !== 'closed')
                                    <input class="input" type="text" wire:model="markRows.{{ $studentId }}.remarks">
                                @else
                                    {{ $row['remarks'] !== '' ? $row['remarks'] : '—' }}
                                @endif
                            </td>
                            <td>{{ $row['validated_at'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                Aucune note.
                                @if($isManage)
                                    Utilisez « Charger les élèves inscrits » pour préparer la feuille.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div><h3 class="sch-modal__title">Modifier l’examen</h3></div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    @include('school::livewire.partials.exam-form-fields')
                </div>
                <div class="sch-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                </div>
            </div>
        </div>
    @endif
</div>
