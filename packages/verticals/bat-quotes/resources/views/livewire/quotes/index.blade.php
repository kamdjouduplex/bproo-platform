@php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 flex-1 min-w-0">
            <input class="input input-sm flex-1 max-w-[240px]"
                   placeholder="{{ __('Recherche…') }}"
                   wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="statusFilter" style="width:130px;">
                <option value="">{{ __('Tous statuts') }}</option>
                <option value="draft">{{ __('Brouillon') }}</option>
                <option value="sent">{{ __('Envoyé') }}</option>
                <option value="accepted">{{ __('Accepté') }}</option>
                <option value="refused">{{ __('Refusé') }}</option>
                <option value="expired">{{ __('Expiré') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            @if($canImport ?? false)
            <a class="btn btn-secondary"
               href="{{ route('tenant.devis.create', ['tenant' => $tenantCode, 'import' => 1]) }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                {{ __('Importer devis client') }}
            </a>
            @endif
            @if($canCreate ?? true)
            <a class="btn btn-primary" href="{{ route('tenant.devis.create', ['tenant' => $tenantCode]) }}">
                + {{ __('Nouveau devis') }}
            </a>
            @endif
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Client') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Offre') }}</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Total HT') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Validité') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($quotes as $quote)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="quote-{{ $quote->id }}">
                        <td class="px-4 py-2.5 font-mono text-xs">
                            <a href="{{ route('tenant.devis.show', ['tenant' => $tenantCode, 'quote' => $quote->id]) }}"
                               class="text-indigo-600 hover:underline font-semibold">{{ $quote->code }}</a>
                            @if(($quote->family_size ?? 1) > 1)
                                <span class="text-[10px] text-violet-600 font-semibold">v{{ $quote->version ?? 1 }}</span>
                                <span class="block text-[10px] text-slate-400 font-normal">{{ __(':n versions', ['n' => $quote->family_size]) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-medium text-slate-800 max-w-[200px] truncate">
                            <a href="{{ route('tenant.devis.show', ['tenant' => $tenantCode, 'quote' => $quote->id]) }}"
                               class="hover:text-indigo-600 transition-colors">{{ $quote->title }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $quote->client?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-400 font-mono text-xs">{{ $quote->offer?->code ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right font-medium text-slate-700">{{ number_format($quote->total_ht, 0, ',', ' ') }}</td>
                        <td class="px-4 py-2.5 text-slate-400">{{ $quote->valid_until?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @php
                                $statusClass = match($quote->status) {
                                    'draft'    => 'bg-slate-100 text-slate-500',
                                    'sent'     => 'bg-blue-100 text-blue-700',
                                    'accepted' => 'bg-emerald-100 text-emerald-700',
                                    'refused'  => 'bg-red-100 text-red-700',
                                    'expired'  => 'bg-amber-100 text-amber-800',
                                    default    => 'bg-slate-100 text-slate-400',
                                };
                                $statusLabel = match($quote->status) {
                                    'draft'    => __('Brouillon'),
                                    'sent'     => __('Envoyé'),
                                    'accepted' => __('Accepté'),
                                    'refused'  => __('Refusé'),
                                    'expired'  => __('Expiré'),
                                    default    => $quote->status,
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1">
                                <a class="table-action"
                                   href="{{ route('tenant.devis.show', ['tenant' => $tenantCode, 'quote' => $quote->id]) }}"
                                   title="{{ __('Voir') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @if($canCreate ?? false)
                                <button type="button" class="table-action"
                                        wire:click="duplicate({{ $quote->id }})"
                                        wire:confirm="{{ __('Créer une copie indépendante de ce devis ?') }}"
                                        title="{{ __('Dupliquer') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun devis.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $quotes->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </div>
</div>
