@php
    $statusClass = match($quote->status) {
        'draft'    => 'bg-slate-100 text-slate-600',
        'sent'     => 'bg-blue-100 text-blue-700',
        'accepted' => 'bg-emerald-100 text-emerald-700',
        'refused'  => 'bg-red-100 text-red-700',
        'expired'  => 'bg-amber-100 text-amber-800',
        default    => 'bg-slate-100 text-slate-500',
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
<div class="page-body">

    {{-- ── Modal refus (top-layer, centré) ─────────────────────────────── --}}
    @if($showRefuseModal)
    <dialog id="refuse-dialog"
            wire:ignore.self
            class="refuse-dialog rounded-xl border-0 p-0 w-full max-w-lg shadow-2xl"
            x-data
            x-init="$nextTick(() => $el.showModal())"
            @cancel.prevent="$wire.call('cancelRefuseQuote')">
        <div class="px-5 py-4 border-b border-slate-200 bg-red-50/80">
            <h3 class="text-base font-semibold text-red-950">{{ __('Refuser le devis') }}</h3>
            <p class="text-sm text-red-800/80 mt-1">{{ __('Indiquez pourquoi le client refuse ce devis.') }}</p>
        </div>
        <div class="px-5 py-4 space-y-4">
            <div class="field">
                <label class="field-label">{{ __('Motif de refus') }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model.live="refuse_category">
                    <option value="">{{ __('— Choisir un motif —') }}</option>
                    @foreach($refuseCategories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('refuse_category') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">
                    {{ __('Commentaire') }}
                    @if($refuse_category === 'other')
                        <span class="text-red-500">*</span>
                    @else
                        <span class="text-slate-400 font-normal">({{ __('optionnel') }})</span>
                    @endif
                </label>
                <textarea class="input" wire:model="refuse_comment" rows="3"
                          placeholder="{{ __('Détails complémentaires pour le suivi commercial…') }}"></textarea>
                @error('refuse_comment') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelRefuseQuote">{{ __('Annuler') }}</button>
            <button type="button" class="btn btn-danger btn-sm" wire:click="confirmRefuseQuote">{{ __('Confirmer le refus') }}</button>
        </div>
    </dialog>
    @endif

    {{-- ── Modal acompte ───────────────────────────────────────────────── --}}
    @if($showAdvanceModal)
    <dialog id="advance-dialog"
            wire:ignore.self
            class="refuse-dialog rounded-xl border-0 p-0 w-full max-w-md shadow-2xl"
            x-data
            x-init="$nextTick(() => $el.showModal())"
            @cancel.prevent="$wire.call('closeAdvanceModal')">
        <div class="px-5 py-4 border-b border-slate-200 bg-indigo-50/80">
            <h3 class="text-base font-semibold text-indigo-950">{{ __('Facture d\'acompte') }}</h3>
            <p class="text-sm text-indigo-800/80 mt-1">{{ __('Pourcentage appliqué sur le reste à facturer (après déduction des acomptes déjà émis).') }}</p>
        </div>
        <div class="px-5 py-4 space-y-4">
            @if($billingSummary && $billingSummary->totalInvoicedTtc > 0)
            <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-900">
                <p>{{ __('Déjà facturé : :amount', ['amount' => number_format($billingSummary->totalInvoicedTtc, 0, ',', ' ') . ' ' . $quote->currency]) }}</p>
                <p class="font-semibold mt-1">{{ __('Reste à facturer : :amount', ['amount' => number_format($billingSummary->remainingToInvoiceTtc, 0, ',', ' ') . ' ' . $quote->currency]) }}</p>
            </div>
            @endif
            <div class="flex flex-wrap gap-2">
                @foreach([20, 30, 40, 50] as $preset)
                <button type="button"
                        class="px-3 py-1.5 text-sm font-semibold rounded-lg border transition-colors {{ (int)$advancePercent === $preset ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-200 hover:border-indigo-300' }}"
                        wire:click="setAdvancePercent({{ $preset }})">
                    {{ $preset }}%
                </button>
                @endforeach
            </div>
            <div class="field">
                <label class="field-label">{{ __('Pourcentage personnalisé') }}</label>
                <div class="flex items-center gap-2">
                    <input class="input flex-1" type="number" min="1" max="100" step="1"
                           wire:model.live="advancePercent">
                    <span class="text-sm text-slate-500 font-medium">%</span>
                </div>
                @error('advancePercent') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm">
                <p class="text-slate-500">{{ __('Montant estimé (TTC)') }}</p>
                <p class="text-lg font-bold text-slate-900 tabular-nums mt-0.5">
                    {{ number_format($advancePreviewAmount, 0, ',', ' ') }} {{ $quote->currency }}
                </p>
                @if($billingSummary)
                <p class="text-[11px] text-slate-400 mt-1">
                    {{ __(':percent% du reste à facturer', ['percent' => (int) $advancePercent]) }}
                    @if($billingSummary->quoteTotalTtc > 0)
                        — {{ __('soit :percent% du devis', ['percent' => number_format($billingSummary->effectivePercentOfQuoteTotal($advancePreviewAmount), 1, ',', ' ')]) }}
                    @endif
                </p>
                <p class="text-[11px] text-slate-400">{{ __('Base restante : :total', ['total' => number_format($billingSummary->remainingToInvoiceTtc, 0, ',', ' ') . ' ' . $quote->currency]) }}</p>
                @endif
            </div>
        </div>
        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="closeAdvanceModal">{{ __('Annuler') }}</button>
            <button type="button" class="btn btn-primary btn-sm" wire:click="confirmAdvanceInvoice">{{ __('Créer la facture d\'acompte') }}</button>
        </div>
    </dialog>
    @endif

    {{-- ── En-tête ─────────────────────────────────────────────────────── --}}
    <div class="card mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h1 class="text-lg font-bold font-mono text-indigo-700">{{ $quote->code }}</h1>
                    @if($versionFamily->count() > 1)
                        <span class="text-[11px] px-2 py-0.5 rounded bg-violet-50 text-violet-700 border border-violet-200 font-semibold">v{{ $quote->version }}</span>
                    @endif
                    <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
                <p class="text-base font-semibold text-slate-800 mb-3">{{ $quote->title }}</p>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('Client') }}</p>
                        <p class="font-medium text-slate-800">{{ $quote->client?->name ?? '—' }}</p>
                        @if($quote->client?->email)
                        <p class="text-xs text-slate-400">{{ $quote->client->email }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('Offre') }}</p>
                        <p class="font-medium text-slate-700">{{ $quote->offer?->code ?? '—' }}</p>
                        @if($quote->offer?->category)
                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-semibold {{ $offerCategoryBadge }}">
                            {{ $offerCategoryLabel }}
                        </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('Validité') }}</p>
                        <p class="font-medium text-slate-700">{{ $quote->valid_until?->format('d/m/Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('Total TTC') }}</p>
                        <p class="font-bold text-slate-900 tabular-nums">{{ number_format($quote->total_ttc, 0, ',', ' ') }} {{ $quote->currency }}</p>
                    </div>
                </div>

                @if($quote->refuse_reason)
                <p class="mt-3 text-sm text-red-800 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                    <span class="font-semibold">{{ __('Motif de refus') }} :</span> {{ $quote->refuse_reason }}
                </p>
                @endif
            </div>

            {{-- ── Actions ───────────────────────────────────────────── --}}
            <div class="flex flex-col items-end gap-2">
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="downloadPdf">{{ __('PDF') }}</button>
                    @if($canExport)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="downloadExcel">{{ __('Excel') }}</button>
                    @endif

                    @if(in_array('sent', $allowedTransitions) && $canSend)
                        @if(!$emailComposerOpened)
                        <button type="button" class="btn btn-primary btn-sm" wire:click="sendByEmail">
                            {{ __('Envoyer par e-mail') }}
                        </button>
                        @else
                        <button type="button" class="btn btn-primary btn-sm" wire:click="sendToClient"
                                wire:confirm="{{ __('Marquer ce devis comme envoyé ?') }}">
                            {{ __('Marquer envoyé') }}
                        </button>
                        @endif
                    @endif
                    @if(in_array($quote->status, ['sent','expired'], true) && $canSend)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="remindClientByEmail">{{ __('Relancer par e-mail') }}</button>
                    @endif

                    @if($quote->status === 'sent' && $canAccept)
                    <button type="button" class="btn btn-success btn-sm" wire:click="acceptQuote" wire:confirm="{{ __('Accepter ce devis ? Un projet sera créé, puis vous pourrez facturer (totale ou acompte).') }}">{{ __('Accepter') }}</button>
                    <button type="button" class="btn btn-danger btn-sm" wire:click="refuseQuote">{{ __('Refuser') }}</button>
                    @endif
                    @if($quote->status === 'expired' && in_array('refused', $allowedTransitions) && $canAccept)
                    <button type="button" class="btn btn-danger btn-sm" wire:click="refuseQuote">{{ __('Refuser') }}</button>
                    @endif
                    @if($quote->status === 'sent' && in_array('expired', $allowedTransitions) && $canSend)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="markExpired" wire:confirm="{{ __('Marquer ce devis comme expiré ?') }}">{{ __('Marquer expiré') }}</button>
                    @endif

                    @if($quote->status === 'accepted')
                        @if($acceptanceCycle['kind'] === 'contract')
                            @if($linkedContract)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="goToLinkedContract">{{ $acceptanceCycle['view_btn'] }}</button>
                            @elseif($canCreateExecution)
                            <button type="button" class="btn btn-primary btn-sm" wire:click="createLinkedExecution">{{ $acceptanceCycle['create_btn'] }}</button>
                            @endif
                        @else
                            @if($linkedProject)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="goToLinkedProject">{{ $acceptanceCycle['view_btn'] }}</button>
                            @elseif($canCreateExecution)
                            <button type="button" class="btn btn-primary btn-sm" wire:click="createLinkedExecution">{{ $acceptanceCycle['create_btn'] }}</button>
                            @endif
                        @endif
                    @endif

                    @if(in_array('draft', $allowedTransitions) && $canEdit)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="reopenToDraft" wire:confirm="{{ __('Réouvrir en brouillon pour modification ?') }}">{{ __('Réouvrir') }}</button>
                    @endif
                    @if($canCreate)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="reviseQuote"
                            wire:confirm="{{ __('Créer la version :version en brouillon ?', ['version' => $quote->version + 1]) }}">{{ __('Réviser') }}</button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="copyQuote"
                            wire:confirm="{{ __('Créer une copie indépendante de ce devis ?') }}">{{ __('Dupliquer') }}</button>
                    @endif
                </div>

                <div class="flex flex-wrap justify-end gap-2 pt-1 border-t border-slate-100 w-full">
                    @if($canEdit)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.devis.edit', ['tenant' => $tenantCode, 'quote' => $quote->id]) }}">{{ __('Modifier') }}</a>
                    @endif
                    @if($canDelete && $quote->status === 'draft')
                    <button type="button" class="btn btn-danger btn-sm" wire:click="deleteQuote" wire:confirm="{{ __('Supprimer ce devis ?') }}">{{ __('Supprimer') }}</button>
                    @endif
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.devis.index', ['tenant' => $tenantCode]) }}">{{ __('Retour') }}</a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Jalons ──────────────────────────────────────────────────────── --}}
    @if($quote->sent_at || $quote->accepted_at || $quote->last_reminder_at)
    <div class="mb-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        @if($quote->sent_at)
        <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-100"><span class="text-slate-400 block">{{ __('Envoyé le') }}</span><span class="font-medium text-slate-700">{{ $quote->sent_at->format('d/m/Y H:i') }}</span></div>
        @endif
        @if($quote->accepted_at)
        <div class="px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-100"><span class="text-emerald-600 block">{{ __('Accepté le') }}</span><span class="font-medium text-emerald-800">{{ $quote->accepted_at->format('d/m/Y H:i') }}</span></div>
        @endif
        @if($quote->last_reminder_at)
        <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-100"><span class="text-slate-400 block">{{ __('Dernière relance') }}</span><span class="font-medium text-slate-700">{{ $quote->last_reminder_at->format('d/m/Y H:i') }}</span></div>
        @endif
    </div>
    @endif

    {{-- ── Versions ────────────────────────────────────────────────────── --}}
    @if($versionFamily->count() > 1)
    <div class="mb-4 rounded-xl border border-violet-200 bg-violet-50/40 overflow-hidden">
        <div class="px-4 py-2.5 border-b border-violet-100 flex items-center justify-between">
            <span class="text-sm font-semibold text-violet-950">{{ __('Historique des versions') }}</span>
            <span class="text-[11px] text-violet-700">{{ __(':count versions', ['count' => $versionFamily->count()]) }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-violet-50/80 text-violet-800">
                        <th class="text-left font-semibold px-4 py-2">{{ __('Version') }}</th>
                        <th class="text-left font-semibold px-4 py-2">{{ __('Statut') }}</th>
                        <th class="text-right font-semibold px-4 py-2">{{ __('Total TTC') }}</th>
                        <th class="text-left font-semibold px-4 py-2">{{ __('Créée le') }}</th>
                        <th class="text-right font-semibold px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($versionFamily as $familyQuote)
                    @php
                        $fvClass = match($familyQuote->status) {
                            'draft' => 'bg-slate-100 text-slate-600', 'sent' => 'bg-blue-100 text-blue-700',
                            'accepted' => 'bg-emerald-100 text-emerald-700', 'refused' => 'bg-red-100 text-red-700',
                            'expired' => 'bg-amber-100 text-amber-800', default => 'bg-slate-100 text-slate-500',
                        };
                        $fvLabel = match($familyQuote->status) {
                            'draft' => __('Brouillon'), 'sent' => __('Envoyé'), 'accepted' => __('Accepté'),
                            'refused' => __('Refusé'), 'expired' => __('Expiré'), default => $familyQuote->status,
                        };
                    @endphp
                    <tr class="border-t border-violet-100 {{ (int)$familyQuote->id === (int)$quote->id ? 'bg-white' : '' }}" wire:key="fv-{{ $familyQuote->id }}">
                        <td class="px-4 py-2 font-mono font-semibold">v{{ $familyQuote->version }}</td>
                        <td class="px-4 py-2"><span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $fvClass }}">{{ $fvLabel }}</span></td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($familyQuote->total_ttc, 0, ',', ' ') }} {{ $familyQuote->currency }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $familyQuote->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2 text-right">
                            @if((int)$familyQuote->id === (int)$quote->id)
                                <span class="text-indigo-600 font-semibold">{{ __('Actuelle') }}</span>
                            @else
                                <a href="{{ route('tenant.devis.show', ['tenant' => $tenantCode, 'quote' => $familyQuote->id]) }}" class="text-indigo-600 hover:underline">{{ __('Voir') }}</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Cycle post-acceptation : Exécution → Facturation ───────────── --}}
    @if($quote->status === 'accepted')
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/30 overflow-hidden">
        <div class="px-4 py-3 border-b border-emerald-100">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-sm font-semibold text-emerald-950">{{ __('Suite du cycle commercial') }}</h3>
                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $offerCategoryBadge }}">
                    {{ $offerCategoryLabel }}
                </span>
            </div>
            <p class="text-xs text-emerald-800/70 mt-0.5">{{ $acceptanceCycle['intro'] }}</p>
        </div>
        <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Étape 1 : Exécution selon le type d'offre --}}
            <div class="rounded-lg border {{ $executionReady ? 'border-emerald-300 bg-white' : 'border-amber-200 bg-amber-50/50' }} p-4">
                <div class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold {{ $executionReady ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }}">1</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800">{{ $acceptanceCycle['step_title'] }}</p>
                        @if($acceptanceCycle['kind'] === 'contract')
                            @if($linkedContract)
                                <p class="text-xs text-slate-500 mt-1">{{ $acceptanceCycle['auto_created'] }}</p>
                                <p class="font-mono font-bold text-emerald-700 mt-2">{{ $linkedContract->code }}</p>
                                <p class="text-sm text-slate-700 truncate">{{ $linkedContract->title }}</p>
                                <button type="button" class="btn btn-secondary btn-sm mt-3" wire:click="goToLinkedContract">{{ $acceptanceCycle['open_btn'] }}</button>
                            @else
                                <p class="text-xs text-amber-800 mt-1">{{ $acceptanceCycle['required'] }}</p>
                                @if($canCreateExecution)
                                <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="createLinkedExecution">{{ $acceptanceCycle['create_btn'] }}</button>
                                @else
                                <p class="text-xs text-slate-500 mt-2">{{ $acceptanceCycle['module_missing'] }}</p>
                                @endif
                            @endif
                        @else
                            @if($linkedProject)
                                <p class="text-xs text-slate-500 mt-1">{{ $acceptanceCycle['auto_created'] }}</p>
                                <p class="font-mono font-bold text-emerald-700 mt-2">{{ $linkedProject->code }}</p>
                                <p class="text-sm text-slate-700 truncate">{{ $linkedProject->title }}</p>
                                <button type="button" class="btn btn-secondary btn-sm mt-3" wire:click="goToLinkedProject">{{ $acceptanceCycle['open_btn'] }}</button>
                            @else
                                <p class="text-xs text-amber-800 mt-1">{{ $acceptanceCycle['required'] }}</p>
                                @if($canCreateExecution)
                                <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="createLinkedExecution">{{ $acceptanceCycle['create_btn'] }}</button>
                                @else
                                <p class="text-xs text-slate-500 mt-2">{{ $acceptanceCycle['module_missing'] }}</p>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Étape 2 : Facturation --}}
            <div class="rounded-lg border {{ $executionReady && $canFacturation ? 'border-indigo-200 bg-white' : 'border-slate-200 bg-slate-50' }} p-4 {{ !$executionReady ? 'opacity-60' : '' }}">
                <div class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold {{ $executionReady ? 'bg-indigo-600 text-white' : 'bg-slate-300 text-white' }}">2</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800">{{ __('Facturation') }}</p>
                        @if(!$executionReady)
                            <p class="text-xs text-slate-500 mt-1">{{ $acceptanceCycle['invoicing_wait'] }}</p>
                        @elseif($canFacturation)
                            @if($billingSummary)
                            <div class="mt-2 mb-3 space-y-1.5 text-xs">
                                <div class="flex justify-between gap-2">
                                    <span class="text-slate-500">{{ __('Total devis') }}</span>
                                    <span class="font-semibold tabular-nums">{{ number_format($billingSummary->quoteTotalTtc, 0, ',', ' ') }} {{ $quote->currency }}</span>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <span class="text-slate-500">{{ __('Déjà facturé') }}</span>
                                    <span class="font-semibold text-indigo-700 tabular-nums">{{ number_format($billingSummary->totalInvoicedTtc, 0, ',', ' ') }} {{ $quote->currency }}</span>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <span class="text-slate-500">{{ __('Encaissé') }}</span>
                                    <span class="font-semibold text-emerald-700 tabular-nums">{{ number_format($billingSummary->totalPaidTtc, 0, ',', ' ') }} {{ $quote->currency }}</span>
                                </div>
                                <div class="flex justify-between gap-2 border-t border-slate-100 pt-1.5">
                                    <span class="text-slate-700 font-medium">{{ __('Reste à facturer') }}</span>
                                    <span class="font-bold text-slate-900 tabular-nums">{{ number_format($billingSummary->remainingToInvoiceTtc, 0, ',', ' ') }} {{ $quote->currency }}</span>
                                </div>
                                @if($billingSummary->quoteTotalTtc > 0)
                                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden mt-1">
                                    <div class="h-full bg-indigo-500 rounded-full" style="width: {{ min(100, $billingSummary->invoicedPercentOfQuote()) }}%"></div>
                                </div>
                                <p class="text-[10px] text-slate-400">{{ __(':percent% du devis facturé', ['percent' => number_format($billingSummary->invoicedPercentOfQuote(), 1, ',', ' ')]) }}</p>
                                @endif
                            </div>
                            @endif

                            @if($billingSummary && !$billingSummary->canInvoiceMore())
                                <p class="text-xs text-emerald-700 font-medium">{{ __('Devis entièrement facturé.') }}</p>
                            @elseif($billingSummary && $billingSummary->hasFinalInvoice && $billingSummary->remainingToInvoiceTtc <= 0.01)
                                <p class="text-xs text-emerald-700 font-medium">{{ __('Facture de solde émise.') }}</p>
                            @else
                                <p class="text-xs text-slate-500 mt-1">
                                    @if($billingSummary && $billingSummary->totalInvoicedTtc > 0)
                                        {{ __('Facture de solde ou nouvel acompte sur le reste à facturer.') }}
                                    @else
                                        {{ __('Facture totale ou acompte personnalisable (20 %, 30 %, 40 %…).') }}
                                    @endif
                                </p>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    @if(!$billingSummary || $billingSummary->canCreateFinalInvoice())
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="createInvoiceFromQuote('full')">
                                        {{ ($billingSummary && $billingSummary->totalInvoicedTtc > 0) ? __('Facture de solde') : __('Facture totale') }}
                                    </button>
                                    @endif
                                    @if(!$billingSummary || $billingSummary->canInvoiceMore())
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="openAdvanceModal">{{ __('Facture d\'acompte…') }}</button>
                                    @endif
                                </div>
                            @endif
                        @else
                            <p class="text-xs text-slate-500 mt-1">{{ __('Module facturation non disponible.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($quote->status === 'accepted' && $executionReady && $billingSummary)
    <div class="mb-4 rounded-xl border border-slate-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-800">{{ __('Suivi facturation & encaissements') }}</h3>
            <span class="text-[11px] text-slate-500">{{ __(':count document(s)', ['count' => $billingSummary->invoices->count()]) }}</span>
        </div>

        @if($billingSummary->invoices->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-600 border-b border-slate-100">
                        <th class="text-left font-semibold px-4 py-2">{{ __('Document') }}</th>
                        <th class="text-left font-semibold px-4 py-2">{{ __('Type') }}</th>
                        <th class="text-left font-semibold px-4 py-2">{{ __('Statut') }}</th>
                        <th class="text-right font-semibold px-4 py-2">{{ __('Montant TTC') }}</th>
                        <th class="text-right font-semibold px-4 py-2">{{ __('Encaissé') }}</th>
                        <th class="text-right font-semibold px-4 py-2">{{ __('Reste dû') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($billingSummary->invoices as $inv)
                    <tr class="border-b border-slate-50 hover:bg-slate-50/50" wire:key="bill-inv-{{ $inv['id'] }}">
                        <td class="px-4 py-2.5">
                            <a class="font-mono font-semibold text-indigo-600 hover:underline" href="{{ route('tenant.facturation.edit', ['tenant' => $tenantCode, 'invoice' => $inv['id']]) }}">
                                {{ $inv['code'] }}
                            </a>
                            @if($inv['issue_date'])
                            <span class="block text-[10px] text-slate-400 mt-0.5">{{ $inv['issue_date'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $inv['type_label'] }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $inv['status_class'] }}">{{ $inv['status_label'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-medium">{{ number_format($inv['total_ttc'], 0, ',', ' ') }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-emerald-700">{{ number_format($inv['amount_paid'], 0, ',', ' ') }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums {{ $inv['amount_due'] > 0 ? 'text-amber-700 font-semibold' : 'text-slate-400' }}">{{ number_format($inv['amount_due'], 0, ',', ' ') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-semibold text-slate-700">
                        <td class="px-4 py-2" colspan="3">{{ __('Totaux') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($billingSummary->totalInvoicedTtc, 0, ',', ' ') }} {{ $quote->currency }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-emerald-700">{{ number_format($billingSummary->totalPaidTtc, 0, ',', ' ') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($billingSummary->totalDueTtc, 0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @else
        <p class="px-4 py-4 text-sm text-slate-500">{{ __('Aucune facture liée à ce devis pour le moment.') }}</p>
        @endif

        @if($billingSummary->payments->isNotEmpty())
        <div class="border-t border-slate-100 px-4 py-3">
            <h4 class="text-xs font-semibold text-slate-700 mb-2">{{ __('Encaissements reçus') }}</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-slate-500 border-b border-slate-100">
                            <th class="text-left font-semibold py-1.5 pr-3">{{ __('Date') }}</th>
                            <th class="text-left font-semibold py-1.5 pr-3">{{ __('Facture') }}</th>
                            <th class="text-left font-semibold py-1.5 pr-3">{{ __('Reçu') }}</th>
                            <th class="text-left font-semibold py-1.5 pr-3">{{ __('Mode') }}</th>
                            <th class="text-right font-semibold py-1.5">{{ __('Montant') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($billingSummary->payments as $payment)
                        <tr class="border-b border-slate-50" wire:key="bill-pay-{{ $payment['id'] }}">
                            <td class="py-2 pr-3 text-slate-600">{{ $payment['payment_date'] }}</td>
                            <td class="py-2 pr-3">
                                <a class="font-mono text-indigo-600 hover:underline" href="{{ route('tenant.facturation.edit', ['tenant' => $tenantCode, 'invoice' => $payment['invoice_id']]) }}">{{ $payment['invoice_code'] }}</a>
                            </td>
                            <td class="py-2 pr-3 font-mono text-[11px] text-emerald-700">{{ $payment['receipt_code'] }}</td>
                            <td class="py-2 pr-3 text-slate-600">{{ $payment['payment_method_label'] }}</td>
                            <td class="py-2 text-right tabular-nums font-semibold text-emerald-700">{{ number_format($payment['amount'], 0, ',', ' ') }} {{ $payment['currency'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ── Lignes ──────────────────────────────────────────────────────── --}}
    <div class="card p-0 overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-700">{{ __('Lignes du devis') }} ({{ $quote->lines->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="text-left text-slate-500 font-semibold px-4 py-2">{{ __('Désignation') }}</th>
                        <th class="text-right text-slate-500 font-semibold px-4 py-2 w-16">{{ __('Qté') }}</th>
                        <th class="text-left text-slate-500 font-semibold px-4 py-2 w-16">{{ __('Unité') }}</th>
                        <th class="text-right text-slate-500 font-semibold px-4 py-2 w-24">{{ __('P.U. HT') }}</th>
                        <th class="text-right text-slate-500 font-semibold px-4 py-2 w-24">{{ __('Montant HT') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quote->lines as $line)
                    @if(($line->line_type ?? '') === 'section')
                    <tr wire:key="line-{{ $line->id }}">
                        <td colspan="5" class="px-4 py-2.5 bg-indigo-50 border-b border-indigo-100 text-[13px] font-bold text-indigo-950 uppercase tracking-wide">
                            {{ $line->description }}
                        </td>
                    </tr>
                    @else
                    <tr class="border-b border-slate-100" wire:key="line-{{ $line->id }}">
                        <td class="px-4 py-2 text-slate-800">{{ $line->description }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-slate-600">{{ number_format($line->quantity, 2, ',', ' ') }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $line->unit ?: '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($line->unit_price, 0, ',', ' ') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums font-medium">{{ number_format($line->amount, 0, ',', ' ') }}</td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Totaux & notes ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card">
            @if($quote->notes)
            <div class="mb-4">
                <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mb-1">{{ __('Notes client') }}</p>
                <p class="text-sm text-slate-700 whitespace-pre-line">{{ $quote->notes }}</p>
            </div>
            @endif
            @if($quote->terms)
            <div>
                <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mb-1">{{ __('Conditions') }}</p>
                <p class="text-sm text-slate-700 whitespace-pre-line">{{ $quote->terms }}</p>
            </div>
            @endif
            @if($quote->internal_notes && ($canEdit || auth('tenant')->user()?->hasPermission('devis.edit')))
            <div class="mt-4 pt-4 border-t border-slate-100">
                <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mb-1">{{ __('Notes internes') }}</p>
                <p class="text-sm text-slate-500 whitespace-pre-line">{{ $quote->internal_notes }}</p>
            </div>
            @endif
        </div>
        <div class="card">
            <table class="w-full text-sm">
                <tr class="border-b border-slate-100"><td class="py-2 text-slate-500">{{ __('Total HT') }}</td><td class="py-2 text-right font-medium tabular-nums">{{ number_format($quote->total_ht, 0, ',', ' ') }} {{ $quote->currency }}</td></tr>
                @if((float)$quote->discount_percent > 0)
                <tr class="border-b border-slate-100"><td class="py-2 text-slate-500">{{ __('Remise') }} ({{ $quote->discount_percent }}%)</td><td class="py-2 text-right tabular-nums text-red-600">-{{ number_format($quote->discount_amount, 0, ',', ' ') }}</td></tr>
                <tr class="border-b border-slate-100"><td class="py-2 text-slate-500">{{ __('Net HT') }}</td><td class="py-2 text-right tabular-nums">{{ number_format($quote->net_ht, 0, ',', ' ') }}</td></tr>
                @endif
                @if((float)$quote->tax_rate > 0)
                <tr class="border-b border-slate-100"><td class="py-2 text-slate-500">{{ __('TVA') }} ({{ $quote->tax_rate }}%)</td><td class="py-2 text-right tabular-nums">{{ number_format($quote->tax_amount, 0, ',', ' ') }}</td></tr>
                @endif
                <tr><td class="py-2 font-bold text-slate-800">{{ __('TOTAL TTC') }}</td><td class="py-2 text-right font-bold text-lg tabular-nums">{{ number_format($quote->total_ttc, 0, ',', ' ') }} {{ $quote->currency }}</td></tr>
            </table>
        </div>
    </div>

    @if($canViewDms)
    <div class="mt-4">
        <livewire:inovcom-dms.entity-documents
            attachable-type="quote"
            :attachable-id="$quote->id"
            wire:key="quote-docs-{{ $quote->id }}"
        />
    </div>
    @endif
</div>

@push('styles')
<style>
dialog.refuse-dialog {
    margin: auto;
    max-height: calc(100vh - 2rem);
    overflow: hidden;
}
dialog.refuse-dialog::backdrop {
    background: rgb(15 23 42 / 0.45);
}
</style>
@endpush
