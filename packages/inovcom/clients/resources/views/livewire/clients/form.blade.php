@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;

    $tabs = [
        'general' => 'Général',
        'coordonnees' => 'Coordonnées',
        'contacts' => 'Contacts',
        'adresses' => 'Adresses',
        'commercial' => 'Commercial & Paiement',
        'comptabilite' => 'Comptabilité',
        'notes' => 'Notes',
    ];
@endphp

<div class="page-body">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <form wire:submit.prevent="save">
        <section class="card" style="margin-bottom:16px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <h2 class="card-title" style="margin:0;">{{ $clientId ? 'Modifier le client ' . $code : 'Créer un client' }}</h2>
                <div style="display:flex; gap:8px;">
                    <a class="btn btn-secondary" href="{{ route('tenant.clients.index', ['tenant' => $tenantCode]) }}">Retour</a>
                    <button type="submit" class="btn btn-primary">{{ $clientId ? 'Mettre à jour' : 'Enregistrer' }}</button>
                </div>
            </div>

            {{-- Onglets --}}
            <div class="client-tabs" style="display:flex; gap:4px; flex-wrap:wrap; margin-top:16px; border-bottom:1px solid #e5e7eb;">
                @foreach ($tabs as $key => $label)
                    <button type="button"
                        wire:click="setTab('{{ $key }}')"
                        @class([
                            'client-tab-btn',
                        ])
                        style="border:none; background:none; padding:10px 14px; cursor:pointer; font-weight:600; font-size:14px; border-bottom:2px solid {{ $activeTab === $key ? '#2563eb' : 'transparent' }}; color:{{ $activeTab === $key ? '#2563eb' : '#6b7280' }};">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </section>

        {{-- ===================== GÉNÉRAL ===================== --}}
        <section class="card" style="margin-bottom:16px; @if($activeTab !== 'general') display:none; @endif">
            <h3 class="card-title">Informations générales</h3>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Code *</label>
                    <input class="input" wire:model="code" placeholder="Ex: CLI-000001" @if(!$clientId) readonly @endif>
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                    @if(!$clientId)<span class="field-hint">Généré automatiquement à l'enregistrement.</span>@endif
                </div>
                <div class="field">
                    <label class="field-label">Type *</label>
                    <select class="input" wire:model.live="type">
                        <option value="individual">Particulier</option>
                        <option value="company">Entreprise</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">{{ $type === 'company' ? 'Raison sociale' : 'Nom complet' }} *</label>
                    <input class="input" wire:model="name" placeholder="{{ $type === 'company' ? 'Ex: SARL ABC Commerce' : 'Ex: Jean Dupont' }}">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">NIF / Tax ID</label>
                    <input class="input" wire:model="tax_id" placeholder="Numéro d'identification fiscale">
                </div>

                @if ($type === 'company')
                <div class="field" style="grid-column: 1 / -1;">
                    <span class="field-label" style="display:block; margin-bottom:8px;">Informations légales entreprise (affichées sur la facture) *</span>
                    <div class="form-grid" style="margin:0;">
                        <div class="field">
                            <label class="field-label">RCCM *</label>
                            <input class="input" wire:model="rccm" placeholder="Ex: RC/YAO/2010/B/520">
                            @error('rccm') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="field-label">NIU *</label>
                            <input class="input" wire:model="niu" placeholder="Ex: M101000033472J">
                            @error('niu') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="field-label">BP (Boîte postale + ville) *</label>
                            <input class="input" wire:model="bp" placeholder="Ex: BP382 YAOUNDE">
                            @error('bp') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                @endif

                <label class="field-toggle">
                    <input type="checkbox" wire:model="is_active">
                    Client actif
                </label>
            </div>
        </section>

        {{-- ===================== COORDONNÉES ===================== --}}
        <section class="card" style="margin-bottom:16px; @if($activeTab !== 'coordonnees') display:none; @endif">
            <h3 class="card-title">Coordonnées</h3>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Email</label>
                    <input class="input" wire:model="email" type="email" placeholder="exemple@email.com">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Téléphone</label>
                    <input class="input" wire:model="phone" placeholder="690 11 73 25">
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Adresse (siège / principale)</label>
                    <input class="input" wire:model="address" placeholder="Adresse principale (facultatif)">
                </div>
                <div class="field">
                    <label class="field-label">BP</label>
                    <input class="input" wire:model="bp" placeholder="Ex: BP382 YAOUNDE">
                </div>
            </div>
            <p class="field-hint" style="margin-top:8px;">Pour des adresses de facturation et de livraison distinctes, utilisez l'onglet <strong>Adresses</strong>.</p>
        </section>

        {{-- ===================== CONTACTS ===================== --}}
        <section class="card" style="margin-bottom:16px; @if($activeTab !== 'contacts') display:none; @endif">
            <div style="display:flex; align-items:center; justify-content:space-between;">
                <h3 class="card-title" style="margin:0;">Contacts</h3>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="addContact">+ Ajouter un contact</button>
            </div>

            @if (count($contacts) === 0)
                <div class="alert" style="margin-top:12px;">Aucun contact. Ajoutez le contact principal, l'acheteur, le comptable, etc.</div>
            @endif

            @foreach ($contacts as $i => $contact)
                <div style="border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-top:12px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <label class="field-toggle" style="margin:0;">
                            <input type="radio" name="primaryContact" @checked($contact['is_primary'] ?? false) wire:click="setPrimaryContact({{ $i }})">
                            Contact principal
                        </label>
                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeContact({{ $i }})">Supprimer</button>
                    </div>
                    <div class="form-grid" style="margin:0;">
                        <div class="field">
                            <label class="field-label">Civilité</label>
                            <select class="input" wire:model="contacts.{{ $i }}.civility">
                                <option value="">—</option>
                                <option value="M.">M.</option>
                                <option value="Mme">Mme</option>
                                <option value="Mlle">Mlle</option>
                                <option value="Dr">Dr</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Prénom *</label>
                            <input class="input" wire:model="contacts.{{ $i }}.first_name">
                            @error("contacts.{$i}.first_name") <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="field-label">Nom</label>
                            <input class="input" wire:model="contacts.{{ $i }}.last_name">
                        </div>
                        <div class="field">
                            <label class="field-label">Rôle *</label>
                            <select class="input" wire:model="contacts.{{ $i }}.role">
                                @foreach ($contactRoles as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Fonction</label>
                            <input class="input" wire:model="contacts.{{ $i }}.position" placeholder="Ex: Responsable achats">
                        </div>
                        <div class="field">
                            <label class="field-label">Email</label>
                            <input class="input" type="email" wire:model="contacts.{{ $i }}.email">
                            @error("contacts.{$i}.email") <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="field-label">Téléphone</label>
                            <input class="input" wire:model="contacts.{{ $i }}.phone">
                        </div>
                        <div class="field">
                            <label class="field-label">Mobile</label>
                            <input class="input" wire:model="contacts.{{ $i }}.mobile">
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ===================== ADRESSES ===================== --}}
        <section class="card" style="margin-bottom:16px; @if($activeTab !== 'adresses') display:none; @endif">
            <div style="display:flex; align-items:center; justify-content:space-between;">
                <h3 class="card-title" style="margin:0;">Adresses (facturation / livraison)</h3>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="addAddress">+ Ajouter une adresse</button>
            </div>

            @if (count($addresses) === 0)
                <div class="alert" style="margin-top:12px;">Aucune adresse détaillée. Vous pouvez distinguer adresse de facturation et de livraison.</div>
            @endif

            @foreach ($addresses as $i => $address)
                <div style="border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-top:12px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <label class="field-toggle" style="margin:0;">
                            <input type="radio" name="defaultAddress" @checked($address['is_default'] ?? false) wire:click="setDefaultAddress({{ $i }})">
                            Adresse par défaut
                        </label>
                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeAddress({{ $i }})">Supprimer</button>
                    </div>
                    <div class="form-grid" style="margin:0;">
                        <div class="field">
                            <label class="field-label">Type *</label>
                            <select class="input" wire:model="addresses.{{ $i }}.type">
                                <option value="billing">Facturation</option>
                                <option value="shipping">Livraison</option>
                                <option value="both">Les deux</option>
                            </select>
                        </div>
                        <div class="field" style="grid-column: span 2;">
                            <label class="field-label">Rue / Quartier</label>
                            <input class="input" wire:model="addresses.{{ $i }}.street">
                        </div>
                        <div class="field">
                            <label class="field-label">Ville</label>
                            <input class="input" wire:model="addresses.{{ $i }}.city">
                        </div>
                        <div class="field">
                            <label class="field-label">Région / État</label>
                            <input class="input" wire:model="addresses.{{ $i }}.state">
                        </div>
                        <div class="field">
                            <label class="field-label">Code postal</label>
                            <input class="input" wire:model="addresses.{{ $i }}.postal_code">
                        </div>
                        <div class="field">
                            <label class="field-label">Pays</label>
                            <input class="input" wire:model="addresses.{{ $i }}.country" maxlength="2" placeholder="CM">
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ===================== COMMERCIAL & PAIEMENT ===================== --}}
        <section class="card" style="margin-bottom:16px; @if($activeTab !== 'commercial') display:none; @endif">
            <h3 class="card-title">Informations commerciales & conditions de paiement</h3>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Segment</label>
                    <select class="input" wire:model="segment_id">
                        <option value="">Aucun</option>
                        @foreach ($segments as $segment)
                            <option value="{{ $segment->id }}">{{ $segment->name }}</option>
                        @endforeach
                    </select>
                    <div style="display:flex; gap:8px; margin-top:6px;">
                        <input class="input input-sm" wire:model="newSegmentName" placeholder="Nouveau segment">
                        <input class="input input-sm" wire:model="newSegmentCode" placeholder="Code">
                        <button type="button" class="btn btn-secondary" wire:click="createSegment">Ajouter</button>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Catégorie client</label>
                    <select class="input" wire:model.live="category_id">
                        <option value="">Aucune</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <div style="display:flex; gap:8px; margin-top:6px;">
                        <input class="input input-sm" wire:model="newCategoryName" placeholder="Nouvelle catégorie">
                        <input class="input input-sm" wire:model="newCategoryCode" placeholder="Code">
                        <button type="button" class="btn btn-secondary" wire:click="createCategory">Ajouter</button>
                    </div>
                    <span class="field-hint">Pré-remplit la remise et le palier tarifaire.</span>
                </div>
                <div class="field">
                    <label class="field-label">Zone géographique</label>
                    <select class="input" wire:model="zone_id">
                        <option value="">Aucune</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    <div style="display:flex; gap:8px; margin-top:6px;">
                        <input class="input input-sm" wire:model="newZoneName" placeholder="Nouvelle zone">
                        <input class="input input-sm" wire:model="newZoneCode" placeholder="Code">
                        <button type="button" class="btn btn-secondary" wire:click="createZone">Ajouter</button>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Commercial affecté</label>
                    <select class="input" wire:model="salesrep_id">
                        <option value="">Aucun</option>
                        @foreach ($salesreps as $rep)
                            <option value="{{ $rep->id }}">{{ $rep->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Condition de paiement</label>
                    <select class="input" wire:model="payment_term_id">
                        <option value="">Aucune</option>
                        @foreach ($paymentTerms as $term)
                            <option value="{{ $term->id }}">{{ $term->name }} ({{ $term->days }} j)</option>
                        @endforeach
                    </select>
                    @if ($paymentTerms->isEmpty())
                        <span class="field-hint">Aucune condition disponible (module Fournisseurs requis pour les gérer).</span>
                    @endif
                </div>
                <div class="field">
                    <label class="field-label">Mode de règlement</label>
                    <select class="input" wire:model="payment_method">
                        <option value="">—</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Palier tarifaire</label>
                    <select class="input" wire:model="price_tier">
                        @foreach ($priceTiers as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('price_tier') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Remise par défaut (%)</label>
                    <input class="input" wire:model="discount_rate" type="number" min="0" max="100" step="0.01" placeholder="0">
                    @error('discount_rate') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Limite de crédit (FCFA)</label>
                    <input class="input" wire:model="credit_limit" type="number" min="0" step="0.01" placeholder="0">
                    @error('credit_limit') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        {{-- ===================== COMPTABILITÉ ===================== --}}
        <section class="card" style="margin-bottom:16px; @if($activeTab !== 'comptabilite') display:none; @endif">
            <h3 class="card-title">Comptabilité & encours</h3>
            <p class="field-hint" style="margin-bottom:12px;">L'encours est calculé automatiquement à partir des dettes du client (non modifiable manuellement).</p>
            <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px;">
                <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                    <div style="font-size:12px; color:#6b7280;">Limite de crédit</div>
                    <strong>{{ fmt_money((float) $credit_limit) }} FCFA</strong>
                </div>
                <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                    <div style="font-size:12px; color:#6b7280;">Encours réel (dettes)</div>
                    <strong style="color:#b91c1c;">{{ fmt_money((float) $insights['outstandingDebt']) }} FCFA</strong>
                </div>
                <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                    <div style="font-size:12px; color:#6b7280;">Disponible crédit</div>
                    <strong style="color:#166534;">{{ fmt_money(max(0, (float) $credit_limit - (float) $insights['outstandingDebt'])) }} FCFA</strong>
                </div>
            </div>
            @if ($clientId)
                <p class="field-hint" style="margin-top:12px;">Consultez l'historique complet (ventes, dettes, paiements) sur la <a href="{{ route('tenant.clients.show', [$clientId, 'tenant' => $tenantCode]) }}">fiche client</a>.</p>
            @endif
        </section>

        {{-- ===================== NOTES ===================== --}}
        <section class="card" style="margin-bottom:16px; @if($activeTab !== 'notes') display:none; @endif">
            <h3 class="card-title">Notes internes</h3>
            <div class="field">
                <textarea class="input" wire:model="notes" rows="6" placeholder="Notes internes sur le client..."></textarea>
            </div>
        </section>

        <div class="page-actions" style="margin-top: 8px;">
            <a class="btn btn-secondary" href="{{ route('tenant.clients.index', ['tenant' => $tenantCode]) }}">Retour</a>
            <button type="submit" class="btn btn-primary">{{ $clientId ? 'Mettre à jour' : 'Enregistrer' }}</button>
        </div>
    </form>
</div>
