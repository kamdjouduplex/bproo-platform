@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $galenicForms = [
        'Comprimé', 'Gélule', 'Capsule', 'Sirop', 'Suspension buvable', 'Solution injectable',
        'Ampoule', 'Pommade', 'Crème', 'Gel', 'Liquide', 'Savon', 'Suppositoire', 'Collyre',
        'Ophtalmique', 'Spray / Aérosol', 'Sachet', 'Gouttes', 'Patch', 'Autre',
    ];
@endphp

<div class="page-body item-form-page pharma-item-form">
    <section class="card item-form-card">
        <header class="item-form-card__head pharma-item-form__head">
            <div>
                <p class="item-form-card__eyebrow">Médicaments</p>
                <h2 class="card-title item-form-card__title">
                    {{ $itemId ? 'Modifier' : 'Nouveau médicament' }}
                </h2>
            </div>
            <div class="pharma-item-form__head-actions">
                @if ($itemId && $sku)
                    <code class="pharma-item-form__sku">{{ $sku }}</code>
                @endif
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.index', ['tenant' => $tenantCode]) }}">Retour</a>
                <button type="button" class="btn btn-primary btn-sm" wire:click="save">
                    {{ $itemId ? 'Enregistrer' : 'Créer' }}
                </button>
            </div>
        </header>

        @if ($errors->any())
            <div class="pharma-item-form__errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="item-form-section">
            <h3 class="item-form-section__title">Produit</h3>
            <div class="form-grid">
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label" for="item-name">Nom commercial *</label>
                    <input id="item-name" class="input" wire:model="name" placeholder="Doliprane 500 mg" autocomplete="off">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label" for="item-dci">DCI *</label>
                    <input id="item-dci" class="input" wire:model="dci" placeholder="Paracétamol" maxlength="120" autocomplete="off">
                    @error('dci') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label" for="item-dosage">Dosage *</label>
                    <input id="item-dosage" class="input" wire:model="dosage" placeholder="500 mg" maxlength="80" autocomplete="off">
                    @error('dosage') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label" for="item-form">Forme *</label>
                    <select id="item-form" class="input" wire:model="pharma_form">
                        <option value="">—</option>
                        @foreach ($galenicForms as $form)
                            <option value="{{ $form }}">{{ $form }}</option>
                        @endforeach
                    </select>
                    @error('pharma_form') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label" for="item-therapeutic">Famille thérapeutique</label>
                    <input id="item-therapeutic" class="input" wire:model="therapeutic_family" placeholder="Antalgique" maxlength="120" autocomplete="off">
                </div>
                <div class="field">
                    <label class="field-label" for="item-barcode">Code-barres</label>
                    <input id="item-barcode" class="input" wire:model="barcode" placeholder="CIP / EAN" inputmode="numeric" autocomplete="off" spellcheck="false">
                    @error('barcode') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label" for="item-sku">Référence</label>
                    @if ($itemId)
                        <input id="item-sku" class="input" value="{{ $sku }}" disabled>
                    @elseif ($this->skuUsesAuto && trim((string) $sku) === '')
                        <div class="pharma-item-form__ref-auto">
                            <code>{{ $this->previewNextReference }}</code>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="useCustomReference">Personnaliser</button>
                        </div>
                    @else
                        <div class="pharma-item-form__ref-custom">
                            <input id="item-sku" class="input" type="text" wire:model.live="sku" placeholder="Réf. interne" autocomplete="off" spellcheck="false">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="useAutoReference">Auto</button>
                        </div>
                        @error('sku') <span class="field-error">{{ $message }}</span> @enderror
                    @endif
                </div>
            </div>
        </section>

        <section class="item-form-section">
            <h3 class="item-form-section__title">Classification</h3>
            <div class="form-grid pharma-item-form__grid pharma-item-form__grid--2">
                <div class="field">
                    <div class="pharma-item-form__label-row">
                        <label class="field-label" for="item-category">Catégorie</label>
                        @if (!$showNewCategoryForm)
                            <button type="button" class="pharma-item-form__inline-link" wire:click="toggleNewCategoryForm">+ Nouvelle</button>
                        @else
                            <span class="pharma-item-form__label-spacer" aria-hidden="true"></span>
                        @endif
                    </div>
                    <select id="item-category" class="input" wire:model.live="category_id">
                        <option value="">—</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @if ($showNewCategoryForm)
                        <div class="pharma-item-form__inline-create">
                            <input class="input input-sm" wire:model="newCategoryName" placeholder="Nom" wire:keydown.enter.prevent="createCategory">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="createCategory">OK</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="toggleNewCategoryForm">×</button>
                        </div>
                        @error('newCategoryName') <span class="field-error">{{ $message }}</span> @enderror
                    @endif
                </div>
                <div class="field">
                    <div class="pharma-item-form__label-row">
                        <label class="field-label" for="item-brand">Marque</label>
                        @if (!$showNewBrandForm)
                            <button type="button" class="pharma-item-form__inline-link" wire:click="toggleNewBrandForm">+ Nouvelle</button>
                        @else
                            <span class="pharma-item-form__label-spacer" aria-hidden="true"></span>
                        @endif
                    </div>
                    <select id="item-brand" class="input" wire:model.live="brand_id">
                        <option value="">—</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    @if ($showNewBrandForm)
                        <div class="pharma-item-form__inline-create">
                            <input class="input input-sm" wire:model="newBrandName" placeholder="Nom" wire:keydown.enter.prevent="createBrand">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="createBrand">OK</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="toggleNewBrandForm">×</button>
                        </div>
                        @error('newBrandName') <span class="field-error">{{ $message }}</span> @enderror
                    @endif
                </div>
                <div class="field">
                    <div class="pharma-item-form__label-row">
                        <label class="field-label" for="item-manufacturer">Laboratoire</label>
                        <span class="pharma-item-form__label-spacer" aria-hidden="true"></span>
                    </div>
                    <input id="item-manufacturer" class="input" wire:model="manufacturer" placeholder="Sanofi" maxlength="120" autocomplete="off">
                </div>
                <div class="field">
                    <div class="pharma-item-form__label-row">
                        <label class="field-label" for="item-storage">Conservation</label>
                        <span class="pharma-item-form__label-spacer" aria-hidden="true"></span>
                    </div>
                    <select id="item-storage" class="input" wire:model="storage_temp">
                        <option value="Ambiante">Ambiante</option>
                        <option value="Frais (8–15 °C)">Frais (8–15 °C)</option>
                        <option value="Réfrigéré (2–8 °C)">Réfrigéré (2–8 °C)</option>
                        <option value="Congelé">Congelé</option>
                        <option value="À l’abri de la lumière">À l’abri de la lumière</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="item-form-section">
            <h3 class="item-form-section__title">Dispensation</h3>
            <div class="pharma-item-form__toggles">
                <label class="field-toggle">
                    <input type="checkbox" wire:model="requires_prescription">
                    Sur ordonnance
                </label>
                <label class="field-toggle">
                    <input type="checkbox" wire:model="batch_tracked">
                    Suivi lots / péremption
                </label>
                <label class="field-toggle">
                    <input type="checkbox" wire:model="is_active">
                    Actif
                </label>
            </div>
        </section>

        <section class="item-form-section item-form-section--units @if($canViewCost) item-form-section--units-with-cost @endif">
            <h3 class="item-form-section__title">Tarif</h3>
            <div class="item-unit-prices">
                <div class="table-scroll item-unit-prices__scroll">
                    <table class="item-unit-prices__table">
                        <thead>
                            <tr>
                                <th class="item-unit-prices__col-unit">Unité</th>
                                <th class="item-unit-prices__col-factor">Facteur</th>
                                <th class="item-unit-prices__col-money">P.V. (FCFA)</th>
                                @if ($canViewCost)
                                    <th class="item-unit-prices__col-money">Coût</th>
                                @endif
                                <th class="item-unit-prices__col-action"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unit_prices as $index => $row)
                                <tr>
                                    <td class="item-unit-prices__col-unit">
                                        <select class="input input-sm item-unit-prices__input" wire:model.live="unit_prices.{{ $index }}.unit_id">
                                            <option value="">—</option>
                                            @foreach ($units as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
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
                                            <button type="button" class="btn btn-secondary btn-sm item-unit-prices__remove" wire:click="removeUnitPrice({{ $index }})">×</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="item-unit-prices__footer">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addUnitPrice">+ Unité</button>
                    <div class="item-unit-prices__new-unit">
                        <input class="input input-sm item-unit-prices__new-unit-input" wire:model="newUnitName" placeholder="Nom">
                        <input class="input input-sm item-unit-prices__new-unit-input item-unit-prices__new-unit-input--abbr" wire:model="newUnitAbbr" placeholder="Abr.">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="createUnit">Ajouter</button>
                    </div>
                </div>
            </div>
            @error('unit_prices') <div class="field-error" style="margin-top:8px;">{{ $message }}</div> @enderror
        </section>

        @if ($storageLocationsEnabled)
        <section class="item-form-section">
            <h3 class="item-form-section__title">Emplacement</h3>
            @if (empty($storage_locations))
                <button type="button" class="btn btn-secondary btn-sm" wire:click="addStorageLocation">+ Ajouter</button>
            @else
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:56px;text-align:center;">Prin.</th>
                                <th>Rayon</th>
                                <th>Allée</th>
                                <th>Étagère</th>
                                <th>Casier</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($storage_locations as $index => $row)
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="radio" name="primary_storage_location"
                                               @checked($row['is_primary'] ?? false)
                                               wire:click="setPrimaryStorageLocation({{ $index }})">
                                    </td>
                                    <td><input class="input input-sm" wire:model="storage_locations.{{ $index }}.zone" placeholder="OTC"></td>
                                    <td><input class="input input-sm" wire:model="storage_locations.{{ $index }}.aisle" placeholder="A"></td>
                                    <td><input class="input input-sm" wire:model="storage_locations.{{ $index }}.shelf" placeholder="2"></td>
                                    <td><input class="input input-sm" wire:model="storage_locations.{{ $index }}.bin" placeholder="14"></td>
                                    <td style="text-align:right;">
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="removeStorageLocation({{ $index }})">×</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:10px;">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addStorageLocation">+ Ligne</button>
                </div>
            @endif
        </section>
        @endif

        <div class="page-actions pharma-item-form__footer">
            <a class="btn btn-secondary" href="{{ route('tenant.items.index', ['tenant' => $tenantCode]) }}">Retour</a>
            <button type="button" class="btn btn-primary" wire:click="save">
                {{ $itemId ? 'Enregistrer' : 'Créer' }}
            </button>
        </div>
    </section>
</div>
