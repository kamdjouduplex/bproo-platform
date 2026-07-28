@php $tenantCode ??= null; @endphp
<div class="page-body">

    {{-- ── Stat cards ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalProducts }}</p>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Produits actifs</p>
            </div>
        </div>
        <div class="card flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalWarehouses }}</p>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Entrepôts</p>
            </div>
        </div>
        <div class="card flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg {{ $lowStockCount > 0 ? 'bg-red-100' : 'bg-emerald-100' }} flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 {{ $lowStockCount > 0 ? 'text-red-600' : 'text-emerald-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold {{ $lowStockCount > 0 ? 'text-red-700' : 'text-slate-800' }}">{{ $lowStockCount }}</p>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Alertes stock bas</p>
            </div>
        </div>
    </div>

    {{-- ── Quick actions ────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @if($canCreate)
        <a href="{{ route('tenant.stock.movements.create', ['tenant' => $tenantCode]) }}"
           class="btn btn-primary">
            + Enregistrer un mouvement
        </a>
        @endif
        @if($canManage)
        <a href="{{ route('tenant.stock.products.index', ['tenant' => $tenantCode]) }}"
           class="btn btn-secondary">Gérer les produits</a>
        <a href="{{ route('tenant.stock.warehouses.index', ['tenant' => $tenantCode]) }}"
           class="btn btn-secondary">Gérer les entrepôts</a>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Stock levels table ───────────────────────────────────── --}}
        <div class="lg:col-span-2">
            <div class="card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-700">Niveaux de stock</h3>
                    <select wire:model.live="warehouseFilter" class="input input-sm w-auto">
                        <option value="0">Tous les entrepôts</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-[12px]">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Produit</th>
                                <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Entrepôt</th>
                                <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Stock</th>
                                <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Unité</th>
                                <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockLevels as $row)
                            @php
                                $qty     = (float) $row->current_stock;
                                $minAlert = (float) ($row->product?->min_stock_alert ?? 0);
                                $isLow   = $minAlert > 0 && $qty < $minAlert;
                                $stateBg = $isLow ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700';
                                $stateLabel = $isLow ? 'Stock bas' : 'Normal';
                            @endphp
                            <tr class="border-b border-slate-100 hover:bg-slate-50" wire:key="sl-{{ $row->product_id }}-{{ $row->warehouse_id }}">
                                <td class="px-4 py-2.5 font-semibold text-slate-700">
                                    {{ $row->product?->name ?? '—' }}
                                    <span class="ml-1 text-[10px] text-slate-400 font-mono">{{ $row->product?->code }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-500">{{ $row->warehouse?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right font-bold text-slate-800">{{ number_format($qty, 3, ',', ' ') }}</td>
                                <td class="px-4 py-2.5 text-slate-400">{{ $row->product?->unit }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $stateBg }}">{{ $stateLabel }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400 text-sm">Aucun stock enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── Recent movements ─────────────────────────────────────── --}}
        <div>
            <div class="card p-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-700">Derniers mouvements</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentMovements as $mv)
                    @php
                        $mvColor = match($mv->type) {
                            'IN'       => 'text-emerald-600',
                            'OUT'      => 'text-red-600',
                            'TRANSFER' => 'text-blue-600',
                            default    => 'text-slate-500',
                        };
                        $mvSign = match($mv->type) {
                            'IN'       => '+',
                            'OUT'      => '−',
                            'TRANSFER' => '⇄',
                            default    => '',
                        };
                    @endphp
                    <div class="px-4 py-2.5" wire:key="mv-{{ $mv->id }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[12px] font-semibold text-slate-700 truncate">{{ $mv->product?->name ?? '—' }}</p>
                                <p class="text-[11px] text-slate-400">{{ $mv->warehouse?->name }} · {{ $mv->created_at->format('d/m H:i') }}</p>
                            </div>
                            <span class="flex-shrink-0 text-[13px] font-bold {{ $mvColor }}">
                                {{ $mvSign }} {{ number_format(abs((float)$mv->quantity), 3, ',', ' ') }}
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="px-4 py-6 text-center text-sm text-slate-400">Aucun mouvement.</div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>
