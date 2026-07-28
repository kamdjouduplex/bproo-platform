<div class="page-body">
    <div class="card p-6">

        <h2 class="text-lg font-semibold text-slate-800 mb-6">
            {{ $isEdit ? 'Modifier la fiche employé' : 'Nouvelle fiche employé' }}
        </h2>

        <form wire:submit="save" class="space-y-5">

            {{-- ── Identité ─────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Prénom <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="first_name" class="input" placeholder="Jean">
                    @error('first_name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Nom <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="last_name" class="input" placeholder="DUPONT">
                    @error('last_name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ── Contact ──────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Email</label>
                    <input type="email" wire:model="email" class="input" placeholder="jean.dupont@exemple.com">
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Téléphone</label>
                    <input type="text" wire:model="phone" class="input" placeholder="+237 6XX XXX XXX">
                    @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ── Poste ────────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Poste / Fonction</label>
                    <input type="text" wire:model="position" class="input" placeholder="Chef de projet">
                    @error('position') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Service / Département</label>
                    <input type="text" wire:model="department" class="input" placeholder="Travaux">
                    @error('department') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ── Contrat ───────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="field">
                    <label class="field-label">Type de contrat <span class="text-red-500">*</span></label>
                    <select wire:model="contract_type" class="input">
                        <option value="cdi">CDI</option>
                        <option value="cdd">CDD</option>
                        <option value="stage">Stage</option>
                        <option value="freelance">Prestataire</option>
                        <option value="alternance">Alternance</option>
                    </select>
                    @error('contract_type') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Date d'embauche <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="hire_date" class="input">
                    @error('hire_date') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Date de fin
                        <span class="text-slate-400 font-normal text-xs">(CDD uniquement)</span>
                    </label>
                    <input type="date" wire:model="end_date" class="input">
                    @error('end_date') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ── Salaire + IBAN ────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Salaire de base (FCFA)</label>
                    <input type="number" wire:model="base_salary" class="input" min="0" step="500" placeholder="0">
                    @error('base_salary') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">IBAN / Coordonnées bancaires</label>
                    <input type="text" wire:model="iban" class="input" placeholder="CMXXXXXXXXXXXXXX">
                    @error('iban') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ── Statut (edit uniquement) ──────────────────────────── --}}
            @if($isEdit)
            <div class="field">
                <label class="field-label">Statut</label>
                <select wire:model="status" class="input sm:w-48">
                    <option value="active">Actif</option>
                    <option value="on_leave">En congé</option>
                    <option value="terminated">Sorti</option>
                </select>
                @error('status') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            @endif

            {{-- ── Notes ────────────────────────────────────────────── --}}
            <div class="field">
                <label class="field-label">Notes internes</label>
                <textarea wire:model="notes" class="input min-h-[80px]"
                          placeholder="Informations complémentaires…"></textarea>
                @error('notes') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            {{-- ── Actions ──────────────────────────────────────────── --}}
            <div class="page-actions border-t pt-4">
                <button type="submit" class="btn btn-primary">
                    {{ $isEdit ? 'Enregistrer les modifications' : 'Créer l\'employé' }}
                </button>
                <button type="button" wire:click="cancel" class="btn btn-secondary">
                    Annuler
                </button>
            </div>

        </form>

    </div>
</div>
