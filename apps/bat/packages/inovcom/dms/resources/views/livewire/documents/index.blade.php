@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $usageMb    = round($usageBytes / 1048576, 1);
@endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-base font-semibold text-slate-800">{{ __('Documents') }}</span>
            <span class="text-[12px] text-slate-400">{{ $usageMb }} MB {{ __('utilisés') }}</span>
            <input class="input input-sm" placeholder="{{ __('Recherche...') }}" wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="category">
                <option value="">{{ __('Toutes catégories') }}</option>
                <option value="contract">{{ __('Contrat') }}</option>
                <option value="plan">{{ __('Plan') }}</option>
                <option value="permit">{{ __('Permis') }}</option>
                <option value="photo">{{ __('Photo') }}</option>
                <option value="report">{{ __('Rapport') }}</option>
                <option value="invoice">{{ __('Facture') }}</option>
                <option value="quote">{{ __('Devis') }}</option>
                <option value="other">{{ __('Autre') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="15">15</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.dms.upload', ['tenant' => $tenantCode]) }}">+ {{ __('Téléverser') }}</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Catégorie') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Fichier') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Taille') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Version') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Téléversé par') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Date') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $doc)
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="doc-{{ $doc->id }}">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    @if($doc->isImage())
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16" class="text-violet-500 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @elseif($doc->isPdf())
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16" class="text-red-500 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16" class="text-slate-400 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    @endif
                                    <span class="font-medium text-slate-700">{{ $doc->title }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">{{ $doc->categoryLabel() }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-[11px] text-slate-400 font-mono">{{ $doc->filename }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $doc->humanFileSize() }}</td>
                            <td class="px-4 py-2.5">
                                @if($doc->versions->count() > 0)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-600">v{{ $doc->version }} ({{ $doc->versions->count() + 1 }} {{ __('vers.') }})</span>
                                @else
                                    <span class="text-[11px] text-slate-400">v{{ $doc->version }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $doc->uploader?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $doc->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1">
                                    <a class="table-action"
                                       href="{{ route('tenant.dms.download', ['tenant' => $tenantCode, 'document' => $doc->id]) }}"
                                       title="{{ __('Télécharger') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                    <button type="button" class="table-action table-action-delete"
                                            wire:click="delete({{ $doc->id }})"
                                            wire:confirm="{{ __('Supprimer ce document ?') }}"
                                            title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun document.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $documents->appends(['tenant' => $tenantCode])->links() }}</div>
    </div>
</div>
