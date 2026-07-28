@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $typeBg = fn($type) => match($type) {
        'visit_terrain'     => 'bg-blue-100 text-blue-800',
        'reunion'           => 'bg-violet-100 text-violet-800',
        'maintenance'       => 'bg-orange-100 text-orange-800',
        'project_milestone' => 'bg-emerald-100 text-emerald-800',
        default             => 'bg-slate-100 text-slate-600',
    };
    $statusBg = fn($status) => match($status) {
        'scheduled'  => 'bg-slate-100 text-slate-600',
        'confirmed'  => 'bg-blue-100 text-blue-800',
        'done'       => 'bg-emerald-100 text-emerald-700',
        'cancelled'  => 'bg-red-100 text-red-700',
        default      => 'bg-slate-100 text-slate-500',
    };
    $statusLabel = fn($status) => match($status) {
        'scheduled' => 'Planifié',
        'confirmed' => 'Confirmé',
        'done'      => 'Terminé',
        'cancelled' => 'Annulé',
        default     => $status,
    };
@endphp
<div>

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 flex-wrap mb-5">
        {{-- Month navigation --}}
        <div class="flex items-center gap-2">
            <button class="w-8 h-8 rounded-lg border border-slate-200 bg-white cursor-pointer flex items-center justify-center text-slate-500 hover:bg-slate-50"
                    wire:click="prevMonth" title="{{ __('Mois précédent') }}">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div class="text-[18px] font-bold text-slate-900 min-w-[180px] text-center">{{ $monthLabel }}</div>
            <button class="w-8 h-8 rounded-lg border border-slate-200 bg-white cursor-pointer flex items-center justify-center text-slate-500 hover:bg-slate-50"
                    wire:click="nextMonth" title="{{ __('Mois suivant') }}">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button class="btn btn-secondary btn-sm" wire:click="goToday">{{ __("Aujourd'hui") }}</button>
        </div>

        {{-- Filters --}}
        <div class="flex gap-2 ml-auto flex-wrap items-center">
            <select class="input input-sm" wire:model.live="filterType">
                <option value="">{{ __('Tous les types') }}</option>
                <option value="visit_terrain">{{ __('Visite terrain') }}</option>
                <option value="reunion">{{ __('Réunion') }}</option>
                <option value="maintenance">{{ __('Maintenance') }}</option>
                <option value="project_milestone">{{ __('Jalon projet') }}</option>
                <option value="other">{{ __('Autre') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="filterUser">
                <option value="">{{ __('Tous les utilisateurs') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>

            {{-- View toggle --}}
            <div class="flex border border-slate-200 rounded-lg overflow-hidden">
                <button class="px-3.5 py-1.5 text-[12px] font-semibold cursor-pointer {{ $view === 'month' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50' }}"
                        wire:click="$set('view','month')">
                    {{ __('Calendrier') }}
                </button>
                <button class="px-3.5 py-1.5 text-[12px] font-semibold cursor-pointer {{ $view === 'list' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50' }}"
                        wire:click="$set('view','list')">
                    {{ __('Liste') }}
                </button>
            </div>

            @if ($canCreate)
                <a href="{{ route('tenant.planning.create', ['tenant' => $tenantCode]) }}" class="btn btn-primary btn-sm">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Nouveau') }}
                </a>
            @endif
        </div>
    </div>

    {{-- ── MONTH VIEW ───────────────────────────────────────────────────── --}}
    @if ($view === 'month')
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="grid grid-cols-7 bg-slate-50 border-b border-slate-200">
                @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $i => $day)
                    <div class="py-2.5 px-2 text-center text-[11px] font-bold uppercase tracking-wider {{ $i >= 5 ? 'text-red-500' : 'text-slate-500' }}">{{ $day }}</div>
                @endforeach
            </div>

            @foreach ($weeks as $week)
                <div class="grid grid-cols-7 border-t border-slate-100">
                    @foreach ($week as $cell)
                        <div class="min-h-[110px] p-1.5 border-r border-slate-100 last:border-r-0 relative
                            {{ !$cell['isCurrentMonth'] ? 'bg-slate-50' : '' }}
                            {{ $cell['isToday'] ? 'bg-blue-50' : '' }}">

                            <div class="text-[13px] font-bold w-6 h-6 flex items-center justify-center rounded-full mb-1
                                {{ $cell['isToday'] ? 'bg-indigo-600 text-white' : (!$cell['isCurrentMonth'] ? 'text-slate-300' : 'text-slate-500') }}">
                                {{ $cell['date']->day }}
                            </div>

                            @foreach ($cell['appointments']->take(3) as $apt)
                                <a href="{{ route('tenant.planning.edit', ['tenant' => $tenantCode, 'appointment' => $apt->id]) }}"
                                   class="block text-[11px] font-semibold px-1.5 py-0.5 rounded mb-0.5 whitespace-nowrap overflow-hidden text-ellipsis no-underline {{ $typeBg($apt->type) }} {{ $apt->status === 'cancelled' ? 'opacity-50 line-through' : '' }}"
                                   title="{{ $apt->title }} — {{ Carbon\Carbon::parse($apt->start_at)->format('H:i') }}">
                                    {{ Carbon\Carbon::parse($apt->start_at)->format('H:i') }} {{ $apt->title }}
                                </a>
                            @endforeach

                            @if ($cell['appointments']->count() > 3)
                                <div class="text-[10px] text-slate-400 font-semibold">+{{ $cell['appointments']->count() - 3 }} autre(s)</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

    {{-- ── LIST VIEW ────────────────────────────────────────────────────── --}}
    @else
        @if ($upcoming->isEmpty())
            <div class="text-center py-12 text-slate-400">
                <div class="text-4xl mb-3">📅</div>
                <div class="text-[14px]">{{ __('Aucun rendez-vous prévu dans les 30 prochains jours.') }}</div>
                @if ($canCreate)
                    <a href="{{ route('tenant.planning.create', ['tenant' => $tenantCode]) }}" class="btn btn-primary btn-sm mt-4 inline-flex">
                        {{ __('Planifier un rendez-vous') }}
                    </a>
                @endif
            </div>
        @else
            <div class="flex flex-col gap-5">
                @foreach ($upcoming as $dayKey => $dayAppointments)
                    @php
                        $dayDate  = \Carbon\Carbon::parse($dayKey);
                        $isToday  = $dayDate->isToday();
                        $dayLabel = $isToday ? "Aujourd'hui — " . $dayDate->isoFormat('dddd D MMMM') : $dayDate->locale('fr')->isoFormat('dddd D MMMM YYYY');
                    @endphp
                    <div>
                        <div class="text-[12px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-lg inline-block mb-2 {{ $isToday ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-600' }}">
                            {{ ucfirst($dayLabel) }}
                        </div>

                        <div class="flex flex-col gap-2">
                            @foreach ($dayAppointments as $apt)
                                @php $participantCount = $apt->participants?->count() ?? 0; @endphp
                                <div class="bg-white border border-slate-200 rounded-xl px-4 py-3.5 flex gap-3.5 items-start"
                                     wire:key="apt-list-{{ $apt->id }}">
                                    <div class="text-[12px] font-bold text-slate-500 min-w-[72px] text-right pt-0.5">
                                        {{ \Carbon\Carbon::parse($apt->start_at)->format('H:i') }}<br>
                                        <span class="text-slate-300 font-normal">{{ \Carbon\Carbon::parse($apt->end_at)->format('H:i') }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[14px] font-bold text-slate-900">{{ $apt->title }}</div>
                                        <div class="text-[12px] text-slate-400 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
                                            @if ($apt->location)
                                                <span>📍 {{ $apt->location }}</span>
                                            @endif
                                            @if ($apt->assignedUser)
                                                <span>👤 {{ $apt->assignedUser->name }}</span>
                                            @endif
                                            @if ($apt->client)
                                                <span>🏢 {{ $apt->client->name }}</span>
                                            @endif
                                            @if ($participantCount > 0)
                                                <span>👥 {{ $participantCount }} {{ __('participant(s)') }}</span>
                                            @endif
                                        </div>
                                        <div class="flex gap-1.5 mt-2 flex-wrap">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-semibold {{ $typeBg($apt->type) }}">{{ $apt->typeLabel() }}</span>
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBg($apt->status) }}">{{ $statusLabel($apt->status) }}</span>
                                            @if ($apt->report)
                                                <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-700">
                                                    ✓ {{ __('CR rédigé') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex gap-1.5 flex-shrink-0">
                                        <a href="{{ route('tenant.planning.edit', ['tenant' => $tenantCode, 'appointment' => $apt->id]) }}"
                                           class="table-action table-action-edit" title="{{ __('Modifier') }}">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        @if ($canDelete)
                                            <button class="table-action table-action-delete"
                                                    wire:click="delete({{ $apt->id }})"
                                                    wire:confirm="{{ __('Supprimer ce rendez-vous ?') }}"
                                                    title="{{ __('Supprimer') }}">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
