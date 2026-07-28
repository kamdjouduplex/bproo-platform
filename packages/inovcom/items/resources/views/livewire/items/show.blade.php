@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $meta = $item->metadata ?? [];
@endphp

<div class="page-body">
    <section class="card" style="margin-bottom: 16px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom: 16px;">
            <div>
                <h2 class="card-title" style="margin:0;">
                    <x-item-label :reference="$item->sku" :name="$item->name" />
                </h2>
            </div>
            <div>
                @if ($item->is_active)
                    <span class="badge badge-success">Actif</span>
                @else
                    <span class="badge badge-warning">Inactif</span>
                @endif
                @if ($item->isProductSet())
                    <span class="badge" style="margin-left:6px;background:#eef2ff;color:#4338ca;">Produit en lot</span>
                @endif
            </div>
        </div>

        @if ($item->isProductSet() && $item->setComponents->isNotEmpty())
            <div style="margin-bottom:16px;padding:14px;border:1px solid #c7d2fe;border-radius:8px;background:#f8fafc;">
                <h3 style="margin:0 0 10px;font-size:15px;">Composition du lot</h3>
                <table style="width:100%;font-size:13px;">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Article</th>
                            <th style="text-align:right;">Quantité / lot</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($item->setComponents as $row)
                            <tr>
                                <td><x-item-label :reference="$row->componentItem?->sku" :name="$row->componentItem?->name" fallback="—" /></td>
                                <td style="text-align:right;">{{ fmt_num($row->quantity) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:12px;">
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Catégorie</div>
                <strong>{{ $item->category?->name ?? '—' }}</strong>
            </div>
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Marque</div>
                <strong>{{ $item->brand?->name ?? '—' }}</strong>
            </div>
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Code-barres</div>
                <strong>{{ $item->barcode ?? '—' }}</strong>
            </div>
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Unité de base</div>
                <strong>{{ $item->unit?->abbreviation ?? $item->unit?->name ?? '—' }}</strong>
            </div>
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Prix de vente (base)</div>
                <strong>{{ fmt_money($item->price) }} FCFA</strong>
            </div>
            @if ($canViewCost)
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Coût / prix d'achat (fiche)</div>
                <strong>{{ fmt_money($item->cost) }} FCFA</strong>
            </div>
            @if ($showPurchaseHistory && $latestMarketEntry)
            <div style="padding:12px; border:1px solid #bfdbfe; border-radius:8px; background:#eff6ff;">
                <div style="font-size:12px; color:#1d4ed8;">Dernier coût d'achat</div>
                @if ($latestMarketEntry->isForeign())
                    <strong style="color:#1d4ed8;">{{ fmt_num($latestMarketEntry->primary_amount, 4) }} {{ $latestMarketEntry->primary_currency }}</strong>
                    @if ($latestMarketEntry->indicative_fcfa !== null)
                        <div style="font-size:11px; color:#6b7280; margin-top:4px;">
                            ≈ {{ fmt_money($latestMarketEntry->indicative_fcfa) }} FCFA <span style="font-style:italic;">(indicatif)</span>
                        </div>
                    @endif
                @else
                    <strong style="color:#1d4ed8;">{{ fmt_money($latestMarketEntry->primary_amount) }} FCFA</strong>
                @endif
                <div style="font-size:11px; color:#6b7280; margin-top:4px;">
                    <span class="badge {{ $latestMarketEntry->isForeign() ? 'badge-warning' : 'badge-info' }}" style="margin-right:6px;">
                        {{ $latestMarketEntry->isForeign() ? 'Étranger' : 'Local' }}
                    </span>
                    {{ $latestMarketEntry->recorded_at->format('d/m/Y H:i') }}
                    @if ($latestMarketEntry->provider)
                        — {{ $latestMarketEntry->provider->name }}
                    @endif
                </div>
            </div>
            @endif
            @endif
        </div>

        @if ($item->description)
        <div style="margin-top:16px;">
            <div style="font-size:12px; color:#6b7280; margin-bottom:6px;">Description</div>
            <div style="white-space:pre-wrap;">{{ $item->description }}</div>
        </div>
        @endif

        @if (!empty($meta['batch_tracked']) || !empty($meta['requires_prescription']))
        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
            @if (!empty($meta['batch_tracked']))
                <span class="badge badge-secondary">Suivi par lot</span>
            @endif
            @if (!empty($meta['requires_prescription']))
                <span class="badge badge-secondary">Sur ordonnance</span>
            @endif
        </div>
        @endif
    </section>

    @if ($item->unitPrices->isNotEmpty())
    <section class="card" style="margin-bottom: 16px;">
        <h3 class="card-title" style="margin-bottom:12px;">Unités de vente</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Unité</th>
                        <th>Facteur</th>
                        <th>Prix vente</th>
                        @if ($canViewCost)
                        <th>Coût</th>
                        @endif
                        <th>Défaut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item->unitPrices as $up)
                        <tr>
                            <td>{{ $up->unit->name ?? '—' }}</td>
                            <td>{{ fmt_num($up->conversion_factor) }}</td>
                            <td>{{ fmt_money($up->price) }}</td>
                            @if ($canViewCost)
                            <td>{{ fmt_money($up->cost) }}</td>
                            @endif
                            <td>{{ $up->is_default ? 'Oui' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

    @if ($showPurchaseHistory)
    <section class="card" style="margin-bottom: 16px;">
        <h3 class="card-title" style="margin-bottom:8px;">Historique des prix d'achat</h3>
        <p style="font-size:13px; color:#6b7280; margin:0 0 12px;">
            Enregistré à chaque réception de commande fournisseur (marchandise effectivement reçue).
        </p>
        @if ($purchasePriceHistory->isEmpty())
            <p style="color:#6b7280;">Aucun prix d'achat enregistré pour cet article (réceptionnez une commande fournisseur).</p>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Coût unitaire</th>
                            <th>Équiv. FCFA <span style="font-weight:400; color:#6b7280;">(indicatif)</span></th>
                            <th>Qté</th>
                            <th>Fournisseur</th>
                            <th>Commande</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchasePriceHistory as $entry)
                            <tr>
                                <td>{{ $entry->recorded_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="badge {{ $entry->isForeign() ? 'badge-warning' : 'badge-info' }}">
                                        {{ $entry->isForeign() ? 'Étranger' : 'Local' }}
                                    </span>
                                </td>
                                <td>
                                    @if ($loop->first)
                                        <strong style="color:#1d4ed8;">
                                            @if ($entry->isForeign())
                                                {{ fmt_num($entry->primary_amount, 4) }} {{ $entry->primary_currency }}
                                            @else
                                                {{ fmt_money($entry->primary_amount) }} FCFA
                                            @endif
                                        </strong>
                                    @else
                                        @if ($entry->isForeign())
                                            {{ fmt_num($entry->primary_amount, 4) }} {{ $entry->primary_currency }}
                                        @else
                                            {{ fmt_money($entry->primary_amount) }} FCFA
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    @if ($entry->isForeign() && $entry->indicative_fcfa !== null)
                                        <span style="color:#6b7280; font-size:12px;">≈ {{ fmt_money($entry->indicative_fcfa) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ fmt_num($entry->quantity) }}</td>
                                <td>{{ $entry->provider?->name ?? '—' }}</td>
                                <td>
                                    @if ($entry->order_id)
                                        <a href="{{ route($entry->order_route, [$entry->order_id, 'tenant' => $tenantCode]) }}">
                                            {{ $entry->order_number ?? '#' . $entry->order_id }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    @endif

    @if ($showStockMovements ?? false)
    <section class="card" style="margin-top: 16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
            <h2 class="card-title" style="margin:0;">Mouvements de stock</h2>
            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.movements.item', ['itemId' => $item->id, 'tenant' => $tenantCode]) }}">Voir tout l'historique</a>
        </div>
        @include('inovcom-stock::partials.movements-table', [
            'rows' => $stockMovements,
            'showItem' => false,
            'paginated' => false,
            'tenantCode' => $tenantCode,
        ])
    </section>
    @endif

    <div class="page-actions">
        <a class="btn btn-secondary" href="{{ route('tenant.items.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
        @if ($canUpdate)
            <a class="btn btn-primary" href="{{ route('tenant.items.edit', [$item->id, 'tenant' => $tenantCode]) }}">Modifier</a>
        @endif
    </div>
</div>
