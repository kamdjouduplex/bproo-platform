<div class="page-body">
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-6">
            {{ $isEdit ? 'Modifier la livraison' : 'Nouvelle livraison' }}
        </h2>

        <form wire:submit="save" class="space-y-5">

            {{-- ── Véhicule & chauffeur ─────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Véhicule <span class="text-red-500">*</span></label>
                    <select wire:model="vehicle_id" class="input">
                        <option value="0">— Choisir un véhicule —</option>
                        @foreach($vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->plate_number }})</option>
                        @endforeach
                    </select>
                    @error('vehicle_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Chauffeur <span class="text-red-500">*</span></label>
                    <select wire:model="driver_id" class="input">
                        <option value="0">— Choisir un chauffeur —</option>
                        @foreach($drivers as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}{{ $d->phone ? ' · ' . $d->phone : '' }}</option>
                        @endforeach
                    </select>
                    @error('driver_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ── Entrepôt, destination, date ──────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="field">
                    <label class="field-label">Entrepôt source <span class="text-red-500">*</span></label>
                    <select wire:model="source_warehouse_id" class="input">
                        <option value="0">— Choisir —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}{{ $w->location ? ' · ' . $w->location : '' }}</option>
                        @endforeach
                    </select>
                    @error('source_warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Destination / Chantier</label>
                    <input type="text" wire:model="destination" class="input" placeholder="Ex: Chantier Nord, Yaoundé">
                    @error('destination') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Date prévue <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="scheduled_at" class="input">
                    @error('scheduled_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ── Articles ─────────────────────────────────────────── --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="field-label mb-0">Articles à livrer <span class="text-red-500">*</span></label>
                    <button type="button" wire:click="addItem" class="btn btn-secondary btn-sm">+ Ajouter un article</button>
                </div>
                @error('items') <p class="field-error mb-2">{{ $message }}</p> @enderror

                <div class="space-y-2">
                    @foreach($items as $i => $item)
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg" wire:key="item-{{ $i }}">
                        <div class="flex-1">
                            <select wire:model="items.{{ $i }}.product_id" class="input input-sm">
                                <option value="">— Produit —</option>
                                @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }}) — {{ $p->unit }}</option>
                                @endforeach
                            </select>
                            @error("items.{$i}.product_id") <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="w-32">
                            <input type="number" wire:model="items.{{ $i }}.quantity"
                                   class="input input-sm text-right" min="0.001" step="0.001" placeholder="Qté">
                            @error("items.{$i}.quantity") <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        @if(count($items) > 1)
                        <button type="button" wire:click="removeItem({{ $i }})"
                                class="text-red-400 hover:text-red-600 flex-shrink-0" title="Supprimer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Notes ────────────────────────────────────────────── --}}
            <div class="field">
                <label class="field-label">Notes</label>
                <textarea wire:model="notes" class="input min-h-[70px]" placeholder="Informations complémentaires…"></textarea>
            </div>

            <div class="page-actions border-t pt-4">
                <button type="submit" class="btn btn-primary">
                    {{ $isEdit ? 'Enregistrer les modifications' : 'Créer la livraison' }}
                </button>
                <button type="button" wire:click="cancel" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>
</div>
