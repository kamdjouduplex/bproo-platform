<div class="page-body">
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-6">
            {{ $isEdit ? 'Modifier le produit' : 'Nouveau produit' }}
        </h2>

        <form wire:submit="save" class="space-y-5">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Nom du produit <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="input" placeholder="Ciment CPJ 45">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Catégorie</label>
                    <select wire:model="category" class="input">
                        <option value="">— Choisir —</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Unité de mesure <span class="text-red-500">*</span></label>
                    <select wire:model="unit" class="input">
                        @foreach($units as $u)
                        <option value="{{ $u }}">{{ $u }}</option>
                        @endforeach
                    </select>
                    @error('unit') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Seuil d'alerte stock bas</label>
                    <input type="number" wire:model="min_stock_alert" class="input" min="0" step="0.001" placeholder="0">
                    @error('min_stock_alert') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="field">
                <label class="field-label">Description</label>
                <textarea wire:model="description" class="input min-h-[80px]" placeholder="Description optionnelle…"></textarea>
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
                    {{ $isEdit ? 'Enregistrer les modifications' : 'Créer le produit' }}
                </button>
                <button type="button" wire:click="cancel" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>
</div>
