@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div>
<div class="card">

    {{-- ═══════════════════════════════════════════════════════════════
         Section 1 — Informations légales de l'entreprise
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3 mb-5">
        <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center">
            <svg width="14" height="14" fill="none" stroke="#4338ca" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/>
            </svg>
        </div>
        <h3 class="text-[12px] font-bold text-slate-700 uppercase tracking-widest">{{ __('1 — Informations légales') }}</h3>
        <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

        <div class="field sm:col-span-2 sm:grid sm:grid-cols-3 sm:gap-4" style="display:contents">
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Raison sociale / Nom') }} <span class="text-red-500">*</span></label>
                <input class="input" wire:model="name" placeholder="Ex : Société ABC SARL">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Code client') }}</label>
                @if ($clientId)
                    <input class="input bg-slate-50 text-slate-500" wire:model="code" readonly disabled>
                @else
                    <input class="input bg-slate-50 text-slate-400" value="Auto (CLI00001…)" readonly disabled>
                @endif
            </div>
        </div>

        <div class="field">
            <label class="field-label">{{ __('Type de client') }}</label>
            <select class="input" wire:model.live="type">
                <option value="company">{{ __('Entreprise') }}</option>
                <option value="individual">{{ __('Particulier') }}</option>
            </select>
        </div>

        @if ($type === 'company')
        <div class="field">
            <label class="field-label">{{ __('Forme juridique') }}</label>
            <select class="input" wire:model="legal_form">
                <option value="">{{ __('— Choisir —') }}</option>
                <option value="sarl">SARL</option>
                <option value="sa">SA</option>
                <option value="sas">SAS</option>
                <option value="gie">GIE</option>
                <option value="eurl">EURL</option>
                <option value="snc">SNC</option>
                <option value="ei">Entreprise individuelle</option>
                <option value="cooperative">Coopérative</option>
                <option value="ong">ONG / Association</option>
                <option value="autre">Autre</option>
            </select>
        </div>

        <div class="field sm:col-span-2">
            <label class="field-label">{{ __("Secteur d'activité") }}</label>
            <input class="input" wire:model="industry" placeholder="Ex : BTP, Télécoms, Agriculture, Santé…">
        </div>
        @endif

        <div class="field">
            <label class="field-label">{{ __('N° fiscal / NIF') }}</label>
            <input class="input" wire:model="tax_id" placeholder="CI0000000000000">
            @error('tax_id') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        @if ($type === 'company')
        <div class="field">
            <label class="field-label">{{ __('RCCM') }}</label>
            <input class="input" wire:model="rccm" placeholder="CI-ABJ-2024-B-00001">
            @error('rccm') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        @endif

        <div class="field">
            <label class="field-label">{{ __('Site web') }}</label>
            <input class="input" wire:model="website" type="url" placeholder="https://www.exemple.com">
            @error('website') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Segment') }}</label>
            <select class="input" wire:model="segment">
                <option value="">{{ __('— Non défini —') }}</option>
                <option value="pme">PME</option>
                <option value="grand_compte">Grand compte</option>
                <option value="startup">Start-up</option>
                <option value="ong">ONG / Association</option>
                <option value="administration">Administration publique</option>
                <option value="particulier">Particulier</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label">{{ __('Délai de paiement (jours)') }}</label>
            <input class="input" wire:model="payment_terms" type="number" min="0" step="1" placeholder="30">
            @error('payment_terms') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Devise') }}</label>
            <select class="input" wire:model="currency">
                <option value="XOF">XOF — Franc CFA (BCEAO)</option>
                <option value="XAF">XAF — Franc CFA (BEAC)</option>
                <option value="EUR">EUR — Euro</option>
                <option value="USD">USD — Dollar US</option>
                <option value="GBP">GBP — Livre sterling</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label">{{ __('Plafond de crédit') }}</label>
            <input class="input" wire:model="credit_limit" type="number" step="0.01" min="0" placeholder="0">
            @error('credit_limit') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field flex items-center gap-3 pt-1">
            <label class="flex items-center gap-2 text-[13px] text-slate-600 font-medium cursor-pointer select-none">
                <input type="checkbox" wire:model="is_active" class="w-4 h-4 accent-indigo-600">
                {{ __('Client actif') }}
            </label>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         Section 2 — Adresse et contact de l'entreprise
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3 mb-5 pt-2 border-t border-slate-100">
        <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center" style="margin-top:-1px">
            <svg width="14" height="14" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
        </div>
        <h3 class="text-[12px] font-bold text-slate-700 uppercase tracking-widest">{{ __('2 — Adresse et contact') }}</h3>
        <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

        <div class="field sm:col-span-2">
            <label class="field-label">{{ __('Adresse postale') }}</label>
            <textarea class="input" wire:model="address" rows="2" placeholder="{{ __('Rue, quartier, BP…') }}"></textarea>
        </div>

        <div class="field">
            <label class="field-label">{{ __('Ville') }}</label>
            <input class="input" wire:model="city" placeholder="Abidjan">
        </div>

        <div class="field">
            <label class="field-label">{{ __('Code postal') }}</label>
            <input class="input" wire:model="postal_code" placeholder="01 BP 0000">
        </div>

        <div class="field sm:col-span-2">
            <label class="field-label">{{ __('Pays') }}</label>
            <input class="input" wire:model="country" placeholder="Côte d'Ivoire">
        </div>

        <div class="field">
            <label class="field-label">{{ __('Email général') }}</label>
            <input class="input" wire:model="email" type="email" placeholder="contact@entreprise.com">
            @error('email') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Téléphone principal') }}</label>
            <input class="input" wire:model="phone" placeholder="+225 07 00 00 00 00">
            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Téléphone secondaire') }}</label>
            <input class="input" wire:model="phone2" placeholder="+225 05 00 00 00 00">
            @error('phone2') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field sm:col-span-2">
            <label class="field-label">{{ __('Notes internes') }}</label>
            <textarea class="input" wire:model="notes" rows="3" placeholder="{{ __('Informations complémentaires, contexte, précisions…') }}"></textarea>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         Section 3 — Personne de contact principale
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3 mb-5 pt-2 border-t border-slate-100">
        <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-amber-50 flex items-center justify-center" style="margin-top:-1px">
            <svg width="14" height="14" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
        </div>
        <h3 class="text-[12px] font-bold text-slate-700 uppercase tracking-widest">{{ __('3 — Interlocuteur principal') }}</h3>
        <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <p class="text-[12px] text-slate-400 mb-4 -mt-2">{{ __('La personne à contacter en priorité au sein de cette entreprise.') }}</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <div class="field">
            <label class="field-label">{{ __('Nom complet') }}</label>
            <input class="input" wire:model="contact_name" placeholder="Jean Kouamé">
            @error('contact_name') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Poste / Fonction') }}</label>
            <input class="input" wire:model="contact_role" placeholder="Directeur Général, DAF, Chef de projet…">
            @error('contact_role') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Email direct') }}</label>
            <input class="input" wire:model="contact_email" type="email" placeholder="jean.kouame@entreprise.com">
            @error('contact_email') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Téléphone direct') }}</label>
            <input class="input" wire:model="contact_phone" placeholder="+225 07 00 00 00 00">
            @error('contact_phone') <span class="field-error">{{ $message }}</span> @enderror
        </div>

    </div>

    {{-- Footer --}}
    <div class="flex items-center gap-2 mt-8 pt-4 border-t border-slate-100">
        <a class="btn btn-secondary" href="{{ route('tenant.clients.index', ['tenant' => $tenantCode]) }}">
            {{ __('Retour') }}
        </a>
        <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $clientId ? __('Mettre à jour') : __('Enregistrer le client') }}</span>
            <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
        </button>
    </div>

</div>
</div>
