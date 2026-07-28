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
                <option value="open">{{ __('Ouvert') }}</option>
                <option value="assigned">{{ __('Assigné') }}</option>
                <option value="in_progress">{{ __('En cours') }}</option>
                <option value="done">{{ __('Terminé') }}</option>
                <option value="closed">{{ __('Clôturé') }}</option>
                <option value="cancelled">{{ __('Annulé') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="priority">
                <option value="">{{ __('Toutes priorités') }}</option>
                <option value="critical">{{ __('Critique') }}</option>
                <option value="high">{{ __('Haute') }}</option>
                <option value="normal">{{ __('Normale') }}</option>
                <option value="low">{{ __('Basse') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.maintenance.orders.create', ['tenant' => $tenantCode]) }}">+ {{ __('Nouvel ordre') }}</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Client') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Type') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Priorité') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Signalé le') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('SLA') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Assigné à') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $slaBreached = $order->isSlaBreached();
                            $slaHours    = $order->slaHoursRemaining();
                            $priorityBg = match($order->priority) {
                                'critical' => 'bg-red-100 text-red-700',
                                'high'     => 'bg-amber-100 text-amber-700',
                                'normal'   => 'bg-blue-100 text-blue-600',
                                default    => 'bg-slate-100 text-slate-500',
                            };
                            $priorityLabel = match($order->priority) {
                                'critical' => __('Critique'),
                                'high'     => __('Haute'),
                                'normal'   => __('Normale'),
                                default    => __('Basse'),
                            };
                            $statusBg = match($order->status) {
                                'open'        => 'bg-slate-100 text-slate-600',
                                'assigned'    => 'bg-blue-100 text-blue-600',
                                'in_progress' => 'bg-indigo-100 text-indigo-700',
                                'done'        => 'bg-emerald-100 text-emerald-700',
                                'closed'      => 'bg-slate-100 text-slate-500',
                                default       => 'bg-red-100 text-red-700',
                            };
                            $statusLabel = match($order->status) {
                                'open'        => __('Ouvert'),
                                'assigned'    => __('Assigné'),
                                'in_progress' => __('En cours'),
                                'done'        => __('Terminé'),
                                'closed'      => __('Clôturé'),
                                default       => __('Annulé'),
                            };
                        @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors {{ $slaBreached ? 'bg-red-50' : '' }}" wire:key="order-{{ $order->id }}">
                            <td class="px-4 py-2.5 font-mono text-[11px] font-semibold text-slate-600">{{ $order->code }}</td>
                            <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $order->title }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $order->client?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500 text-[11px]">
                                @if($order->type === 'corrective') {{ __('Correctif') }}
                                @elseif($order->type === 'preventive') {{ __('Préventif') }}
                                @else <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-700">{{ __('Urgence') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $priorityBg }}">{{ $priorityLabel }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $order->reported_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                @if($order->due_at)
                                    @if($slaBreached)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-700" title="{{ __('SLA dépassé') }}">
                                            -{{ abs((int)$slaHours) }}h
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700" title="{{ $order->due_at->format('d/m/Y H:i') }}">
                                            {{ (int)$slaHours }}h
                                        </span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $order->assignedUser?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBg }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1">
                                    <a class="table-action table-action-edit"
                                       href="{{ route('tenant.maintenance.orders.edit', ['tenant' => $tenantCode, 'maintenance_order' => $order->id]) }}"
                                       title="{{ __('Modifier') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <a class="table-action"
                                       href="{{ route('tenant.maintenance.interventions.create', ['tenant' => $tenantCode, 'maintenance_order' => $order->id]) }}"
                                       title="{{ __('Planifier intervention') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </a>
                                    <button type="button" class="table-action table-action-delete"
                                            wire:click="delete({{ $order->id }})"
                                            wire:confirm="{{ __('Supprimer cet ordre ?') }}"
                                            title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun ordre de maintenance.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $orders->appends(['tenant' => $tenantCode])->links() }}</div>
    </div>
</div>
