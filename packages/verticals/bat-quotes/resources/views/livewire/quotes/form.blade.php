@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $statusClass = match($status ?? 'draft') {
        'draft'    => 'bg-slate-100 text-slate-600',
        'sent'     => 'bg-blue-100 text-blue-700',
        'accepted' => 'bg-emerald-100 text-emerald-700',
        'refused'  => 'bg-red-100 text-red-700',
        'expired'  => 'bg-amber-100 text-amber-800',
        default    => 'bg-slate-100 text-slate-500',
    };
    $statusLabel = match($status ?? 'draft') {
        'draft'    => __('Brouillon'),
        'sent'     => __('Envoyé'),
        'accepted' => __('Accepté'),
        'refused'  => __('Refusé'),
        'expired'  => __('Expiré'),
        default    => $status,
    };
@endphp
<div>

    @if ($quoteId)
    <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 mb-4">
        <div class="flex items-center gap-3">
            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $statusClass }}">{{ $statusLabel }}</span>
            <span class="text-[13px] text-slate-500 font-medium font-mono">{{ $code }}</span>
            @if(($versionFamily ?? collect())->count() > 1)
                <span class="text-[11px] px-2 py-0.5 rounded bg-violet-50 text-violet-700 border border-violet-200 font-semibold">{{ __('v:version', ['version' => $quoteVersion ?? 1]) }}</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="downloadPdf">{{ __('Aperçu PDF') }}</button>
            @if($canExport ?? false)
            <button type="button" class="btn btn-secondary btn-sm" wire:click="downloadExcel">{{ __('Excel') }}</button>
            @endif
            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.devis.show', ['tenant' => $tenantCode, 'quote' => $quoteId]) }}">{{ __('Voir la fiche') }}</a>
        </div>
    </div>
    @endif

    {{-- ── Import rapide : déposer le fichier client ─────────────────────── --}}
    @if($canImport ?? false)
    <div class="mb-4 rounded-xl border-2 border-dashed transition-colors {{ ($highlightDropZone ?? false) ? 'border-emerald-400 bg-emerald-50/60' : 'border-slate-200 bg-slate-50/80 hover:border-emerald-300 hover:bg-emerald-50/40' }}"
         x-data
         x-on:dragover.prevent="$el.classList.add('border-emerald-400','bg-emerald-50')"
         x-on:dragleave.prevent="$el.classList.remove('border-emerald-400','bg-emerald-50')"
         x-on:drop.prevent="$el.classList.remove('border-emerald-400','bg-emerald-50'); const f = $event.dataTransfer.files[0]; if(f){ $refs.dropInput.files = $event.dataTransfer.files; $refs.dropInput.dispatchEvent(new Event('change',{bubbles:true})); }">
        <label class="flex flex-col items-center justify-center gap-2 px-6 py-8 cursor-pointer text-center">
            <input type="file" x-ref="dropInput" class="sr-only" wire:model="dropImportFile"
                   accept=".xlsx,.xls,.csv,.txt">
            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-800">{{ __('Déposez le devis Excel du client ici') }}</p>
                <p class="text-xs text-slate-500 mt-1 max-w-lg">{{ __('Les lignes apparaissent immédiatement ci-dessous. Vous modifiez les prix dans le système, puis Enregistrer — plus besoin de copie locale.') }}</p>
            </div>
            <span class="text-xs text-emerald-700 font-medium underline">{{ __('ou cliquez pour choisir un fichier') }}</span>
            <div wire:loading wire:target="dropImportFile" class="text-xs text-slate-500 mt-1">{{ __('Lecture du fichier…') }}</div>
            @error('dropImportFile') <span class="field-error text-xs">{{ $message }}</span> @enderror
        </label>
        @if($canImport ?? false)
        <div class="text-center pb-3">
            <button type="button" class="text-[11px] text-slate-400 hover:text-indigo-600 underline" wire:click="openImportModal">
                {{ __('Format inhabituel ? Configuration manuelle des colonnes') }}
            </button>
        </div>
        @endif
    </div>

    @if($pendingSourceFilename ?? false)
    <div class="mb-4 flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-50 border border-blue-100 text-sm text-blue-800">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span>
            @if($quoteId)
                {{ __('Fichier source « :file » archivé sur ce devis.', ['file' => $pendingSourceFilename]) }}
            @else
                {{ __('Fichier « :file » sera archivé à l\'enregistrement.', ['file' => $pendingSourceFilename]) }}
            @endif
        </span>
    </div>
    @endif
    @endif

    {{-- ── Main form card ───────────────────────────────────────────────── --}}
    <div class="card">
        <h2 class="text-base font-semibold text-slate-800 mb-5">{{ $quoteId ? __('Modifier le devis') : __('Nouveau devis') }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            {{-- Code --}}
            @if($quoteId)
                <div class="field">
                    <label class="field-label">{{ __('Code') }}</label>
                    <input class="input bg-slate-50 text-slate-500" wire:model="code" readonly disabled>
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            @else
                <div class="field">
                    <label class="field-label">{{ __('Code') }}</label>
                    <input class="input bg-slate-50 text-slate-400" value="—" readonly disabled>
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Généré automatiquement') }}</span>
                </div>
            @endif

            {{-- Title --}}
            <div class="field">
                <label class="field-label">{{ __('Titre / Objet') }} <span class="text-red-500">*</span></label>
                <input class="input" wire:model="title" placeholder="{{ __('Objet du devis') }}">
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Offer picker --}}
            <div class="field">
                <label class="field-label">{{ __('Offre') }}</label>
                <div x-show="$wire.offer_id" class="flex items-center gap-2.5 min-h-10 px-3.5 bg-violet-50 border-[1.5px] border-violet-200 rounded-lg" style="{{ $offer_id ? '' : 'display:none;' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 text-violet-600 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span class="flex-1 text-[13px] font-semibold text-violet-800 truncate" x-text="$wire.offerLabel ?? '{{ $offerLabel }}'">{{ $offerLabel }}</span>
                    <button type="button" class="text-[11px] text-slate-500 bg-white border border-slate-200 rounded px-2 py-0.5 hover:bg-slate-50" wire:click="openOfferPicker">{{ __('Changer') }}</button>
                    <button type="button" class="text-slate-400 hover:text-red-500 text-lg leading-none" wire:click="clearOffer" title="{{ __('Retirer') }}">×</button>
                </div>
                <button type="button" x-show="!$wire.offer_id"
                    class="flex items-center gap-2 w-full min-h-10 px-3.5 bg-white border-[1.5px] border-dashed border-slate-300 rounded-lg text-[13px] text-slate-500 hover:border-violet-400 hover:text-violet-700 hover:bg-violet-50/30 transition-colors text-left"
                    style="{{ $offer_id ? 'display:none;' : '' }}" wire:click="openOfferPicker">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    {{ __('Rechercher une offre (optionnel)...') }}
                </button>
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Laissez vide pour un devis direct sans offre.') }}</span>
                @error('offer_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Client picker --}}
            <div class="field">
                <label class="field-label">{{ __('Client') }} <span class="text-red-500">*</span></label>
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
                @error('client_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Status (workflow géré depuis la fiche Voir) --}}
            <div class="field">
                <label class="field-label">{{ __('Statut') }}</label>
                <select class="input" wire:model="status" disabled>
                    <option value="draft">{{ __('Brouillon') }}</option>
                </select>
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Envoi, acceptation et refus depuis la fiche devis.') }}</span>
            </div>

            {{-- Valid Until --}}
            <div class="field">
                <label class="field-label">{{ __('Validité (date limite)') }}</label>
                <input class="input" wire:model="valid_until" type="date">
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Par défaut : un mois à partir d\'aujourd\'hui. Modifiable à tout moment.') }}</span>
                @error('valid_until') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Currency --}}
            <div class="field">
                <label class="field-label">{{ __('Devise') }}</label>
                <select class="input" wire:model="currency">
                    <option value="XOF">XOF – Franc CFA (UEMOA)</option>
                    <option value="XAF">XAF – Franc CFA (CEMAC)</option>
                    <option value="EUR">EUR – Euro</option>
                    <option value="USD">USD – Dollar US</option>
                    <option value="GBP">GBP – Livre sterling</option>
                    <option value="MAD">MAD – Dirham marocain</option>
                    <option value="GNF">GNF – Franc Guinéen</option>
                </select>
                @error('currency') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Spacer --}}
            <div class="hidden sm:block"></div>

            {{-- Notes (full width) --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Notes client') }}</label>
                <textarea class="input" wire:model="notes" rows="2" placeholder="{{ __('Conditions, délais, remarques visibles sur le devis...') }}"></textarea>
            </div>

            {{-- Internal notes (full width) --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Notes internes') }}</label>
                <textarea class="input" wire:model="internal_notes" rows="2" placeholder="{{ __('Commentaires internes (non visibles sur le PDF)...') }}"></textarea>
            </div>

            {{-- Terms (full width) --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Conditions générales / Termes') }}</label>
                <textarea class="input" wire:model="terms" rows="2" placeholder="{{ __('Conditions de paiement, garanties, clauses contractuelles...') }}"></textarea>
            </div>
        </div>

        {{-- ── Line items ─────────────────────────────────────────────────── --}}
        <div class="mt-7">

            <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-slate-800">{{ __('Lignes du devis') }}</span>
                    <label class="inline-flex items-center gap-1.5 text-[12px] text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" wire:model.live="showLineTypeColumn" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        {{ __('Colonne type') }}
                    </label>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition-colors cursor-pointer"
                            wire:click="openItemPicker">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                        {{ __('Ajouter depuis le catalogue') }}
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addLine">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('Ligne vide') }}
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addSectionLine">
                        {{ __('+ Titre / Lot') }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-lg">
                @php $lineColCount = $showLineTypeColumn ? 9 : 8; @endphp
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="bg-slate-50 border-b-2 border-slate-200">
                            <th class="text-left text-slate-500 font-semibold text-[12px] px-2 py-2 min-w-[220px]">{{ __('Désignation') }}</th>
                            @if($showLineTypeColumn)
                            <th class="text-left text-slate-500 font-semibold text-[12px] px-2 py-2 w-[100px]">{{ __('Type') }}</th>
                            @endif
                            <th class="text-right text-slate-500 font-semibold text-[12px] px-2 py-2 w-[72px]">{{ __('Qté') }}</th>
                            <th class="text-left text-slate-500 font-semibold text-[12px] px-2 py-2 w-[64px]">{{ __('Unité') }}</th>
                            <th class="text-right text-slate-500 font-semibold text-[12px] px-2 py-2 w-[100px]">{{ __('P.U. HT') }}</th>
                            <th class="text-right text-slate-500 font-semibold text-[12px] px-2 py-2 w-[70px]">{{ __('Rem. %') }}</th>
                            <th class="text-right text-slate-500 font-semibold text-[12px] px-2 py-2 w-[90px]">{{ __('Coût') }}</th>
                            <th class="text-right text-slate-500 font-semibold text-[12px] px-2 py-2 w-[100px]">{{ __('Montant HT') }}</th>
                            <th class="w-9"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $index => $line)
                        @php
                            $isSection = ($line['line_type'] ?? '') === 'section';
                        @endphp
                        @if($isSection)
                        <tr wire:key="line-{{ $index }}">
                            <td colspan="{{ $lineColCount }}" class="px-3 py-2 bg-indigo-50 border-b border-indigo-100">
                                <div class="flex items-center gap-2 w-full">
                                    <input class="flex-1 min-w-0 bg-transparent border-0 border-b border-transparent focus:border-indigo-300 focus:ring-0 text-[13px] font-bold text-indigo-950 uppercase tracking-wide px-0 py-1"
                                           wire:model="lines.{{ $index }}.description"
                                           placeholder="{{ __('LOT 1 — GROS ŒUVRE') }}">
                                    @if (count($lines) > 1)
                                    <button type="button" class="table-action table-action-delete flex-shrink-0"
                                            wire:click="removeLine({{ $index }})"
                                            title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @else
                        <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors" wire:key="line-{{ $index }}">
                            <td class="px-1 py-1">
                                <div class="flex items-center gap-1">
                                    @if(!empty($line['item_id']))
                                        <span class="inline-flex items-center justify-center w-4 h-4 bg-indigo-50 text-indigo-600 rounded flex-shrink-0" title="{{ __('Article du catalogue') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-2.5 h-2.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                                        </span>
                                    @endif
                                    <input class="input input-sm w-full" wire:model="lines.{{ $index }}.description"
                                           placeholder="{{ __('Désignation, prestation, produit...') }}">
                                </div>
                            </td>
                            @if($showLineTypeColumn)
                            <td class="px-1 py-1">
                                <select class="input input-sm w-full" wire:model.live="lines.{{ $index }}.line_type">
                                    <option value="section">{{ __('Titre / Lot') }}</option>
                                    <option value="service">{{ __('Service') }}</option>
                                    <option value="product">{{ __('Produit') }}</option>
                                    <option value="work">{{ __('Travaux') }}</option>
                                    <option value="subtotal">{{ __('Sous-total') }}</option>
                                </select>
                            </td>
                            @endif
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full text-right" type="number" step="0.001" min="0"
                                       wire:model.live="lines.{{ $index }}.quantity">
                            </td>
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full" wire:model="lines.{{ $index }}.unit"
                                       placeholder="m², u, forfait…">
                            </td>
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full text-right" type="number" step="0.01" min="0"
                                       wire:model.live="lines.{{ $index }}.unit_price">
                            </td>
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full text-right" type="number" step="0.01" min="0" max="100"
                                       wire:model.live="lines.{{ $index }}.discount_percent" placeholder="0">
                            </td>
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full text-right" type="number" step="0.01" min="0"
                                       wire:model.live="lines.{{ $index }}.cost" placeholder="0"
                                       title="{{ __('Coût de revient (calcul marge)') }}">
                            </td>
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full text-right bg-slate-50 text-slate-700 font-semibold cursor-default" type="text"
                                       wire:model="lines.{{ $index }}.amount" readonly>
                            </td>
                            <td class="px-1 py-1 text-center">
                                @if (count($lines) > 1)
                                    <button type="button" class="table-action table-action-delete"
                                            wire:click="removeLine({{ $index }})"
                                            title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Financial summary ────────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-start justify-between gap-6 mt-5">
            {{-- Controls --}}
            <div class="flex flex-wrap gap-4 items-end flex-1 min-w-[200px]">
                <div class="field flex-1 min-w-[140px]">
                    <label class="field-label">{{ __('Remise globale (%)') }}</label>
                    <input class="input" type="number" step="0.01" min="0" max="100"
                           wire:model.live="discount_percent" placeholder="0">
                    @error('discount_percent') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field flex-1 min-w-[140px]">
                    <label class="field-label">{{ __('TVA (%)') }}</label>
                    <input class="input" type="number" step="0.01" min="0" max="100"
                           wire:model.live="tax_rate" placeholder="0">
                    @error('tax_rate') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Totals panel --}}
            <div class="min-w-[280px] bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                <table class="w-full border-collapse">
                    <tbody>
                        <tr>
                            <td class="py-1 text-[14px] text-slate-500">{{ __('Total HT') }}</td>
                            <td class="py-1 text-right text-[14px] font-medium text-slate-800 tabular-nums">{{ number_format($computed_total_ht, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @if($computed_discount > 0)
                        <tr>
                            <td class="py-1 text-[14px] text-slate-500">{{ __('Remise') }} ({{ $discount_percent }}%)</td>
                            <td class="py-1 text-right text-[14px] font-medium text-red-600 tabular-nums">– {{ number_format($computed_discount, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 text-[14px] text-slate-500">{{ __('Net HT') }}</td>
                            <td class="py-1 text-right text-[14px] font-medium text-slate-800 tabular-nums">{{ number_format($computed_net_ht, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @endif
                        @if($computed_tax > 0)
                        <tr>
                            <td class="py-1 text-[14px] text-slate-500">{{ __('TVA') }} ({{ $tax_rate }}%)</td>
                            <td class="py-1 text-right text-[14px] font-medium text-slate-800 tabular-nums">+ {{ number_format($computed_tax, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @endif
                        <tr class="border-t-2 border-slate-200">
                            <td class="pt-2 pb-1 text-[14px] font-semibold text-slate-700">{{ __('Total TTC') }}</td>
                            <td class="pt-2 pb-1 text-right text-[16px] font-bold text-slate-900 tabular-nums">{{ number_format($computed_total_ttc, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @php
                            $marginColor = $computed_margin_pct < 15
                                ? 'text-red-600'
                                : ($computed_margin_pct >= 30 ? 'text-emerald-700' : 'text-slate-700');
                        @endphp
                        <tr class="border-t border-dashed border-slate-200">
                            <td class="pt-1.5 text-[14px] text-slate-500">{{ __('Marge') }}</td>
                            <td class="pt-1.5 text-right text-[14px] font-semibold tabular-nums {{ $marginColor }}">{{ number_format($computed_margin_pct, 1) }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Bottom actions ──────────────────────────────────────────────── --}}
        <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
            <a class="btn btn-secondary" href="{{ route('tenant.devis.index', ['tenant' => $tenantCode]) }}">{{ __('Retour') }}</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $quoteId ? __('Mettre à jour') : __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
            </button>
        </div>
    </div>

    {{-- ── Documents source client (devis enregistré) ───────────────────── --}}
    @if(($quoteId ?? null) && ($canViewDms ?? false))
    <div class="mt-4">
        <livewire:inovcom-dms.entity-documents
            attachable-type="quote"
            :attachable-id="$quoteId"
            wire:key="quote-docs-{{ $quoteId }}-{{ $documentsRefreshKey ?? 0 }}"
        />
        @if($sourceArchiveAvailable ?? false)
        <p class="text-[11px] text-slate-400 mt-2 px-1">{{ __('Chaque import archive automatiquement le fichier Excel reçu du client.') }}</p>
        @endif
    </div>
    @endif

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
                    <div class="flex items-center gap-3 px-4 py-2.5 cursor-pointer border-b border-slate-50 hover:bg-slate-50 transition-colors" wire:click="selectClient({{ $c['id'] }})" wire:key="cp-client-{{ $c['id'] }}">
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
                        @if(trim($clientSearch ?? '') !== '')
                            <p>{{ __('Aucun client trouvé pour « :q »', ['q' => $clientSearch]) }}</p>
                        @else
                            <p>{{ __('Saisissez au moins 2 caractères pour rechercher') }}</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ OFFER PICKER ═════════════════════════════════════════════════ --}}
    <div x-show="$wire.showOfferPicker" wire:click.self="closeOfferPicker"
         class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/55 backdrop-blur-sm"
         style="display:none;">
        <div class="bg-white rounded-2xl w-[560px] max-w-[calc(100vw-32px)] max-h-[540px] flex flex-col shadow-2xl overflow-hidden"
             x-data x-effect="if ($wire.showOfferPicker) $nextTick(() => $refs.offerInput?.focus())">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                <span class="text-[14px] font-bold text-slate-900">{{ __('Sélectionner une offre') }}</span>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100 hover:text-slate-700" wire:click="closeOfferPicker">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-4 py-3 border-b border-slate-100">
                <input x-ref="offerInput" wire:model.live.debounce.300ms="offerSearch"
                       class="input" placeholder="{{ __('Rechercher par code ou intitulé...') }}" autocomplete="off">
            </div>
            <div class="overflow-y-auto flex-1">
                @forelse ($offerResults as $o)
                    <div class="flex items-center gap-3 px-4 py-2.5 cursor-pointer border-b border-slate-50 hover:bg-slate-50 transition-colors" wire:click="selectOffer({{ $o['id'] }})" wire:key="cp-offer-{{ $o['id'] }}">
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-semibold text-slate-800 truncate">{{ $o['title'] }}</div>
                            @if(!empty($o['client_name']))
                                <div class="text-[11px] text-slate-400 truncate">{{ $o['client_name'] }}</div>
                            @endif
                        </div>
                        <span class="font-mono text-[11px] text-violet-600 flex-shrink-0">{{ $o['code'] }}</span>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-9 text-slate-400 text-[13px]">
                        @if(trim($offerSearch ?? '') !== '')
                            <p>{{ __('Aucune offre trouvée pour « :q »', ['q' => $offerSearch]) }}</p>
                        @else
                            <p>{{ __('Saisissez au moins 2 caractères pour rechercher') }}</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         ITEM CATALOG PICKER — native <dialog> (browser top-layer)
         Only rendered when showItemPicker=true, so no ghost can appear.
    ══════════════════════════════════════════════════════════════════════════ --}}
    @if($showItemPicker)
    <dialog
        id="catalog-picker"
        x-data
        x-init="$nextTick(() => { $el.showModal(); $el.querySelector('.cp-search__input')?.focus(); })"
        @cancel.prevent="$el.close(); $wire.call('closeItemPicker')"
    >
        {{-- Header --}}
        <div class="cp-header">
            <div class="cp-header__left">
                <span class="cp-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                </span>
                <div>
                    <div class="cp-title">{{ __('Catalogue d\'articles') }}</div>
                    <div class="cp-subtitle">{{ __('Ajoutez autant d\'articles que nécessaire, puis cliquez Terminé.') }}</div>
                </div>
            </div>
            <button type="button" class="cp-close"
                    @click="$el.closest('dialog').close(); $wire.call('closeItemPicker')"
                    title="{{ __('Fermer') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Search --}}
        <div class="cp-search">
            <div class="cp-search__wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16" class="cp-search__icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" class="cp-search__input"
                    wire:model.live.debounce.300ms="itemSearch"
                    placeholder="{{ __('Essayez un nom de produit, une marque, un vendeur ou une catégorie.') }}"
                    autocomplete="off">
                @if($itemSearch)
                    <button type="button" class="cp-search__clear" wire:click="$set('itemSearch','')" title="{{ __('Effacer') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Body --}}
        <div class="cp-body">

            {{-- Loading overlay while searching --}}
            <div wire:loading wire:target="itemSearch" class="cp-loading">
                <svg class="cp-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="#e2e8f0" stroke-width="3"/>
                    <path d="M12 2a10 10 0 0110 10" stroke="#6366f1" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span>{{ __('Recherche...') }}</span>
            </div>

            @if($catalogItems->isEmpty())
                    <div class="cp-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="44" height="44"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                        @if($itemSearch)
                            <p>{{ __('Aucun résultat pour « :q »', ['q' => $itemSearch]) }}</p>
                            <span>{{ __('Essayez un autre mot-clé.') }}</span>
                        @else
                            <p>{{ __('Aucun article actif dans le catalogue.') }}</p>
                            <span>{{ __('Créez des articles depuis le menu Articles.') }}</span>
                        @endif
                    </div>
                @else
                    @php
                        $cpPalette = [
                            ['bg'=>'#eef2ff','text'=>'#4338ca'],
                            ['bg'=>'#fce7f3','text'=>'#be185d'],
                            ['bg'=>'#fef3c7','text'=>'#b45309'],
                            ['bg'=>'#dcfce7','text'=>'#15803d'],
                            ['bg'=>'#fee2e2','text'=>'#b91c1c'],
                            ['bg'=>'#e0f2fe','text'=>'#0369a1'],
                            ['bg'=>'#f3e8ff','text'=>'#7c3aed'],
                            ['bg'=>'#ffedd5','text'=>'#c2410c'],
                            ['bg'=>'#ecfeff','text'=>'#0e7490'],
                            ['bg'=>'#f0fdf4','text'=>'#166534'],
                        ];
                    @endphp
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th class="cp-thumb-header"></th>
                                <th>{{ __('Désignation') }}</th>
                                <th>{{ __('Réf.') }}</th>
                                <th>{{ __('Catégorie') }}</th>
                                <th style="text-align:right;">{{ __('Prix HT') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($catalogItems as $item)
                            @php
                                $alreadyAdded = in_array($item->id, $addedItemIds, true);
                                $words    = preg_split('/\s+/', trim($item->name));
                                $initials = mb_strtoupper(mb_substr($words[0], 0, 1));
                                if (count($words) >= 2) {
                                    $initials .= mb_strtoupper(mb_substr($words[1], 0, 1));
                                }
                                $colorIdx  = ord(mb_strtoupper(mb_substr($item->name, 0, 1)) ?: 'A') % count($cpPalette);
                                $cpColor   = $cpPalette[$colorIdx];
                            @endphp
                            <tr class="cp-row {{ $alreadyAdded ? 'cp-row--added' : '' }}" wire:key="cp-item-{{ $item->id }}">
                                <td class="cp-thumb-cell">
                                    <div class="cp-thumb" style="background:{{ $cpColor['bg'] }};color:{{ $cpColor['text'] }};">
                                        {{ $initials }}
                                    </div>
                                </td>
                                <td>
                                    <div class="cp-item-name" style="display:inline-flex;align-items:center;gap:7px;flex-wrap:wrap;">
                                        {{ $item->name }}
                                        @if($alreadyAdded)
                                            <span class="cp-added-badge">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="10" height="10"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                {{ __('Ajouté') }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($item->description)
                                        <div class="cp-item-desc">{{ \Illuminate\Support\Str::limit($item->description, 70) }}</div>
                                    @endif
                                </td>
                                <td class="cp-sku">{{ $item->sku ?? '—' }}</td>
                                <td class="cp-meta">{{ $item->category?->name ?? '—' }}</td>
                                <td class="cp-price">
                                    {{ number_format((float)$item->price, 0, ',', ' ') }}
                                    <small>{{ $currency }}</small>
                                </td>
                                <td class="cp-add-cell">
                                    <button type="button" class="{{ $alreadyAdded ? 'cp-add-btn cp-add-btn--added' : 'cp-add-btn' }}"
                                            wire:click="addItemFromCatalog({{ $item->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="addItemFromCatalog({{ $item->id }})">
                                        <span wire:loading.remove wire:target="addItemFromCatalog({{ $item->id }})" style="display:inline-flex;align-items:center;gap:5px;white-space:nowrap;">
                                            @if($alreadyAdded)
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                            @endif
                                            {{ __('Ajouter') }}
                                        </span>
                                        <span wire:loading wire:target="addItemFromCatalog({{ $item->id }})">…</span>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @php $limit = $itemSearch !== '' ? 50 : 10; @endphp
                    @if($catalogItems->count() >= $limit)
                        <div class="cp-limit">
                            @if($itemSearch)
                                {{ __('50 résultats max — affinez la recherche pour plus de précision.') }}
                            @else
                                {{ __('10 premiers articles — tapez dans la recherche pour filtrer.') }}
                            @endif
                        </div>
                    @endif
                @endif
        </div>

        {{-- Footer --}}
        <div class="cp-footer">
            <span class="cp-footer__hint">{{ __('Le devis est mis à jour automatiquement à chaque ajout.') }}</span>
            <button type="button" class="cp-done-btn"
                    @click="$el.closest('dialog').close(); $wire.call('closeItemPicker')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ __('Terminé') }}
            </button>
        </div>
    </dialog>
    @endif

    {{-- ══ REFUSE MODAL ══════════════════════════════════════════════════════ --}}
    @if($showRefuseModal)
    <dialog id="refuse-dialog" x-data x-init="$nextTick(() => $el.showModal())"
            @cancel.prevent="$wire.call('cancelRefuseQuote')"
            class="refuse-dialog rounded-xl border-0 p-0 w-full max-w-lg shadow-2xl">
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

    {{-- ══ IMPORT EXCEL MODAL ════════════════════════════════════════════════ --}}
    @if($showImportModal)
    <dialog id="import-dialog" x-data x-init="$nextTick(() => $el.showModal())"
            @cancel.prevent="$wire.call('closeImportModal')"
            class="rounded-xl border-0 p-0 w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 bg-slate-50">
            <div>
                <h3 class="text-base font-semibold text-slate-800">{{ __('Importer des lignes') }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ __('Excel (.xlsx, .xls) ou CSV — mapping flexible, sans modèle imposé') }}</p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600" wire:click="closeImportModal">✕</button>
        </div>

        <div class="p-5 overflow-y-auto flex-1">
            {{-- Steps indicator --}}
            <div class="flex items-center gap-2 mb-5 text-xs font-medium">
                <span class="px-2.5 py-1 rounded-full {{ $importStep === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">1. {{ __('Fichier') }}</span>
                <span class="text-slate-300">›</span>
                <span class="px-2.5 py-1 rounded-full {{ $importStep === 2 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">2. {{ __('Colonnes') }}</span>
                <span class="text-slate-300">›</span>
                <span class="px-2.5 py-1 rounded-full {{ $importStep === 3 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">3. {{ __('Aperçu') }}</span>
            </div>

            @if($importStep === 1)
                <div class="field max-w-lg">
                    <label class="field-label">{{ __('Fichier') }}</label>
                    <input type="file" class="input" wire:model="importFile" accept=".xlsx,.xls,.csv,.txt">
                    @error('importFile') <span class="field-error">{{ $message }}</span> @enderror
                    <p class="text-[11px] text-slate-400 mt-1">{{ __('Max. 10 Mo. La première feuille sera lue pour Excel.') }}</p>
                </div>
                <div wire:loading wire:target="importFile,parseImportFile" class="text-sm text-slate-500 mt-2">{{ __('Analyse en cours…') }}</div>
            @endif

            @if($importStep === 2)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div class="field">
                        <label class="field-label">{{ __('Ligne d\'en-têtes') }}</label>
                        <input type="number" class="input" min="1" wire:model.live="importHeaderRow">
                    </div>
                    <div class="field sm:col-span-2">
                        <label class="field-label">{{ __('Mode d\'import') }}</label>
                        <select class="input" wire:model="importMode">
                            <option value="replace">{{ __('Remplacer toutes les lignes actuelles') }}</option>
                            <option value="append">{{ __('Ajouter à la fin des lignes existantes') }}</option>
                        </select>
                    </div>
                </div>

                <p class="text-sm text-slate-600 mb-3">{{ __('Associez chaque champ aux colonnes de votre fichier :') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    @php
                        $fieldLabels = [
                            'description' => __('Désignation') . ' *',
                            'quantity' => __('Quantité'),
                            'unit' => __('Unité'),
                            'unit_price' => __('Prix unitaire HT'),
                            'amount' => __('Montant HT'),
                            'discount_percent' => __('Remise %'),
                            'cost' => __('Coût / déboursé'),
                            'line_type' => __('Type de ligne'),
                        ];
                    @endphp
                    @foreach($importFields as $field)
                    <div class="field">
                        <label class="field-label text-xs">{{ $fieldLabels[$field] ?? $field }}</label>
                        <select class="input input-sm" wire:model.live="importMapping.{{ $field }}">
                            <option value="">{{ __('— Ignorer —') }}</option>
                            @foreach($importHeaders as $colIndex => $headerLabel)
                                <option value="{{ $colIndex }}">{{ $headerLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>

                @if($importTotalLines > 0)
                    <p class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2">
                        {{ __(':count lignes détectées', ['count' => $importTotalLines]) }}
                        @if($importSkippedRows > 0)
                            · {{ __(':n ignorées (vides ou totaux)', ['n' => $importSkippedRows]) }}
                        @endif
                    </p>
                @endif
            @endif

            @if($importStep === 3)
                <p class="text-sm text-slate-600 mb-3">
                    {{ __('Aperçu des :count premières lignes — :total au total.', [
                        'count' => count($importPreviewLines),
                        'total' => $importTotalLines,
                    ]) }}
                </p>
                @foreach($importWarnings as $warning)
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded px-2 py-1 mb-2">{{ $warning }}</p>
                @endforeach
                <div class="overflow-x-auto border border-slate-200 rounded-lg">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="text-left px-2 py-2">{{ __('Désignation') }}</th>
                                <th class="text-right px-2 py-2">{{ __('Qté') }}</th>
                                <th class="text-left px-2 py-2">{{ __('Unité') }}</th>
                                <th class="text-right px-2 py-2">{{ __('P.U.') }}</th>
                                <th class="text-right px-2 py-2">{{ __('Montant') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($importPreviewLines as $previewLine)
                            <tr class="border-b border-slate-100" wire:key="preview-{{ $loop->index }}">
                                <td class="px-2 py-1.5">{{ \Illuminate\Support\Str::limit($previewLine['description'], 60) }}</td>
                                <td class="px-2 py-1.5 text-right">{{ $previewLine['quantity'] }}</td>
                                <td class="px-2 py-1.5">{{ $previewLine['unit'] ?: '—' }}</td>
                                <td class="px-2 py-1.5 text-right">{{ number_format((float)$previewLine['unit_price'], 0, ',', ' ') }}</td>
                                <td class="px-2 py-1.5 text-right font-medium">{{ number_format((float)$previewLine['amount'], 0, ',', ' ') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="field mt-4 max-w-xs">
                    <label class="field-label">{{ __('Mode d\'import') }}</label>
                    <select class="input input-sm" wire:model="importMode">
                        <option value="replace">{{ __('Remplacer toutes les lignes') }}</option>
                        <option value="append">{{ __('Ajouter aux lignes existantes') }}</option>
                    </select>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="closeImportModal">{{ __('Annuler') }}</button>
            <div class="flex gap-2">
                @if($importStep === 1)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="parseImportFile"
                            wire:loading.attr="disabled" wire:target="importFile,parseImportFile"
                            @disabled(!$importFile)>
                        {{ __('Analyser le fichier') }}
                    </button>
                @elseif($importStep === 2)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('importStep', 1)">{{ __('Retour') }}</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="goToImportPreview">{{ __('Aperçu') }}</button>
                @else
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('importStep', 2)">{{ __('Retour') }}</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="confirmImport"
                            wire:loading.attr="disabled" wire:target="confirmImport">
                        {{ __('Importer :count lignes', ['count' => $importTotalLines]) }}
                    </button>
                @endif
            </div>
        </div>
    </dialog>
    @endif

</div>

@push('styles')
<style>
dialog#import-dialog, dialog#refuse-dialog {
    padding: 0; border: none;
    box-shadow: 0 32px 96px rgba(15,23,42,.35);
}
dialog#import-dialog::backdrop, dialog#refuse-dialog::backdrop {
    background: rgba(15,23,42,.55);
    backdrop-filter: blur(2px);
}
dialog#catalog-picker {
    padding: 0; border: none; border-radius: 16px;
    box-shadow: 0 32px 96px rgba(15,23,42,.35), 0 4px 16px rgba(15,23,42,.12);
    width: min(860px, calc(100vw - 32px));
    max-height: 86vh; overflow: hidden;
}
dialog#catalog-picker[open] {
    display: flex; flex-direction: column;
}
dialog#catalog-picker::backdrop {
    background: rgba(15,23,42,.55);
    backdrop-filter: blur(2px);
}

/* Header */
.cp-header { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid #e2e8f0; flex-shrink:0; gap:12px; }
.cp-header__left { display:flex; align-items:center; gap:12px; min-width:0; }
.cp-icon { display:flex; align-items:center; justify-content:center; width:38px; height:38px; background:#eef2ff; border-radius:10px; color:#4f46e5; flex-shrink:0; }
.cp-title { font-size:15px; font-weight:700; color:#1e293b; }
.cp-subtitle { font-size:12px; color:#94a3b8; margin-top:2px; }
.cp-close { display:flex; align-items:center; justify-content:center; width:34px; height:34px; border:none; background:none; border-radius:8px; color:#64748b; cursor:pointer; flex-shrink:0; transition:background .15s,color .15s; }
.cp-close:hover { background:#f1f5f9; color:#1e293b; }

/* Search */
.cp-search { padding:14px 24px 10px; border-bottom:1px solid #f1f5f9; flex-shrink:0; background:#fafbfc; }
.cp-search__wrap { position:relative; display:flex; align-items:center; }
.cp-search__icon { position:absolute; left:12px; color:#94a3b8; pointer-events:none; flex-shrink:0; }
.cp-search__input { width:100%; padding:10px 40px; border:1.5px solid #e2e8f0; border-radius:9px; font-size:14px; background:#fff; outline:none; transition:border-color .15s, box-shadow .15s; }
.cp-search__input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.cp-search__clear { position:absolute; right:11px; background:none; border:none; cursor:pointer; color:#94a3b8; display:flex; padding:4px; border-radius:4px; }
.cp-search__clear:hover { color:#475569; }
.cp-count { font-size:11px; color:#94a3b8; margin-top:7px; }

/* Body */
.cp-body { position:relative; overflow-y:auto; flex:1; min-height:0; }

/* Loading spinner */
.cp-loading { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; background:rgba(255,255,255,.88); z-index:10; font-size:13px; color:#64748b; }
.cp-spinner { width:30px; height:30px; animation:cp-spin .75s linear infinite; }
@keyframes cp-spin { to { transform:rotate(360deg); } }

/* Empty state */
.cp-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:56px 24px; text-align:center; color:#94a3b8; }
.cp-empty svg { margin-bottom:14px; opacity:.4; }
.cp-empty p { font-size:14px; font-weight:600; color:#475569; margin:0 0 4px; }
.cp-empty span { font-size:13px; }

/* Table */
.cp-table { width:100%; border-collapse:collapse; font-size:13px; }
.cp-table thead th { position:sticky; top:0; z-index:1; background:#f8fafc; padding:10px 16px; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; border-bottom:2px solid #e2e8f0; white-space:nowrap; text-align:left; }
.cp-thumb-header { width:56px; padding:10px 0 10px 16px !important; }
.cp-table tbody td { padding:10px 16px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.cp-thumb-cell { width:56px; padding:8px 0 8px 16px !important; }
.cp-thumb { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; letter-spacing:.03em; user-select:none; }
.cp-row { cursor:default; transition:background .1s; }
.cp-row:hover td { background:#f8f7ff; }
.cp-row:last-child td { border-bottom:none; }
.cp-item-name { font-weight:500; color:#1e293b; line-height:1.3; }
.cp-item-desc { font-size:11px; color:#94a3b8; margin-top:3px; line-height:1.4; }
.cp-sku { font-family:ui-monospace,monospace; font-size:12px; color:#475569; white-space:nowrap; }
.cp-meta { font-size:12px; color:#64748b; }
.cp-price { font-weight:600; color:#1e293b; white-space:nowrap; text-align:right; }
.cp-price small { font-size:11px; color:#94a3b8; font-weight:400; margin-left:2px; }
.cp-add-cell { text-align:right; white-space:nowrap; }
.cp-add-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; background:#4f46e5; color:#fff; border:none; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; transition:background .15s, transform .1s; }
.cp-add-btn:hover { background:#4338ca; transform:translateY(-1px); }
.cp-add-btn:active { transform:translateY(0); }
.cp-add-btn:disabled { opacity:.6; cursor:default; transform:none; }
.cp-add-btn--added { background:#059669; }
.cp-add-btn--added:hover { background:#047857; }
.cp-row--added td { background:#f0fdf4; }
.cp-row--added:hover td { background:#dcfce7; }
.cp-added-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; background:#dcfce7; color:#16a34a; border-radius:20px; font-size:10px; font-weight:700; white-space:nowrap; }

.cp-limit { text-align:center; font-size:12px; color:#94a3b8; padding:10px 16px; background:#fafbfc; border-top:1px solid #f1f5f9; }

/* Footer */
.cp-footer { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0; }
.cp-footer__hint { font-size:12px; color:#94a3b8; flex:1; }
.cp-done-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 22px; background:#1e293b; color:#fff; border:none; border-radius:9px; font-size:13px; font-weight:600; cursor:pointer; transition:background .15s; flex-shrink:0; }
.cp-done-btn:hover { background:#0f172a; }
</style>
@endpush
