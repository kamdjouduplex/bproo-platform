@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;

    $colConfig = [
        'todo' => [
            'label'      => __('À faire'),
            'headerBg'   => 'bg-slate-100',
            'titleColor' => 'text-slate-700',
            'countBg'    => 'bg-slate-200 text-slate-600',
            'addHover'   => 'hover:border-slate-300 hover:text-slate-700',
        ],
        'in_progress' => [
            'label'      => __('En cours'),
            'headerBg'   => 'bg-blue-50',
            'titleColor' => 'text-blue-700',
            'countBg'    => 'bg-blue-100 text-blue-700',
            'addHover'   => 'hover:border-blue-300 hover:text-blue-700',
        ],
        'done' => [
            'label'      => __('Terminé'),
            'headerBg'   => 'bg-emerald-50',
            'titleColor' => 'text-emerald-700',
            'countBg'    => 'bg-emerald-100 text-emerald-700',
            'addHover'   => 'hover:border-emerald-300 hover:text-emerald-700',
        ],
    ];
@endphp

<div>

    {{-- ── 1. Project selector ──────────────────────────────────────────────── --}}
    <div class="card mb-5">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[280px]">
                <label class="field-label text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    {{ __('Projet') }}
                </label>
                <select class="input text-[14px] font-medium" wire:model.live="projectId">
                    <option value="">— {{ __('Sélectionner un projet pour commencer') }} —</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} – {{ \Illuminate\Support\Str::limit($p->title, 55) }}</option>
                    @endforeach
                </select>
            </div>
            @if($currentProject)
            <div class="flex items-center gap-2 pb-0.5">
                @php
                    $pStatusBg = match($currentProject->status) {
                        'in_progress' => 'bg-blue-100 text-blue-700',
                        'planned'     => 'bg-slate-100 text-slate-600',
                        'on_hold'     => 'bg-amber-100 text-amber-700',
                        default       => 'bg-slate-100 text-slate-500',
                    };
                    $pStatusLabel = match($currentProject->status) {
                        'in_progress' => __('En cours'),
                        'planned'     => __('Planifié'),
                        'on_hold'     => __('En attente'),
                        default       => $currentProject->status,
                    };
                @endphp
                <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $pStatusBg }}">{{ $pStatusLabel }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── 2. Stats bar (once project selected) ───────────────────────────── --}}
    @if($currentProject)
    <div class="flex flex-wrap items-stretch gap-3 mb-5">

        {{-- Task counters --}}
        <div class="flex flex-1 min-w-0 bg-white border border-slate-200 rounded-xl divide-x divide-slate-100 overflow-hidden">
            <div class="flex flex-col items-center justify-center px-5 py-3 min-w-[70px]">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">{{ __('Total') }}</span>
                <span class="text-2xl font-bold text-slate-800 leading-tight">{{ $stats['total'] }}</span>
                <span class="text-[10px] text-slate-400">{{ __('tâches') }}</span>
            </div>
            <div class="flex flex-col items-center justify-center px-5 py-3 min-w-[70px]">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">{{ __('À faire') }}</span>
                <span class="text-2xl font-bold text-slate-600 leading-tight">{{ $stats['todo'] }}</span>
                <span class="text-[10px] text-slate-400">&nbsp;</span>
            </div>
            <div class="flex flex-col items-center justify-center px-5 py-3 min-w-[70px]">
                <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-0.5">{{ __('En cours') }}</span>
                <span class="text-2xl font-bold text-blue-600 leading-tight">{{ $stats['in_progress'] }}</span>
                <span class="text-[10px] text-slate-400">&nbsp;</span>
            </div>
            <div class="flex flex-col items-center justify-center px-5 py-3 min-w-[70px]">
                <span class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider mb-0.5">{{ __('Terminées') }}</span>
                <span class="text-2xl font-bold text-emerald-600 leading-tight">{{ $stats['done'] }}</span>
                <span class="text-[10px] text-slate-400">&nbsp;</span>
            </div>
            <div class="flex flex-col items-center justify-center px-5 py-3 min-w-[70px]">
                <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider mb-0.5">{{ __('Validées') }}</span>
                <span class="text-2xl font-bold text-indigo-600 leading-tight">{{ $stats['validated'] }}</span>
                <span class="text-[10px] text-slate-400">&nbsp;</span>
            </div>
        </div>

        {{-- Project advancement (from site reports) --}}
        <div class="bg-white border border-slate-200 rounded-xl px-5 py-3 flex flex-col justify-center min-w-[200px]">
            @php $progress = (float) $currentProject->progress_percent; @endphp
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Avancement projet') }}</span>
                <span class="text-[18px] font-bold {{ $progress >= 100 ? 'text-emerald-600' : 'text-indigo-600' }}">
                    {{ number_format($progress, 0) }}%
                </span>
            </div>
            <div class="relative bg-slate-100 rounded-full h-2.5 overflow-hidden">
                <div class="absolute inset-y-0 left-0 rounded-full transition-[width] duration-500
                            {{ $progress >= 100 ? 'bg-emerald-500' : 'bg-indigo-500' }}"
                     style="width: {{ min($progress, 100) }}%">
                </div>
            </div>
            <span class="text-[10px] text-slate-400 mt-1.5">{{ __('Source : dernier rapport terrain') }}</span>
        </div>
    </div>

    {{-- ── 3. Tabs ──────────────────────────────────────────────────────────── --}}
    <div class="flex gap-1 border-b border-slate-200 mb-5">
        <button type="button"
                wire:click="$set('activeTab', 'kanban')"
                class="module-tab {{ $activeTab === 'kanban' ? 'module-tab--active' : '' }}">
            {{ __('Kanban') }}
        </button>
        <button type="button"
                wire:click="$set('activeTab', 'rapports')"
                class="module-tab {{ $activeTab === 'rapports' ? 'module-tab--active' : '' }}">
            {{ __('Rapports terrain') }}
        </button>
    </div>

    {{-- ── 4a. KANBAN TAB ───────────────────────────────────────────────────── --}}
    @if($activeTab === 'kanban')

    {{-- Period controls + add button --}}
    <div class="flex flex-wrap items-end gap-3 mb-5">
        <div>
            <label class="field-label">{{ __('Vue') }}</label>
            <div class="flex rounded-lg border border-slate-200 overflow-hidden">
                <button type="button"
                        wire:click="$set('periodType', 'day')"
                        class="px-3 py-[7px] text-[12px] font-medium transition-colors
                               {{ $periodType === 'day' ? 'bg-blue-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50' }}">
                    {{ __('Jour') }}
                </button>
                <button type="button"
                        wire:click="$set('periodType', 'week')"
                        class="px-3 py-[7px] text-[12px] font-medium transition-colors border-l border-slate-200
                               {{ $periodType === 'week' ? 'bg-blue-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50' }}">
                    {{ __('Semaine') }}
                </button>
            </div>
        </div>

        <div>
            <label class="field-label">{{ __('Période') }}</label>
            <div class="flex items-center gap-1">
                <button type="button" wire:click="previousPeriod" class="btn btn-secondary btn-sm !px-2.5">‹</button>
                <span class="px-3 py-[7px] text-[13px] font-medium text-slate-700 bg-white border border-slate-200 rounded-lg min-w-[210px] text-center select-none">
                    {{ $periodLabel }}
                </span>
                <button type="button" wire:click="nextPeriod" class="btn btn-secondary btn-sm !px-2.5">›</button>
            </div>
        </div>

        {{-- Late filter --}}
        <div>
            <label class="field-label opacity-0 select-none">.</label>
            <button type="button"
                    wire:click="$toggle('filterLate')"
                    class="flex items-center gap-1.5 px-3 py-[7px] text-[12px] font-semibold rounded-lg border transition-colors
                           {{ $filterLate
                               ? 'bg-red-500 border-red-500 text-white shadow-sm'
                               : 'bg-white border-slate-200 text-slate-500 hover:border-red-300 hover:text-red-500' }}"
                    title="{{ __('Afficher uniquement les tâches en retard (toutes périodes)') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('En retard') }}
                @if($filterLate)
                    <span class="ml-0.5 opacity-80">✕</span>
                @endif
            </button>
        </div>

        @if($canCreate)
        <div class="ml-auto">
            <label class="field-label opacity-0 select-none">.</label>
            <button type="button" class="btn btn-primary" wire:click="openAddModal('todo')">
                + {{ __('Nouvelle tâche') }}
            </button>
        </div>
        @endif
    </div>

    {{-- Late filter banner --}}
    @if($filterLate)
    <div class="flex items-center gap-2 px-4 py-2.5 mb-4 bg-red-50 border border-red-200 rounded-xl text-[12px] text-red-700 font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14" class="flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ __('Filtre actif : tâches en retard — toutes périodes confondues, triées par échéance.') }}
        <button type="button" wire:click="$toggle('filterLate')" class="ml-auto underline hover:no-underline">{{ __('Désactiver') }}</button>
    </div>
    @endif

    {{-- Kanban board --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
        @foreach (['todo', 'in_progress', 'done'] as $colKey)
        @php
            $col   = $colConfig[$colKey];
            $tasks = $columns[$colKey];
            $count = count($tasks);
        @endphp

        <div class="flex flex-col gap-2">

            {{-- Column header --}}
            <div class="flex items-center justify-between px-3 py-2.5 rounded-xl {{ $col['headerBg'] }}">
                <div class="flex items-center gap-2">
                    <span class="text-[13px] font-bold {{ $col['titleColor'] }}">{{ $col['label'] }}</span>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-bold {{ $col['countBg'] }}">
                        {{ $count }}
                    </span>
                    @if($colKey === 'done')
                        @php $validatedInCol = collect($tasks)->where('is_validated', true)->count(); @endphp
                        @if($validatedInCol > 0)
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
                                ✓ {{ $validatedInCol }}
                            </span>
                        @endif
                    @endif
                </div>
                @if($canCreate)
                <button type="button"
                        wire:click="openAddModal('{{ $colKey }}')"
                        class="text-slate-400 hover:opacity-70 text-[20px] leading-none font-light transition-opacity"
                        title="{{ __('Ajouter une tâche') }}">+</button>
                @endif
            </div>

            {{-- Task cards --}}
            <div class="flex flex-col gap-2 min-h-[100px]">
                @forelse ($tasks as $task)
                @php
                    $today      = now()->toDateString();
                    $isOverdue  = $task->due_date && !$task->is_validated && $task->due_date->toDateString() < $today;
                    $isDueToday = $task->due_date && $task->due_date->toDateString() === $today;
                    $dueDateColor = $isOverdue ? 'text-red-500' : ($isDueToday ? 'text-orange-500' : 'text-slate-400');

                    $taskAssignees = collect($task->assignee_ids ?? [])
                        ->map(fn($id) => $usersById->get($id))
                        ->filter();
                    $extraAssignees = max(0, $taskAssignees->count() - 3);
                @endphp
                <div wire:key="task-{{ $task->id }}"
                     class="group bg-white border rounded-xl px-3 py-2.5 shadow-sm transition-shadow hover:shadow-md cursor-default
                            {{ $task->is_validated ? 'border-l-4 border-emerald-300 border-l-emerald-400' : ($isOverdue ? 'border-l-4 border-l-red-300 border-red-200' : 'border-slate-200') }}">

                    {{-- Title row --}}
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <span class="text-[13px] font-semibold leading-snug
                                     {{ $task->is_validated ? 'text-slate-400 line-through' : 'text-slate-800' }}">
                            {{ $task->title }}
                        </span>
                        @if($task->is_validated)
                            <span class="flex-shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 whitespace-nowrap">
                                ✓ {{ __('Validée') }}
                            </span>
                        @endif
                    </div>

                    {{-- Description --}}
                    @if($task->description)
                        <p class="text-[12px] text-slate-400 leading-snug mb-1.5 line-clamp-2">
                            {{ $task->description }}
                        </p>
                    @endif

                    {{-- Due date --}}
                    @if($task->due_date)
                        <div class="flex items-center gap-1 mb-1 {{ $dueDateColor }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="11" height="11"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-[11px] font-medium">
                                {{ $task->due_date->locale('fr')->isoFormat('D MMM YYYY') }}
                                @if($isOverdue) <span class="font-bold">({{ __('en retard') }})</span> @endif
                                @if($isDueToday) <span class="font-bold">({{ __("aujourd'hui") }})</span> @endif
                            </span>
                        </div>
                    @endif

                    {{-- Footer: assignees + actions --}}
                    <div class="flex items-center justify-between gap-1 mt-2 pt-2 border-t border-slate-100">

                        {{-- Assignees --}}
                        <div class="flex items-center gap-0.5">
                            @if($taskAssignees->isNotEmpty())
                                @foreach ($taskAssignees->take(3) as $u)
                                    <span class="flex-shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold select-none"
                                          title="{{ $u->name }}">
                                        {{ strtoupper(mb_substr($u->name, 0, 2)) }}
                                    </span>
                                @endforeach
                                @if($extraAssignees > 0)
                                    <span class="flex-shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-500 text-[10px] font-bold select-none">
                                        +{{ $extraAssignees }}
                                    </span>
                                @endif
                            @else
                                <span class="text-[11px] text-slate-300 italic">{{ __('Non assigné') }}</span>
                            @endif
                        </div>

                        {{-- Action buttons — visible on hover --}}
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">

                            {{-- Move left ← (requires edit) --}}
                            @if($canEdit)
                                @if($colKey === 'in_progress')
                                    <button type="button" class="table-action"
                                            wire:click="moveTask({{ $task->id }}, 'todo')"
                                            title="{{ __('Remettre à faire') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                @elseif($colKey === 'done')
                                    <button type="button" class="table-action"
                                            wire:click="moveTask({{ $task->id }}, 'in_progress')"
                                            title="{{ __('Remettre en cours') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                @endif
                            @endif

                            {{-- Move right → (requires edit) --}}
                            @if($canEdit)
                                @if($colKey === 'todo')
                                    <button type="button" class="table-action"
                                            wire:click="moveTask({{ $task->id }}, 'in_progress')"
                                            title="{{ __('Démarrer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                @elseif($colKey === 'in_progress')
                                    <button type="button" class="table-action"
                                            wire:click="moveTask({{ $task->id }}, 'done')"
                                            title="{{ __('Marquer terminé') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                @endif
                            @endif

                            {{-- Edit (requires edit) --}}
                            @if($canEdit)
                            <button type="button" class="table-action table-action-edit"
                                    wire:click="openEditModal({{ $task->id }})"
                                    title="{{ __('Modifier') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            @endif

                            {{-- Validate / un-validate (Done column, requires validate) --}}
                            @if($colKey === 'done' && $canValidate)
                                @if($task->is_validated)
                                    <button type="button"
                                            class="table-action"
                                            style="color:#d97706;border-color:#fcd34d;"
                                            wire:click="invalidateTask({{ $task->id }})"
                                            title="{{ __('Retirer la validation') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @else
                                    <button type="button"
                                            class="table-action table-action-edit"
                                            wire:click="validateTask({{ $task->id }})"
                                            title="{{ __('Valider la tâche') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                @endif
                            @endif

                            {{-- Delete (requires delete) --}}
                            @if($canDelete)
                            <button type="button"
                                    class="table-action table-action-delete"
                                    wire:click="deleteTask({{ $task->id }})"
                                    wire:confirm="{{ __('Supprimer cette tâche ?') }}"
                                    title="{{ __('Supprimer') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-slate-300 border-2 border-dashed border-slate-100 rounded-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22" class="mb-1.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span class="text-[12px]">{{ __('Aucune tâche') }}</span>
                    </div>
                @endforelse
            </div>

            @if($canCreate)
            <button type="button"
                    wire:click="openAddModal('{{ $colKey }}')"
                    class="text-[12px] text-slate-400 border border-dashed border-slate-200 rounded-xl py-2 transition-colors text-center w-full {{ $col['addHover'] }}">
                + {{ __('Ajouter') }}
            </button>
            @endif
        </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-4 mt-5 pt-4 border-t border-slate-100 text-[11px] text-slate-400">
        <span>{{ __('Survol carte → actions') }}</span>
        <span class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 border-l-4 border-l-emerald-400 border border-emerald-300 rounded-sm"></span>
            {{ __('Validée') }}
        </span>
        <span class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 border-l-4 border-l-red-300 border border-red-200 rounded-sm"></span>
            {{ __('En retard') }}
        </span>
        <span>{{ __('Semaine : lundi au samedi') }}</span>
    </div>

    @endif {{-- end kanban tab --}}

    {{-- ── 4b. RAPPORTS TERRAIN TAB ─────────────────────────────────────────── --}}
    @if($activeTab === 'rapports')

    <div class="flex items-center justify-between mb-4">
        <p class="text-[13px] text-slate-500">
            {{ __('Historique des rapports de chantier pour ce projet — lisez l\'évolution de l\'avancement.') }}
        </p>
        @if($canCreate)
        <a href="{{ route('tenant.suivi.create', ['tenant' => $tenantCode]) }}"
           class="btn btn-primary btn-sm flex-shrink-0">
            + {{ __('Nouveau rapport') }}
        </a>
        @endif
    </div>

    @if($reports && $reports->count() > 0)
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Date') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Travaux réalisés') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Effectif') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5 min-w-[160px]">{{ __('Avancement') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $r)
                    @php
                        $rStatusBg = match($r->status) {
                            'draft'     => 'bg-slate-100 text-slate-600',
                            'submitted' => 'bg-blue-100 text-blue-700',
                            'validated' => 'bg-emerald-100 text-emerald-700',
                            default     => 'bg-slate-100 text-slate-500',
                        };
                        $rStatusLabel = match($r->status) {
                            'draft'     => __('Brouillon'),
                            'submitted' => __('Soumis'),
                            'validated' => __('Validé'),
                            default     => $r->status,
                        };
                        $barColor = $r->progress_percent >= 100 ? 'bg-emerald-500' : 'bg-indigo-500';
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="rpt-{{ $r->id }}">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('tenant.suivi.edit', ['tenant' => $tenantCode, 'site_report' => $r->id]) }}"
                               class="font-mono text-[11px] font-semibold text-blue-600 hover:text-blue-800">
                                {{ $r->code }}
                            </a>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap">
                            {{ $r->report_date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                        </td>
                        <td class="px-4 py-2.5 text-slate-600 max-w-[220px]">
                            <span class="line-clamp-2">{{ $r->work_done ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-center text-slate-700 font-medium">{{ $r->workers_count }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-slate-100 rounded-full h-2 min-w-[80px] overflow-hidden">
                                    <div class="{{ $barColor }} h-2 rounded-full transition-[width]"
                                         style="width: {{ min($r->progress_percent, 100) }}%"></div>
                                </div>
                                <span class="text-[12px] font-bold {{ $r->progress_percent >= 100 ? 'text-emerald-600' : 'text-indigo-600' }} whitespace-nowrap w-10 text-right">
                                    {{ $r->progress_percent }}%
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $rStatusBg }}">
                                {{ $rStatusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5">
                            <a class="table-action table-action-edit"
                               href="{{ route('tenant.suivi.edit', ['tenant' => $tenantCode, 'site_report' => $r->id]) }}"
                               title="{{ __('Modifier') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="flex flex-col items-center justify-center py-16 text-slate-300 border-2 border-dashed border-slate-100 rounded-xl">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="40" height="40" class="mb-3"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p class="text-[14px] font-medium text-slate-400">{{ __('Aucun rapport pour ce projet') }}</p>
        @if($canCreate)
        <a href="{{ route('tenant.suivi.create', ['tenant' => $tenantCode]) }}"
           class="mt-3 btn btn-primary btn-sm">
            + {{ __('Créer le premier rapport') }}
        </a>
        @endif
    </div>
    @endif

    @endif {{-- end rapports tab --}}

    @else {{-- no project selected --}}

    <div class="flex flex-col items-center justify-center py-24 text-slate-300">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="48" height="48" class="mb-4 text-slate-200">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
        </svg>
        <p class="text-[16px] font-semibold text-slate-400">{{ __('Sélectionnez un projet pour commencer') }}</p>
        <p class="text-[13px] text-slate-300 mt-1">{{ __('Le tableau Kanban et les rapports terrain s\'afficheront ici') }}</p>
    </div>

    @endif {{-- end project selected check --}}


    {{-- ── Task modal (add / edit) ─────────────────────────────────────────── --}}
    @if($showTaskModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(15,23,42,.5);backdrop-filter:blur(2px)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col"
             @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h3 class="text-[15px] font-semibold text-slate-800">
                    {{ $editTaskId ? __('Modifier la tâche') : __('Nouvelle tâche') }}
                </h3>
                <button type="button" wire:click="closeTaskModal"
                        class="text-slate-400 hover:text-slate-600 transition-colors leading-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-5 py-4 flex flex-col gap-3 overflow-y-auto max-h-[70vh]">

                {{-- Column --}}
                <div class="field">
                    <label class="field-label">{{ __('Colonne') }}</label>
                    <select class="input" wire:model="newStatus">
                        <option value="todo">{{ __('À faire') }}</option>
                        <option value="in_progress">{{ __('En cours') }}</option>
                        <option value="done">{{ __('Terminé') }}</option>
                    </select>
                </div>

                {{-- Title --}}
                <div class="field">
                    <label class="field-label">{{ __('Titre') }} <span class="text-red-500">*</span></label>
                    <input class="input" wire:model="newTitle"
                           placeholder="{{ __('Description courte de la tâche') }}">
                    @error('newTitle') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                {{-- Description --}}
                <div class="field">
                    <label class="field-label">{{ __('Description') }}</label>
                    <textarea class="input" wire:model="newDescription" rows="2"
                              placeholder="{{ __('Détails, contexte, instructions…') }}"></textarea>
                </div>

                {{-- Due date --}}
                <div class="field">
                    <label class="field-label">{{ __('Date d\'échéance') }}</label>
                    <input class="input" type="date" wire:model="newDueDate">
                    @error('newDueDate') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                {{-- Assignees (multi-select checkboxes) --}}
                <div class="field">
                    <label class="field-label">
                        {{ __('Intervenants') }}
                        @if(!empty($newAssigneeIds))
                            <span class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold">
                                {{ count($newAssigneeIds) }}
                            </span>
                        @endif
                    </label>
                    <div class="border border-slate-200 rounded-lg max-h-40 overflow-y-auto">
                        @forelse ($users as $u)
                        <label class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 transition-colors px-3 py-2 {{ !$loop->last ? 'border-b border-slate-100' : '' }}">
                            <input type="checkbox"
                                   value="{{ $u->id }}"
                                   wire:model="newAssigneeIds"
                                   class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-50 text-blue-600 text-[10px] font-bold">
                                    {{ strtoupper(mb_substr($u->name, 0, 2)) }}
                                </span>
                                <span class="text-[13px] text-slate-700">{{ $u->name }}</span>
                            </div>
                        </label>
                        @empty
                        <p class="px-3 py-2 text-[12px] text-slate-400">{{ __('Aucun utilisateur') }}</p>
                        @endforelse
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
                <button type="button" class="btn btn-secondary" wire:click="closeTaskModal">
                    {{ __('Annuler') }}
                </button>
                <button type="button" class="btn btn-primary"
                        wire:click="saveTask"
                        wire:loading.attr="disabled" wire:target="saveTask">
                    <span wire:loading.remove wire:target="saveTask">
                        {{ $editTaskId ? __('Enregistrer') : __('Ajouter') }}
                    </span>
                    <span wire:loading wire:target="saveTask">{{ __('…') }}</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
