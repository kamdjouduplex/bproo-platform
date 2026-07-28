<div>
    @php
        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
        $cur = $tenantCurrency ?? config('inovcom.currency', 'XOF');
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <input class="input input-sm" placeholder="{{ __('Recherche…') }}" wire:model.debounce.400ms="search">
            <select class="input input-sm" wire:model="perPage" style="width:70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
        @can('items.create')
        <a class="btn btn-primary" href="{{ route('tenant.items.create', ['tenant' => $tenantCode]) }}">
            + {{ __('Nouvel article') }}
        </a>
        @endcan
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Désignation') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Réf. / SKU') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Catégorie') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Unité') }}</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Prix vente') }}</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Coût achat') }}</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Marge') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $pv = (float) $item->price;
                            $pa = (float) $item->cost;
                            $margin = $pv > 0 ? round(($pv - $pa) / $pv * 100, 1) : null;
                            $marginColor = $margin === null ? 'text-slate-400'
                                         : ($margin >= 20 ? 'text-emerald-600' : ($margin >= 0 ? 'text-amber-600' : 'text-red-600'));
                        @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="item-{{ $item->id }}">
                            <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $item->name }}</td>
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">{{ $item->sku ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $item->category?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $item->unit?->abbreviation ?? $item->unit?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-slate-700">
                                {{ number_format($pv, 0, ',', ' ') }} {{ $cur }}
                            </td>
                            <td class="px-4 py-2.5 text-right text-slate-500">
                                {{ number_format($pa, 0, ',', ' ') }} {{ $cur }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-semibold {{ $marginColor }}">
                                {{ $margin !== null ? $margin . ' %' : '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($item->is_active)
                                    <span class="badge badge-success">{{ __('Actif') }}</span>
                                @else
                                    <span class="badge badge-warning">{{ __('Inactif') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1">
                                    @can('items.edit')
                                    <a class="table-action table-action-edit"
                                        href="{{ route('tenant.items.edit', [$item->id, 'tenant' => $tenantCode]) }}"
                                        title="{{ __('Modifier') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @endcan
                                    @can('items.delete')
                                    <button class="table-action table-action-delete"
                                        wire:click="delete({{ $item->id }})"
                                        wire:confirm="{{ __('Supprimer cet article ?') }}"
                                        title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-400 text-sm">
                                {{ __('Aucun article dans le catalogue.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $items->appends(['tenant' => $tenantCode])->links() }}</div>
    </div>
</div>
