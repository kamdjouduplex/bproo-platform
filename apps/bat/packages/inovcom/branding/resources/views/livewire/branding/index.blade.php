<div>

    {{-- Section 1 — Identité de l'entreprise --}}
    <section class="card mb-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __("Identité de l'entreprise") }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Nom commercial') }} <span class="text-red-500">*</span></label>
                <input type="text" class="input" placeholder="Ex : KREOBAT SARL" wire:model="shop_name">
                @error('shop_name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Slogan / Tagline') }}</label>
                <input type="text" class="input" placeholder="Ex : Construire l'avenir ensemble" wire:model="company_tagline">
                @error('company_tagline') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Message de bienvenue (page de connexion)') }}</label>
                <textarea class="input" rows="2" placeholder="Ex : Bienvenue dans votre espace de gestion KREOBAT" wire:model="login_welcome_message"></textarea>
                @error('login_welcome_message') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Site web') }}</label>
                <input type="url" class="input" placeholder="https://www.kreobat.cm" wire:model="company_website">
                @error('company_website') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </section>

    {{-- Section 2 — Coordonnées & Contacts --}}
    <section class="card mb-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Coordonnées & Contacts') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Email professionnel') }}</label>
                <input type="email" class="input" placeholder="contact@kreobat.cm" wire:model="company_email">
                @error('company_email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Téléphone principal') }}</label>
                <input type="text" class="input" placeholder="+237 2 33 00 00 00" wire:model="company_phone">
                @error('company_phone') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Téléphone secondaire / WhatsApp') }}</label>
                <input type="text" class="input" placeholder="+237 6 99 00 00 00" wire:model="company_phone2">
                @error('company_phone2') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Ville') }}</label>
                <input type="text" class="input" placeholder="Douala" wire:model="company_city">
                @error('company_city') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Adresse complète') }}</label>
                <textarea class="input" rows="2" placeholder="Rue des Palmiers, Akwa, BP 2200" wire:model="company_address"></textarea>
                @error('company_address') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Pays') }}</label>
                <input type="text" class="input" placeholder="Cameroun" wire:model="company_country">
                @error('company_country') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Code postal') }}</label>
                <input type="text" class="input" placeholder="BP 2200" wire:model="company_postal_code">
                @error('company_postal_code') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Registre du commerce / NINEA') }}</label>
                <input type="text" class="input" placeholder="RC : Dla/2020/B/001" wire:model="company_registration">
                @error('company_registration') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Numéro contribuable / Tax ID') }}</label>
                <input type="text" class="input" placeholder="M052019876A" wire:model="company_tax_id">
                @error('company_tax_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </section>

    {{-- Section 3 — Finance & Facturation --}}
    <section class="card mb-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Finance & Facturation') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Devise') }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model="currency">
                    <option value="XOF">XOF — Franc CFA (UEMOA)</option>
                    <option value="XAF">XAF — Franc CFA (CEMAC)</option>
                    <option value="EUR">EUR — Euro</option>
                    <option value="USD">USD — Dollar US</option>
                    <option value="GBP">GBP — Livre sterling</option>
                    <option value="MAD">MAD — Dirham marocain</option>
                </select>
                @error('currency') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Taux TVA par défaut (%)') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" max="100" class="input" placeholder="19.25" wire:model="tax_rate">
                @error('tax_rate') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Conditions de paiement (jours)') }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model="payment_terms">
                    <option value="0">Comptant</option>
                    <option value="15">15 jours</option>
                    <option value="30">30 jours</option>
                    <option value="45">45 jours</option>
                    <option value="60">60 jours</option>
                    <option value="90">90 jours</option>
                </select>
                @error('payment_terms') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Préfixe factures') }} <span class="text-red-500">*</span></label>
                <input type="text" class="input" placeholder="FAC" wire:model="invoice_prefix" maxlength="10">
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Ex :') }} {{ $invoice_prefix ?: 'FAC' }}00001</span>
                @error('invoice_prefix') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Préfixe proformas') }} <span class="text-red-500">*</span></label>
                <input type="text" class="input" placeholder="PRO" wire:model="proforma_prefix" maxlength="10">
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Ex :') }} {{ $proforma_prefix ?: 'PRO' }}00001 — {{ __('acomptes, devis facturables') }}</span>
                @error('proforma_prefix') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Préfixe avoirs') }} <span class="text-red-500">*</span></label>
                <input type="text" class="input" placeholder="AV" wire:model="credit_note_prefix" maxlength="10">
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Ex :') }} {{ $credit_note_prefix ?: 'AV' }}00001</span>
                @error('credit_note_prefix') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Préfixe devis') }} <span class="text-red-500">*</span></label>
                <input type="text" class="input" placeholder="DEV" wire:model="quote_prefix" maxlength="10">
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Ex :') }} {{ $quote_prefix ?: 'DEV' }}00001</span>
                @error('quote_prefix') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <h3 class="text-[13px] font-bold text-slate-600 mt-5 mb-3">
            {{ __('Coordonnées bancaires') }}
            <span class="text-[11px] font-normal text-slate-400">{{ __('(imprimées sur les factures)') }}</span>
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Banque') }}</label>
                <input type="text" class="input" placeholder="Afriland First Bank" wire:model="bank_name">
                @error('bank_name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('N° de compte') }}</label>
                <input type="text" class="input" placeholder="10005 00001 00000123456 12" wire:model="bank_account">
                @error('bank_account') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('IBAN / Code Swift') }}</label>
                <input type="text" class="input" placeholder="CM21 1000 5000 0100 0001 2345 612" wire:model="bank_iban">
                @error('bank_iban') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <h3 class="text-[13px] font-bold text-slate-600 mt-5 mb-3">{{ __('Pied de page des factures / devis') }}</h3>
        <div class="field">
            <label class="field-label">{{ __('Texte de bas de page') }}</label>
            <textarea class="input" rows="3"
                placeholder="Ex : Tout litige sera soumis au Tribunal de Commerce de Douala. Pénalités de retard : 1,5% par mois."
                wire:model="invoice_footer"></textarea>
            @error('invoice_footer') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </section>

    {{-- Section 4 — Régionalisation --}}
    <section class="card mb-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Régionalisation') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="field">
                <label class="field-label">{{ __("Langue de l'interface") }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model="locale">
                    <option value="fr">🇫🇷 Français</option>
                    <option value="en">🇬🇧 English</option>
                </select>
                @error('locale') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Fuseau horaire') }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model="timezone">
                    <optgroup label="Afrique">
                        <option value="Africa/Douala">Africa/Douala (WAT +1)</option>
                        <option value="Africa/Lagos">Africa/Lagos (WAT +1)</option>
                        <option value="Africa/Abidjan">Africa/Abidjan (GMT +0)</option>
                        <option value="Africa/Dakar">Africa/Dakar (GMT +0)</option>
                        <option value="Africa/Nairobi">Africa/Nairobi (EAT +3)</option>
                        <option value="Africa/Casablanca">Africa/Casablanca (WET +0/+1)</option>
                        <option value="Africa/Johannesburg">Africa/Johannesburg (SAST +2)</option>
                        <option value="Africa/Cairo">Africa/Cairo (EET +2)</option>
                    </optgroup>
                    <optgroup label="Europe">
                        <option value="Europe/Paris">Europe/Paris (CET +1/+2)</option>
                        <option value="Europe/London">Europe/London (GMT/BST)</option>
                    </optgroup>
                    <optgroup label="Autres">
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">America/New_York (EST)</option>
                    </optgroup>
                </select>
                @error('timezone') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Format de date') }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model="date_format">
                    <option value="d/m/Y">JJ/MM/AAAA ({{ now()->format('d/m/Y') }})</option>
                    <option value="d-m-Y">JJ-MM-AAAA ({{ now()->format('d-m-Y') }})</option>
                    <option value="Y-m-d">AAAA-MM-JJ ({{ now()->format('Y-m-d') }})</option>
                    <option value="m/d/Y">MM/JJ/AAAA ({{ now()->format('m/d/Y') }})</option>
                </select>
                @error('date_format') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </section>

    {{-- Actions --}}
    <div class="flex items-center gap-2 mt-4">
        <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">{{ __('Enregistrer la configuration') }}</span>
            <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
        </button>
    </div>

</div>
