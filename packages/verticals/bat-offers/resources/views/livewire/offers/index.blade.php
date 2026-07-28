@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $statusClass = [
        'draft'      => 'bg-slate-100 text-slate-600',
        'in_review'  => 'bg-indigo-100 text-indigo-700',
        'terminated' => 'bg-emerald-100 text-emerald-700',
        'closed'     => 'bg-red-100 text-red-700',
    ];
    $statusLabel = [
        'draft'      => __('Brouillon'),
        'in_review'  => __('En révision'),
        'terminated' => __('Traitée'),
        'closed'     => __('Clôturée'),
    ];
    $sourceLabels = [
        'call_for_tender' => __("Appel d'offres"),
        'recommendation'  => __('Recommandation'),
        'existing_client' => __('Client existant'),
    ];
@endphp
<div>
    {{-- ── Toolbar ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <input class="input input-sm" placeholder="{{ __('Recherche…') }}"
                   wire:model.live.debounce.400ms="search" style="min-width:180px;">
            <select class="input input-sm" wire:model.live="filterStatus">
                <option value="">{{ __('Tous statuts') }}</option>
                <option value="draft">{{ __('Brouillon') }}</option>
                <option value="in_review">{{ __('En révision') }}</option>
                <option value="terminated">{{ __('Terminée') }}</option>
                <option value="closed">{{ __('Clôturée') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="filterCategory">
                <option value="">{{ __('Toutes catégories') }}</option>
                @foreach(\InovCom\Kernel\Support\ServiceCatalog::offerCategories() as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.offres.create', ['tenant' => $tenantCode]) }}">
            + {{ __('Nouvelle offre') }}
        </a>
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Intitulé') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Catégorie') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Client') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Assigné à') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Source') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Reçue le') }}</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($offers as $offer)
                    @php
                        $sc = $statusClass[$offer->status] ?? 'bg-slate-100 text-slate-500';
                        $sl = $statusLabel[$offer->status] ?? $offer->status;
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="offer-{{ $offer->id }}">
                        <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400 whitespace-nowrap">{{ $offer->code }}</td>
                        <td class="px-4 py-2.5 font-semibold text-slate-800 max-w-[220px]">
                            <a href="{{ route('tenant.offres.edit', ['tenant' => $tenantCode, 'offer' => $offer->id]) }}"
                               class="hover:text-blue-600 transition-colors">{{ $offer->title }}</a>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="badge {{ \InovCom\Kernel\Support\ServiceCatalog::offerCategoryBadgeClass($offer->category) }}">
                                {{ \InovCom\Kernel\Support\ServiceCatalog::offerCategoryLabel($offer->category) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $offer->client?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if ($offer->assignedUser)
                                <span class="text-[12px] text-slate-600">{{ $offer->assignedUser->name }}</span>
                            @else
                                <span class="text-[12px] text-slate-300">{{ __('Non assigné') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-[12px] text-slate-500">
                            @if ($offer->source)
                                <div>{{ $sourceLabels[$offer->source] ?? $offer->source }}</div>
                            @endif
                            @if ($offer->channel)
                                <div class="text-[11px] text-slate-400">
                                    {{ \InovCom\Offres\Models\Offer::CHANNELS[$offer->channel] ?? $offer->channel }}
                                    @if ($offer->channel_detail) — {{ Str::limit($offer->channel_detail, 25) }} @endif
                                </div>
                            @endif
                            @if (!$offer->source && !$offer->channel) <span class="text-slate-300">—</span> @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $sl }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-[12px] text-slate-400 whitespace-nowrap">{{ $offer->received_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center justify-end gap-1.5">
                                @if (!in_array($offer->status, ['terminated', 'closed']))
                                    <button type="button"
                                        class="inline-flex items-center px-2.5 py-1 text-[11px] font-medium rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition-colors whitespace-nowrap"
                                        wire:click="openProcessModal({{ $offer->id }})">
                                        {{ __('Traiter') }} →
                                    </button>
                                @endif
                                <a class="table-action table-action-edit"
                                   href="{{ route('tenant.offres.edit', ['tenant' => $tenantCode, 'offer' => $offer->id]) }}"
                                   title="{{ __('Modifier') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @if ($offer->status === 'draft')
                                    <button type="button" class="table-action table-action-delete"
                                        wire:click="delete({{ $offer->id }})"
                                        wire:confirm="{{ __('Supprimer cette offre ?') }}"
                                        title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-8 h-8 text-slate-300 mx-auto mb-2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="text-slate-400 text-sm">{{ __('Aucune offre ne correspond à vos critères.') }}</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($offers->hasPages())
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $offers->appends(['tenant' => $tenantCode])->links() }}
        </div>
        @endif
    </div>

    {{-- ══ PROCESS MODAL ════════════════════════════════════════════════ --}}
    <div x-show="$wire.showProcessModal"
         wire:click.self="closeProcessModal"
         class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/55 backdrop-blur-sm"
         style="display:none;">
        @if ($processingOffer !== null)
        @php
            $po  = $processingOffer;
            $psc = $statusClass[$po->status] ?? 'bg-slate-100 text-slate-500';
            $psl = $statusLabel[$po->status] ?? $po->status;
        @endphp
        <div class="bg-white rounded-2xl w-[540px] max-w-[calc(100vw-32px)] shadow-2xl overflow-hidden">

            {{-- Head --}}
            <div class="flex items-start justify-between px-6 py-5 border-b border-slate-200 bg-slate-50">
                <div>
                    <div class="text-[15px] font-bold text-slate-900">{{ __('Traiter l\'offre') }}</div>
                    <div class="font-mono text-[12px] text-slate-400 mt-0.5">{{ $po->code }}</div>
                    <div class="text-[13px] font-semibold text-slate-700 mt-1.5">{{ $po->title }}</div>
                </div>
                <div class="flex items-center gap-2.5">
                    <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap {{ $psc }}">{{ $psl }}</span>
                    <button type="button" class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-800 transition-colors" wire:click="closeProcessModal">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Meta --}}
            <div class="grid grid-cols-2 gap-3 px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">{{ __('Client') }}</div>
                    <div class="text-[13px] font-semibold text-slate-800">{{ $po->client?->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">{{ __('Assigné à') }}</div>
                    <div class="text-[13px] font-semibold text-slate-800">
                        @if ($po->assignedUser) {{ $po->assignedUser->name }}
                        @else <span class="text-amber-500">⚠ {{ __('Non assigné') }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">{{ __('Catégorie') }}</div>
                    <div class="text-[13px] font-semibold text-slate-800">{{ \InovCom\Kernel\Support\ServiceCatalog::offerCategoryLabel($po->category) }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">{{ __('Reçue le') }}</div>
                    <div class="text-[13px] font-semibold text-slate-800">{{ $po->received_at?->format('d/m/Y') ?? '—' }}</div>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5">
                @error('process')
                    <div class="flex items-start gap-2 px-3.5 py-2.5 bg-red-50 border border-red-200 rounded-lg mb-4 text-[13px] text-red-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 flex-shrink-0 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $message }}
                    </div>
                @enderror

                @if ($po->status === 'draft')
                    <div class="flex items-center justify-between gap-4 px-4 py-3.5 rounded-xl border border-indigo-200 bg-indigo-50">
                        <div>
                            <div class="text-[13px] font-bold text-slate-900 mb-1">{{ __('Passer en révision') }}</div>
                            @if (!$po->assigned_to)
                                <div class="text-[12px] text-amber-700">{{ __('⚠ Assignez d\'abord un collaborateur via Modifier.') }}</div>
                            @else
                                <div class="text-[12px] text-slate-600">{{ __('Soumis à') }} <strong>{{ $po->assignedUser->name }}</strong> {{ __('pour analyse.') }}</div>
                            @endif
                        </div>
                        <button type="button" class="btn btn-primary btn-sm whitespace-nowrap flex-shrink-0"
                            wire:click="moveToInReview" wire:loading.attr="disabled" wire:target="moveToInReview"
                            @if(!$po->assigned_to) disabled @endif>
                            <span wire:loading.remove wire:target="moveToInReview">{{ __('En révision') }} →</span>
                            <span wire:loading wire:target="moveToInReview">...</span>
                        </button>
                    </div>

                @elseif ($po->status === 'in_review')
                    <div class="flex flex-col gap-3">
                        {{-- Create quote --}}
                        <div class="flex items-center justify-between gap-4 px-4 py-3.5 rounded-xl border border-indigo-200 bg-indigo-50">
                            <div>
                                <div class="text-[13px] font-bold text-slate-900 mb-1">{{ __('Créer un devis') }}</div>
                                <div class="text-[12px] text-slate-600">{{ __('L\'offre passera en Terminée et vous serez redirigé vers le devis.') }}</div>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm whitespace-nowrap flex-shrink-0"
                                wire:click="createQuote" wire:loading.attr="disabled" wire:target="createQuote">
                                <span wire:loading.remove wire:target="createQuote">{{ __('Créer le devis') }} →</span>
                                <span wire:loading wire:target="createQuote">...</span>
                            </button>
                        </div>

                        {{-- Close --}}
                        @if ($canCloseOffer)
                        <div x-show="!$wire.showCloseForm">
                            <div class="flex items-center justify-between gap-4 px-4 py-3.5 rounded-xl border border-red-200 bg-red-50">
                                <div>
                                    <div class="text-[13px] font-bold text-slate-900 mb-1">{{ __('Clôturer l\'offre') }}</div>
                                    <div class="text-[12px] text-slate-600">{{ __('Marquer comme non retenue. Action irréversible.') }}</div>
                                </div>
                                <button type="button" class="btn btn-sm border-red-300 text-red-700 bg-white hover:bg-red-50 whitespace-nowrap flex-shrink-0"
                                    wire:click="openCloseForm">{{ __('Clôturer') }}</button>
                            </div>
                        </div>
                        <div x-show="$wire.showCloseForm" class="bg-red-50 border border-red-200 rounded-xl p-4" style="display:none;">
                            <div class="text-[13px] font-bold text-red-800 mb-3">{{ __('Motif de clôture — obligatoire') }}</div>
                            <div class="field mb-3">
                                <label class="field-label">{{ __('Type de motif') }} <span class="text-red-500">*</span></label>
                                <select wire:model="closeReason" class="input border-red-200">
                                    <option value="">— {{ __('Sélectionnez un motif') }} —</option>
                                    @foreach ($closeReasons as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('closeReason') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field mb-4">
                                <label class="field-label">{{ __('Note explicative') }} <span class="text-red-500">*</span></label>
                                <textarea wire:model="closeNote" rows="3" class="input border-red-200"
                                    placeholder="{{ __('Expliquez brièvement (min. 10 caractères)...') }}"></textarea>
                                @error('closeNote') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelCloseForm">{{ __('Annuler') }}</button>
                                <button type="button" class="btn btn-sm border-red-300 text-red-700 bg-white hover:bg-red-50"
                                    wire:click="closeOffer" wire:loading.attr="disabled" wire:target="closeOffer">
                                    <span wire:loading.remove wire:target="closeOffer">{{ __('Confirmer la clôture') }}</span>
                                    <span wire:loading wire:target="closeOffer">...</span>
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>

                @elseif ($po->status === 'terminated')
                    <div class="text-center py-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-9 h-9 text-emerald-400 mx-auto mb-2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-[13px] font-bold text-emerald-700 mb-1">{{ __('Offre traitée') }}</p>
                        <p class="text-[13px] text-slate-500">{{ __("Aucune action supplémentaire n'est possible.") }}</p>
                        @if ($po->quote_id && $po->quote)
                        <div class="inline-flex items-center gap-2 mt-3 px-3.5 py-2 bg-emerald-50 border border-emerald-200 rounded-lg text-[13px]">
                            <span class="text-emerald-700 font-semibold">{{ __('Devis généré :') }}</span>
                            <a href="{{ route('tenant.devis.edit', ['tenant' => $tenantCode, 'quote' => $po->quote_id]) }}"
                               class="font-bold font-mono text-emerald-700 underline">{{ $po->quote->code }}</a>
                        </div>
                        @endif
                    </div>
                @elseif ($po->status === 'closed')
                    <div class="text-center py-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-9 h-9 text-red-300 mx-auto mb-2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-[13px] font-bold text-red-800 mb-1">{{ __('Offre clôturée') }}</p>
                        @if ($po->close_reason)
                        <div class="text-left mt-3 bg-red-50 border border-red-200 rounded-xl p-3.5">
                            <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">{{ __('Motif') }}</div>
                            <div class="text-[13px] font-semibold text-red-800 mb-1.5">{{ $closeReasons[$po->close_reason] ?? $po->close_reason }}</div>
                            @if ($po->close_note)
                                <div class="text-[13px] text-slate-600 leading-relaxed border-t border-red-200 pt-2 whitespace-pre-line">{{ $po->close_note }}</div>
                            @endif
                        </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex justify-end px-6 py-3.5 border-t border-slate-200 bg-slate-50">
                <button type="button" class="btn btn-secondary" wire:click="closeProcessModal">{{ __('Fermer') }}</button>
            </div>
        </div>
        @endif
    </div>
</div>
