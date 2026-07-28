@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <form wire:submit="save">
        <section class="card" style="padding:20px; margin-bottom:16px;">
            <h3 class="form-section-title">Client et dates</h3>
            <div class="form-grid">
                <div class="field" style="grid-column:1/-1; position:relative;">
                    <label class="field-label">Client *</label>
                    @if ($clientPicker)
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <strong>{{ $clientPicker['name'] }}</strong>
                            @if ($clientPicker['code'])<span class="badge badge-secondary">{{ $clientPicker['code'] }}</span>@endif
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="clearClient">Changer</button>
                        </div>
                    @else
                        <input class="input"
                               type="search"
                               wire:model.live.debounce.200ms="clientSearch"
                               placeholder="Rechercher un client (nom, code, tél)…"
                               autocomplete="off">
                        @if (count($clientResults) > 0)
                            <div style="position:absolute; z-index:40; left:0; right:0; margin-top:4px; max-height:220px; overflow:auto; border:1px solid #e5e7eb; border-radius:8px; background:#fff; box-shadow:0 8px 20px rgba(0,0,0,.08);">
                                @foreach ($clientResults as $c)
                                    <button type="button"
                                            wire:click="pickClient({{ $c['id'] }})"
                                            style="display:block; width:100%; text-align:left; padding:10px 12px; border:none; border-bottom:1px solid #f1f5f9; background:#fff; cursor:pointer;">
                                        <strong>{{ $c['name'] }}</strong>
                                        @if ($c['code'])<span style="color:#6b7280;"> · {{ $c['code'] }}</span>@endif
                                        @if (!empty($c['phone']))
                                            <div style="font-size:12px; color:#6b7280;">{{ $c['phone'] }}</div>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @elseif (strlen(trim($clientSearch)) >= 1 && count($clientResults) === 0)
                            <p class="field-hint" style="margin-top:6px;">Aucun client trouvé.</p>
                        @endif
                    @endif
                    @error('client_id') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Date réservation</label>
                    <input class="input" type="date" wire:model="reservation_date" required>
                </div>
                <div class="field">
                    <label class="field-label">Retrait prévu</label>
                    <input class="input" type="date" wire:model="expected_date">
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Notes</label>
                    <textarea class="input" wire:model="notes" rows="2" placeholder="Ex. Client rappelle demain pour confirmer"></textarea>
                </div>
            </div>
        </section>

        <section class="card" style="padding:20px; margin-bottom:16px;">
            <h3 class="form-section-title">Articles à réserver</h3>
            <p style="font-size:13px; color:#6b7280; margin:0 0 12px;">Le stock disponible sera bloqué pour les autres ventes et devis.</p>

            <div style="position:relative;">
                <input class="input"
                       type="search"
                       wire:model.live.debounce.150ms="itemSearch"
                       placeholder="{{ item_search_placeholder() }}"
                       autocomplete="off">

                @if (trim($itemSearch) !== '' && count($searchResults) > 0)
                    <div wire:key="item-results-{{ md5($itemSearch) }}"
                         style="position:absolute; z-index:50; left:0; right:0; margin-top:4px; max-height:280px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:8px; background:#fff; box-shadow:0 8px 24px rgba(0,0,0,.1);">
                        @foreach ($searchResults as $item)
                            <button type="button"
                                    wire:click.prevent="addItemToCart({{ $item['id'] }})"
                                    style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; width:100%; text-align:left; padding:12px; border:none; border-bottom:1px solid #f1f5f9; background:#fff; cursor:pointer;"
                                    onmouseover="this.style.background='#f0f7ff'"
                                    onmouseout="this.style.background='#fff'">
                                <span>
                                    <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                    @if ($item['available_qty'] !== null)
                                        <div style="font-size:12px; margin-top:4px; color:{{ $item['available_qty'] > 0 ? '#16a34a' : '#dc2626' }}; font-weight:600;">
                                            Stock dispo : {{ fmt_num($item['available_qty']) }}
                                        </div>
                                    @endif
                                </span>
                                <span style="white-space:nowrap; font-weight:600;">{{ fmt_money($item['price']) }} FCFA</span>
                            </button>
                        @endforeach
                    </div>
                @elseif (trim($itemSearch) !== '' && count($searchResults) === 0)
                    <p class="field-hint" style="margin-top:8px;">Aucun article trouvé pour « {{ $itemSearch }} ».</p>
                @endif
            </div>

            @if (count($cart) > 0)
                <div class="table-scroll" style="margin-top:16px;">
                    <table>
                        <thead><tr><th>Article</th><th>Qté</th><th>P.U.</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($cart as $index => $row)
                                <tr wire:key="cart-{{ $row['item_id'] }}-{{ $index }}">
                                    <td><x-item-label :reference="$row['item_sku'] ?? null" :name="$row['item_name'] ?? null" /></td>
                                    <td><input class="input input-sm" type="number" step="0.001" min="0.001" wire:model.live.debounce.200ms="cart.{{ $index }}.quantity" style="width:90px;"></td>
                                    <td><input class="input input-sm" type="number" step="0.01" min="0" wire:model.live.debounce.200ms="cart.{{ $index }}.unit_price" style="width:110px;"></td>
                                    <td>{{ fmt_money((float)($row['line_total'] ?? ((float)($row['quantity'] ?? 0) * (float)($row['unit_price'] ?? 0)))) }}</td>
                                    <td><button type="button" class="btn btn-secondary btn-sm" wire:click="removeFromCart({{ $index }})">×</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="field-hint" style="margin-top:14px;">Recherchez un article ci-dessus pour l’ajouter à la réservation.</p>
            @endif
        </section>

        <div class="page-actions">
            <button type="submit" class="btn btn-primary">Créer la réservation</button>
            <a class="btn btn-secondary" href="{{ route('tenant.reservations.index', ['tenant' => $tenantCode]) }}">← Réservations</a>
        </div>
    </form>
</div>
