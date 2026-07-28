<div class="page-body">

    {{-- ── Stats bar ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="card p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">{{ $stats['total'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Total employés</div>
        </div>
        <div class="card p-4 text-center cursor-pointer {{ $statusFilter === 'active' ? 'ring-2 ring-emerald-400' : '' }}"
             wire:click="$set('statusFilter', '{{ $statusFilter === 'active' ? '' : 'active' }}')">
            <div class="text-2xl font-bold text-emerald-600">{{ $stats['active'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Actifs</div>
        </div>
        <div class="card p-4 text-center cursor-pointer {{ $statusFilter === 'on_leave' ? 'ring-2 ring-amber-400' : '' }}"
             wire:click="$set('statusFilter', '{{ $statusFilter === 'on_leave' ? '' : 'on_leave' }}')">
            <div class="text-2xl font-bold text-amber-600">{{ $stats['on_leave'] }}</div>
            <div class="text-xs text-slate-500 mt-1">En congé</div>
        </div>
        <div class="card p-4 text-center cursor-pointer {{ $statusFilter === 'terminated' ? 'ring-2 ring-red-400' : '' }}"
             wire:click="$set('statusFilter', '{{ $statusFilter === 'terminated' ? '' : 'terminated' }}')">
            <div class="text-2xl font-bold text-red-600">{{ $stats['terminated'] }}</div>
            <div class="text-xs text-slate-500 mt-1">Sortis</div>
        </div>
    </div>

    {{-- ── Filters + actions ────────────────────────────────────────────── --}}
    <div class="card p-4 mb-6 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   class="input pl-9 w-full" placeholder="Rechercher un employé…">
        </div>

        @if($departments->isNotEmpty())
        <select wire:model.live="deptFilter" class="input sm:w-48">
            <option value="">Tous les services</option>
            @foreach($departments as $dept)
                <option value="{{ $dept }}">{{ $dept }}</option>
            @endforeach
        </select>
        @endif

        @if($canCreate)
        <a href="{{ route('tenant.rh.create', ['tenant' => request()->query('tenant') ?? session('tenant_code')]) }}"
           class="btn btn-primary whitespace-nowrap">
            + Nouvel employé
        </a>
        @endif
    </div>

    {{-- ── Employee grid ────────────────────────────────────────────────── --}}
    @if($employees->isEmpty())
        <div class="card p-12 text-center text-slate-500">
            <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M17 20h5v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2h5M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>
            </svg>
            <p class="font-medium">Aucun employé trouvé</p>
            <p class="text-sm mt-1">
                @if($search || $statusFilter || $deptFilter)
                    Modifiez vos filtres de recherche.
                @else
                    Commencez par créer votre premier employé.
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($employees as $emp)
            <div wire:key="emp-{{ $emp->id }}" class="card p-5 flex flex-col gap-4 hover:shadow-md transition-shadow group">

                {{-- Avatar + name --}}
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full {{ $emp->avatarColor() }} flex items-center justify-center text-white font-bold text-lg">
                        {{ $emp->initials() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-800 truncate">{{ $emp->fullName() }}</p>
                        <p class="text-sm text-slate-500 truncate">{{ $emp->position ?? '—' }}</p>
                    </div>
                    <span class="badge {{ $emp->statusBadgeClass() }} flex-shrink-0">{{ $emp->statusLabel() }}</span>
                </div>

                {{-- Meta info --}}
                <div class="space-y-1 text-sm text-slate-600">
                    @if($emp->department)
                    <div class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                        </svg>
                        <span class="truncate">{{ $emp->department }}</span>
                    </div>
                    @endif
                    <div class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/>
                        </svg>
                        <span>Embauché le {{ $emp->hire_date->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 14l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                        <span>{{ $emp->contractLabel() }}</span>
                    </div>
                    <div class="flex items-center gap-2 font-medium text-slate-700">
                        <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                        <span>{{ number_format((float)$emp->base_salary, 0, ',', ' ') }} FCFA / mois</span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-2 pt-1 border-t border-slate-100">
                    <a href="{{ route('tenant.rh.show', ['employee' => $emp->id, 'tenant' => request()->query('tenant') ?? session('tenant_code')]) }}"
                       class="btn btn-secondary btn-sm flex-1 text-center">
                        Voir le profil
                    </a>
                    @if($canEdit)
                    <a href="{{ route('tenant.rh.edit', ['employee' => $emp->id, 'tenant' => request()->query('tenant') ?? session('tenant_code')]) }}"
                       class="btn btn-sm btn-secondary px-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                    @endif
                    @if($canDelete)
                    <button wire:click="delete({{ $emp->id }})"
                            wire:confirm="Supprimer cet employé ? Cette action est irréversible."
                            class="btn btn-sm btn-danger px-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    @endif
                </div>

            </div>
            @endforeach
        </div>
    @endif

</div>
