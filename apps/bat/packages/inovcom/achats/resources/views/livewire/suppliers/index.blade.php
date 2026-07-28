@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <input class="input input-sm" placeholder="{{ __('Recherche...') }}" wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option><option value="25">25</option><option value="50">50</option>
            </select>
            <a class="btn btn-secondary" href="{{ route('tenant.achats.index', ['tenant' => $tenantCode]) }}">{{ __('Bons de commande') }}</a>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.achats.suppliers.create', ['tenant' => $tenantCode]) }}">+ {{ __('Nouveau fournisseur') }}</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Nom') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Contact') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Email') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Téléphone') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="supplier-{{ $supplier->id }}">
                        <td class="px-4 py-2.5 font-mono text-[11px] font-semibold text-slate-600">{{ $supplier->code }}</td>
                        <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $supplier->name }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $supplier->contact_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $supplier->email ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $supplier->phone ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if($supplier->is_active)
                                <span class="badge badge-success">{{ __('Actif') }}</span>
                            @else
                                <span class="badge badge-danger">{{ __('Inactif') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1">
                                <a class="table-action table-action-edit"
                                   href="{{ route('tenant.achats.suppliers.edit', ['tenant' => $tenantCode, 'supplier' => $supplier->id]) }}"
                                   title="{{ __('Modifier') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button type="button" class="table-action table-action-delete"
                                        wire:click="delete({{ $supplier->id }})"
                                        wire:confirm="{{ __('Supprimer ce fournisseur ?') }}"
                                        title="{{ __('Supprimer') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun fournisseur.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $suppliers->appends(['tenant' => $tenantCode])->links() }}</div>
    </div>
</div>
