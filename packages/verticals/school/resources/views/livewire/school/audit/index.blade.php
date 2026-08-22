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
        ];
        $eventMeta = [
            'created' => ['label' => 'Créé', 'class' => 'badge-success'],
            'updated' => ['label' => 'Modifié', 'class' => 'badge-info'],
            'deleted' => ['label' => 'Supprimé', 'class' => 'badge-danger'],
            'status_changed' => ['label' => 'Statut', 'class' => 'badge-warning'],
        ];
    @endphp

    @if(! $hasTable)
        <section class="card app-table-card" style="padding:20px;">
            <p>La table <code>audit_logs</code> n’est pas encore migrée sur ce tenant.</p>
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
                        @forelse($logs as $log)
                            @php
                                $em = $eventMeta[$log->event] ?? ['label' => $log->event, 'class' => 'badge-secondary'];
                                $old = is_string($log->old_values) ? json_decode($log->old_values, true) : (array) $log->old_values;
                                $new = is_string($log->new_values) ? json_decode($log->new_values, true) : (array) $log->new_values;
                                $old = is_array($old) ? $old : [];
                                $new = is_array($new) ? $new : [];
                            @endphp
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->user_id ? ($userMap[$log->user_id] ?? '#'.$log->user_id) : '—' }}</td>
                                <td>{{ $tableLabels[$log->auditable_type] ?? $log->auditable_type }}</td>
                                <td>{{ $log->auditable_id }}</td>
                                <td><span class="badge {{ $em['class'] }}">{{ $em['label'] }}</span></td>
                                <td style="max-width:320px; font-size:11px; color:#475569;">
                                    @if($old || $new)
                                        @foreach(array_unique(array_merge(array_keys($old), array_keys($new))) as $key)
                                            <div><strong>{{ $key }}</strong>:
                                                {{ is_scalar($old[$key] ?? null) || $old[$key] === null ? ($old[$key] ?? '∅') : json_encode($old[$key]) }}
                                                →
                                                {{ is_scalar($new[$key] ?? null) || $new[$key] === null ? ($new[$key] ?? '∅') : json_encode($new[$key]) }}
                                            </div>
                                        @endforeach
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">Aucune entrée pour ces critères.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs)
                <div style="margin:12px 16px 16px;">{{ $logs->links() }}</div>
            @endif
        </section>
    @endif
</div>
