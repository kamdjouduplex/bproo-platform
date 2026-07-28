@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card">
        <h2 class="card-title">{{ $providerId ? 'Modifier un fournisseur' : 'Créer un fournisseur' }}</h2>
        
        <form wire:submit.prevent="save">
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Code *</label>
                    <input class="input" wire:model="code" placeholder="Ex: FOUR-000001">
                </div>
                <div class="field">
                    <label class="field-label">Nom *</label>
                    <input class="input" wire:model="name" placeholder="Ex: Distributeur ABC">
                </div>
                <div class="field">
                    <label class="field-label">Téléphone</label>
                    <input class="input" wire:model="phone" placeholder="+237 6XX XXX XXX">
                </div>
                <div class="field">
                    <label class="field-label">Email</label>
                    <input class="input" wire:model="email" type="email" placeholder="exemple@email.com">
                </div>
                <div class="field">
                    <label class="field-label">Adresse</label>
                    <input class="input" wire:model="address" placeholder="Rue, quartier">
                </div>
                <div class="field">
                    <label class="field-label">Ville</label>
                    <input class="input" wire:model="city" placeholder="Ville">
                </div>
                <div class="field">
                    <label class="field-label">Pays</label>
                    <input class="input" wire:model="country" placeholder="CM" maxlength="2">
                </div>
                <label class="field-toggle">
                    <input type="checkbox" wire:model.live="is_foreign">
                    Fournisseur étranger
                </label>
                @if ($is_foreign)
                    <div class="field">
                        <label class="field-label">Devise par défaut</label>
                        <select class="input" wire:model="default_currency">
                            <option value="">— Choisir —</option>
                            @foreach ($currencies as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="field">
                    <label class="field-label">NIF / Tax ID</label>
                    <input class="input" wire:model="tax_id" placeholder="Numéro d'identification fiscale">
                </div>
                <div class="field">
                    <label class="field-label">Mode de paiement</label>
                    <select class="input" wire:model="payment_method">
                        <option value="">— Non défini —</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Conditions de paiement (délai)</label>
                    <select class="input" wire:model="payment_term_id">
                        <option value="">Aucune</option>
                        @foreach ($paymentTerms as $term)
                            <option value="{{ $term->id }}">{{ $term->name }} ({{ $term->days }} jours)</option>
                        @endforeach
                    </select>
                    <div style="display:flex; gap:8px; margin-top:6px;">
                        <input class="input input-sm" wire:model="newPaymentTermName" placeholder="Nouveau terme">
                        <input class="input input-sm" wire:model="newPaymentTermDays" type="number" min="0" placeholder="Jours" style="width: 80px;">
                        <button type="button" class="btn btn-secondary" wire:click="createPaymentTerm">Ajouter</button>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Notes</label>
                    <textarea class="input" wire:model="notes" rows="3" placeholder="Notes internes..."></textarea>
                </div>
                <label class="field-toggle">
                    <input type="checkbox" wire:model="is_active">
                    Actif
                </label>
            </div>

            <div class="page-actions" style="margin-top: 24px;">
                <a class="btn btn-secondary" href="{{ route('tenant.providers.index', ['tenant' => $tenantCode]) }}">Retour</a>
                <button type="submit" class="btn btn-primary">
                    {{ $providerId ? 'Mettre à jour' : 'Enregistrer' }}
                </button>
            </div>
        </form>
    </section>
</div>
