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
        <div id="sale-form-error"
             class="alert alert-error"
             style="margin-bottom: 16px; background: #fef2f2; border: 1px solid #dc2626; color: #b91c1c; padding: 12px 16px; border-radius: 6px; font-weight: 500;"
             x-data
             x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
            {{ session('error') }}
        </div>
    @endif

    @if ($saleId)
        {{-- View mode for existing sale --}}
        @php
            $sale = \InovCom\Sales\Models\Sale::with(['lines', 'payments', 'client', 'confirmedReturns'])->find($saleId);
            if ($sale && $sale->prescription_id
                && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('prescriptions')
                && class_exists(\InovCom\Prescriptions\Models\Prescription::class)) {
                $sale->load(['prescription.lines.item']);
            }
            $rxSummary = null;
            if ($sale && $sale->prescription_id
                && app()->bound(\InovCom\Kernel\Contracts\PrescriptionsApi::class)) {
                $rxApi = app(\InovCom\Kernel\Contracts\PrescriptionsApi::class);
                if ($rxApi->isAvailable()) {
                    $rxSummary = $rxApi->saleDispensationSummary((int) $sale->prescription_id, $sale->lines->map(fn ($line) => [
                        'item_id' => $line->item_id,
                        'quantity' => $line->quantity,
                        'conversion_factor' => $line->conversion_factor ?? 1,
                        'item_name' => $line->item_name,
                        'metadata' => $line->metadata,
                    ])->all());
                }
            }
            $canReturn = false;
            $user = auth('tenant')->user();
            if ($user) {
                $canReturn = (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists())
                    || (method_exists($user, 'hasPermission') && ($user->hasPermission('sales.return') || $user->hasPermission('sales.update')));
            }
            $hasReturnable = false;
            if ($canReturn && $sale && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('sale_returns')) {
                $returnService = app(\InovCom\Sales\Services\SaleReturnsService::class);
                foreach ($sale->lines as $line) {
                    if ($returnService->returnableQuantity($line) > 0) {
                        $hasReturnable = true;
                        break;
                    }
                }
            }
            $saleCur = $sale
                ? (\App\Services\TenantCurrencyService::label($sale->currency_code ?: null) ?: 'FCFA')
                : 'FCFA';
        @endphp
        <section class="card">
            <h2 class="card-title">Vente: {{ $sale->sale_number }}</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                <div>
                    <p><strong>Date:</strong> {{ $sale->sale_date->format('d/m/Y H:i') }}</p>
                    <p><strong>Client:</strong> {{ $sale->client?->name ?? 'Client occasionnel' }}</p>
                    @if ($sale->prescription_id && $sale->relationLoaded('prescription') && $sale->prescription)
                        <p><strong>Ordonnance:</strong> {{ $sale->prescription->number }}
                            <span style="color:#64748b;">({{ $sale->prescription->dispensationStatusLabel() }})</span>
                            @if (\Illuminate\Support\Facades\Route::has('tenant.prescriptions.print'))
                                <a href="{{ route('tenant.prescriptions.print', ['prescription' => $sale->prescription_id, 'tenant' => $tenantCode]) }}" target="_blank" style="margin-left:8px;font-size:12px;">Imprimer l’ordonnance</a>
                            @endif
                        </p>
                    @endif
                </div>
                <div>
                    <p><strong>Sous-total:</strong> {{ fmt_money($sale->subtotal) }} {{ $saleCur }}</p>
                    <p><strong>Remise:</strong> {{ fmt_money($sale->discount_amount) }} {{ $saleCur }}</p>
                    <p><strong>Total:</strong> <strong>{{ fmt_money($sale->total) }} {{ $saleCur }}</strong></p>
                    @if ($sale->currency_code)
                        <p><strong>Devise:</strong> {{ $sale->currency_code }}</p>
                    @endif
                    @if ($sale->totalReturned() > 0)
                        <p><strong>Retours :</strong> −{{ fmt_money($sale->totalReturned()) }} {{ $saleCur }}</p>
                        <p><strong>Net :</strong> <strong>{{ fmt_money($sale->netTotal()) }} {{ $saleCur }}</strong></p>
                    @endif
                    @if ($sale->isFullyReturned())
                        <p><span class="badge" style="background:#fef3c7;color:#92400e;">Vente intégralement retournée</span></p>
                    @endif
                </div>
            </div>

            <h3 style="margin-bottom: 12px;">Articles</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Quantité</th>
                            <th>Prix unitaire</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->lines as $line)
                            <tr>
                                <td><x-item-label :reference="$line->item_sku" :name="$line->item_name" /></td>
                                <td>{{ fmt_num($line->quantity) }}</td>
                                <td>{{ fmt_money($line->unit_price) }} {{ $saleCur }}</td>
                                <td>{{ fmt_money($line->line_total) }} {{ $saleCur }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (!empty($rxSummary) && !empty($rxSummary['lines']))
                <h3 style="margin: 24px 0 12px;">Délivrance ordonnance {{ $rxSummary['number'] }}</h3>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Médicament</th>
                                <th>Prescrit</th>
                                <th>Ce ticket</th>
                                <th>Total délivré</th>
                                <th>Reste</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rxSummary['lines'] as $rxLine)
                                <tr>
                                    <td>{{ $rxLine['item_name'] }}</td>
                                    <td>{{ fmt_num($rxLine['prescribed']) }}</td>
                                    <td>{{ fmt_num($rxLine['this_sale']) }}</td>
                                    <td>{{ fmt_num($rxLine['dispensed']) }}</td>
                                    <td><strong>{{ fmt_num($rxLine['remaining']) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($sale->payments->count() > 0)
                <h3 style="margin-top: 24px; margin-bottom: 12px;">Paiements</h3>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Méthode</th>
                                <th>Montant</th>
                                <th>Référence</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sale->payments as $payment)
                                <tr>
                                    <td>{{ $payment->method_label }}</td>
                                    <td>{{ fmt_money($payment->amount) }} {{ \App\Services\TenantCurrencyService::label($payment->currency_code ?: $sale->currency_code) ?: $saleCur }}</td>
                                    <td>{{ $payment->transaction_reference ?? '-' }}</td>
                                    <td>{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($sale->confirmedReturns->count() > 0)
                <h3 style="margin-top: 24px; margin-bottom: 12px;">Retours enregistrés</h3>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>N° retour</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sale->confirmedReturns as $ret)
                                <tr>
                                    <td>{{ $ret->return_number }}</td>
                                    <td>{{ $ret->return_date->format('d/m/Y') }}</td>
                                    <td>{{ \InovCom\Sales\Models\SaleReturn::typeLabel($ret->type) }}</td>
                                    <td>{{ fmt_money($ret->total_refund) }} {{ $saleCur }}</td>
                                    <td>
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.sales.returns.show', ['saleReturn' => $ret->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @php
                $saleStockMovements = collect();
                if (
                    $sale
                    && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('stock_movements')
                    && class_exists(\InovCom\Stock\Services\StockMovementService::class)
                ) {
                    $saleStockMovements = app(\InovCom\Stock\Services\StockMovementService::class)->listForSale((int) $sale->id);
                }
            @endphp
            @if ($saleStockMovements->isNotEmpty())
                <h3 style="margin-top: 24px; margin-bottom: 12px;">Mouvements de stock</h3>
                <div class="table-scroll" style="margin-bottom: 8px;">
                    @include('inovcom-stock::partials.movements-table', [
                        'rows' => $saleStockMovements,
                        'showItem' => true,
                        'paginated' => false,
                        'tenantCode' => $tenantCode,
                    ])
                </div>
                @if (\Illuminate\Support\Facades\Route::has('tenant.stock.movements'))
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.movements', ['tenant' => $tenantCode, 'reference_type' => 'sale', 'reference_id' => $sale->id]) }}">Journal complet</a>
                @endif
            @endif

            <div class="page-actions" style="margin-top: 24px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                <a class="btn btn-secondary" href="{{ route('tenant.sales.index', ['tenant' => $tenantCode]) }}">Liste ventes</a>
                @if ($canReturn && $hasReturnable)
                    <a class="btn btn-primary" href="{{ route('tenant.sales.return.create', ['sale' => $sale->id, 'tenant' => $tenantCode]) }}">Retour produit</a>
                @endif
                <span style="margin-right: 8px;">Imprimer :</span>
                <a class="btn btn-secondary" href="{{ route('tenant.sales.print', [$sale->id, 'tenant' => $tenantCode, 'type' => 'ticket']) }}">Reçu / Ticket</a>
                <a class="btn btn-primary" href="{{ route('tenant.sales.print', [$sale->id, 'tenant' => $tenantCode, 'type' => 'invoice']) }}">Facture A4</a>
            </div>
        </section>
    @else
        {{-- POS Interface --}}
        @php
            $cur = $this->currencyLabel;
            $multiCurrency = count($enabledCurrencies) > 1;
        @endphp
        <form wire:submit.prevent="save">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                {{-- Left: Cart and Items --}}
                <div>
                    <section class="card">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom: 8px;">
                            <h2 class="card-title" style="margin:0;">Recherche d'article</h2>
                            @if ($multiCurrency)
                                <label style="display:flex; align-items:center; gap:8px; font-size:13px;">
                                    <span>Devise vente</span>
                                    <select class="input input-sm" wire:model.live="sale_currency" style="width: auto; min-width: 110px;">
                                        @foreach ($enabledCurrencies as $ec)
                                            <option value="{{ $ec['code'] }}">{{ $ec['code'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @else
                                <span style="font-size:12px; color:#64748b;">Devise : <strong>{{ $cur }}</strong></span>
                            @endif
                        </div>
                        <input class="input" 
                               wire:model.live.debounce.150ms="itemSearch" 
                               placeholder="{{ item_search_placeholder() }}" 
                               autofocus
                               autocomplete="off">
                        
                        @if (!empty($itemSearch) && !empty($searchResults))
                            <div style="margin-top: 12px; max-height: 350px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                @foreach ($searchResults as $variant)
                                    <div style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;" 
                                         wire:click="addItemToCart({{ json_encode($variant) }})"
                                         onmouseover="this.style.background='#f0f7ff'" 
                                         onmouseout="this.style.background='white'"
                                         class="search-result-item">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="flex: 1;">
                                                <x-item-label :reference="$variant['sku'] ?? null" :name="$variant['name'] ?? null" />
                                                <span style="font-size: 12px; color: #666;"> — {{ $variant['unit_name'] }}</span>
                                                <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                                    @if($variant['barcode'] ?? null)
                                                        <span>Code : {{ $variant['barcode'] }}</span>
                                                    @endif
                                                </div>
                                                @if (isset($variant['available_qty']))
                                                    <div style="font-size: 12px; margin-top: 6px;">
                                                        <span style="color: {{ ($variant['available_qty'] ?? 0) > 0 ? '#16a34a' : '#dc2626' }}; font-weight: 600;">
                                                            Stock : {{ fmt_num($variant['available_qty']) }}
                                                        </span>
                                                        @if (!empty($variant['location_label']))
                                                            <span style="margin: 0 8px; color:#d1d5db;">|</span>
                                                            <span style="color:#4b5563;">📍 {{ $variant['location_label'] }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                @if (!empty($variant['batch_hint']))
                                                    <div style="font-size: 11px; margin-top: 4px; color: {{ str_contains($variant['batch_hint'], 'Aucun') ? '#b91c1c' : '#0f766e' }};">
                                                        Lot : {{ $variant['batch_hint'] }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div style="text-align: right; margin-left: 16px;">
                                                <div style="font-weight: 600; color: #2563eb;">
                                                    {{ fmt_money((float)($variant['price'] ?? 0)) }} {{ \App\Services\TenantCurrencyService::label($default_currency) }}
                                                </div>
                                                <div style="font-size: 11px; color: #999;">
                                                    / {{ $variant['unit_name'] ?? 'pc' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif (!empty($itemSearch) && empty($searchResults))
                            <div style="margin-top: 12px; padding: 20px; text-align: center; color: #999; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                                Aucun article trouvé
                            </div>
                        @endif
                    </section>

                    <section class="card" style="margin-top: 16px;">
                        <h2 class="card-title">Panier</h2>
                        @if (empty($cart))
                            <p style="text-align: center; padding: 40px; color: #999;">Panier vide</p>
                        @else
                            <div class="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Article</th>
                                            <th>Qté</th>
                                            <th>Prix</th>
                                            <th>Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($cart as $index => $item)
                                            <tr>
                                                <td>
                                                    <x-item-label :reference="$item['item_sku'] ?? null" :name="$item['item_name'] ?? null" />
                                                    @if (!empty($item['is_set']))
                                                        <span class="badge" style="margin-left:4px;background:#eef2ff;color:#4338ca;font-size:10px;">Lot</span>
                                                    @endif
                                                    @if (!empty($item['unit_name']))
                                                        <span style="font-size: 12px; color: #666;">({{ $item['unit_name'] }})</span>
                                                    @endif
                                                    @if (!empty($item['batch_tracked']))
                                                        <div style="margin-top:6px; max-width:280px;">
                                                            @if (count($item['batch_options'] ?? []) > 1)
                                                                <label style="display:block;font-size:11px;color:#64748b;margin-bottom:2px;">Lot à vendre</label>
                                                                <select
                                                                    class="input input-sm"
                                                                    style="width:100%;font-size:12px;"
                                                                    wire:change="setCartBatch({{ $index }}, $event.target.value)"
                                                                >
                                                                    <option value="" @selected(empty($item['batch_id']))>FEFO (péremption la plus proche)</option>
                                                                    @foreach ($item['batch_options'] as $opt)
                                                                        <option value="{{ $opt['id'] }}" @selected((int) ($item['batch_id'] ?? 0) === (int) $opt['id'])>
                                                                            {{ $opt['label'] }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            @endif
                                                            @if (!empty($item['batch_summary']))
                                                                <div style="margin-top:4px;font-size:11px;line-height:1.35;color:{{ str_contains($item['batch_summary'], 'insuffisant') || str_contains($item['batch_summary'], 'Aucun lot') ? '#b91c1c' : '#166534' }};">
                                                                    {{ $item['batch_summary'] }}
                                                                </div>
                                                            @elseif (empty($item['batch_options']))
                                                                <div style="margin-top:4px;font-size:11px;color:#b91c1c;">Aucun lot non périmé</div>
                                                            @endif
                                                        </div>
                                                    @endif
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
                                                    @if ($this->canModifyPrice())
                                                        <input type="number" 
                                                               class="input input-sm" 
                                                               wire:model.debounce.300ms="cart.{{ $index }}.unit_price"
                                                               wire:change="updateCartPrice({{ $index }}, $event.target.value)"
                                                               min="0" 
                                                               step="0.01" 
                                                               style="width: 100px;"> {{ $cur }}
                                                    @else
                                                        {{ fmt_money((float)($item['unit_price'] ?? 0)) }} {{ $cur }}
                                                    @endif
                                                </td>
                                                <td>{{ fmt_money((float)($item['line_total'] ?? 0)) }} {{ $cur }}</td>
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

                {{-- Right: Sale Details and Payment --}}
                <div>
                    @if ($suspendedSales->isNotEmpty())
                        <section class="card" style="margin-bottom: 16px; border-left: 4px solid #3b82f6;">
                            <h2 class="card-title">Ventes suspendues</h2>
                            <p style="margin-bottom: 12px; color: #555; font-size: 12px;">Reprenez une vente mise de côté ou supprimez-la.</p>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                @foreach ($suspendedSales as $suspended)
                                    <li style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; gap: 12px;">
                                        <span style="font-size: 13px;">{{ $suspended->summary }} — {{ $suspended->created_at->format('d/m H:i') }}{{ $suspended->user ? ' · ' . $suspended->user->name : '' }}</span>
                                        <span style="display: flex; gap: 8px; flex-shrink: 0;">
                                            <a href="{{ route('tenant.sales.create', ['tenant' => $tenantCode, 'resume' => $suspended->id]) }}" class="btn btn-primary btn-sm">Reprendre</a>
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="deleteSuspended({{ $suspended->id }})" wire:confirm="Supprimer cette vente suspendue ?">Supprimer</button>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <section class="card">
                        <h2 class="card-title">Détails de la vente</h2>
                        <div class="form-grid">
                            <div class="field" style="position: relative;{{ !empty($highlightClientField) ? ' outline:2px solid #f59e0b; outline-offset:4px; border-radius:8px; padding:8px;' : '' }}">
                                <label class="field-label">
                                    @if (!empty($cartNeedsPrescription))
                                        Client / patient
                                    @else
                                        Client (optionnel)
                                    @endif
                                </label>
                                @if ($client_id)
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                        <span class="badge badge-info" style="font-size:13px; padding:8px 12px;">{{ $clientSearch }}</span>
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="clearClient">Changer</button>
                                    </div>
                                @else
                                    <div style="display:flex; gap:8px; align-items:stretch;">
                                        <input class="input"
                                               type="search"
                                               wire:model.live.debounce.250ms="clientSearch"
                                               placeholder="Rechercher un client (nom, code, tél)…"
                                               autocomplete="off"
                                               style="flex:1;">
                                        @if (!empty($canQuickCreateClient))
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="openQuickClientModal" title="Créer un client rapidement">+ Nouveau</button>
                                        @endif
                                    </div>
                                    @if (trim($clientSearch) !== '' && count($clientResults) > 0)
                                        <div style="position:absolute; z-index:40; left:0; right:0; margin-top:4px; max-height:240px; overflow:auto; border:1px solid #ddd; border-radius:6px; background:#fff; box-shadow:0 8px 20px rgba(0,0,0,.1);">
                                            @foreach ($clientResults as $client)
                                                <button type="button"
                                                        style="display:block; width:100%; text-align:left; padding:10px 12px; border:0; border-bottom:1px solid #eee; background:#fff; cursor:pointer;"
                                                        wire:click="selectClient({{ $client['id'] }})"
                                                        onmouseover="this.style.background='#f0f7ff'"
                                                        onmouseout="this.style.background='#fff'">
                                                    <strong>{{ $client['name'] }}</strong>
                                                    <span style="color:#6b7280; font-size:12px;"> · {{ $client['code'] }}</span>
                                                    @if (!empty($client['phone']))
                                                        <div style="font-size:12px; color:#6b7280;">{{ $client['phone'] }}</div>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif (strlen(trim($clientSearch)) >= 2 && count($clientResults) === 0)
                                        <p class="field-hint" style="margin-top:6px;">
                                            Aucun client trouvé.
                                            @if (!empty($canQuickCreateClient))
                                                <button type="button" class="btn btn-secondary btn-sm" style="margin-left:6px;" wire:click="openQuickClientModal">Créer « {{ \Illuminate\Support\Str::limit(trim($clientSearch), 24) }} »</button>
                                            @endif
                                        </p>
                                    @endif
                                @endif
                            </div>
                            @if (!empty($cartNeedsPrescription))
                                <div class="field" style="grid-column: 1 / -1;">
                                    <label class="field-label">Ordonnance</label>
                                    @if ($rxAttached)
                                        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start; padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
                                            <div style="flex:1; min-width:200px;">
                                                <div style="font-weight:600;">{{ $rxAttached['number'] }}
                                                    <span style="font-weight:400; color:#166534; font-size:13px;"> · {{ $rxAttached['status_label'] }}</span>
                                                </div>
                                                @if (!empty($rxAttached['client_name']))
                                                    <div style="font-size:13px; color:#374151; margin-top:2px;">Patient : {{ $rxAttached['client_name'] }}</div>
                                                @endif
                                                <div style="font-size:12px; color:#64748b; margin-top:4px;">{{ $rxAttached['lines_summary'] }}</div>
                                                @if (!empty($rxAttached['valid_until']))
                                                    <div style="font-size:12px; color:#64748b;">Valide jusqu’au {{ $rxAttached['valid_until'] }}</div>
                                                @endif
                                            </div>
                                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                                <button type="button" class="btn btn-secondary btn-sm" wire:click="openRxModal('search')">Changer</button>
                                                <button type="button" class="btn btn-secondary btn-sm" wire:click="detachPrescription">Retirer</button>
                                            </div>
                                        </div>
                                    @else
                                        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:12px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px;">
                                            <div style="flex:1; min-width:180px; font-size:13px; color:#92400e;">
                                                Ordonnance requise pour :
                                                <strong>{{ implode(', ', $rxRequiredNames ?? []) }}</strong>
                                            </div>
                                            <button type="button" class="btn btn-primary btn-sm" wire:click="openRxModal('create')">Ajouter une ordonnance</button>
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="openRxModal('search')">Continuer une ordonnance</button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <label class="field-toggle" style="grid-column: 1 / -1;">
                                <input type="checkbox" wire:model.live="showDiscount">
                                Appliquer une remise
                            </label>
                            @if ($showDiscount)
                                <div class="field">
                                    <label class="field-label">Remise (montant)</label>
                                    <input class="input" wire:model.live.debounce.300ms="discount_amount" type="number" min="0" step="0.01" placeholder="0">
                                </div>
                                <div class="field">
                                    <label class="field-label">Remise (%)</label>
                                    <input class="input" wire:model.live.debounce.300ms="discount_percent" type="number" min="0" max="100" step="0.01" placeholder="0">
                                </div>
                            @endif
                        </div>

                        <div style="margin-top: 20px; padding: 16px; background: #f5f5f5; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Sous-total:</span>
                                <strong>{{ fmt_money($this->subtotal) }} {{ $cur }}</strong>
                            </div>
                            @if ($this->discount > 0)
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span>Remise:</span>
                                    <strong>-{{ fmt_money($this->discount) }} {{ $cur }}</strong>
                                </div>
                            @endif
                            <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 2px solid #333; font-size: 18px;">
                                <span><strong>TOTAL:</strong></span>
                                <strong>{{ fmt_money($this->total) }} {{ $cur }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="card" style="margin-top: 16px;">
                        <h2 class="card-title">Paiement</h2>

                        <div style="margin-bottom: 12px; padding: 10px 12px; background: #f0f9ff; border-radius: 4px; font-size: 12px;">
                            <strong>Total à payer:</strong> {{ fmt_money($this->total) }} {{ $cur }}
                            @if ($this->total > 0)
                                — <strong>Alloué:</strong> {{ fmt_money($this->totalAllocated) }} {{ $cur }}
                                — <strong>Restant:</strong> <span style="{{ $this->remaining < 0 ? 'color: red;' : ($this->remaining > 0 ? 'color: #b45309;' : 'color: green;') }}">{{ fmt_money($this->remaining) }} {{ $cur }}</span>
                            @endif
                        </div>
                        <div class="table-scroll">
                            <table style="font-size: 12px;">
                                <thead>
                                    <tr>
                                        <th style="font-size: 12px;">Méthode</th>
                                        <th style="font-size: 12px;">Montant</th>
                                        @if ($multiCurrency)
                                            <th style="font-size: 12px;">Devise</th>
                                        @endif
                                        <th style="font-size: 12px;">Réf. transaction (Orange/MTN)</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payment_rows as $index => $row)
                                        <tr>
                                            <td>
                                                <select class="input input-sm" wire:model="payment_rows.{{ $index }}.method" style="min-width: 140px;">
                                                    @foreach ($paymentMethodLabels as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="input input-sm" wire:model.live="payment_rows.{{ $index }}.amount" min="0" step="0.01" placeholder="0" style="width: 120px;">
                                            </td>
                                            @if ($multiCurrency)
                                                <td>
                                                    <select class="input input-sm" wire:model.live="payment_rows.{{ $index }}.currency_code" style="min-width: 90px;">
                                                        @foreach ($enabledCurrencies as $ec)
                                                            <option value="{{ $ec['code'] }}">{{ $ec['code'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                            @endif
                                            <td>
                                                @if (in_array($row['method'] ?? '', ['orange_money', 'mtn_money'], true))
                                                    <input type="text" class="input input-sm" wire:model="payment_rows.{{ $index }}.transaction_reference" placeholder="N° transaction" style="width: 140px;">
                                                @else
                                                    <span style="color: #999;">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if (count($payment_rows) > 1)
                                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removePaymentRow({{ $index }})" title="Supprimer">×</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="addPaymentRow">+ Ajouter une ligne</button>
                            @if ($this->remaining > 0.01)
                                <span style="margin-left: 8px;">Remplir le restant en:</span>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="fillRemainingWithMethod('cash')">Espèces</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="fillRemainingWithMethod('orange_money')">Orange Money</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="fillRemainingWithMethod('mtn_money')">MTN Money</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="fillRemainingWithMethod('credit')">Crédit</button>
                            @endif
                        </div>
                        @if ($this->creditAmount > 0 && !$client_id)
                            <p style="margin-top: 12px; color: #b45309;">⚠ Sélectionnez un client pour la partie crédit ({{ fmt_money($this->creditAmount) }} {{ $cur }}).</p>
                        @endif
                    </section>

                    <div class="page-actions" style="margin-top: 16px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                        @if (!empty($cart))
                            <button type="button" class="btn btn-secondary" wire:click="suspend">Suspendre la vente</button>
                        @endif
                        <a class="btn btn-secondary" href="{{ route('tenant.sales.index', ['tenant' => $tenantCode]) }}">Annuler</a>
                        <button type="submit" class="btn btn-primary" {{ empty($cart) ? 'disabled' : '' }}>
                            Enregistrer la vente
                        </button>
                    </div>
                </div>
            </div>
        </form>
    @endif

    @if ($showRxModal)
        <div class="modal-backdrop"
             style="position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:80;display:flex;align-items:center;justify-content:center;padding:16px;"
             wire:click.self="closeRxModal">
            <div style="width:min(640px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,.2);padding:20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;">
                    <div>
                        <h2 style="margin:0;font-size:1.15rem;">Ordonnance pour cette vente</h2>
                        <p style="margin:6px 0 0;font-size:13px;color:#64748b;">Créer rapidement ou rattacher une ordonnance déjà partielle.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeRxModal">Fermer</button>
                </div>

                <div style="display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                    <button type="button"
                            class="btn btn-sm {{ $rxModalTab === 'create' ? 'btn-primary' : 'btn-secondary' }}"
                            wire:click="setRxModalTab('create')">Nouvelle</button>
                    <button type="button"
                            class="btn btn-sm {{ $rxModalTab === 'search' ? 'btn-primary' : 'btn-secondary' }}"
                            wire:click="setRxModalTab('search')">Rechercher / continuer</button>
                </div>

                @if ($rxModalTab === 'create')
                    <div class="form-grid" style="margin-bottom:12px;">
                        <div class="field">
                            <label class="field-label">Prescripteur</label>
                            <input class="input" wire:model="rx_prescriber_name" placeholder="Dr. … (optionnel)">
                        </div>
                        <div class="field">
                            <label class="field-label">Valide jusqu’au</label>
                            <input class="input" type="date" wire:model="rx_valid_until">
                        </div>
                    </div>
                    <p class="field-hint" style="margin-bottom:8px;">
                        Quantité <strong>prescrite</strong> (ex. 10) — la vente peut n’en délivrer qu’une partie (ex. 5). Les lignes viennent des articles « sur ordonnance » du panier.
                    </p>
                    <div style="overflow-x:auto;margin-bottom:12px;">
                        <table style="min-width:100%;font-size:13px;">
                            <thead>
                                <tr>
                                    <th>Médicament</th>
                                    <th style="width:90px;">Prescrit</th>
                                    <th>Posologie</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rx_lines as $i => $line)
                                    <tr wire:key="rx-line-{{ $i }}-{{ $line['item_id'] ?? 0 }}">
                                        <td style="padding:6px 4px;">{{ $line['item_name'] ?: '—' }}</td>
                                        <td style="padding:6px 4px;">
                                            <input class="input input-sm" type="number" min="0.001" step="any" wire:model="rx_lines.{{ $i }}.quantity" style="width:80px;">
                                        </td>
                                        <td style="padding:6px 4px;">
                                            <input class="input input-sm" wire:model="rx_lines.{{ $i }}.instructions" placeholder="1 cp x 2/j">
                                        </td>
                                        <td style="padding:6px 4px;">
                                            @if (count($rx_lines) > 1)
                                                <button type="button" class="btn btn-secondary btn-sm" wire:click="removeRxLine({{ $i }})">×</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;">
                        <button type="button" class="btn btn-secondary" wire:click="closeRxModal">Annuler</button>
                        <button type="button" class="btn btn-primary" wire:click="createAndAttachPrescription">
                            Créer et rattacher
                        </button>
                    </div>
                @else
                    <div class="field" style="margin-bottom:12px;">
                        <label class="field-label">Recherche</label>
                        <input class="input"
                               type="search"
                               wire:model.live.debounce.250ms="rxSearch"
                               placeholder="N° ordonnance, nom patient, téléphone…"
                               autocomplete="off">
                        <p class="field-hint" style="margin-top:4px;">
                            @if ($client_id && trim($rxSearch) === '')
                                Ordonnances ouvertes du client sélectionné. Tapez pour chercher aussi par référence.
                            @else
                                Continuité : rattachez une ordonnance partiellement délivrée à cette vente.
                            @endif
                        </p>
                    </div>
                    @if (count($rxSearchResults) === 0)
                        <p style="font-size:13px;color:#64748b;padding:12px 0;">Aucune ordonnance délivrable trouvée.</p>
                    @else
                        <div style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow:auto;">
                            @foreach ($rxSearchResults as $rx)
                                <button type="button"
                                        wire:click="attachPrescription({{ $rx['id'] }})"
                                        style="text-align:left;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;cursor:pointer;"
                                        onmouseover="this.style.borderColor='#93c5fd';this.style.background='#f8fafc'"
                                        onmouseout="this.style.borderColor='#e5e7eb';this.style.background='#fff'">
                                    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                        <strong>{{ $rx['number'] }}</strong>
                                        <span style="font-size:12px;color:#166534;">{{ $rx['status_label'] }}</span>
                                    </div>
                                    @if (!empty($rx['client_name']))
                                        <div style="font-size:13px;margin-top:2px;">{{ $rx['client_name'] }}</div>
                                    @endif
                                    <div style="font-size:12px;color:#64748b;margin-top:4px;">{{ $rx['lines_summary'] }}</div>
                                    @if (!empty($rx['valid_until']))
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Valide jusqu’au {{ $rx['valid_until'] }}</div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif

    @if ($showQuickClientModal && !empty($canQuickCreateClient))
        <div class="modal-backdrop"
             style="position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:85;display:flex;align-items:center;justify-content:center;padding:16px;"
             wire:click.self="closeQuickClientModal">
            <div style="width:min(420px,100%);background:#fff;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,.2);padding:20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
                    <div>
                        <h2 style="margin:0;font-size:1.1rem;">Nouveau client</h2>
                        <p style="margin:6px 0 0;font-size:13px;color:#64748b;">Création rapide — le code est généré automatiquement.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeQuickClientModal">Fermer</button>
                </div>
                <div class="form-grid" style="grid-template-columns:1fr;">
                    <div class="field">
                        <label class="field-label">Nom *</label>
                        <input class="input" wire:model="quick_client_name" placeholder="Nom du patient / client" autofocus>
                    </div>
                    <div class="field">
                        <label class="field-label">Téléphone</label>
                        <input class="input" wire:model="quick_client_phone" placeholder="Optionnel">
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;margin-top:16px;">
                    <button type="button" class="btn btn-secondary" wire:click="closeQuickClientModal">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="createQuickClient">Créer et sélectionner</button>
                </div>
            </div>
        </div>
    @endif
</div>
