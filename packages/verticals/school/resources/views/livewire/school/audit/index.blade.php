<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    @php
        $tableLabels = [
            'school_students' => 'Élèves',
            'school_enrollments' => 'Inscriptions',
            'school_attendance_records' => 'Présences',
            'school_courses' => 'Cours',
            'school_timetable_slots' => 'Emploi du temps',
            'school_rooms' => 'Salles',
            'school_payments' => 'Paiements',
            'school_exams' => 'Examens',
            'school_exam_marks' => 'Notes',
            'school_fee_structures' => 'Frais',
            'academic_years' => 'Années',
            'school_result_publications' => 'Publications',
            'student_id_cards' => 'Cartes ID',
            'school_student_documents' => 'Pièces',
            'school_teachers' => 'Enseignants',
        ];
        $eventMeta = [
            'created' => ['label' => 'Créé', 'class' => 'badge-success'],
            'updated' => ['label' => 'Modifié', 'class' => 'badge-info'],
            'deleted' => ['label' => 'Supprimé', 'class' => 'badge-danger'],
            'status_changed' => ['label' => 'Statut', 'class' => 'badge-warning'],
        ];
    @endphp

    @if($loadError !== '')
        <section class="card app-table-card" style="padding:20px;">
            <p>{{ $loadError }}</p>
        </section>
    @elseif(! $hasTable)
        <section class="card app-table-card" style="padding:20px;">
            <p>La table <code>audit_logs</code> n’est pas encore migrée sur ce tenant.</p>
            <p style="margin:8px 0 0; font-size:13px; color:#64748b;">Sur le VPS : <code>php artisan tenant:migrate school</code></p>
        </section>
    @else
        <section class="card app-table-card">
            <div class="sch-filters" style="flex-wrap:wrap;">
                <select class="input" wire:model.live="filterTable" style="max-width:180px;">
                    <option value="">Toutes les entités</option>
                    @foreach($tables as $t)
                        <option value="{{ $t }}">{{ $tableLabels[$t] ?? $t }}</option>
                    @endforeach
                </select>
                <select class="input" wire:model.live="filterEvent" style="max-width:150px;">
                    <option value="">Tous les événements</option>
                    <option value="created">Créé</option>
                    <option value="updated">Modifié</option>
                    <option value="deleted">Supprimé</option>
                    <option value="status_changed">Changement statut</option>
                </select>
                <select class="input" wire:model.live="filterUser" style="max-width:160px;">
                    <option value="">Tous les utilisateurs</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <input type="date" class="input" wire:model.live="dateFrom" style="max-width:150px;">
                <input type="date" class="input" wire:model.live="dateTo" style="max-width:150px;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Entité</th>
                            <th>ID</th>
                            <th>Événement</th>
                            <th>Modifications</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $em = $eventMeta[$row['event']] ?? ['label' => $row['event'], 'class' => 'badge-secondary'];
                            @endphp
                            <tr>
                                <td>{{ $row['created'] }}</td>
                                <td>{{ $row['user'] }}</td>
                                <td>{{ $tableLabels[$row['type']] ?? $row['type'] }}</td>
                                <td>{{ $row['id'] }}</td>
                                <td><span class="badge {{ $em['class'] }}">{{ $em['label'] }}</span></td>
                                <td style="max-width:320px; font-size:11px; color:#475569;">
                                    @forelse($row['changes'] as $change)
                                        <div><strong>{{ $change['key'] }}</strong>: {{ $change['old'] }} → {{ $change['new'] }}</div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td>{{ $row['ip'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">Aucune entrée pour ces critères.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs && $logs->hasPages())
                <div style="margin:12px 16px 16px; display:flex; gap:8px; align-items:center;">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="previousPage" @disabled($logs->onFirstPage())>Précédent</button>
                    <span style="font-size:13px; color:#64748b;">Page {{ $logs->currentPage() }} / {{ $logs->lastPage() }}</span>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="nextPage" @disabled(! $logs->hasMorePages())>Suivant</button>
                </div>
            @endif
        </section>
    @endif
</div>
