<div class="page-body">
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-6">Enregistrer un mouvement de stock</h2>

        <form wire:submit="save" class="space-y-5">

            {{-- ── Type ─────────────────────────────────────────────── --}}
            <div class="field">
                <label class="field-label">Type de mouvement <span class="text-red-500">*</span></label>
                <div class="flex flex-wrap gap-3 mt-1">
                    @foreach(['IN' => ['label' => 'Entrée', 'color' => 'emerald'], 'OUT' => ['label' => 'Sortie', 'color' => 'red'], 'TRANSFER' => ['label' => 'Transfert', 'color' => 'blue']] as $val => $opt)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="type" value="{{ $val }}"
                               class="accent-slate-700">
                        <span class="text-sm font-medium text-slate-700">{{ $opt['label'] }}</span>
                    </label>
                    @endforeach
                </div>
                @error('type') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            {{-- ── Product ───────────────────────────────────────────── --}}
            <div class="field">
                <label class="field-label">Produit <span class="text-red-500">*</span></label>
                <select wire:model="product_id" class="input">
                    <option value="0">— Choisir un produit —</option>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }}) — {{ $p->unit }}</option>
                    @endforeach
                </select>
                @error('product_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            {{-- ── Warehouse(s) ──────────────────────────────────────── --}}
            @if($type === 'TRANSFER')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Entrepôt source <span class="text-red-500">*</span></label>
                    <select wire:model="warehouse_id" class="input">
                        <option value="0">— Depuis —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}{{ $w->location ? ' · ' . $w->location : '' }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Entrepôt destination <span class="text-red-500">*</span></label>
                    <select wire:model="to_warehouse_id" class="input">
                        <option value="0">— Vers —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}{{ $w->location ? ' · ' . $w->location : '' }}</option>
                        @endforeach
                    </select>
                    @error('to_warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="field">
                    <label class="field-label">Entrepôt <span class="text-red-500">*</span></label>
                    <select wire:model="warehouse_id" class="input">
                        <option value="0">— Choisir —</option>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}{{ $w->location ? ' · ' . $w->location : '' }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Motif / Référence</label>
                    <select wire:model="reference_type" class="input">
                        @foreach($referenceTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @endif

            {{-- ── Quantity ──────────────────────────────────────────── --}}
            <div class="field sm:w-48">
                <label class="field-label">Quantité <span class="text-red-500">*</span></label>
                <input type="number" wire:model="quantity" class="input" min="0.001" step="0.001" placeholder="0">
                @error('quantity') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            {{-- ── Notes ────────────────────────────────────────────── --}}
            <div class="field">
                <label class="field-label">Notes</label>
                <textarea wire:model="notes" class="input min-h-[70px]" placeholder="Informations complémentaires…"></textarea>
            </div>

            <div class="page-actions border-t pt-4">
                <button type="submit" class="btn btn-primary">Enregistrer le mouvement</button>
                <button type="button" wire:click="cancel" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>
</div>
