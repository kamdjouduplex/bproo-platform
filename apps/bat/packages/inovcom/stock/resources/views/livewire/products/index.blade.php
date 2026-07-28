@php $tenantCode ??= null; @endphp
<div class="page-body">

    {{-- ── Toolbar ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <input class="input input-sm" placeholder="Recherche…"
                   wire:model.live.debounce.400ms="search" style="min-width:180px;">
            <select class="input input-sm" wire:model.live="categoryFilter">
                <option value="">Toutes catégories</option>
                @foreach($categories as $cat)
                <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
            <a class="btn btn-secondary btn-sm"
               href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">← Tableau de bord</a>
        </div>
        @if($canCreate)
        <a class="btn btn-primary"
           href="{{ route('tenant.stock.products.create', ['tenant' => $tenantCode]) }}">+ Nouveau produit</a>
        @endif
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Code</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Nom</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Catégorie</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Unité</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Stock total</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Statut</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                    @php
                        $stock = (float) ($stockTotals[$product->id] ?? 0);
                        $isLow = (float)$product->min_stock_alert > 0 && $stock < (float)$product->min_stock_alert;
                        $statusBg = $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500';
                        $statusLabel = $product->is_active ? 'Actif' : 'Inactif';
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="prd-{{ $product->id }}">
                        <td class="px-4 py-2.5 font-mono text-[11px] font-semibold text-slate-500">{{ $product->code }}</td>
                        <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $product->name }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $product->category ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $product->unit }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <span class="{{ $isLow ? 'font-bold text-red-600' : 'text-slate-700' }}">
                                {{ number_format($stock, 3, ',', ' ') }}
                            </span>
                            @if($isLow)
                            <span class="ml-1 text-[10px] text-red-500" title="Stock en dessous du seuil d'alerte">⚠</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBg }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1">
                                @if($canEdit)
                                <a class="table-action table-action-edit"
                                   href="{{ route('tenant.stock.products.edit', ['tenant' => $tenantCode, 'stock_product' => $product->id]) }}"
                                   title="Modifier">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                                @if($canDelete)
                                <button type="button" class="table-action table-action-delete"
                                        wire:click="delete({{ $product->id }})"
                                        wire:confirm="Supprimer ce produit ?" title="Supprimer">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">Aucun produit trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $products->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </div>
</div>
