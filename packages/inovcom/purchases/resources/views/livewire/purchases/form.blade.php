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

    <form wire:submit.prevent="saveDraft">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            {{-- Left: Cart and Items --}}
            <div>
                <section class="card">
                    <h2 class="card-title">Recherche d'article</h2>
                    <input class="input" 
                           wire:model.live.debounce.150ms="itemSearch" 
                           placeholder="{{ item_search_placeholder() }}" 
                           autofocus
                           autocomplete="off">
                    
                    @if (!empty($itemSearch) && !empty($searchResults))
                        <div style="margin-top: 12px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            @foreach ($searchResults as $item)
                                <div style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;" 
                                     wire:click="addItemToCart({{ json_encode($item) }})"
                                     onmouseover="this.style.background='#f0f7ff'" 
                                     onmouseout="this.style.background='white'">
                                    <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                    <div style="font-size: 12px; color: #666;">
                                        @if($item['barcode'] ?? null)
                                            Code : {{ $item['barcode'] }}
                                            <span style="margin: 0 8px;">|</span>
                                        @endif
                                        @if (!empty($item['has_last_purchase_cost']))
                                            | <strong style="color: #2563eb;">Dernier coût d'achat : {{ fmt_money((float) $item['last_purchase_cost']) }} FCFA</strong>
                                        @else
                                            | Coût article : {{ fmt_money((float) $item['cost']) }} FCFA
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="card" style="margin-top: 16px;">
                    <h2 class="card-title">Articles commandés</h2>
                    @if (empty($cart))
                        <p style="text-align: center; padding: 40px; color: #999;">Panier vide</p>
                    @else
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Article</th>
                                        <th>Qté</th>
                                        <th>Coût d'achat</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cart as $index => $item)
                                        <tr>
                                            <td>
                                                <x-item-label :reference="$item['item_sku'] ?? null" :name="$item['item_name'] ?? null" />
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="input input-sm" 
                                                       wire:model.debounce.300ms="cart.{{ $index }}.quantity"
                                                       wire:change="updateCartQuantity({{ $index }}, $event.target.value)"
                                                       min="0.001" 
                                                       step="0.001" 
                                                       style="width: 80px;">
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="input input-sm" 
                                                       wire:model.debounce.300ms="cart.{{ $index }}.unit_price"
                                                       wire:change="updateCartPrice({{ $index }}, $event.target.value)"
                                                       min="0" 
                                                       step="1" 
                                                       style="width: 100px;">
                                            </td>
                                            <td>{{ fmt_money((float)$item['line_total']) }} FCFA</td>
                                            <td>
                                                <button type="button" class="btn btn-secondary btn-sm" wire:click="removeFromCart({{ $index }})">×</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            {{-- Right: Order Details --}}
            <div>
                <section class="card">
                    <h2 class="card-title">Détails de la commande</h2>
                    <div class="form-grid">
                        <div class="form-group @error('provider_id') form-group--invalid @enderror" style="grid-column: 1 / -1;">
                            <label class="field-label">Fournisseur <span class="field-hint">(optionnel)</span></label>

                            @if ($providerPicker)
                                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px 16px; border:1px solid #bbf7d0; border-radius:8px; background:#f0fdf4;">
                                    <div style="min-width:0; flex:1;">
                                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                            <strong style="font-size:15px;">{{ $providerPicker['name'] }}</strong>
                                            <span class="badge badge-secondary">{{ $providerPicker['code'] }}</span>
                                            @if (!empty($providerPicker['payment_method_label']) && $providerPicker['payment_method_label'] !== '—')
                                                <span class="badge badge-info">{{ $providerPicker['payment_method_label'] }}</span>
                                            @endif
                                        </div>
                                        <div style="margin-top:8px; font-size:13px; color:#4b5563; display:flex; flex-direction:column; gap:4px;">
                                            @if (!empty($providerPicker['phone']))
                                                <span>Tél. {{ $providerPicker['phone'] }}</span>
                                            @endif
                                            @if (!empty($providerPicker['email']))
                                                <span>{{ $providerPicker['email'] }}</span>
                                            @endif
                                            @if (!empty($providerPicker['city']))
                                                <span>{{ $providerPicker['city'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="clearProvider" style="flex-shrink:0;">
                                        Changer
                                    </button>
                                </div>
                            @else
                                <div style="position:relative;">
                                    <input
                                        class="input @error('provider_id') input--invalid @enderror"
                                        type="search"
                                        wire:model.live.debounce.200ms="providerSearch"
                                        placeholder="Nom, code, téléphone, email, ville… (min. 2 caractères)"
                                        autocomplete="off"
                                    >
                                    <p class="field-hint" style="margin-top:6px;">Le dernier coût d'achat affiché provient de l'historique des achats (confirmés ou réceptionnés), filtré par fournisseur si renseigné.</p>
                                    <div wire:loading wire:target="providerSearch" class="field-hint" style="margin-top:4px;">
                                        Recherche en cours…
                                    </div>
                                    @if (strlen(trim($providerSearch)) >= 2 && count($providerResults) === 0)
                                        <div wire:loading.remove wire:target="providerSearch" style="margin-top:8px; padding:12px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa; color:#6b7280; font-size:13px;">
                                            Aucun fournisseur actif trouvé pour « {{ $providerSearch }} ».
                                        </div>
                                    @endif
                                    @if (count($providerResults) > 0)
                                        <div style="margin-top:8px; max-height:240px; overflow-y:auto; border:1px solid #d1d5db; border-radius:8px; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                                            @foreach ($providerResults as $p)
                                                <button
                                                    type="button"
                                                    wire:click="selectProvider({{ $p['id'] }})"
                                                    wire:key="purchase-provider-{{ $p['id'] }}"
                                                    style="display:block; width:100%; text-align:left; padding:12px 14px; border:none; border-bottom:1px solid #eee; background:transparent; cursor:pointer;"
                                                    onmouseover="this.style.background='#f0fdf4'"
                                                    onmouseout="this.style.background='transparent'"
                                                >
                                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                                        <div>
                                                            <strong>{{ $p['name'] }}</strong>
                                                            <span style="color:#6b7280; font-size:12px;"> — {{ $p['code'] }}</span>
                                                        </div>
                                                        @if (!empty($p['payment_method_label']) && $p['payment_method_label'] !== '—')
                                                            <span class="badge badge-secondary" style="font-size:10px;">{{ $p['payment_method_label'] }}</span>
                                                        @endif
                                                    </div>
                                                    @if (!empty($p['phone']) || !empty($p['email']) || !empty($p['city']))
                                                        <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                                                            @if (!empty($p['phone'])){{ $p['phone'] }}@endif
                                                            @if (!empty($p['phone']) && (!empty($p['email']) || !empty($p['city']))) · @endif
                                                            @if (!empty($p['email'])){{ $p['email'] }}@endif
                                                            @if (!empty($p['email']) && !empty($p['city'])) · @endif
                                                            @if (!empty($p['city'])){{ $p['city'] }}@endif
                                                        </div>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif (strlen(trim($providerSearch)) > 0 && strlen(trim($providerSearch)) < 2)
                                        <p class="field-hint" style="margin-top:6px;">Saisissez au moins 2 caractères.</p>
                                    @endif
                                </div>
                            @endif

                            @error('provider_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="field-label">Date de commande *</label>
                            <input class="input" wire:model="order_date" type="date" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Date de livraison prévue</label>
                            <input class="input" wire:model="expected_date" type="date">
                        </div>
                        <div class="field">
                            <label class="field-label">Notes</label>
                            <textarea class="input" wire:model="notes" rows="3"></textarea>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding: 16px; background: #f5f5f5; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 2px solid #333; font-size: 18px;">
                            <span><strong>TOTAL:</strong></span>
                            <strong>{{ fmt_money($this->total) }} FCFA</strong>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.purchases.index', ['tenant' => $tenantCode]) }}">Retour</a>
            <button type="submit" class="btn btn-secondary" {{ empty($cart) ? 'disabled' : '' }}>
                Enregistrer en brouillon
            </button>
            @if ($canConfirm)
                <button type="button" class="btn btn-primary" wire:click="saveAndConfirm" {{ empty($cart) ? 'disabled' : '' }}
                        wire:confirm="Confirmer cet achat ? Les prix seront enregistrés dans l'historique.">
                    Confirmer un achat
                </button>
            @endif
        </div>
    </form>
</div>
