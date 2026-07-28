<div class="page-body">
    <section class="card">
        <h2 class="card-title">{{ $planId ? 'Modifier le plan' : 'Nouveau plan' }}</h2>
        @if ($errors->any())
            <div class="card-body" style="margin-bottom: 16px; background: #fef2f2; border: 1px solid #dc2626; color: #b91c1c; padding: 12px 16px; border-radius: 6px;">
                <strong>Veuillez corriger les erreurs :</strong>
                <ul style="margin: 8px 0 0 16px; padding: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Nom du plan</label>
                <input class="input" placeholder="Ex: Standard Mensuel" wire:model="name">
            </div>
            <div class="field">
                <label class="field-label">Slug (identifiant)</label>
                <input class="input" placeholder="standard-monthly" wire:model="slug">
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-label">Description</label>
                <textarea class="input" rows="2" placeholder="Optionnel" wire:model="description"></textarea>
            </div>
            <div class="field">
                <label class="field-label">Prix</label>
                <input class="input" type="number" min="0" step="0.01" wire:model="price">
            </div>
            <div class="field">
                <label class="field-label">Devise</label>
                <input class="input" placeholder="XOF" wire:model="currency" maxlength="3">
            </div>
            <div class="field">
                <label class="field-label">Période de facturation</label>
                <select class="input" wire:model="billing_interval">
                    @foreach(\App\Models\Plan::billingIntervals() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label">Ordre d'affichage</label>
                <input class="input" type="number" min="0" wire:model="sort_order">
            </div>
            <label class="field-toggle">
                <input type="checkbox" wire:model="is_active">
                Plan actif (proposable aux vendeurs)
            </label>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('system.plans') }}">Retour</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                {{ $planId ? 'Mettre à jour' : 'Créer' }}
            </button>
        </div>
    </section>
</div>
