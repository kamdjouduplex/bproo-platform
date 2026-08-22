<div class="page-body">
    @include('school::livewire.partials.crud-styles')
    <style>
        .sch-doc-plus {
            width: 28px; height: 28px; border-radius: 8px;
            border: 1px solid #cbd5e1; background: #fff; color: #0f766e;
            font-size: 18px; font-weight: 600; line-height: 1; cursor: pointer;
        }
        .sch-doc-plus:hover { background: #f0fdfa; }
        .sch-doc-panel {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows .28s ease;
        }
        .sch-doc-panel.is-open { grid-template-rows: 1fr; }
        .sch-doc-panel__body { overflow: hidden; }
        .sch-doc-expand > td { padding: 0; border-top: none; background: #f8fafc; }
        tbody.sch-doc-group.is-open tr.sch-doc-main td { background: #f0fdfa; }
        .sch-doc-miss { color: #b91c1c; font-weight: 600; font-size: 13px; }
        .sch-doc-ok { color: #047857; font-weight: 600; font-size: 13px; }
    </style>

    @if(! $tableReady)
        <section class="card app-table-card" style="padding:20px;">
            <p>La table des pièces n’est pas encore migrée sur ce tenant. Exécutez <code>php artisan tenant:migrate</code>.</p>
        </section>
    @else
        <section class="card app-table-card">
            <div class="sch-list-head">
                <h2 class="sch-list-head__title">Pièces</h2>
                <div class="sch-list-head__actions">
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.students.index', ['tenant' => $tenantCode]) }}">Élèves</a>
                </div>
            </div>

            <div class="sch-filters">
                <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Matricule, nom, prénom…">
                <select class="input" wire:model.live="classId" style="max-width:200px;">
                    <option value="">Toutes les classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
                <select class="input" wire:model.live="filterIncomplete" style="max-width:200px;">
                    <option value="">Tous les dossiers</option>
                    <option value="1">Dossiers incomplets</option>
                </select>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th style="width:44px;"></th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    @forelse($students as $s)
                        @php
                            $cov = $s->document_coverage ?? ['have' => 0, 'required' => 0, 'complete' => false, 'missing' => [], 'totalFiles' => 0];
                            $openByDefault = (int) $studentId === (int) $s->id ? 'true' : 'false';
                        @endphp
                        <tbody
                            class="sch-doc-group"
                            x-data="{ open: {{ $openByDefault }} }"
                            :class="open && 'is-open'"
                            wire:key="doc-group-{{ $s->id }}"
                        >
                            <tr class="sch-doc-main">
                                <td>
                                    <button type="button" class="sch-doc-plus" @click="open = !open" :title="open ? 'Replier' : 'Déplier'">
                                        <span x-text="open ? '−' : '+'"></span>
                                    </button>
                                </td>
                                <td>
                                    <strong>{{ $s->full_name }}</strong>
                                    <div style="font-size:12px; color:#64748b;">{{ $s->student_code ?: '—' }}</div>
                                </td>
                                <td>{{ $s->currentEnrollment?->schoolClass?->name ?? '—' }}</td>
                                <td>
                                    @if($cov['complete'])
                                        <span class="sch-doc-ok">Complet</span>
                                        <div style="font-size:12px; color:#94a3b8; margin-top:2px;">{{ $cov['totalFiles'] }} fichier(s)</div>
                                    @else
                                        <span class="sch-doc-miss">Documents manquants</span>
                                        <div style="font-size:12px; color:#94a3b8; margin-top:2px;">{{ $cov['have'] }}/{{ $cov['required'] }} obligatoires</div>
                                    @endif
                                </td>
                                <td class="sch-row-actions">
                                    @if($hasStudentShow)
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.students.show', ['tenant' => $tenantCode, 'id' => $s->id, 'tab' => 'pieces']) }}">Fiche</a>
                                    @endif
                                </td>
                            </tr>
                            <tr class="sch-doc-expand">
                                <td colspan="5">
                                    <div class="sch-doc-panel" :class="open && 'is-open'">
                                        <div class="sch-doc-panel__body">
                                            <div style="padding:4px 12px 14px;">
                                                @include('school::livewire.partials.student-documents', [
                                                    'listOnly' => true,
                                                    'studentDocuments' => $s->documents->sortByDesc('id')->values(),
                                                    'studentRow' => $s,
                                                    'canManageDocuments' => false,
                                                    'documentChecklist' => [],
                                                    'documentTypes' => [],
                                                ])
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="5">Aucun élève. {{ $filterIncomplete === '1' ? 'Tous les dossiers obligatoires sont complets, ou aucun élève n’est inscrit.' : 'Créez d’abord des fiches élèves.' }}</td></tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
            <div style="margin:12px 16px 16px;">{{ $students->links() }}</div>
        </section>
    @endif
</div>
