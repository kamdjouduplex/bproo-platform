<div class="page-body">
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-6">
            {{ $isEdit ? 'Modifier l\'entrepôt' : 'Nouvel entrepôt' }}
        </h2>

        <form wire:submit="save" class="space-y-5">

            <div class="field">
                <label class="field-label">Nom de l'entrepôt <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" class="input" placeholder="Entrepôt principal">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label class="field-label">Localisation / Adresse</label>
                <input type="text" wire:model="location" class="input" placeholder="Rue de la Paix, Douala">
                @error('location') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label class="field-label">Description</label>
                <textarea wire:model="description" class="input min-h-[80px]" placeholder="Informations complémentaires…"></textarea>
                @error('description') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            @if($isEdit)
            <div class="field">
                <label class="field-label">Statut</label>
                <select wire:model="is_active" class="input sm:w-40">
                    <option value="1">Actif</option>
                    <option value="0">Inactif</option>
                </select>
            </div>
            @endif

            <div class="page-actions border-t pt-4">
                <button type="submit" class="btn btn-primary">
                    {{ $isEdit ? 'Enregistrer les modifications' : 'Créer l\'entrepôt' }}
                </button>
                <button type="button" wire:click="cancel" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>
</div>
