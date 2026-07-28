@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">
            {{ session('error') }}
        </div>
    @endif

    @if (!$canAdjust)
        <div class="alert alert-error" style="margin-bottom: 16px;">
            Vous n'avez pas la permission pour effectuer un ajustement manuel de stock.
        </div>
    @endif

    <section class="card">
        <h2 class="card-title">Ajustement de stock</h2>
        
        <form wire:submit.prevent="adjust">
            <div class="form-grid">
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Rechercher un article</label>
                    <input class="input" 
                           wire:model.live.debounce.150ms="itemSearch" 
                           placeholder="{{ item_search_placeholder() }}" 
                           autofocus
                           autocomplete="off"
                           {{ ($selectedItemId || !$canAdjust) ? 'disabled' : '' }}>
                    
                    @if (!empty($itemSearch) && !empty($searchResults) && !$selectedItemId)
                        <div style="margin-top: 12px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            @foreach ($searchResults as $item)
                                <div style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;" 
                                     wire:click="selectItem({{ json_encode($item) }})"
                                     onmouseover="this.style.background='#f0f7ff'" 
                                     onmouseout="this.style.background='white'">
                                    <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                    <div style="font-size: 12px; color: #666;">
                                        @if($item['barcode'] ?? null)
                                            Code : {{ $item['barcode'] }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($selectedItem)
                    <div class="field" style="grid-column: 1 / -1; padding: 16px; background: #f5f5f5; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <div>
                                <x-item-label :reference="$selectedItem->sku" :name="$selectedItem->name" />
                                @if ($selectedItem->barcode)
                                    <div style="font-size: 12px; color: #666; margin-top: 4px;">Code : {{ $selectedItem->barcode }}</div>
                                @endif
                            </div>
                            <button type="button" class="btn btn-secondary" wire:click="clearSelection">Changer</button>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label class="field-label">Stock actuel</label>
                                <div style="font-size: 24px; font-weight: 700; color: #2563eb;">
                                    {{ fmt_num($currentStock) }}
                                </div>
                            </div>
                            <div>
                                <label class="field-label">Nouveau stock *</label>
                                <input class="input" 
                                       wire:model="newQuantity" 
                                       type="number" 
                                       min="0" 
                                       step="1" 
                                       placeholder="0"
                                       required>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label class="field-label">Stock d'alerte actuel</label>
                                <div style="font-size: 18px; font-weight: 600; color: #666;">
                                    {{ $currentReorderPoint ? fmt_num($currentReorderPoint) : 'Non défini' }}
                                </div>
                            </div>
                            <div>
                                <label class="field-label">Nouveau stock d'alerte</label>
                                <input class="input" 
                                       wire:model="newReorderPoint" 
                                       type="number" 
                                       min="0" 
                                       step="1" 
                                       placeholder="Laisser vide pour supprimer">
                            </div>
                        </div>
                        
                        <div class="field" style="grid-column: 1 / -1;">
                            <label class="field-label">Raison (optionnel)</label>
                            <textarea class="input" 
                                      wire:model="reason" 
                                      rows="2" 
                                      placeholder="Ex: Inventaire physique, correction d'erreur..."></textarea>
                        </div>

                        <div style="margin-top: 16px; padding: 12px; background: white; border-radius: 4px; border: 1px solid #ddd;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Stock actuel:</span>
                                <strong>{{ fmt_money($currentStock) }}</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Nouveau stock:</span>
                                <strong>{{ fmt_num((float)($newQuantity ?: 0)) }}</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px solid #ddd; font-weight: 600; color: {{ ((float)($newQuantity ?: 0) - $currentStock) >= 0 ? '#16a34a' : '#dc2626' }};">
                                <span>Différence:</span>
                                <strong>
                                    {{ ((float)($newQuantity ?: 0) - $currentStock) >= 0 ? '+' : '' }}{{ fmt_num((float)($newQuantity ?: 0) - $currentStock) }}
                                </strong>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="page-actions" style="margin-top: 24px;">
                <a class="btn btn-secondary" href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">Retour</a>
                @if ($selectedItemId)
                    <button type="submit" class="btn btn-primary" @disabled(!$canAdjust)>Enregistrer l'ajustement</button>
                @endif
            </div>
        </form>
    </section>
</div>
