@php $tenantCode ??= null; @endphp
<div class="page-body">

    {{-- ── Stats ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach([
            ['label' => 'En attente',  'key' => 'pending',     'bg' => 'bg-amber-100',  'text' => 'text-amber-700'],
            ['label' => 'En cours',    'key' => 'in_progress', 'bg' => 'bg-blue-100',   'text' => 'text-blue-700'],
            ['label' => 'Livrées',     'key' => 'completed',   'bg' => 'bg-emerald-100','text' => 'text-emerald-700'],
        ] as $s)
        <button type="button"
                wire:click="$set('statusFilter', '{{ $loop->first && $statusFilter === $s['key'] ? '' : $s['key'] }}')"
                class="card flex items-center gap-4 text-left hover:ring-2 hover:ring-slate-300 transition-all">
            <p class="text-2xl font-bold text-slate-800">{{ $stats[$s['key']] }}</p>
            <p class="text-xs {{ $s['text'] }} font-semibold uppercase tracking-wide">{{ $s['label'] }}</p>
        </button>
        @endforeach
    </div>

    {{-- ── Toolbar ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <input class="input input-sm" placeholder="Code, destination…"
                   wire:model.live.debounce.400ms="search" style="min-width:200px;">
            <select class="input input-sm" wire:model.live="statusFilter">
                <option value="">Tous les statuts</option>
                <option value="pending">En attente</option>
                <option value="in_progress">En cours</option>
                <option value="completed">Livrées</option>
                <option value="cancelled">Annulées</option>
            </select>
            <a class="btn btn-secondary btn-sm"
               href="{{ route('tenant.logistique.vehicles.index', ['tenant' => $tenantCode]) }}">Véhicules</a>
            <a class="btn btn-secondary btn-sm"
               href="{{ route('tenant.logistique.drivers.index', ['tenant' => $tenantCode]) }}">Chauffeurs</a>
        </div>
        @if($canCreate)
        <a class="btn btn-primary"
           href="{{ route('tenant.logistique.create', ['tenant' => $tenantCode]) }}">+ Nouvelle livraison</a>
        @endif
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Code</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Véhicule / Chauffeur</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Entrepôt</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Destination</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Prévu le</th>
                        <th class="text-center text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Articles</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Statut</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $d)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="del-{{ $d->id }}">
                        <td class="px-4 py-2.5 font-mono text-[11px] font-semibold text-slate-600">
                            <a class="hover:text-indigo-600"
                               href="{{ route('tenant.logistique.show', ['tenant' => $tenantCode, 'delivery' => $d->id]) }}">
                                {{ $d->code }}
                            </a>
                        </td>
                        <td class="px-4 py-2.5">
                            <p class="font-semibold text-slate-700">{{ $d->vehicle?->name ?? '—' }}</p>
                            <p class="text-[11px] text-slate-400">{{ $d->driver?->name ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $d->sourceWarehouse?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $d->destination ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $d->scheduled_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-center text-slate-600 font-semibold">{{ $d->items_count }}</td>
                        <td class="px-4 py-2.5">
                            <span class="badge {{ $d->statusBadgeClass() }}">{{ $d->statusLabel() }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1 flex-wrap">
                                <a class="table-action table-action-edit"
                                   href="{{ route('tenant.logistique.show', ['tenant' => $tenantCode, 'delivery' => $d->id]) }}"
                                   title="Voir">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @if($canEdit && $d->status === 'pending')
                                <button class="btn btn-sm bg-blue-50 text-blue-600 hover:bg-blue-100 border-0 px-2 py-0.5"
                                        wire:click="markInProgress({{ $d->id }})">▶ Démarrer</button>
                                @endif
                                @if($canComplete && $d->status === 'in_progress')
                                <button class="btn btn-sm bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-0 px-2 py-0.5"
                                        wire:click="markCompleted({{ $d->id }})"
                                        wire:confirm="Marquer cette livraison comme complétée ? Le stock sera déduit.">
                                    ✓ Livré
                                </button>
                                @endif
                                @if($canEdit && $d->status === 'pending')
                                <button class="table-action table-action-delete"
                                        wire:click="cancel({{ $d->id }})"
                                        wire:confirm="Annuler cette livraison ?" title="Annuler">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">Aucune livraison trouvée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $deliveries->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </div>
</div>
