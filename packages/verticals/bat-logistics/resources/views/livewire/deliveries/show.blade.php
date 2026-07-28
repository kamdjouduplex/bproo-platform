@php $tenantCode ??= null; @endphp
<div class="page-body">

    {{-- ── Header card ──────────────────────────────────────────────── --}}
    <div class="card mb-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-xl font-bold text-slate-800 font-mono">{{ $delivery->code }}</h1>
                    <span class="{{ $delivery->statusBadgeClass() }}">{{ $delivery->statusLabel() }}</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-1 text-sm">
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Véhicule</p>
                        <p class="font-semibold text-slate-700">{{ $delivery->vehicle?->name ?? '—' }}</p>
                        <p class="text-xs text-slate-400">{{ $delivery->vehicle?->plate_number }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Chauffeur</p>
                        <p class="font-semibold text-slate-700">{{ $delivery->driver?->name ?? '—' }}</p>
                        <p class="text-xs text-slate-400">{{ $delivery->driver?->phone }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Entrepôt source</p>
                        <p class="font-semibold text-slate-700">{{ $delivery->sourceWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Prévu le</p>
                        <p class="font-semibold text-slate-700">{{ $delivery->scheduled_at?->format('d/m/Y') ?? '—' }}</p>
                    </div>
                </div>
                @if($delivery->destination)
                <p class="mt-2 text-sm text-slate-500">📍 {{ $delivery->destination }}</p>
                @endif
                @if($delivery->completed_at)
                <p class="mt-1 text-xs text-emerald-600">✓ Livrée le {{ $delivery->completed_at->format('d/m/Y à H:i') }}</p>
                @endif
                @if($delivery->notes)
                <p class="mt-2 text-xs text-slate-400 italic">{{ $delivery->notes }}</p>
                @endif
            </div>

            {{-- ── Workflow actions ─────────────────────────────────── --}}
            <div class="flex flex-wrap gap-2">
                @if($canEdit && $delivery->status === 'pending')
                <a class="btn btn-secondary"
                   href="{{ route('tenant.logistique.edit', ['tenant' => $tenantCode, 'delivery' => $delivery->id]) }}">
                    Modifier
                </a>
                <button type="button" wire:click="markInProgress" class="btn btn-primary">
                    ▶ Démarrer la livraison
                </button>
                <button type="button" wire:click="cancelDelivery"
                        wire:confirm="Annuler cette livraison ?"
                        class="btn text-red-600 border-red-200 hover:bg-red-50">Annuler</button>
                @endif

                @if($canComplete && $delivery->status === 'in_progress')
                <button type="button" wire:click="markCompleted"
                        wire:confirm="Marquer comme livrée ? Le stock sera automatiquement déduit."
                        class="btn btn-success">
                    ✓ Marquer comme livrée
                </button>
                @endif

                <a class="btn btn-secondary"
                   href="{{ route('tenant.logistique.index', ['tenant' => $tenantCode]) }}">← Retour</a>
            </div>
        </div>
    </div>

    {{-- ── Items table ───────────────────────────────────────────────── --}}
    <div class="card p-0 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-700">Articles ({{ $delivery->items->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Produit</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Code</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Quantité</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Unité</th>
                        @if($delivery->status === 'completed')
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Stock</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($delivery->items as $item)
                    <tr class="border-b border-slate-100 hover:bg-slate-50" wire:key="item-{{ $item->id }}">
                        <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $item->product?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">{{ $item->product?->code }}</td>
                        <td class="px-4 py-2.5 text-right font-bold text-slate-700">{{ number_format((float)$item->quantity, 3, ',', ' ') }}</td>
                        <td class="px-4 py-2.5 text-slate-400">{{ $item->product?->unit }}</td>
                        @if($delivery->status === 'completed')
                        <td class="px-4 py-2.5">
                            <span class="badge badge-success">Déduit</span>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400 text-sm">Aucun article.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
