@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div
    class="page-body"
    x-data
    x-on:focus-document-field.window="
        const id = $event.detail?.id || ($event.detail && $event.detail[0]) || null;
        const el = id ? document.getElementById(id) : null;
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof el.focus === 'function' && !el.disabled) {
            setTimeout(() => el.focus({ preventScroll: true }), 280);
        }
    "
>
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;" role="status">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div
            id="quotation-action-alert"
            class="alert alert-error"
            style="margin-bottom: 16px;"
            role="alert"
            aria-live="assertive"
            x-data
            x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })"
        >
            <strong style="display:block; margin-bottom:4px;">Action impossible</strong>
            {{ session('error') }}
        </div>
    @endif

    @if ($quotation)
        <div style="margin-bottom: 16px;">
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom: 12px;">
                <span class="badge badge-info">Statut : {{ $quotation->commercialStatusLabel() }}</span>
                <span class="badge badge-secondary">N° {{ $quotation->number }}</span>
                @if ($quotation->revision > 1)
                    <span class="badge badge-secondary">Rév. {{ $quotation->revision }}</span>
                @endif
                @if ($quotation->isAccepted())
                    <span class="badge badge-success">Accepté le {{ $quotation->validated_at?->format('d/m/Y H:i') }}</span>
                    @if (($fulfillment['status'] ?? '') === 'partial')
                        <span class="badge badge-warning">Livraison partielle</span>
                    @elseif (($fulfillment['status'] ?? '') === 'delivered')
                        <span class="badge badge-success">Livré</span>
                    @endif
                @endif
            </div>
            <div class="page-actions" style="flex-wrap:wrap; gap:8px;">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.quotations.index', ['tenant' => $tenantCode]) }}">← Liste</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.quotations.print', [$quotation->id, 'tenant' => $tenantCode]) }}">Imprimer</a>

                @if ($canValidate && $quotation->status === 'draft')
                    <button type="button" class="btn btn-primary btn-sm" wire:click="setStatus('sent')" wire:confirm="Marquer ce devis comme envoyé au client ?">
                        Marquer envoyé
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="setStatus('suspended')">Suspendre</button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="setStatus('rejected')" wire:confirm="Rejeter ce devis ?">
                        Rejeter
                    </button>
                @endif

                @if ($canValidate && $quotation->status === 'sent')
                    @php
                        $savedPurchaseOrder = trim((string) ($quotation->customer_purchase_order ?? ''));
                        $unsavedPurchaseOrder = trim($customer_purchase_order) !== ''
                            && trim($customer_purchase_order) !== $savedPurchaseOrder;
                        $canMarkAccepted = $savedPurchaseOrder !== '' && !$unsavedPurchaseOrder;
                    @endphp
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        wire:click="setStatus('accepted')"
                        @if ($canMarkAccepted) wire:confirm="Le client a accepté ce devis ?" @endif
                    >
                        Marquer accepté
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="setStatus('suspended')">Suspendre</button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="setStatus('rejected')" wire:confirm="Le client a refusé ce devis ?">
                        Rejeter
                    </button>
                @endif

                @if ($canValidate && $quotation->status === 'suspended')
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="setStatus('sent')">Reprendre (envoyé)</button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="setStatus('rejected')" wire:confirm="Rejeter ce devis ?">
                        Rejeter
                    </button>
                @endif

                @if ($canEdit)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="createRevision">Nouvelle révision</button>
                @endif

                @if ($quotation->canCreateInvoice())
                    @if ($hasDeliverableRemaining ?? true)
                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoicing.deliveries.from_quotation', ['tenant' => $tenantCode, 'quotation_id' => $quotation->id]) }}">
                            {{ ($deliveryNotes ?? collect())->isNotEmpty() ? 'Compléter la commande (reliquat)' : 'Créer bon de livraison' }}
                        </a>
                    @endif
                    @if ($linkedDeliveryNote)
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.deliveries.show', ['deliveryNote' => $linkedDeliveryNote->id, 'tenant' => $tenantCode]) }}">
                            Voir le dernier BL
                        </a>
                    @endif
                    @if ($linkedInvoice ?? null)
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.edit', [$linkedInvoice->id, 'tenant' => $tenantCode]) }}">
                            Voir la facture {{ $linkedInvoice->invoice_number }}
                        </a>
                    @else
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.create', ['tenant' => $tenantCode, 'quotation_id' => $quotation->id]) }}">
                            Facturer la commande
                        </a>
                    @endif
                @endif

                @if ($canDelete && $quotation->status === 'draft')
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="deleteQuotation" wire:confirm="Supprimer définitivement ce devis ?">
                        Supprimer
                    </button>
                @endif
            </div>
        </div>

        @if ($quotation->isAccepted() && ($fulfillment['lines'] ?? []))
            <section class="card" style="margin-bottom:16px;padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                    <h3 style="margin:0 0 8px;">{{ ($fulfillment['status'] ?? '') === 'delivered' ? 'Livraison' : 'Reliquat de commande' }}</h3>
                    @if (($fulfillment['status'] ?? '') === 'delivered')
                        <span class="badge badge-success">Livraison complète</span>
                    @elseif (($fulfillment['status'] ?? '') === 'partial')
                        <span class="badge badge-warning">Livraison partielle</span>
                    @endif
                </div>
                <p style="margin:0 0 12px;font-size:13px;color:#6b7280;">
                    @if (($fulfillment['status'] ?? '') === 'delivered')
                        Toute la commande a été livrée
                        ({{ fmt_num($fulfillment['delivered'] ?? 0) }} / {{ fmt_num($fulfillment['ordered'] ?? 0) }}).
                    @else
                        Les livraisons successives restent rattachées à ce devis. La facture porte sur la <strong>commande complète</strong>
                        ({{ fmt_num($fulfillment['ordered'] ?? 0) }}), même si une livraison est partielle.
                        Quantité restante :
                        <strong>{{ fmt_num($fulfillment['remaining'] ?? 0) }}</strong>
                        / {{ fmt_num($fulfillment['ordered'] ?? 0) }} commandé(s).
                    @endif
                </p>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th>Commandé</th>
                                <th>Livré</th>
                                <th>Reste</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fulfillment['lines'] as $line)
                                <tr>
                                    <td>{{ $line['item_name'] }}</td>
                                    <td>{{ fmt_num($line['ordered']) }}</td>
                                    <td>{{ fmt_num($line['delivered']) }}</td>
                                    <td><strong>{{ fmt_num($line['remaining']) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if (($deliveryNotes ?? collect())->isNotEmpty())
            <section class="card" style="margin-bottom:16px;padding:16px;">
                <h3 style="margin:0 0 12px;">Historique des livraisons</h3>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>BL</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Qté</th>
                                <th>Par</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryNotes as $note)
                                <tr>
                                    <td><strong>{{ $note->delivery_number }}</strong></td>
                                    <td>{{ $note->delivery_date?->format('d/m/Y') }}</td>
                                    <td>{{ $note->status === 'confirmed' ? 'Confirmé' : ($note->status === 'draft' ? 'Brouillon' : 'Annulé') }}</td>
                                    <td>{{ fmt_num($note->lines->sum('quantity')) }}</td>
                                    <td>{{ $note->confirmer?->name ?? $note->creator?->name ?? '—' }}</td>
                                    <td>
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.deliveries.show', ['deliveryNote' => $note->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif

    <form wire:submit.prevent="save">
        @include('partials.form-validation-summary')

        <section class="card" style="margin-bottom: 16px;">
            <h3 style="margin-bottom: 12px;">Informations</h3>
            <div class="form-grid">
                <div class="form-group @error('client_id') form-group--invalid @enderror" style="grid-column: 1 / -1;">
                    <label class="field-label">Client *</label>

                    @if ($clientPicker)
                        <div @class([
                            'client-picker--invalid' => $errors->has('client_id'),
                        ]) style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px 16px; border:1px solid #bfdbfe; border-radius:8px; background:#f0f9ff;">
                            <div style="min-width:0; flex:1;">
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                    <strong style="font-size:15px;">{{ $clientPicker['name'] }}</strong>
                                    <span class="badge badge-secondary">{{ $clientPicker['code'] }}</span>
                                    <span class="badge badge-info">{{ $clientPicker['type_label'] }}</span>
                                </div>
                                <div style="margin-top:8px; font-size:13px; color:#4b5563; display:flex; flex-direction:column; gap:4px;">
                                    @if (!empty($clientPicker['phone']))
                                        <span>Tél. {{ $clientPicker['phone'] }}</span>
                                    @endif
                                    @if (!empty($clientPicker['email']))
                                        <span>{{ $clientPicker['email'] }}</span>
                                    @endif
                                    @if (!empty($clientPicker['address']))
                                        <span>{{ \Illuminate\Support\Str::limit($clientPicker['address'], 80) }}</span>
                                    @endif
                                </div>
                            </div>
                            @if ($canEdit)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="clearClient" style="flex-shrink:0;">
                                    Changer
                                </button>
                            @endif
                        </div>
                    @elseif ($canEdit)
                        <div style="position:relative;">
                            <input
                                class="input @error('client_id') input--invalid @enderror"
                                type="search"
                                wire:model.live.debounce.200ms="clientSearch"
                                placeholder="Nom, code, téléphone, email, NIU… (min. 2 caractères)"
                                autocomplete="off"
                                @if (!$quotationId) autofocus @endif
                            >
                            <div wire:loading wire:target="clientSearch" style="font-size:12px; color:#6b7280; margin-top:6px;">
                                Recherche en cours…
                            </div>
                            @if (strlen(trim($clientSearch)) >= 2 && count($clientResults) === 0)
                                <div wire:loading.remove wire:target="clientSearch" style="margin-top:8px; padding:12px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa; color:#6b7280; font-size:13px;">
                                    Aucun client actif trouvé pour « {{ $clientSearch }} ».
                                </div>
                            @endif
                            @if (count($clientResults) > 0)
                                <div style="margin-top:8px; max-height:280px; overflow-y:auto; border:1px solid #d1d5db; border-radius:8px; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                                    @foreach ($clientResults as $c)
                                        <button
                                            type="button"
                                            wire:click="selectClient({{ $c['id'] }})"
                                            wire:key="quotation-client-{{ $c['id'] }}"
                                            style="display:block; width:100%; text-align:left; padding:12px 14px; border:none; border-bottom:1px solid #eee; background:transparent; cursor:pointer;"
                                            onmouseover="this.style.background='#f0f7ff'"
                                            onmouseout="this.style.background='transparent'"
                                        >
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                                <div>
                                                    <strong>{{ $c['name'] }}</strong>
                                                    <span style="color:#6b7280; font-size:12px;"> — {{ $c['code'] }}</span>
                                                </div>
                                                <span class="badge badge-secondary" style="font-size:10px;">{{ $c['type_label'] }}</span>
                                            </div>
                                            @if (!empty($c['phone']) || !empty($c['email']))
                                                <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                                                    @if (!empty($c['phone'])){{ $c['phone'] }}@endif
                                                    @if (!empty($c['phone']) && !empty($c['email'])) · @endif
                                                    @if (!empty($c['email'])){{ $c['email'] }}@endif
                                                </div>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (strlen(trim($clientSearch)) < 2 && strlen(trim($clientSearch)) > 0)
                                <p style="font-size:12px; color:#6b7280; margin-top:6px;">Saisissez au moins 2 caractères.</p>
                            @endif
                        </div>
                    @else
                        <p style="color:#6b7280;">Aucun client sélectionné.</p>
                    @endif

                    @error('client_id') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group @error('quote_date') form-group--invalid @enderror">
                    <label class="field-label">Date du devis *</label>
                    <input class="input @error('quote_date') input--invalid @enderror" type="date" wire:model="quote_date" @disabled(!$canEdit)>
                    @error('quote_date') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group @error('valid_until') form-group--invalid @enderror">
                    <label class="field-label">Valide jusqu'au</label>
                    <input class="input @error('valid_until') input--invalid @enderror" type="date" wire:model="valid_until" @disabled(!$canEdit)>
                    @error('valid_until') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group @error('customer_purchase_order') form-group--invalid @enderror">
                    <label class="field-label" for="customer-purchase-order">
                        N° demande achat
                        @if ($quotation && $quotation->status === 'sent')
                            <span style="color:#b45309;">*</span>
                        @endif
                    </label>
                    <input
                        id="customer-purchase-order"
                        class="input @error('customer_purchase_order') input--invalid @enderror"
                        type="text"
                        wire:model="customer_purchase_order"
                        placeholder="Ex. DA-2026-014"
                        @disabled(!$canEdit)
                        maxlength="120"
                        @error('customer_purchase_order') aria-invalid="true" @enderror
                    >
                    @if ($quotation && $quotation->status === 'sent')
                        <p class="field-hint">Obligatoire pour accepter le devis (enregistrez après saisie).</p>
                    @endif
                    @error('customer_purchase_order') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                </div>
                <div @class([
                    'form-group',
                    'form-group--invalid' => $errors->has('discount_percent') || $errors->has('discount_amount'),
                ]) style="grid-column: 1 / -1;">
                    <label class="field-label">Remise globale</label>
                    <div class="quote-discount-row">
                        <select class="input quote-discount-row__mode" wire:model.live="discount_mode" @disabled(!$canEdit)>
                            <option value="percent">Pourcentage (%)</option>
                            <option value="amount">Montant fixe (FCFA)</option>
                        </select>
                        @if ($discount_mode === 'percent')
                            <div class="quote-discount-row__value">
                                <input
                                    class="input quote-discount-row__input @error('discount_percent') input--invalid @enderror"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    wire:model.live="discount_percent"
                                    placeholder="0"
                                    @disabled(!$canEdit)
                                >
                                <span class="quote-discount-row__suffix">%</span>
                            </div>
                        @else
                            <div class="quote-discount-row__value">
                                <input
                                    class="input quote-discount-row__input @error('discount_amount') input--invalid @enderror"
                                    type="number"
                                    step="1"
                                    min="0"
                                    wire:model.live="discount_amount"
                                    placeholder="0"
                                    @disabled(!$canEdit)
                                >
                                <span class="quote-discount-row__suffix">FCFA</span>
                            </div>
                        @endif
                    </div>
                    @error('discount_percent') <span class="field-error">{{ $message }}</span> @enderror
                    @error('discount_amount') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="field-label">Taxes du devis</label>
                    <p class="field-hint" style="margin-top:4px;">Chaque taxe peut être en pourcentage (%) ou en montant fixe. TVA + sur le TTC ; IS/IR − retenu sur le HT (net à payer = HT − IS).</p>
                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                        @foreach($tax_lines as $i => $taxLine)
                            <div wire:key="quote-tax-line-{{ $i }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <input class="input" type="text" wire:model.live="tax_lines.{{ $i }}.name" placeholder="Nom de taxe (TVA, IR, ...)" style="min-width:180px; flex:1;" @disabled(!$canEdit)>
                                <select class="input" wire:model.live="tax_lines.{{ $i }}.mode" style="width:140px;" @disabled(!$canEdit)>
                                    <option value="percent">Pourcentage (%)</option>
                                    <option value="amount">Montant fixe</option>
                                </select>
                                <select class="input" wire:model.live="tax_lines.{{ $i }}.effect" style="width:150px;" @disabled(!$canEdit) title="Addition ou soustraction sur le TTC">
                                    <option value="add">+ Addition</option>
                                    <option value="subtract">− Soustraction</option>
                                </select>
                                @if (($taxLine['mode'] ?? 'amount') === 'percent')
                                    <input class="input" type="number" min="0" step="0.001" wire:model.live="tax_lines.{{ $i }}.rate" style="width:140px;" placeholder="%"
                                           @disabled(!$canEdit)>
                                @else
                                    <input class="input" type="number" min="0" step="0.01" wire:model.live="tax_lines.{{ $i }}.amount" style="width:160px;" placeholder="FCFA"
                                           @disabled(!$canEdit)>
                                @endif
                                @if ($canEdit && count($tax_lines) > 1)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeTaxLine({{ $i }})">×</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($canEdit)
                        <div style="margin-top:8px;">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="addTaxLine">+ Ajouter une taxe</button>
                        </div>
                    @endif
                </div>
            </div>
            <div class="form-group @error('notes') form-group--invalid @enderror" style="margin-top: 12px;">
                <label class="field-label">Notes</label>
                <textarea class="input @error('notes') input--invalid @enderror" wire:model="notes" rows="4" @disabled(!$canEdit)></textarea>
                @error('notes') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </section>

        @if ($canEdit)
        <section class="card" style="margin-bottom: 16px;">
            <h3 class="card-title" style="margin-bottom: 12px;">Ajouter des articles</h3>
            <label class="field-label">Recherche article</label>
            <div style="position:relative;">
                <input
                    class="input"
                    type="search"
                    wire:model.live.debounce.200ms="itemSearch"
                    placeholder="Désignation, référence ou code-barres… (min. 2 caractères)"
                    autocomplete="off"
                >
                <div wire:loading wire:target="itemSearch" style="font-size:12px; color:#6b7280; margin-top:6px;">
                    Recherche en cours…
                </div>
                @if (strlen(trim($itemSearch)) >= 2 && count($searchResults) === 0)
                    <div wire:loading.remove wire:target="itemSearch" style="margin-top:8px; padding:12px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa; color:#6b7280; font-size:13px;">
                        Aucun article actif trouvé pour « {{ $itemSearch }} ».
                    </div>
                @endif
                @php
                    $stockStyle = function ($status) {
                        return match ($status) {
                            'in' => 'background:#dcfce7;color:#166534;',
                            'low' => 'background:#fef3c7;color:#92400e;',
                            'out' => 'background:#fee2e2;color:#991b1b;',
                            default => 'background:#f3f4f6;color:#6b7280;',
                        };
                    };
                @endphp
                @if (count($searchResults) > 0)
                    <div style="margin-top:8px; max-height:300px; overflow-y:auto; border:1px solid #d1d5db; border-radius:8px; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                        @foreach ($searchResults as $item)
                            <button
                                type="button"
                                wire:click="addItemToCart({{ $item['id'] }})"
                                wire:key="quotation-item-{{ $item['id'] }}"
                                style="display:block; width:100%; text-align:left; padding:12px 14px; border:none; border-bottom:1px solid #eee; background:transparent; cursor:pointer;"
                                onmouseover="this.style.background='#f0f7ff'"
                                onmouseout="this.style.background='transparent'"
                            >
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                    <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                    <span style="font-size:12px; color:#1d4ed8; white-space:nowrap;">
                                        {{ fmt_money((float) $item['price']) }} FCFA
                                    </span>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:5px;">
                                    @if (isset($item['stock_status']))
                                        <span style="display:inline-block; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; {{ $stockStyle($item['stock_status']) }}">
                                            {{ $item['stock_label'] }}
                                            @if ($item['stock_status'] !== 'na')
                                                · {{ fmt_num((float) $item['stock_qty'], 2) }} en stock
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                @if (!empty($item['barcode']))
                                    <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                                        Code {{ $item['barcode'] }}
                                    </div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen(trim($itemSearch)) > 0 && strlen(trim($itemSearch)) < 2)
                    <p style="font-size:12px; color:#6b7280; margin-top:6px;">Saisissez au moins 2 caractères.</p>
                @endif
            </div>
            <p style="font-size:12px; color:#6b7280; margin-top:8px;">
                Astuce : saisissez la référence ou le code-barres exact pour ajouter l'article directement au devis.
            </p>
        </section>
        @endif

        <section @class([
            'card',
            'app-table-card',
            'form-section--invalid' => $errors->has('cart'),
        ]) style="margin-bottom: 16px;">
            @error('cart')
                <div class="field-error" style="padding: 12px 12px 0; font-size: 13px; font-weight: 600;">{{ $message }}</div>
            @enderror
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                <h3 class="form-section-title" style="border:none; padding:0; margin:0;">Lignes du devis</h3>
                <div style="display:flex; align-items:center; flex-wrap:wrap; gap:16px;">
                    <div class="lines-discount-mode">
                        <span class="lines-discount-mode__label">Remise ligne</span>
                        <div class="lines-discount-mode__toggle" role="group" aria-label="Unité de remise par ligne">
                            <button
                                type="button"
                                class="lines-discount-mode__btn {{ $lines_discount_mode === 'percent' ? 'is-active' : '' }}"
                                wire:click="setLinesDiscountMode('percent')"
                                @disabled(!$canEdit)
                            >%</button>
                            <button
                                type="button"
                                class="lines-discount-mode__btn {{ $lines_discount_mode === 'amount' ? 'is-active' : '' }}"
                                wire:click="setLinesDiscountMode('amount')"
                                @disabled(!$canEdit)
                            >FCFA</button>
                        </div>
                    </div>
                    @if ($canEdit)
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                            <input type="checkbox" wire:model.live="show_markup_coefficient">
                            Coût et coefficient (PU = coût × coef.)
                        </label>
                    @elseif ($show_markup_coefficient)
                        <span class="field-hint">Saisie par coût et coefficient</span>
                    @endif
                </div>
            </div>
            <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th style="width:56px;">N°</th>
                        <th>Référence / Article</th>
                        <th>Qté</th>
                        @if ($show_markup_coefficient)
                            <th>Coût</th>
                            <th>Coef.</th>
                        @endif
                        <th>P.U.</th>
                        <th>Remise</th>
                        <th title="Saisissez le prix de vente souhaité — la remise se calcule">P.U. net</th>
                        <th>Total</th>
                        @if($canEdit)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @php
                        $cartStockStyle = function ($status) {
                            return match ($status) {
                                'in' => 'background:#dcfce7;color:#166534;',
                                'low' => 'background:#fef3c7;color:#92400e;',
                                'out' => 'background:#fee2e2;color:#991b1b;',
                                default => 'background:#f3f4f6;color:#6b7280;',
                            };
                        };
                    @endphp
                    @foreach ($cart as $index => $row)
                    @php
                        $pu = (float) ($row['unit_price'] ?? 0);
                        $qty = (float) ($row['quantity'] ?? 0);
                        $lineMode = ($lines_discount_mode ?? 'percent') === 'amount' ? 'amount' : 'percent';
                        $lineInput = max(0, (float) ($row['line_discount'] ?? 0));
                        if ($lineMode === 'percent') {
                            $rem = min($pu, round($pu * min(100, $lineInput) / 100, 2));
                        } else {
                            $rem = min($pu, $lineInput);
                        }
                        $puNet = max(0, $pu - $rem);
                        $si = $cartStock[(int) ($row['item_id'] ?? 0)] ?? null;
                        $askedQty = $qty;
                        $insufficient = $si && $si['tracked'] && $askedQty > (float) $si['qty'];
                    @endphp
                    <tr>
                        <td class="num" style="text-align:center; font-weight:600;">{{ (int) ($row['line_number'] ?? (($index + 1) * 10)) }}</td>
                        <td>
                            <x-item-label :reference="$row['item_sku'] ?? null" :name="$row['item_name'] ?? null" />
                            @if ($si)
                                <div style="margin-top:4px;">
                                    <span style="display:inline-block; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; {{ $cartStockStyle($si['status']) }}">
                                        {{ $si['label'] }}
                                        @if ($si['status'] !== 'na')
                                            · {{ fmt_num((float) $si['qty'], 2) }} en stock
                                        @endif
                                    </span>
                                    @if ($insufficient)
                                        <span style="display:inline-block; font-size:11px; font-weight:600; color:#991b1b; margin-left:6px;">⚠ Qté demandée &gt; stock</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            <input class="input input-sm @error('cart.'.$index.'.quantity') input--invalid @enderror" type="number" step="0.001" wire:model.live="cart.{{ $index }}.quantity" @disabled(!$canEdit) style="width:90px;">
                            @error('cart.'.$index.'.quantity') <span class="field-error">{{ $message }}</span> @enderror
                        </td>
                        @if ($show_markup_coefficient)
                            <td><input class="input input-sm" type="number" step="0.01" min="0" wire:model.live="cart.{{ $index }}.unit_cost" @disabled(!$canEdit) style="width:100px;" title="Coût unitaire"></td>
                            <td><input class="input input-sm" type="number" step="0.0001" min="0" wire:model.live="cart.{{ $index }}.markup_coefficient" @disabled(!$canEdit) style="width:90px;" title="Coefficient"></td>
                        @endif
                        <td>
                            <input class="input input-sm @error('cart.'.$index.'.unit_price') input--invalid @enderror" type="number" step="0.01" wire:model.live="cart.{{ $index }}.unit_price" @disabled(!$canEdit) style="width:110px;" placeholder="" title="Modifiable — le coefficient n'est pas recalculé automatiquement">
                            @error('cart.'.$index.'.unit_price') <span class="field-error">{{ $message }}</span> @enderror
                            @if ($show_markup_coefficient && !($row['pu_override'] ?? false) && $pu > 0)
                                <div class="field-hint" style="margin-top:2px;">Suggéré : coût × coef.</div>
                            @endif
                        </td>
                        <td>
                            <div class="quote-line-discount">
                                <input
                                    class="input input-sm quote-line-discount__value"
                                    type="number"
                                    step="{{ $lineMode === 'percent' ? 'any' : '0.01' }}"
                                    min="0"
                                    @if ($lineMode === 'percent') max="100" @endif
                                    wire:model.live="cart.{{ $index }}.line_discount"
                                    @disabled(!$canEdit)
                                    placeholder="0"
                                >
                                @if ($lineInput > 0)
                                    <span class="quote-line-discount__unit">{{ $lineMode === 'percent' ? '%' : 'FCFA' }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <input
                                class="input input-sm"
                                type="number"
                                step="1"
                                min="0"
                                @if ($pu > 0) max="{{ $pu }}" @endif
                                wire:model.live="cart.{{ $index }}.unit_price_net"
                                @disabled(!$canEdit)
                                style="width:110px;"
                                title="Prix de vente souhaité — la remise se calcule automatiquement"
                            >
                        </td>
                        <td>{{ fmt_money((float)($row['line_total'] ?? 0)) }}</td>
                        @if ($canEdit)
                        <td><button type="button" class="btn btn-secondary btn-sm" wire:click="removeFromCart({{ $index }})">×</button></td>
                        @endif
                    </tr>
                    @endforeach
                    @php
                        $quoteLineColspan = 7 + ($show_markup_coefficient ? 2 : 0) + ($canEdit ? 1 : 0);
                    @endphp
                    @if (count($cart) === 0)<tr><td colspan="{{ $quoteLineColspan }}">Aucune ligne.</td></tr>@endif
                </tbody>
            </table>
            </div>
            <div class="document-totals-split">
                @include('partials.document-margin-summary', [
                    'totalCost' => $marginSummary['total_cost'] ?? 0,
                    'margin' => $marginSummary['margin'] ?? 0,
                    'marginPercent' => $marginSummary['margin_percent'] ?? null,
                ])
                <div class="document-totals-split__financial">
                @php
                    $t = $savedTotals ?? [
                        'subtotal' => $subtotal,
                        'discount_percent' => $discountPct,
                        'discount_amount' => $discount,
                        'net_ht' => $netHt,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'tax_lines' => $taxLinesComputed ?? [],
                        'ttc' => $ttc ?? $total,
                        'total' => $total,
                    ];
                @endphp
                @if (($t['discount_amount'] ?? 0) > 0)
                    <div style="color:#b45309;">
                        Remise globale
                        @if (($t['discount_percent'] ?? 0) > 0)
                            ({{ fmt_num($t['discount_percent']) }} %)
                        @elseif (($discountMode ?? 'percent') === 'amount')
                            (montant fixe)
                        @endif
                        : <strong>−{{ fmt_money($t['discount_amount']) }} FCFA</strong>
                    </div>
                @endif
                <div>Montant HT : <strong>{{ fmt_money($t['net_ht']) }} FCFA</strong></div>
                @if (($t['tax_amount'] ?? 0) != 0)
                    @foreach(($t['tax_lines'] ?? []) as $line)
                        @if (($line['tax_amount'] ?? 0) > 0)
                            @php
                                $taxSubtract = ($line['tax_effect'] ?? 'add') === 'subtract';
                            @endphp
                            <div>
                                Montant {{ $line['tax_name'] ?? 'Taxe' }}
                                @if (($line['tax_mode'] ?? 'amount') === 'percent' && isset($line['tax_rate']))
                                    ({{ fmt_num((float) $line['tax_rate'], 2) }} %)
                                @endif
                                :
                                <strong>{{ $taxSubtract ? '−' : '+' }}{{ fmt_money((float) ($line['tax_amount'] ?? 0)) }} FCFA</strong>
                            </div>
                        @endif
                    @endforeach
                @endif
                @if (($t['ttc'] ?? 0) > 0)
                    <div>Montant TTC : <strong>{{ fmt_money($t['ttc']) }} FCFA</strong></div>
                @endif
                <div style="font-size: 1.15em; margin-top: 8px; padding-top: 8px; border-top: 2px solid #333;">
                    Net à payer :
                    <strong>{{ fmt_money($t['total']) }} FCFA</strong>
                </div>
                </div>
            </div>
        </section>

        @if ($canEdit)
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        @endif
        <a class="btn btn-secondary" href="{{ route('tenant.quotations.index', ['tenant' => $tenantCode]) }}">Retour</a>
    </form>
</div>
