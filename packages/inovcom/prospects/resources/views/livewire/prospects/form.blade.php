<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <form wire:submit="save">
        <section class="card" style="padding:20px;margin-bottom:16px;">
            <h3 class="form-section-title" style="margin-top:0;">Identité</h3>
            <div class="prospect-form-grid-2">
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Nom / raison sociale *</label>
                    <input class="input" wire:model="name" required maxlength="255" placeholder="Ex. SARL Kamfo Négoce">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Type</label>
                    <select class="input" wire:model.live="type">
                        @foreach (\InovCom\Prospects\Models\Prospect::typeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="prospect-form-hint">Entreprise : RCCM et NIU seront exigés à la conversion client.</span>
                </div>
                <div class="field">
                    <label class="field-label">Source *</label>
                    <select class="input" wire:model="source">
                        @foreach (\InovCom\Prospects\Models\Prospect::sourceOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="prospect-form-hint">D’où vient ce lead — utile pour mesurer le ROI des canaux.</span>
                </div>
                <div class="field">
                    <label class="field-label">Téléphone</label>
                    <input class="input" wire:model="phone" maxlength="40" placeholder="6XX XXX XXX">
                </div>
                <div class="field">
                    <label class="field-label">E-mail</label>
                    <input class="input" type="email" wire:model="email" placeholder="contact@exemple.com">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Adresse</label>
                    <textarea class="input" rows="2" wire:model="address" placeholder="Ville, quartier…"></textarea>
                    <span class="prospect-form-hint">Au moins un téléphone ou un e-mail est requis pour convertir en client.</span>
                </div>
            </div>
        </section>

        @if ($type === 'company')
            <section class="card" style="padding:20px;margin-bottom:16px;">
                <h3 class="form-section-title" style="margin-top:0;">Identifiants entreprise</h3>
                <p class="prospect-form-hint" style="margin:0 0 12px;">
                    À renseigner avant la conversion : mêmes règles que la fiche client.
                </p>
                <div class="prospect-form-grid-2">
                    <div class="field">
                        <label class="field-label">RCCM *</label>
                        <input class="input" wire:model="rccm" placeholder="Ex. RC/YAO/2010/B/520">
                        <span class="prospect-form-hint">Obligatoire pour convertir une entreprise.</span>
                        @error('rccm') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="field-label">NIU *</label>
                        <input class="input" wire:model="niu" placeholder="Ex. M101000033472J">
                        <span class="prospect-form-hint">Obligatoire pour convertir une entreprise.</span>
                        @error('niu') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="field-label">N° fiscal (optionnel)</label>
                        <input class="input" wire:model="tax_id">
                    </div>
                </div>
            </section>
        @endif

        <section class="card" style="padding:20px;margin-bottom:16px;">
            <h3 class="form-section-title" style="margin-top:0;">Commercial &amp; estimation</h3>
            <div class="prospect-form-grid-2">
                <div class="field">
                    <label class="field-label">Commercial</label>
                    <select class="input" wire:model="owner_id">
                        <option value="">— Non assigné —</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Coût du lead (FCFA)</label>
                    <input class="input" type="number" min="0" step="1" wire:model="cost">
                    <span class="prospect-form-hint">
                        Ce que ce lead vous a coûté à acquérir (part de campagne, salon, pub…). Sert au calcul du CAC.
                    </span>
                </div>
                <div class="field">
                    <label class="field-label">CA potentiel estimé (FCFA)</label>
                    <input class="input" type="number" min="0" step="1" wire:model="expected_value">
                    <span class="prospect-form-hint">
                        Chiffre d’affaires que vous estimez pouvoir réaliser avec ce prospect s’il devient client.
                    </span>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Notes</label>
                    <textarea class="input" rows="3" wire:model="notes" placeholder="Contexte, besoins, objections…"></textarea>
                </div>
            </div>
        </section>

        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('tenant.prospects.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                {{ $prospectId ? 'Enregistrer' : 'Créer le prospect' }}
            </button>
        </div>
    </form>
</div>
