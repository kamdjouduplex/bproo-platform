@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <input class="input input-sm" placeholder="{{ __('Recherche...') }}"
                   wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <a class="btn btn-secondary" href="{{ route('tenant.achats.suppliers.index', ['tenant' => $tenantCode]) }}">
                {{ __('Fournisseurs') }}
            </a>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.achats.create', ['tenant' => $tenantCode]) }}">
            + {{ __('Nouveau bon') }}
        </a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Fournisseur') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Projet') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Total HT') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Commandé le') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        @php
                            $statusBg = match($po->status) {
                                'draft'              => 'bg-slate-100 text-slate-600',
                                'pending_validation' => 'bg-amber-100 text-amber-700',
                                'validated'          => 'bg-blue-100 text-blue-700',
                                'ordered'            => 'bg-violet-100 text-violet-700',
                                'received'           => 'bg-emerald-100 text-emerald-700',
                                'partially_received' => 'bg-teal-100 text-teal-700',
                                'cancelled'          => 'bg-red-100 text-red-700',
                                default              => 'bg-slate-100 text-slate-500',
                            };
                            $statusLabel = match($po->status) {
                                'draft'              => __('Brouillon'),
                                'pending_validation' => __('En attente'),
                                'validated'          => __('Validé'),
                                'ordered'            => __('Commandé'),
                                'received'           => __('Reçu'),
                                'partially_received' => __('Partiel'),
                                'cancelled'          => __('Annulé'),
                                default              => $po->status,
                            };
                        @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="po-{{ $po->id }}">
                            <td class="px-4 py-2.5 font-mono text-[11px] font-semibold text-slate-600">{{ $po->code }}</td>
                            <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $po->supplier?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">{{ $po->project?->code ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-700 font-medium">{{ number_format($po->total_ht, 0, ',', ' ') }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $po->ordered_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBg }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1">
                                    <a class="table-action table-action-edit"
                                       href="{{ route('tenant.achats.edit', ['tenant' => $tenantCode, 'purchase_order' => $po->id]) }}"
                                       title="{{ __('Modifier') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button type="button" class="table-action table-action-delete"
                                            wire:click="delete({{ $po->id }})"
                                            wire:confirm="{{ __('Supprimer ce bon de commande ?') }}"
                                            title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun bon de commande.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $purchaseOrders->appends(['tenant' => $tenantCode])->links() }}</div>
    </div>
</div>
