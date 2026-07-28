<div>
    <div class="card">
        <h2 class="card-title text-base mb-4">{{ __('Paramètres généraux') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Devise') }}</label>
                <input class="input" wire:model="currency" placeholder="XOF">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Langue') }}</label>
                <select class="input" wire:model="locale">
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">{{ __('Fuseau horaire') }}</label>
                <input class="input" wire:model="timezone" placeholder="Africa/Abidjan">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Taux de taxe (%)') }}</label>
                <input class="input" wire:model="tax_rate" type="number" min="0" step="0.01">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Préfixe facture') }}</label>
                <input class="input" wire:model="invoice_prefix" placeholder="INV">
            </div>
        </div>
        <div class="flex items-center gap-2 mt-6">
            <a class="btn btn-secondary" href="{{ route('system.tenants') }}">{{ __('Retour') }}</a>
            <button class="btn btn-primary" wire:click="save">{{ __('Enregistrer') }}</button>
        </div>
    </div>
</div>
