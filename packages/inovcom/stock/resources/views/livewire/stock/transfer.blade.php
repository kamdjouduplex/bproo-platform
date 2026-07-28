@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif

    <section class="card">
        <h2 class="card-title">Transfert inter-boutiques</h2>

        <form wire:submit.prevent="transfer">
            <div class="form-grid">
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Article</label>
                    <input class="input" wire:model.live.debounce.200ms="itemSearch" placeholder="{{ item_search_placeholder() }}">
                    @if (!empty($searchResults))
                        <div style="margin-top: 8px; border: 1px solid #ddd; border-radius: 6px;">
                            @foreach ($searchResults as $item)
                                <button type="button" class="btn btn-secondary" style="display:block; width:100%; text-align:left; border-radius:0;" wire:click="selectItem({{ $item['id'] }})">
                                    <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="field">
                    <label class="field-label">Boutique source</label>
                    <select class="input" wire:model="from_store_id">
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Boutique destination</label>
                    <select class="input" wire:model="to_store_id">
                        <option value="">Choisir</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Quantité</label>
                    <input class="input" type="number" min="0.001" step="0.001" wire:model="quantity">
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Raison (optionnel)</label>
                    <input class="input" wire:model="reason" placeholder="Ex: Rééquilibrage entre boutiques">
                </div>
            </div>

            <div class="page-actions">
                <a class="btn btn-secondary" href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">Retour</a>
                <button type="submit" class="btn btn-primary">Transférer</button>
            </div>
        </form>
    </section>
</div>
