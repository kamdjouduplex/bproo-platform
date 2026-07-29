@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card">
        <h2 class="card-title">Recherche stock & emplacement</h2>
        <p style="color:#6b7280; font-size:14px; margin:0 0 16px;">
            Tapez le nom, la référence, le code-barres ou un code emplacement (ex. A-12-3) pour savoir si le produit est en stock et où le trouver en magasin.
        </p>

        @if (!$locationsEnabled)
            <div class="alert" style="background:#fffbeb; border-color:#f59e0b; color:#92400e;">
                Les emplacements ne sont pas encore activés. Exécutez la migration tenant du module Stock :
                <code>php artisan tenant:migrate {code}</code>
            </div>
        @else
            <div class="form-grid" style="margin-bottom: 16px;">
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Rechercher un article</label>
                    <input class="input" type="search" wire:model.live.debounce.300ms="search"
                           placeholder="{{ item_search_placeholder(true, 'rayon ou code emplacement') }}" autofocus>
                </div>
                <div class="field">
                    <label class="field-toggle">
                        <input type="checkbox" wire:model.live="inStockOnly">
                        Uniquement les articles en stock
                    </label>
                </div>
            </div>

            @if (strlen(trim($search)) < 1)
                <p style="text-align:center; color:#9ca3af; padding:32px;">Commencez à saisir pour afficher les résultats.</p>
            @elseif ($results->isEmpty())
                <p style="text-align:center; color:#9ca3af; padding:32px;">Aucun article trouvé.</p>
            @else
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th>Stock dispo.</th>
                                <th>Emplacement</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $row)
                                <tr>
                                    <td><x-item-label :reference="$row['sku'] ?? null" :name="$row['name'] ?? null" /></td>
                                    <td>
                                        @if ($row['in_stock'])
                                            <span style="color:#16a34a; font-weight:600;">{{ fmt_num($row['available_qty']) }}</span>
                                        @else
                                            <span class="badge badge-error">Rupture</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $codesList = $row['location_codes_list'] ?? []; @endphp
                                        @if (!empty($codesList))
                                            <div style="display:flex; flex-direction:column; gap:4px;">
                                                @foreach ($codesList as $i => $code)
                                                    <span style="display:flex; align-items:center; gap:6px;">
                                                        <code class="stock-location-code">{{ $code }}</code>
                                                        @if ($i === 0 && count($codesList) > 1)
                                                            <span class="badge badge-success" style="font-size:11px;">Principal</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        @elseif ($row['location_code'] ?? null)
                                            <code class="stock-location-code">{{ $row['location_code'] }}</code>
                                        @else
                                            <span style="color:#9ca3af;">Non renseigné</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        <div class="page-actions" style="margin-top: 20px;">
            <a class="btn btn-secondary" href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">Retour au stock</a>
        </div>
    </section>
</div>
