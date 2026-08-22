@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body item-form-page">
    <section class="card item-form-card">
        <header class="item-form-card__head">
            <div>
                <p class="item-form-card__eyebrow">{{ $catalogNoun['title'] ?? 'Catalogue' }}</p>
                <h2 class="card-title item-form-card__title">
                    {{ $itemId ? 'Modifier le '.$catalogNoun['singular'] : 'Nouveau '.$catalogNoun['singular'] }}
                </h2>
            </div>
            @if ($itemId && $sku)
                <div class="item-ref-badge item-ref-badge--locked">
                    <span class="item-ref-badge__label">Référence</span>
                    <code class="item-ref-badge__code">{{ $sku }}</code>
                </div>
            @endif
        </header>

        <section class="item-form-section">
            <h3 class="item-form-section__title">Identité</h3>
            <div class="item-form-identity">
                <div class="field item-form-identity__name">
                    <label class="field-label" for="item-name">Désignation *</label>
                    <input id="item-name" class="input" wire:model="name" placeholder="Ex: Huile végétale 1L" autocomplete="off">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="item-ref-panel {{ $itemId ? 'item-ref-panel--locked' : '' }}">
                    <div class="item-ref-panel__head">
                        <label class="field-label" for="item-sku">Référence article</label>
                        @if (!$itemId)
                            <div class="item-ref-panel__modes" role="group" aria-label="Mode de référence">
                                <button
                                    type="button"
                                    class="item-ref-panel__mode {{ $this->skuUsesAuto ? 'item-ref-panel__mode--active' : '' }}"
                                    wire:click="useAutoReference"
                                >Automatique</button>
                                <button
                                    type="button"
                                    class="item-ref-panel__mode {{ $preferCustomReference ? 'item-ref-panel__mode--active' : '' }}"
                                    wire:click="useCustomReference"
                                >Personnalisée</button>
                            </div>
                        @endif
                    </div>

                    @if ($itemId)
                        <div class="item-ref-panel__locked">
                            <code>{{ $sku }}</code>
                            <p class="item-ref-panel__hint">La référence est définitive après la création de l'article.</p>
                        </div>
                    @else
                        <div class="item-ref-panel__body">
                            @if ($this->skuUsesAuto)
                                <div class="item-ref-panel__auto">
                                    <span class="item-ref-panel__auto-label">Sera attribuée à l'enregistrement</span>
                                    <code class="item-ref-panel__preview">{{ $this->previewNextReference }}</code>
                                </div>
                                <p class="item-ref-panel__hint">
                                    Laissez le champ vide ou cliquez sur <strong>Personnalisée</strong> pour saisir votre propre code (référence interne, code fournisseur…).
                                </p>
                            @endif

                            <div class="item-ref-panel__input-wrap {{ $this->skuUsesAuto ? 'item-ref-panel__input-wrap--optional' : '' }}">
                                <input
                                    id="item-sku"
                                    class="input item-ref-panel__input"
                                    type="text"
                                    wire:model.live="sku"
                                    placeholder="{{ $this->skuUsesAuto ? 'Saisir ici pour une référence personnalisée…' : 'Ex: FOURN-2024-42, ART-CLIENT-01' }}"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                @if (!$this->skuUsesAuto)
                                    <span class="item-ref-panel__status item-ref-panel__status--custom">Référence personnalisée</span>
                                @endif
                            </div>
                            @error('sku') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="item-form-section">
            <h3 class="item-form-section__title">Classification</h3>
            <p class="item-form-section__desc">
                Classez l'article pour le retrouver facilement dans le catalogue, les filtres et les rapports.
                La <strong>référence</strong> est déjà définie ci-dessus — le code-barres sert uniquement au scanner.
            </p>

            <div class="item-classification">
                <div class="item-classification__scan">
                    <label class="field-label" for="item-barcode">Code-barres (scanner)</label>
                    <input
                        id="item-barcode"
                        class="input"
                        wire:model="barcode"
                        placeholder="Ex: 3760123456789"
                        inputmode="numeric"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <p class="item-classification__hint">
                        Optionnel. Numéro scanné en caisse (EAN, UPC…). Ne remplace pas la référence interne.
                    </p>
                    @error('barcode') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="item-classification__taxonomy">
                    <div class="item-taxonomy-card">
                        <div class="item-taxonomy-card__header">
                            <span class="item-taxonomy-card__badge" aria-hidden="true">Cat.</span>
                            <div>
                                <label class="field-label" for="item-category">Catégorie</label>
                                <p class="item-taxonomy-card__help">Famille produit — ex. Roulements, Filtres, Joints</p>
                            </div>
                        </div>
                        <select id="item-category" class="input item-taxonomy-card__select" wire:model.live="category_id">
                            <option value="">— Choisir une catégorie —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @if ($categories->isEmpty())
                            <p class="item-taxonomy-card__empty">Aucune catégorie pour l'instant.</p>
                        @endif

                        @if (!$showNewCategoryForm)
                            <button type="button" class="item-taxonomy-card__toggle" wire:click="toggleNewCategoryForm">
                                + Créer une catégorie
                            </button>
                        @else
                            <div class="item-taxonomy-card__create">
                                <label class="field-label" for="new-category">Nom de la catégorie</label>
                                <div class="item-taxonomy-card__create-row">
                                    <input
                                        id="new-category"
                                        class="input input-sm"
                                        wire:model="newCategoryName"
                                        placeholder="Ex: Roulements"
                                        wire:keydown.enter.prevent="createCategory"
                                    >
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="createCategory">Créer</button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="toggleNewCategoryForm">Annuler</button>
                                </div>
                                @error('newCategoryName') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="item-taxonomy-card">
                        <div class="item-taxonomy-card__header">
                            <span class="item-taxonomy-card__badge item-taxonomy-card__badge--brand" aria-hidden="true">M.</span>
                            <div>
                                <label class="field-label" for="item-brand">Marque</label>
                                <p class="item-taxonomy-card__help">Fabricant ou gamme — ex. SKF, Timken, Caterpillar</p>
                            </div>
                        </div>
                        <select id="item-brand" class="input item-taxonomy-card__select" wire:model.live="brand_id">
                            <option value="">— Choisir une marque —</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                        @if ($brands->isEmpty())
                            <p class="item-taxonomy-card__empty">Aucune marque pour l'instant.</p>
                        @endif

                        @if (!$showNewBrandForm)
                            <button type="button" class="item-taxonomy-card__toggle" wire:click="toggleNewBrandForm">
                                + Créer une marque
                            </button>
                        @else
                            <div class="item-taxonomy-card__create">
                                <label class="field-label" for="new-brand">Nom de la marque</label>
                                <div class="item-taxonomy-card__create-row">
                                    <input
                                        id="new-brand"
                                        class="input input-sm"
                                        wire:model="newBrandName"
                                        placeholder="Ex: SKF"
                                        wire:keydown.enter.prevent="createBrand"
                                    >
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="createBrand">Créer</button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="toggleNewBrandForm">Annuler</button>
                                </div>
                                @error('newBrandName') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>
                </div>

                <div class="item-classification__description field">
                    <label class="field-label" for="item-description">Description (optionnelle)</label>
                    <textarea
                        id="item-description"
                        class="input"
                        wire:model="description"
                        rows="3"
                        placeholder="Dimensions, compatibilité engin, références équivalentes…"
                    ></textarea>
                    <p class="item-classification__hint">Visible sur la fiche article. N'affecte pas la vente ni le stock.</p>
                </div>
            </div>
        </section>

        <section class="item-form-section">
            <h3 class="item-form-section__title">Options</h3>
            <div class="form-grid">
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-toggle">
                    <input type="checkbox" wire:model="is_active">
                    {{ ucfirst($catalogNoun['singular'] ?? 'article') }} actif (visible en vente et dans les listes)
                </label>
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-toggle">
                    <input type="checkbox" wire:model.live="is_set" @disabled(!$setServiceReady)>
                    Produit en lot (composition d’{{ $catalogNoun['plural'] ?? 'articles' }})
                </label>
                @if (!$setServiceReady)
                    <p style="font-size:12px;color:#6b7280;margin:4px 0 0;">Migration « lots » requise pour ce tenant.</p>
                @endif
            </div>
            @if ($isPharmacyCatalog ?? false)
            <div class="field" style="grid-column: 1 / -1;">
                <span class="field-label" style="display:block; margin-bottom:8px;">Pharmacie</span>
                <label class="field-toggle" style="margin-right:16px;">
                    <input type="checkbox" wire:model="batch_tracked" @disabled($is_set)>
                    Suivi par lot / date de péremption
                </label>
                <label class="field-toggle">
                    <input type="checkbox" wire:model="requires_prescription">
                    Sur ordonnance (obligatoire au POS)
                </label>
            </div>
            <div class="field">
                <label class="field-label">DCI (dénomination commune)</label>
                <input class="input" wire:model="dci" placeholder="Ex. Paracétamol" maxlength="120">
            </div>
            <div class="field">
                <label class="field-label">Famille thérapeutique</label>
                <input class="input" wire:model="therapeutic_family" placeholder="Ex. Antalgique" maxlength="120">
            </div>
            <div class="field">
                <label class="field-label">Forme</label>
                <input class="input" wire:model="pharma_form" placeholder="Ex. Comprimé, sirop, injectable" maxlength="80">
            </div>
            <div class="field">
                <label class="field-label">Dosage</label>
                <input class="input" wire:model="dosage" placeholder="Ex. 500 mg" maxlength="80">
            </div>
            <div class="field">
                <label class="field-label">Fabricant</label>
                <input class="input" wire:model="manufacturer" placeholder="Laboratoire" maxlength="120">
            </div>
            <div class="field">
                <label class="field-label">Conservation</label>
                <input class="input" wire:model="storage_temp" placeholder="Ex. Ambiante, 2–8 °C" maxlength="80">
            </div>
            @endif
            </div>
        </section>

        @if ($is_set && $setServiceReady)
        <section class="item-form-section">
            <h3 class="item-form-section__title">Composition du lot</h3>
            <div style="padding: 16px; border: 1px solid #c7d2fe; border-radius: 8px; background: #eef2ff;">
            <p style="margin: 0 0 12px; font-size: 13px; color: #4338ca;">
                Indiquez les articles inclus dans <strong>1 lot</strong> et leur quantité (en unité de base du composant).
                Le stock des composants sera déduit à chaque vente du lot.
            </p>
            @error('set_components') <div class="field-error" style="margin-bottom:8px;">{{ $message }}</div> @enderror
            <table style="width:100%; min-width: 480px;">
                <thead>
                    <tr>
                        <th>Article composant</th>
                        <th style="width:120px;">Qté / lot</th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($set_components as $index => $row)
                        <tr>
                            <td>
                                <select class="input input-sm" wire:model="set_components.{{ $index }}.component_item_id" style="min-width:220px;">
                                    <option value="">— Choisir —</option>
                                    @foreach ($componentItems as $candidate)
                                        @php $candidateIsSet = !empty(($candidate->metadata ?? [])['is_set']); @endphp
                                        @if (!$candidateIsSet)
                                            <option value="{{ $candidate->id }}">{{ item_display($candidate->sku, $candidate->name) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="input input-sm" type="number" min="0.001" step="any" wire:model="set_components.{{ $index }}.quantity">
                            </td>
                            <td>
                                @if (count($set_components) > 1)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeSetComponent({{ $index }})">×</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="button" class="btn btn-secondary btn-sm" style="margin-top:10px;" wire:click="addSetComponent">+ Ajouter un composant</button>
            </div>
        </section>
        @endif

        <section class="item-form-section item-form-section--units @if($canViewCost) item-form-section--units-with-cost @endif">
            <h3 class="item-form-section__title">Unités de vente</h3>
            <p class="item-form-section__desc">
                Définissez le prix de vente @if($canViewCost) et le coût @endif par unité. Ex. : pièce (1), carton (15 pièces).
            </p>

            <div class="item-unit-prices">
                <div class="table-scroll item-unit-prices__scroll">
                    <table class="item-unit-prices__table">
                        <thead>
                            <tr>
                                <th class="item-unit-prices__col-unit">Unité</th>
                                <th class="item-unit-prices__col-factor">Facteur<br><span class="item-unit-prices__th-hint">1 unité = X base</span></th>
                                <th class="item-unit-prices__col-money">Prix vente ({{ currency_label() }})</th>
                                @if ($canViewCost)
                                    <th class="item-unit-prices__col-money">Coût ({{ currency_label() }})</th>
                                @endif
                                <th class="item-unit-prices__col-action" aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unit_prices as $index => $row)
                                <tr>
                                    <td class="item-unit-prices__col-unit">
                                        <select class="input input-sm item-unit-prices__input" wire:model.live="unit_prices.{{ $index }}.unit_id">
                                            <option value="">— Choisir —</option>
                                            @foreach ($units as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->abbreviation ?? $unit->name }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="item-unit-prices__col-factor">
                                        <input class="input input-sm item-unit-prices__input item-unit-prices__input--num" wire:model="unit_prices.{{ $index }}.conversion_factor" type="number" min="0.0001" step="any" placeholder="1">
                                    </td>
                                    <td class="item-unit-prices__col-money">
                                        <input class="input input-sm item-unit-prices__input item-unit-prices__input--num" wire:model="unit_prices.{{ $index }}.price" type="number" min="0" step="1" placeholder="0">
                                        @error('unit_prices.'.$index.'.price') <div class="field-error" style="font-size:11px;margin-top:2px;">{{ $message }}</div> @enderror
                                    </td>
                                    @if ($canViewCost)
                                        <td class="item-unit-prices__col-money">
                                            <input class="input input-sm item-unit-prices__input item-unit-prices__input--num" wire:model="unit_prices.{{ $index }}.cost" type="number" min="0" step="1" placeholder="0">
                                        </td>
                                    @endif
                                    <td class="item-unit-prices__col-action">
                                        @if (count($unit_prices) > 1)
                                            <button type="button" class="btn btn-secondary btn-sm item-unit-prices__remove" wire:click="removeUnitPrice({{ $index }})" title="Supprimer cette ligne">×</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="item-unit-prices__footer">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addUnitPrice">+ Ajouter une unité de vente</button>
                    <div class="item-unit-prices__new-unit">
                        <span class="item-unit-prices__new-unit-label">Créer une unité :</span>
                        <input class="input input-sm item-unit-prices__new-unit-input" wire:model="newUnitName" placeholder="Nom">
                        <input class="input input-sm item-unit-prices__new-unit-input item-unit-prices__new-unit-input--abbr" wire:model="newUnitAbbr" placeholder="Abrév.">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="createUnit">Ajouter</button>
                    </div>
                </div>
            </div>
            @error('unit_prices') <div class="field-error" style="margin-top:8px;">{{ $message }}</div> @enderror
        </section>

        @if ($storageLocationsEnabled)
        <section class="item-form-section">
            <h3 class="item-form-section__title">Emplacements en magasin</h3>
            <p style="font-size: 13px; color: #6b7280; margin: 0 0 12px;">
                Indiquez où ranger / retrouver cet article (rayon, allée, étagère). Un article peut occuper
                <strong>plusieurs emplacements</strong> ; cochez celui à utiliser en priorité (principal).
                Utilisé dans la recherche stock et au POS.
            </p>

            @if (empty($storage_locations))
                <div style="padding:12px 14px; background:#f9fafb; border:1px dashed #e5e7eb; border-radius:8px; color:#6b7280; margin-bottom:12px;">
                    Aucun emplacement défini pour cet article.
                </div>
            @else
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:70px; text-align:center;">Principal</th>
                                <th>Rayon *</th>
                                <th>Allée</th>
                                <th>Étagère / niveau</th>
                                <th>Casier</th>
                                <th>Code</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($storage_locations as $index => $row)
                                @php
                                    $zone = trim($row['zone'] ?? '');
                                    $rowCode = $zone !== ''
                                        ? \InovCom\Stock\Models\StorageLocation::buildCode($zone, $row['aisle'] ?? null, $row['shelf'] ?? null, $row['bin'] ?? null)
                                        : '';
                                @endphp
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="radio" name="primary_storage_location"
                                               @checked($row['is_primary'] ?? false)
                                               wire:click="setPrimaryStorageLocation({{ $index }})"
                                               title="Définir comme emplacement principal">
                                    </td>
                                    <td><input class="input input-sm" wire:model="storage_locations.{{ $index }}.zone" placeholder="Ex: Alimentaire, A"></td>
                                    <td><input class="input input-sm" wire:model="storage_locations.{{ $index }}.aisle" placeholder="Ex: 3"></td>
                                    <td><input class="input input-sm" wire:model="storage_locations.{{ $index }}.shelf" placeholder="Ex: 2"></td>
                                    <td><input class="input input-sm" wire:model="storage_locations.{{ $index }}.bin" placeholder="Ex: B"></td>
                                    <td>
                                        @if ($rowCode !== '')
                                            <code>{{ $rowCode }}</code>
                                        @else
                                            <span style="color:#9ca3af;">—</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="removeStorageLocation({{ $index }})" title="Supprimer cet emplacement">×</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div style="margin-top:12px;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="addStorageLocation">+ Ajouter un emplacement</button>
            </div>
        </section>
        @endif

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.items.index', ['tenant' => $tenantCode]) }}">Retour</a>
            <button type="button" class="btn btn-primary" wire:click="save">
                {{ $itemId ? 'Mettre à jour' : 'Enregistrer' }}
            </button>
        </div>
    </section>
</div>
