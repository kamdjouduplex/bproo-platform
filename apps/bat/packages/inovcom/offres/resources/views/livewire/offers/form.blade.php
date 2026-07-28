@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $statusClass = [
        'draft'      => 'bg-slate-100 text-slate-600',
        'in_review'  => 'bg-indigo-100 text-indigo-700',
        'terminated' => 'bg-emerald-100 text-emerald-700',
        'closed'     => 'bg-red-100 text-red-700',
    ];
    $currentStatusMeta = $statusClass[$currentStatus] ?? 'bg-slate-100 text-slate-500';
    $currentStatusLabel = match($currentStatus) {
        'draft'      => __('Brouillon'),
        'in_review'  => __('En révision'),
        'terminated' => __('Traitée'),
        'closed'     => __('Clôturée'),
        default      => $currentStatus,
    };
@endphp
<div>
    <div class="card">
        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <h2 class="text-base font-semibold text-slate-800">
                    {{ $offerId ? __('Modifier l\'offre') : __('Nouvelle offre') }}
                </h2>
                @if ($offerId)
                    <span class="font-mono text-[12px] text-slate-400">{{ $code }}</span>
                @endif
            </div>
            @if ($offerId)
                <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap flex-shrink-0 {{ $currentStatusMeta }}">{{ $currentStatusLabel }}</span>
            @endif
        </div>

        {{-- ── Linked quote banner ────────────────────────────────────── --}}
        @if ($currentStatus === 'terminated' && $linkedQuoteId)
            <div class="flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl mb-4 text-[13px] text-emerald-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-[18px] h-[18px] flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="flex-1"><strong>{{ __('Devis généré :') }}</strong>
                    <a href="{{ route('tenant.devis.edit', ['tenant' => $tenantCode, 'quote' => $linkedQuoteId]) }}"
                       class="font-bold font-mono ml-1.5 underline">{{ $linkedQuoteCode }}</a>
                </span>
            </div>
        @endif

        {{-- ── Close reason banner ─────────────────────────────────────── --}}
        @if ($currentStatus === 'closed' && $storedCloseReason)
            <div class="px-4 py-3.5 bg-red-50 border border-red-200 rounded-xl mb-4">
                <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">{{ __('Motif de clôture') }}</div>
                <div class="text-[13px] font-semibold text-red-800 {{ $storedCloseNote ? 'mb-2' : '' }}">{{ $closeReasons[$storedCloseReason] ?? $storedCloseReason }}</div>
                @if ($storedCloseNote)
                    <div class="text-[13px] text-slate-600 leading-relaxed border-t border-red-200 pt-2 whitespace-pre-line">{{ $storedCloseNote }}</div>
                @endif
            </div>
        @endif

        {{-- ── Form ───────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="field">
                <label class="field-label">{{ __('Code') }}</label>
                @if ($offerId)
                    <input class="input bg-slate-50 text-slate-500" value="{{ $code }}" readonly disabled>
                @else
                    <input class="input bg-slate-50 text-slate-400" value="—" readonly disabled>
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Généré automatiquement à la création') }}</span>
                @endif
            </div>

            <div class="field">
                <label class="field-label">{{ __('Intitulé de l\'offre') }} <span class="text-red-500">*</span></label>
                <input class="input" wire:model="title" placeholder="{{ __('Objet / intitulé de l\'offre reçue') }}">
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Catégorie') }}</label>
                <select class="input" wire:model="category">
                    @foreach(\InovCom\Kernel\Support\ServiceCatalog::offerCategories() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Date de réception') }}</label>
                <input class="input" wire:model="received_at" type="date">
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Pré-remplie à la date du jour') }}</span>
            </div>

            {{-- Client picker --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Client') }}</label>
                <div x-show="$wire.client_id" class="flex items-center gap-2.5 min-h-10 px-3.5 bg-sky-50 border-[1.5px] border-sky-200 rounded-lg" style="{{ $client_id ? '' : 'display:none;' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 text-sky-600 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="flex-1 text-[13px] font-semibold text-sky-700 truncate" x-text="$wire.clientLabel ?? '{{ $clientLabel }}'">{{ $clientLabel }}</span>
                    <button type="button" class="text-[11px] text-slate-500 bg-white border border-slate-200 rounded px-2 py-0.5 hover:bg-slate-50" wire:click="openClientPicker">{{ __('Changer') }}</button>
                    <button type="button" class="text-slate-400 hover:text-red-500 text-lg leading-none" wire:click="clearClient" title="{{ __('Retirer') }}">×</button>
                </div>
                <button type="button" x-show="!$wire.client_id"
                    class="flex items-center gap-2 w-full min-h-10 px-3.5 bg-white border-[1.5px] border-dashed border-slate-300 rounded-lg text-[13px] text-slate-500 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50/30 transition-colors text-left"
                    style="{{ $client_id ? 'display:none;' : '' }}" wire:click="openClientPicker">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    {{ __('Rechercher et sélectionner un client...') }}
                </button>
            </div>

            {{-- User (assignee) picker --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Assigné à') }} <span class="text-slate-400 font-normal text-[11px]">{{ __('(commercial / chargé d\'étude)') }}</span></label>
                <div x-show="$wire.assigned_to" class="flex items-center gap-2.5 min-h-10 px-3.5 bg-emerald-50 border-[1.5px] border-emerald-200 rounded-lg" style="{{ $assigned_to ? '' : 'display:none;' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 text-emerald-600 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="flex-1 text-[13px] font-semibold text-emerald-700 truncate" x-text="$wire.userLabel ?? '{{ $userLabel }}'">{{ $userLabel }}</span>
                    <button type="button" class="text-[11px] text-slate-500 bg-white border border-slate-200 rounded px-2 py-0.5 hover:bg-slate-50" wire:click="openUserPicker">{{ __('Changer') }}</button>
                    <button type="button" class="text-slate-400 hover:text-red-500 text-lg leading-none" wire:click="clearUser" title="{{ __('Retirer') }}">×</button>
                </div>
                <button type="button" x-show="!$wire.assigned_to"
                    class="flex items-center gap-2 w-full min-h-10 px-3.5 bg-white border-[1.5px] border-dashed border-emerald-300 rounded-lg text-[13px] text-emerald-700 hover:bg-emerald-50 transition-colors text-left"
                    style="{{ $assigned_to ? 'display:none;' : '' }}" wire:click="openUserPicker">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ __('Rechercher et assigner un collaborateur...') }}
                    <span class="ml-auto text-[11px] text-slate-400">{{ __('Requis pour passer en révision') }}</span>
                </button>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Source') }}</label>
                <select class="input" wire:model="source">
                    <option value="">{{ __('— Non renseignée —') }}</option>
                    <option value="call_for_tender">{{ __('Appel d\'offres') }}</option>
                    <option value="recommendation">{{ __('Recommandation') }}</option>
                    <option value="existing_client">{{ __('Client existant') }}</option>
                </select>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Canal d\'acquisition') }}</label>
                <div class="grid gap-2.5" style="grid-template-columns:1fr 2fr;">
                    <select class="input" wire:model="channel">
                        <option value="">{{ __('— Non renseigné —') }}</option>
                        @foreach ($channels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input class="input" wire:model="channel_detail" placeholder="{{ __('Précision (page, campagne...)') }}" maxlength="500">
                </div>
                @error('channel_detail') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Notes & contexte') }}</label>
                <textarea class="input" wire:model="notes" rows="4" placeholder="{{ __('Informations complémentaires, contexte de réception...') }}"></textarea>
            </div>
        </div>

        {{-- ── Documents ───────────────────────────────────────────────── --}}
        <div class="mt-5 pt-5 border-t border-slate-100">
            @if ($offerId && class_exists(\InovCom\Dms\Http\Livewire\EntityDocuments::class))
                <livewire:inovcom-dms.entity-documents
                    attachable-type="offer"
                    :attachable-id="$offerId"
                    :key="'offer-docs-' . $offerId"
                />
            @else
                <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 border border-dashed border-slate-200 rounded-xl text-[13px] text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 flex-shrink-0 text-slate-300"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    {{ __('Enregistrez l\'offre pour pouvoir y attacher des documents.') }}
                </div>
            @endif
        </div>

        {{-- ── Footer ──────────────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mt-5 pt-4 border-t border-slate-100">
            <a class="btn btn-secondary" href="{{ route('tenant.offres.index', ['tenant' => $tenantCode]) }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Retour') }}
            </a>
            <div class="flex items-center gap-2.5">
                @if ($offerId && $currentStatus === 'in_review' && $canCloseOffer)
                    <button type="button" class="btn btn-sm border-red-200 text-red-700 bg-white hover:bg-red-50" wire:click="openCloseModal">
                        {{ __('Clôturer l\'offre') }}
                    </button>
                @endif
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $offerId ? __('Mettre à jour') : __('Enregistrer') }}</span>
                    <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ══ CLIENT PICKER ════════════════════════════════════════════════ --}}
    <div x-show="$wire.showClientPicker" wire:click.self="closeClientPicker"
         class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/55 backdrop-blur-sm"
         style="display:none;">
        <div class="bg-white rounded-2xl w-[520px] max-w-[calc(100vw-32px)] max-h-[540px] flex flex-col shadow-2xl overflow-hidden"
             x-data x-effect="if ($wire.showClientPicker) $nextTick(() => $refs.clientInput?.focus())">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                <span class="text-[14px] font-bold text-slate-900">{{ __('Sélectionner un client') }}</span>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100 hover:text-slate-700" wire:click="closeClientPicker">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-4 py-3 border-b border-slate-100">
                <input x-ref="clientInput" wire:model.live.debounce.300ms="clientSearch"
                       class="input" placeholder="{{ __('Rechercher par nom, code ou email...') }}" autocomplete="off">
            </div>
            <div class="overflow-y-auto flex-1">
                @forelse ($clientResults as $c)
                    <div class="flex items-center gap-3 px-4 py-2.5 cursor-pointer border-b border-slate-50 hover:bg-slate-50 transition-colors" wire:click="selectClient({{ $c['id'] }})">
                        <div class="flex-1 text-[13px] font-semibold text-slate-800">{{ $c['name'] }}</div>
                        <span class="font-mono text-[11px] text-slate-400">{{ $c['code'] }}</span>
                        @if (($c['type'] ?? '') === 'company')
                            <span class="badge badge-info text-[10px]">{{ __('Entreprise') }}</span>
                        @else
                            <span class="badge text-[10px]" style="background:#f3e8ff;color:#7c3aed;">{{ __('Particulier') }}</span>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-9 text-slate-400 text-[13px]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-7 h-7 mb-2 text-slate-300"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        {{ strlen($clientSearch) > 0 ? __('Aucun client ne correspond.') : __('Tapez pour rechercher...') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ USER PICKER ══════════════════════════════════════════════════ --}}
    <div x-show="$wire.showUserPicker" wire:click.self="closeUserPicker"
         class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/55 backdrop-blur-sm"
         style="display:none;">
        <div class="bg-white rounded-2xl w-[520px] max-w-[calc(100vw-32px)] max-h-[540px] flex flex-col shadow-2xl overflow-hidden"
             x-data x-effect="if ($wire.showUserPicker) $nextTick(() => $refs.userInput?.focus())">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                <span class="text-[14px] font-bold text-slate-900">{{ __('Assigner un collaborateur') }}</span>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100 hover:text-slate-700" wire:click="closeUserPicker">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-4 py-3 border-b border-slate-100">
                <input x-ref="userInput" wire:model.live.debounce.300ms="userSearch"
                       class="input" placeholder="{{ __('Rechercher par nom...') }}" autocomplete="off">
            </div>
            <div class="overflow-y-auto flex-1">
                @forelse ($userResults as $u)
                    <div class="flex items-center gap-3 px-4 py-2.5 cursor-pointer border-b border-slate-50 hover:bg-slate-50 transition-colors" wire:click="selectUser({{ $u['id'] }})">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-[11px] font-bold text-emerald-700 flex-shrink-0">
                            {{ mb_strtoupper(mb_substr($u['name'], 0, 1)) }}
                        </div>
                        <div class="text-[13px] font-semibold text-slate-800">{{ $u['name'] }}</div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-9 text-slate-400 text-[13px]">
                        {{ strlen($userSearch) > 0 ? __('Aucun collaborateur ne correspond.') : __('Tapez pour rechercher...') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ CLOSE OFFER MODAL ════════════════════════════════════════════ --}}
    <div x-show="$wire.showCloseModal" wire:click.self="cancelCloseModal"
         class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/55 backdrop-blur-sm"
         style="display:none;">
        <div class="bg-white rounded-2xl w-[500px] max-w-[calc(100vw-32px)] shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-red-200 bg-red-50">
                <span class="text-[14px] font-bold text-red-800 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    {{ __('Clôturer l\'offre — motif obligatoire') }}
                </span>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-red-200 text-red-400 hover:bg-red-100" wire:click="cancelCloseModal">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-5">
                <p class="text-[13px] text-slate-600 leading-relaxed mb-4">{{ __('Cette action est irréversible. Veuillez indiquer le motif de clôture avant de confirmer.') }}</p>
                <div class="field mb-3">
                    <label class="field-label">{{ __('Type de motif') }} <span class="text-red-500">*</span></label>
                    <select wire:model="closeReason" class="input">
                        <option value="">— {{ __('Sélectionnez un motif') }} —</option>
                        @foreach ($closeReasons as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('closeReason') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field mb-5">
                    <label class="field-label">{{ __('Note explicative') }} <span class="text-red-500">*</span></label>
                    <textarea wire:model="closeNote" rows="4" class="input"
                        placeholder="{{ __('Expliquez brièvement pourquoi cette offre est clôturée (min. 10 caractères)...') }}"></textarea>
                    @error('closeNote') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-2.5">
                    <button type="button" class="btn btn-secondary" wire:click="cancelCloseModal">{{ __('Annuler') }}</button>
                    <button type="button" class="btn btn-sm border-red-300 text-red-700 bg-red-50 hover:bg-red-100"
                        wire:click="closeOffer" wire:loading.attr="disabled" wire:target="closeOffer">
                        <span wire:loading.remove wire:target="closeOffer">{{ __('Confirmer la clôture') }}</span>
                        <span wire:loading wire:target="closeOffer">{{ __('Clôture en cours...') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
