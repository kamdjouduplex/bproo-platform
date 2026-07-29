<div class="page-body">
    <section class="card">
        <h2 class="card-title">Personnalisation de votre portail</h2>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Nom de la boutique (affiché comme logo)</label>
                <input class="input" placeholder="Ex: Ma Boutique" wire:model="shop_name">
                @error('shop_name')
                    <div class="field-label" style="color:#dc2626;">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label class="field-label">Message de bienvenue (page de connexion)</label>
                <textarea class="input" rows="3" placeholder="Bienvenue dans votre espace de gestion" wire:model="login_welcome_message"></textarea>
                @error('login_welcome_message')
                    <div class="field-label" style="color:#dc2626;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="page-actions">
            <button class="btn btn-primary" wire:click="save">
                Enregistrer
            </button>
        </div>
    </section>
</div>
