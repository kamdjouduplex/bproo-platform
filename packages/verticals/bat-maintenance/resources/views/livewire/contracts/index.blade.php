@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div>
    @include('inovcom-maintenance::livewire._tabs')

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <input class="input input-sm" placeholder="{{ __('Recherche...') }}" wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="status">
                <option value="">{{ __('Tous les statuts') }}</option>
                <option value="active">{{ __('Actif') }}</option>
                <option value="suspended">{{ __('Suspendu') }}</option>
                <option value="expired">{{ __('Expiré') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.maintenance.contracts.create', ['tenant' => $tenantCode]) }}">+ {{ __('Nouveau contrat') }}</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Client') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Type') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Début') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Fin') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Réponse SLA') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contracts as $contract)
                        @php
                            $typeLabel = match($contract->type) {
                                'preventive'   => __('Préventif'),
                                'corrective'   => __('Correctif'),
                                default        => __('Tous services'),
                            };
                            $statusBg = match($contract->status) {
                                'active'    => 'bg-emerald-100 text-emerald-700',
                                'suspended' => 'bg-amber-100 text-amber-700',
                                default     => 'bg-slate-100 text-slate-600',
                            };
                            $statusLabel = match($contract->status) {
                                'active'    => __('Actif'),
                                'suspended' => __('Suspendu'),
                                default     => __('Expiré'),
                            };
                        @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="contract-{{ $contract->id }}">
                            <td class="px-4 py-2.5 font-mono text-[11px] font-semibold text-slate-600">{{ $contract->code }}</td>
                            <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $contract->client?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $contract->title ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500 text-[11px]">{{ $typeLabel }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $contract->start_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $contract->end_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $contract->response_time ? $contract->response_time . 'h' : '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBg }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1">
                                    <a class="table-action table-action-edit"
                                       href="{{ route('tenant.maintenance.contracts.edit', ['tenant' => $tenantCode, 'maintenance_contract' => $contract->id]) }}"
                                       title="{{ __('Modifier') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button type="button" class="table-action table-action-delete"
                                            wire:click="delete({{ $contract->id }})"
                                            wire:confirm="{{ __('Supprimer ce contrat ?') }}"
                                            title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun contrat.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $contracts->appends(['tenant' => $tenantCode])->links() }}</div>
    </div>
</div>
