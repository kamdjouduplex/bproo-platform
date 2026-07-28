@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    <form wire:submit.prevent="save">
        <section class="card">
            <h2 class="card-title">Enregistrement de perte</h2>

            <div class="field" style="margin-bottom: 16px;">
                <label class="field-label">Article *</label>
                <input class="input" wire:model.live.debounce.150ms="itemSearch" placeholder="{{ item_search_placeholder() }}" autocomplete="off" {{ $recordId ? 'readonly' : '' }}>
                @if (!$recordId && !empty($itemSearch) && !empty($searchResults))
                    <div style="margin-top: 8px; max-height: 260px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        @foreach ($searchResults as $item)
                            <div style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer;" wire:click="selectItemById({{ $item['id'] }})" wire:key="item-{{ $item['id'] }}">
                                <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                <div style="font-size: 12px; color: #666;">
                                    @if($item['barcode'] ?? null)
                                        Code : {{ $item['barcode'] }}
                                        <span style="margin: 0 8px;">|</span>
                                    @endif
                                    Coût : {{ fmt_money((float)$item['cost']) }} FCFA
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Raison de perte *</label>
                    <select class="input" wire:model="loss_reason_id" required>
                        <option value="">Sélectionner</option>
                        @foreach ($reasons as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Quantité *</label>
                    <input class="input" wire:model.live="quantity" type="number" step="0.001" min="0.001" required>
                </div>
                <div class="field">
                    <label class="field-label">Valeur (FCFA)</label>
                    <input class="input" wire:model="value" type="number" step="0.01" min="0" placeholder="Calculée si vide">
                </div>
                <div class="field">
                    <label class="field-label">Date de la perte *</label>
                    <input class="input" wire:model="loss_date" type="date" required>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Description</label>
                    <textarea class="input" wire:model="description" rows="3" placeholder="Détails..."></textarea>
                </div>
            </div>
        </section>

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.losses.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            <button type="submit" class="btn btn-primary">{{ $recordId ? 'Mettre à jour' : 'Enregistrer' }}</button>
        </div>
    </form>
</div>
