@php $tenantCode ??= null; @endphp
<div class="page-body">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <input class="input input-sm" placeholder="Recherche…"
                   wire:model.live.debounce.400ms="search" style="min-width:200px;">
            <a class="btn btn-secondary btn-sm"
               href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">← Tableau de bord</a>
        </div>
        @if($canCreate)
        <a class="btn btn-primary"
           href="{{ route('tenant.stock.warehouses.create', ['tenant' => $tenantCode]) }}">+ Nouvel entrepôt</a>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($warehouses as $warehouse)
        <div class="card" wire:key="wh-{{ $warehouse->id }}">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $warehouse->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $warehouse->is_active ? 'Actif' : 'Inactif' }}
                </span>
            </div>
            <h3 class="font-semibold text-slate-800 mb-1">{{ $warehouse->name }}</h3>
            @if($warehouse->location)
            <p class="text-xs text-slate-400 mb-1">📍 {{ $warehouse->location }}</p>
            @endif
            @if($warehouse->description)
            <p class="text-xs text-slate-500 mb-3">{{ $warehouse->description }}</p>
            @endif
            <p class="text-xs text-slate-400 mb-4">{{ $movementCounts[$warehouse->id] ?? 0 }} mouvement(s) enregistré(s)</p>
            <div class="flex items-center gap-2 border-t border-slate-100 pt-3">
                @if($canEdit)
                <a class="btn btn-secondary btn-sm"
                   href="{{ route('tenant.stock.warehouses.edit', ['tenant' => $tenantCode, 'stock_warehouse' => $warehouse->id]) }}">
                    Modifier
                </a>
                @endif
                @if($canDelete)
                <button type="button" class="btn btn-sm text-red-500 hover:text-red-700 hover:bg-red-50"
                        wire:click="delete({{ $warehouse->id }})"
                        wire:confirm="Supprimer cet entrepôt ?">
                    Supprimer
                </button>
                @endif
            </div>
        </div>
        @empty
        <div class="sm:col-span-2 lg:col-span-3 card py-12 text-center text-slate-400">
            Aucun entrepôt configuré.
        </div>
        @endforelse
    </div>
</div>
