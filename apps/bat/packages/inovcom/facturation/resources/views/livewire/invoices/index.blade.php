@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 flex-1 min-w-0">
            <input class="input input-sm flex-1 max-w-[240px]"
                   placeholder="{{ __('Recherche...') }}"
                   wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.facturation.create', ['tenant' => $tenantCode]) }}">
            + {{ __('Nouvelle facture') }}
        </a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Client') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Projet') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Date') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Échéance') }}</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Total TTC') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                    @php
                        $statusClass = match($invoice->status) {
                            'draft'     => 'bg-slate-100 text-slate-600',
                            'sent'      => 'bg-blue-100 text-blue-700',
                            'paid'      => 'bg-emerald-100 text-emerald-700',
                            'overdue'   => 'bg-amber-100 text-amber-700',
                            'cancelled' => 'bg-slate-100 text-slate-400',
                            default     => 'bg-slate-100 text-slate-500',
                        };
                        $statusLabel = match($invoice->status) {
                            'draft'     => __('Brouillon'),
                            'sent'      => __('Envoyée'),
                            'paid'      => __('Payée'),
                            'overdue'   => __('En retard'),
                            'cancelled' => __('Annulée'),
                            default     => $invoice->status,
                        };
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="invoice-{{ $invoice->id }}">
                        <td class="px-4 py-2.5 font-mono text-xs text-indigo-600">{{ $invoice->code }}</td>
                        <td class="px-4 py-2.5 text-slate-700 font-medium">{{ $invoice->client?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-400 font-mono text-xs">{{ $invoice->project?->code ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $invoice->issue_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right font-medium text-slate-700">{{ number_format($invoice->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1">
                                <a class="table-action table-action-edit"
                                   href="{{ route('tenant.facturation.edit', ['tenant' => $tenantCode, 'invoice' => $invoice->id]) }}"
                                   title="{{ __('Modifier') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button type="button" class="table-action table-action-delete"
                                        wire:click="delete({{ $invoice->id }})"
                                        wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer cette facture ?') }}"
                                        title="{{ __('Supprimer') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucune facture.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $invoices->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </div>
</div>
