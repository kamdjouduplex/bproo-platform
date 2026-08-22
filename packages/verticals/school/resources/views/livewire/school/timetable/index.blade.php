<div class="page-body sch-tt">
    @include('school::livewire.partials.crud-styles')
    <style>
        .sch-tt { display: flex; flex-direction: column; gap: 16px; }
        .sch-tt .app-table-card { margin-top: 0; }
        .sch-tt-legend { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 16px 14px; }
        .sch-tt-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;
            background: #f8fafc; border: 1px solid #e2e8f0; color: #334155;
        }
        .sch-tt-dot { width: 10px; height: 10px; border-radius: 999px; }
        .sch-tt-grid-wrap { overflow: auto; padding: 0 12px 16px; }
        .sch-tt-grid {
            min-width: 860px;
            display: grid;
            grid-template-columns: 92px repeat(6, minmax(120px, 1fr));
            gap: 4px;
        }
        .sch-tt-head, .sch-tt-time {
            font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
            color: #64748b; padding: 8px 6px; text-align: center;
        }
        .sch-tt-time { text-align: right; color: #94a3b8; font-variant-numeric: tabular-nums; text-transform: none; letter-spacing: 0; }
        .sch-tt-cell {
            min-height: 64px; border: 1px dashed #e2e8f0; border-radius: 10px;
            background: #fafbfc; cursor: pointer; padding: 4px;
        }
        .sch-tt-cell:hover { border-color: #3fa796; background: #f0fdfa; }
        .sch-tt-block {
            height: 100%; min-height: 56px; border-radius: 8px; padding: 6px 8px; color: #fff;
            font-size: 12px; line-height: 1.25; box-shadow: 0 4px 10px rgba(15,23,42,.12);
        }
        .sch-tt-block strong { display: block; font-size: 13px; }
        .sch-tt-break {
            min-height: 28px; border: none; border-radius: 8px;
            background: #fff7ed; color: #9a3412; cursor: default;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
        }
        .sch-tt-time.is-break { color: #c2410c; }
    </style>

    <section class="card app-table-card">
        <div class="sch-list-head">
            <div>
                <h2 class="sch-list-head__title">{{ $pageTitle }}</h2>
                <p class="sch-modal__hint" style="margin:4px 0 0;">{{ $slotsCount }} séance(s) cette semaine. Cliquez une case vide pour ajouter.</p>
            </div>
            <div class="sch-list-head__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.courses.index', ['tenant' => $tenantCode]) }}">Liste des cours</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.rooms.index', ['tenant' => $tenantCode]) }}">Salles</a>
                <a class="btn btn-secondary btn-sm" href="{{ $printUrl }}" target="_blank">Imprimer</a>
            </div>
        </div>
        <div class="sch-filters" style="flex-wrap:wrap;">
            <select class="input" wire:model.live="yearId" style="max-width:200px;">
                @foreach($years as $y)
                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="viewMode" style="max-width:160px;">
                <option value="class">Par classe</option>
                <option value="teacher">Par enseignant</option>
            </select>
            @if($viewMode === 'teacher')
                <select class="input" wire:model.live="teacherId" style="max-width:240px;">
                    <option value="">Choisir l’enseignant</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                    @endforeach
                </select>
            @else
                <select class="input" wire:model.live="classId" style="max-width:200px;">
                    <option value="">Choisir la classe</option>
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        @if($courses->isNotEmpty())
            <div class="sch-tt-legend">
                @foreach($courses as $course)
                    <span class="sch-tt-pill">
                        <span class="sch-tt-dot" style="background:{{ $course->displayColor() }}"></span>
                        {{ $course->subject?->name }}
                        @if($viewMode === 'class')
                            · {{ $course->teacher?->full_name }}
                        @else
                            · {{ $course->schoolClass?->name }}
                        @endif
                    </span>
                @endforeach
            </div>
        @endif
        @if(($viewMode === 'teacher' && $teacherId === '') || ($viewMode !== 'teacher' && $classId === ''))
            <p class="sch-modal__hint" style="padding:0 16px 16px;">Choisissez {{ $viewMode === 'teacher' ? 'un enseignant' : 'une classe' }} pour afficher la grille, puis cliquez une case pour placer un cours.</p>
        @endif
        <div class="sch-tt-grid-wrap">
            <div class="sch-tt-grid">
                <div></div>
                @foreach($weekdays as $day)
                    <div class="sch-tt-head">{{ $day }}</div>
                @endforeach
                @foreach($periods as $period)
                    @php $isBreak = ($period['type'] ?? 'lesson') === 'break'; @endphp
                    <div class="sch-tt-time {{ $isBreak ? 'is-break' : '' }}">{{ $period['start'] }}<br><span style="font-weight:500;">{{ $period['end'] }}</span></div>
                    @foreach($weekdays as $dayNum => $dayLabel)
                        @if($isBreak)
                            <div class="sch-tt-break" wire:key="break-{{ $dayNum }}-{{ $period['start'] }}">{{ $period['label'] }}</div>
                        @else
                            @php $cellSlots = $grid[$period['start']][$dayNum] ?? []; @endphp
                            <div class="sch-tt-cell" wire:key="cell-{{ $dayNum }}-{{ $period['start'] }}" wire:click="openCell({{ $dayNum }}, '{{ $period['start'] }}', '{{ $period['end'] }}', {{ isset($cellSlots[0]) ? (int) $cellSlots[0]->id : 0 }})">
                                @foreach($cellSlots as $slot)
                                    <div class="sch-tt-block" style="background:{{ $slot->course?->displayColor() ?? '#3fa796' }}">
                                        <strong>{{ $slot->course?->subject?->name }}</strong>
                                        <span>
                                            @if($viewMode === 'teacher')
                                                {{ $slot->course?->schoolClass?->name }}
                                            @else
                                                {{ $slot->course?->teacher?->full_name }}
                                            @endif
                                        </span>
                                        @if($slot->roomLabel())
                                            <span>{{ $slot->roomLabel() }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>

    @if($showSlotForm)
        <div class="sch-modal-backdrop" wire:click.self="closeSlotForm">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">{{ $editingSlotId ? 'Modifier le créneau' : 'Placer un cours' }}</h3>
                        <p class="sch-modal__hint">{{ \School\Support\SchoolTimetable::weekdayLabel($weekday) }} · {{ $startTime }} – {{ $endTime }}</p>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="closeSlotForm">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Jour</label>
                            <select class="input" wire:model="weekday">
                                @foreach($weekdays as $num => $label)
                                    <option value="{{ $num }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Salle</label>
                            <select class="input" wire:model="roomId">
                                <option value="">— Aucune —</option>
                                @foreach($rooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->displayLabel() }}</option>
                                @endforeach
                            </select>
                            <p class="sch-modal__hint" style="margin:6px 0 0;">
                                <a href="{{ route('tenant.school.rooms.index', ['tenant' => $tenantCode]) }}">Gérer les salles</a>
                            </p>
                        </div>
                        <div>
                            <label class="label">Début</label>
                            <input class="input" type="time" wire:model="startTime">
                            @error('startTime') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Fin</label>
                            <input class="input" type="time" wire:model="endTime">
                            @error('endTime') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-span-2">
                            <label class="label">Cours existant</label>
                            <select class="input" wire:model.live="courseId">
                                <option value="">— Nouveau ci-dessous —</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}">{{ $course->label() }}{{ $course->schoolClass && $viewMode === 'teacher' ? ' ('.$course->schoolClass->name.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($viewMode === 'class')
                            <div>
                                <label class="label">Ou nouvelle matière</label>
                                <select class="input" wire:model="newSubjectId">
                                    <option value="">—</option>
                                    @foreach($subjects as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label">Enseignant</label>
                                <select class="input" wire:model="newTeacherId">
                                    <option value="">—</option>
                                    @foreach($teachers as $t)
                                        <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="sch-modal__foot">
                    @if($editingSlotId && $canManage)
                        <button type="button" class="btn btn-secondary" wire:click="deleteSlot({{ $editingSlotId }})" wire:confirm="Retirer ce créneau ?">Supprimer</button>
                    @endif
                    <div style="flex:1;"></div>
                    <button type="button" class="btn btn-secondary" wire:click="closeSlotForm">Annuler</button>
                    @if($canManage)
                        <button type="button" class="btn btn-primary" wire:click="saveSlot">Enregistrer</button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
