@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card">
        <h2 class="card-title">Nouveau lot</h2>
        <p class="text-muted" style="margin-bottom: 16px;">Enregistrer un lot manuellement (réception hors achats ou stock initial).</p>

        <form wire:submit="save" class="form-grid">
            <div class="field">
                <label class="field-label">Article <span class="text-red-600">*</span></label>
                <select class="input" wire:model="item_id" required>
                    <option value="">— Choisir un article —</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}">{{ item_display($item->sku, $item->name) }}</option>
                    @endforeach
                </select>
                @error('item_id')
                    <span class="text-red-600 text-sm">{{ $message }}</span>
                @enderror
            </div>
            <div class="field">
                <label class="field-label">N° lot <span class="text-red-600">*</span></label>
                <input class="input" type="text" wire:model="batch_number" placeholder="Ex: LOT-2026-001" required>
                @error('batch_number')
                    <span class="text-red-600 text-sm">{{ $message }}</span>
                @enderror
            </div>
            <div class="field">
                <label class="field-label">Date de péremption <span class="text-red-600">*</span></label>
                <input class="input" type="date" wire:model="expiry_date" required>
                @error('expiry_date')
                    <span class="text-red-600 text-sm">{{ $message }}</span>
                @enderror
            </div>
            <div class="field">
                <label class="field-label">Quantité <span class="text-red-600">*</span></label>
                <input class="input" type="number" wire:model="quantity" min="0.001" step="any" placeholder="0" required>
                @error('quantity')
                    <span class="text-red-600 text-sm">{{ $message }}</span>
                @enderror
            </div>
            <div class="field" style="grid-column: 1 / -1; display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn btn-primary">Enregistrer le lot</button>
                <a class="btn btn-secondary" href="{{ route('tenant.batches.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            </div>
        </form>
    </section>
</div>
