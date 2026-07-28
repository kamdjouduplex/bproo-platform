<div class="page-body">
    <section class="card">
        <h2 class="card-title">Paramètres généraux</h2>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Devise</label>
                <input class="input" wire:model="currency" placeholder="XOF">
            </div>
            <div class="field">
                <label class="field-label">Langue</label>
                <select class="input" wire:model="locale">
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Fuseau horaire</label>
                <select class="input" wire:model="timezone">
                    <option value="Africa/Douala">Afrique / Douala (Cameroun, UTC+1)</option>
                    <option value="Africa/Lagos">Afrique / Lagos (UTC+1)</option>
                    <option value="Africa/Kinshasa">Afrique / Kinshasa (UTC+1)</option>
                    <option value="Africa/Libreville">Afrique / Libreville (UTC+1)</option>
                    <option value="Africa/Brazzaville">Afrique / Brazzaville (UTC+1)</option>
                    <option value="Africa/Abidjan">Afrique / Abidjan (UTC)</option>
                    <option value="Africa/Dakar">Afrique / Dakar (UTC)</option>
                    <option value="Africa/Johannesburg">Afrique / Johannesburg (UTC+2)</option>
                    <option value="Africa/Nairobi">Afrique / Nairobi (UTC+3)</option>
                    <option value="Europe/Paris">Europe / Paris</option>
                    <option value="UTC">UTC</option>
                    @if ($timezone !== '' && ! in_array($timezone, [
                        'Africa/Douala', 'Africa/Lagos', 'Africa/Kinshasa', 'Africa/Libreville', 'Africa/Brazzaville',
                        'Africa/Abidjan', 'Africa/Dakar', 'Africa/Johannesburg', 'Africa/Nairobi', 'Europe/Paris', 'UTC',
                    ], true))
                        <option value="{{ $timezone }}">{{ $timezone }}</option>
                    @endif
                </select>
            </div>
            <div class="field">
                <label class="field-label">Taux de taxe (%)</label>
                <input class="input" wire:model="tax_rate" type="number" min="0" step="0.01">
            </div>
            <div class="field">
                <label class="field-label">Préfixe facture</label>
                <input class="input" wire:model="invoice_prefix" placeholder="INV">
            </div>
            <label class="field-toggle">
                <input type="checkbox" wire:model="multi_store_enabled">
                Tenant multi-magasins
            </label>
        </div>
        <hr style="margin: 16px 0; border: 0; border-top: 1px solid #e5e7eb;">
        <h3 class="card-title">Activation Multi-magasins (batch)</h3>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Nom du magasin par défaut</label>
                <input class="input" wire:model="default_store_name" placeholder="Magasin principal">
            </div>
            <div class="field">
                <label class="field-label">Statut setup</label>
                <input class="input" value="{{ $tenant->multi_store_setup_status ?? 'pending' }}" disabled>
            </div>
            @if($tenant->multi_store_setup_error)
                <div class="field" style="grid-column: 1 / -1; color:#dc2626;">
                    {{ $tenant->multi_store_setup_error }}
                </div>
            @endif
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('system.tenants') }}">Retour</a>
            <button class="btn btn-secondary" wire:click="enableMultiStore">Lancer setup multi-magasins</button>
            <button class="btn btn-primary" wire:click="save">Enregistrer</button>
        </div>
    </section>
</div>
