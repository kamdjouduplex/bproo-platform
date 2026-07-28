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

    @if ($readOnly)
        <div class="alert alert-warning" style="margin-bottom: 16px;">
            Cette commande est confirmée et ne peut plus être modifiée.
        </div>
    @endif

    <form wire:submit.prevent="saveDraft">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            <div>
                <section class="card">
                    <h2 class="card-title">Recherche d'article</h2>
                    <input class="input"
                           wire:model.live.debounce.150ms="itemSearch"
                           placeholder="{{ item_search_placeholder() }}"
                           @disabled($readOnly)
                           autofocus
                           autocomplete="off">

                    @if (!empty($itemSearch) && !empty($searchResults))
                        <div style="margin-top: 12px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            @foreach ($searchResults as $item)
                                <div style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer;"
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
                                            <strong style="color: #2563eb;">Dernier coût d'achat ({{ $currency_code }}) : {{ fmt_num((float) $item['last_purchase_cost_foreign'], 4) }}</strong>
                                            <span style="color: #888;"> — modifiable dans le panier</span>
                                        @else
                                            Aucun achat étranger en {{ $currency_code }} — saisir le coût dans le panier
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
                                        <th>Coût unit. ({{ $currency_code }})</th>
                                        <th>Total ({{ $currency_code }})</th>
                                        <th>Prix unit. FCFA</th>
                                        <th>Total FCFA</th>
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
                                                       wire:change="updateCartQuantity({{ $index }}, $event.target.value)"
                                                       value="{{ $item['quantity'] }}"
                                                       @disabled($readOnly)
                                                       min="0.001"
                                                       step="0.001"
                                                       style="width: 80px;">
                                            </td>
                                            <td>
                                                <input type="number"
                                                       class="input input-sm"
                                                       wire:change="updateCartPriceForeign({{ $index }}, $event.target.value)"
                                                       value="{{ $item['unit_price_foreign'] }}"
                                                       @disabled($readOnly)
                                                       min="0"
                                                       step="0.0001"
                                                       style="width: 100px;">
                                            </td>
                                            <td>{{ fmt_money((float) $item['line_total_foreign']) }}</td>
                                            <td>{{ fmt_money((float) $item['unit_price_local']) }}</td>
                                            <td>{{ fmt_money((float) $item['line_total_local']) }}</td>
                                            <td>
                                                @if (!$readOnly)
                                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeFromCart({{ $index }})">×</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <div>
                <section class="card">
                    <h2 class="card-title">Détails de la commande</h2>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label class="field-label">Fournisseur</label>
                            @if ($providerPicker)
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px; border:1px solid #ddd; border-radius:8px;">
                                    <div>
                                        <strong>{{ $providerPicker['name'] }}</strong>
                                        <div style="font-size:12px; color:#666;">{{ $providerPicker['code'] }}</div>
                                    </div>
                                    @if (!$readOnly)
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="clearProvider">Changer</button>
                                    @endif
                                </div>
                            @elseif (!$readOnly)
                                <input class="input" wire:model.live.debounce.300ms="providerSearch" placeholder="Rechercher un fournisseur…">
                                @if (!empty($providerResults))
                                    <div style="margin-top:8px; border:1px solid #ddd; border-radius:4px; background:#fff;">
                                        @foreach ($providerResults as $row)
                                            <div style="padding:10px; border-bottom:1px solid #eee; cursor:pointer;"
                                                 wire:click="selectProvider({{ $row['id'] }})">
                                                {{ $row['name'] }} ({{ $row['code'] }})
                                                @if ($row['is_foreign'] ?? false)
                                                    <span class="badge badge-info">Étranger</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <p>—</p>
                            @endif
                        </div>

                        <div class="field">
                            <label class="field-label">Date commande *</label>
                            <input class="input" type="date" wire:model="order_date" @disabled($readOnly)>
                        </div>
                        <div class="field">
                            <label class="field-label">Date prévue</label>
                            <input class="input" type="date" wire:model="expected_date" @disabled($readOnly)>
                        </div>
                        <div class="field">
                            <label class="field-label">Devise *</label>
                            <select class="input" wire:model.live="currency_code" @disabled($readOnly)>
                                @foreach ($currencies as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Taux de change → FCFA *</label>
                            <input class="input" type="number" wire:model.live.debounce.300ms="exchange_rate" min="0.000001" step="0.000001" @disabled($readOnly)>
                            <div class="field-hint">1 {{ $currency_code }} = {{ fmt_num((float) $exchange_rate, 4) }} FCFA</div>
                        </div>
                        <div class="field" style="grid-column: 1 / -1;">
                            <label class="field-label">Notes</label>
                            <textarea class="input" wire:model="notes" rows="3" @disabled($readOnly)></textarea>
                        </div>
                    </div>
                </section>

                <section class="card" style="margin-top: 16px;">
                    <h2 class="card-title">Totaux</h2>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span>Sous-total ({{ $currency_code }})</span>
                        <strong>{{ fmt_money($this->subtotalForeign) }} {{ $currency_code }}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:1.1em;">
                        <span>Total FCFA</span>
                        <strong>{{ fmt_money($this->subtotalLocal) }} FCFA</strong>
                    </div>
                </section>

                @if (!$readOnly)
                    <div class="page-actions" style="margin-top: 16px; display:flex; flex-direction:column; gap:8px;">
                        <a class="btn btn-secondary" href="{{ route('tenant.foreign_purchases.index', ['tenant' => $tenantCode]) }}">Retour</a>
                        <button type="submit" class="btn btn-secondary">Enregistrer brouillon</button>
                        @if ($canConfirm)
                            <button type="button" class="btn btn-primary" wire:click="saveAndConfirm">Enregistrer et confirmer</button>
                        @endif
                    </div>
                @else
                    <div class="page-actions" style="margin-top: 16px;">
                        <a class="btn btn-secondary" href="{{ route('tenant.foreign_purchases.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>
