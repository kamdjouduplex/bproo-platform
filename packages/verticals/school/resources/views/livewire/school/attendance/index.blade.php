<div class="page-body">
    @include('school::livewire.partials.crud-styles')
    <style>
        .sch-att-chip {
            display: inline-flex; flex-direction: column; gap: 2px;
            min-width: 150px; max-width: 240px; text-align: left;
            padding: 8px 12px; border-radius: 10px; border: 1px solid #e2e8f0;
            background: #fff; cursor: pointer; color: inherit;
        }
        .sch-att-chip.is-on { border-color: #3fa796; background: #f0fdfa; box-shadow: 0 0 0 2px rgba(63,167,150,.18); }
        .sch-att-chip strong { font-size: 13px; }
        .sch-att-chip span { font-size: 11px; color: #64748b; }
        .sch-att-dot { width: 8px; height: 8px; border-radius: 999px; display: inline-block; margin-right: 6px; }
    </style>

    <section class="card app-table-card" style="margin-bottom:14px;">
        <div class="sch-list-head">
            <div>
                <h2 class="sch-list-head__title">Appel par cours</h2>
                <p class="sch-modal__hint" style="margin:4px 0 0;">{{ $weekdayLabel }} · {{ $sessionTitle }}</p>
            </div>
            <div class="sch-list-head__actions">
                <a class="btn btn-secondary btn-sm" href="{{ $printUrl }}" target="_blank">Imprimer la feuille</a>
                @if($canManage)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="save">Enregistrer l’appel</button>
                @endif
            </div>
        </div>
        <div class="sch-filters" style="flex-wrap:wrap; align-items:center;">
            <select class="input" wire:model.live="yearId" style="max-width:200px;">
                @foreach($years as $y)
                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="classId" style="max-width:180px;">
                <option value="">Choisir une classe</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="shiftDate(-1)">←</button>
            <input class="input" type="date" wire:model.live="attendanceDate" style="max-width:170px;">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="shiftDate(1)">→</button>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="goToday">Aujourd’hui</button>
        </div>

        <div style="padding:0 16px 14px;">
            @if($isSunday)
                <p class="sch-modal__hint">Dimanche : aucun cours à l’emploi du temps. L’appel général de la classe reste disponible.</p>
            @elseif($sessions->isEmpty())
                <p class="sch-modal__hint">
                    Aucune séance ce jour pour cette classe.
                    @if($timetableUrl)
                        <a href="{{ $timetableUrl }}">Ouvrir l’emploi du temps</a> pour programmer les cours,
                    @endif
                    ou faites l’appel général ci-dessous.
                </p>
            @else
                <p class="sch-modal__hint" style="margin-bottom:8px;">Séances du jour — cliquez pour faire l’appel du cours.</p>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    @foreach($sessions as $slot)
                        <button type="button" class="sch-att-chip {{ (string) $slot->id === $slotId ? 'is-on' : '' }}" wire:click="selectSession('{{ $slot->id }}')">
                            <strong>
                                <span class="sch-att-dot" style="background:{{ $slot->course?->displayColor() ?? '#3fa796' }}"></span>
                                {{ $slot->course?->subject?->name ?? 'Cours' }}
                            </strong>
                            <span>{{ $slot->startHm() }} – {{ $slot->endHm() }} · {{ $slot->course?->teacher?->full_name }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:10px; padding:0 16px 14px;">
            <div style="padding:10px 14px; border:1px solid #bbf7d0; background:#f0fdf4; border-radius:8px; min-width:110px;">
                <div style="font-size:11px; color:#166534;">Présents</div>
                <div style="font-size:20px; font-weight:700;">{{ $counts['present'] }}</div>
            </div>
            <div style="padding:10px 14px; border:1px solid #fecaca; background:#fef2f2; border-radius:8px; min-width:110px;">
                <div style="font-size:11px; color:#991b1b;">Absents</div>
                <div style="font-size:20px; font-weight:700;">{{ $counts['absent'] }}</div>
            </div>
            <div style="padding:10px 14px; border:1px solid #fde68a; background:#fffbeb; border-radius:8px; min-width:110px;">
                <div style="font-size:11px; color:#92400e;">Retards</div>
                <div style="font-size:20px; font-weight:700;">{{ $counts['late'] }}</div>
            </div>
            <div style="padding:10px 14px; border:1px solid #e2e8f0; background:#f8fafc; border-radius:8px; min-width:110px;">
                <div style="font-size:11px; color:#475569;">Effectif</div>
                <div style="font-size:20px; font-weight:700;">{{ $counts['total'] }}</div>
            </div>
        </div>
    </section>

    <section class="card app-table-card">
        @if($canManage && $counts['total'] > 0)
            <div style="display:flex; gap:8px; flex-wrap:wrap; padding:12px 16px 0;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="markAll('present')">Tous présents</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="markAll('absent')">Tous absents</button>
            </div>
        @endif
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Élève</th>
                        <th>Statut</th>
                        <th>Remarque</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roster as $e)
                        @php $sid = (string) $e->student_id; @endphp
                        <tr>
                            <td><strong>{{ $e->student?->student_code }}</strong></td>
                            <td>{{ $e->student?->full_name }}</td>
                            <td>
                                <select class="input" wire:model="marks.{{ $sid }}" @disabled(! $canManage) style="min-width:140px;">
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="input" type="text" wire:model="remarks.{{ $sid }}" @disabled(! $canManage) placeholder="Optionnel">
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Aucun élève inscrit dans cette classe pour l’année choisie.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($canManage && $counts['total'] > 0)
            <div style="padding:12px 16px 16px;">
                <button type="button" class="btn btn-primary" wire:click="save">Enregistrer l’appel</button>
            </div>
        @endif
    </section>
</div>
