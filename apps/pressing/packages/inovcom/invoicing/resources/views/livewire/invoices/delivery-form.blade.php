@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <section class="card">
        <h2 class="card-title">{{ $deliveryNoteId ? 'Modifier le bon de livraison' : 'Nouveau bon de livraison' }}</h2>
        <p style="color:#6b7280;font-size:14px;margin:0 0 16px;">
            {{ $sourceLabel }} <strong>{{ $sourceNumber }}</strong> — {{ $clientName }}
        </p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="field">
                <label class="field-label">N° demande achat</label>
                <input class="input" type="text" wire:model="customer_purchase_order" placeholder="Ex. DA-2026-014" maxlength="120">
            </div>
            <div class="field">
                <label class="field-label">Notes (optionnel)</label>
                <textarea class="input" wire:model="notes" rows="2" placeholder="Instructions, transporteur…"></textarea>
            </div>
        </div>

        <div style="margin-bottom:16px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
            <strong style="font-size:13px;">Options d'impression du BL</strong>
            <div style="display:flex;flex-wrap:wrap;gap:20px;margin-top:10px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                    <input type="checkbox" wire:model.boolean.live="show_prices">
                    Afficher les prix sur le BL imprimé
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                    <input type="checkbox" wire:model.boolean.live="show_discounts" @disabled(!$show_prices)>
                    Afficher les remises (lignes et globale)
                </label>
            </div>
            @if (!$show_prices)
                <p class="field-hint" style="margin-top:8px;">Sans prix : le BL ne montre que les références, désignations et quantités livrées.</p>
            @endif
            <p class="field-hint" style="margin-top:8px;color:#92400e;">
                Enregistrez le brouillon pour conserver le n° demande achat et les options d'impression.
            </p>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 style="margin:0;">Lignes à livrer</h3>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="selectFullDelivery">Tout livrer (reste)</button>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Référence / Article</th>
                        <th>{{ $sourceLabel === 'Devis' ? 'Commandé' : 'Facturé' }}</th>
                        <th>Déjà livré</th>
                        <th>Reste</th>
                        <th>Qté à livrer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineRows as $index => $row)
                        <tr>
                            <td>
                                <x-item-label :reference="$row['item_sku'] ?? null" :name="$row['item_name'] ?? null" />
                            </td>
                            <td>{{ fmt_num($row['invoiced_qty']) }}</td>
                            <td>{{ fmt_num($row['already_delivered']) }}</td>
                            <td>{{ fmt_num($row['deliverable_qty']) }}</td>
                            <td>
                                <input class="input input-sm" type="number" min="0" max="{{ $row['deliverable_qty'] }}" step="any"
                                       wire:model="lineRows.{{ $index }}.quantity" style="width:100px;">
                            </td>
                        </tr>
                    @endforeach
                    @if (count($lineRows) === 0)
                        <tr><td colspan="5" style="text-align:center;color:#9ca3af;">Rien à livrer.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="page-actions" style="margin-top:24px;flex-wrap:wrap;gap:8px;">
            <a class="btn btn-secondary" href="{{ $backUrl }}">Retour</a>
            @if (count($lineRows) > 0)
                <button type="button" class="btn btn-primary" wire:click="saveDraft">Enregistrer brouillon</button>
            @endif
        </div>
    </section>
</div>
