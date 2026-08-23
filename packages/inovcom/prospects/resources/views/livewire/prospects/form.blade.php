@if (View::exists('inovcom-crm::partials.styles'))
    @include('inovcom-crm::partials.styles')
@endif
<div class="page-body crm-v2">
    @if (session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <form wire:submit="save">
        <section class="crm-card" style="margin-bottom:16px;">
            <h3 class="crm-card__title">Capture rapide</h3>
            <p class="crm-act-row__meta" style="margin-bottom:12px;">Peu de champs maintenant — vous enrichirez la fiche ensuite.</p>
            <div class="prospect-form-grid-2">
                <div class="field">
                    <label class="field-label">Type</label>
                    <select class="input" wire:model.live="type">
                        @foreach (\InovCom\Prospects\Models\Prospect::typeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Source *</label>
                    <select class="input" wire:model="source">
                        @foreach (\InovCom\Prospects\Models\Prospect::sourceOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Prénom</label>
                    <input class="input" wire:model="first_name">
                </div>
                <div class="field">
                    <label class="field-label">Nom</label>
                    <input class="input" wire:model="last_name">
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">{{ $type === 'company' ? 'Entreprise *' : 'Nom affiché *' }}</label>
                    <input class="input" wire:model="name" required maxlength="255" placeholder="Ex. KREOBAT SARL">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                @if ($type === 'individual')
                    <div class="field">
                        <label class="field-label">Entreprise</label>
                        <input class="input" wire:model="company_name">
                    </div>
                @endif
                <div class="field">
                    <label class="field-label">Fonction</label>
                    <input class="input" wire:model="job_title" placeholder="Ex. Responsable IT">
                </div>
                <div class="field">
                    <label class="field-label">Téléphone</label>
                    <input class="input" wire:model="phone">
                </div>
                <div class="field">
                    <label class="field-label">WhatsApp</label>
                    <input class="input" wire:model="whatsapp">
                </div>
                <div class="field">
                    <label class="field-label">E-mail</label>
                    <input class="input" type="email" wire:model="email">
                </div>
                <div class="field">
                    <label class="field-label">Ville</label>
                    <input class="input" wire:model="city">
                </div>
                <div class="field">
                    <label class="field-label">Produit / service recherché</label>
                    <input class="input" wire:model="product_interest" placeholder="Ex. ERP BTP">
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Besoin</label>
                    <input class="input" wire:model="need" placeholder="Besoin principal en une phrase">
                </div>
                <div class="field">
                    <label class="field-label">Commercial</label>
                    <select class="input" wire:model="owner_id">
                        <option value="">—</option>
                        @foreach ($owners as $o)
                            <option value="{{ $o->id }}">{{ $o->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Commentaire</label>
                    <textarea class="input" rows="2" wire:model="notes"></textarea>
                </div>
            </div>
        </section>

        @if ($type === 'company' && $prospectId)
            <section class="crm-card" style="margin-bottom:16px;">
                <h3 class="crm-card__title">Identifiants entreprise (avant conversion client)</h3>
                <div class="prospect-form-grid-2">
                    <div class="field"><label class="field-label">RCCM</label><input class="input" wire:model="rccm"></div>
                    <div class="field"><label class="field-label">NIU</label><input class="input" wire:model="niu"></div>
                </div>
            </section>
        @endif

        <div class="crm-v2-actions">
            <a class="btn btn-secondary" href="{{ route('tenant.prospects.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
    </form>
</div>
