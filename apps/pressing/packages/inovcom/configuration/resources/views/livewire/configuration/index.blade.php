@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif
    <section class="card" style="max-width: 720px; padding: 24px; margin-bottom: 24px;">
        <div class="table-title" style="margin-bottom: 8px;">Logos du magasin</div>
        <p style="font-size: 12px; color: #6b7280; margin-bottom: 20px;">
            Utilisés sur la connexion, le menu, les factures, devis, tickets et autres documents imprimés.
        </p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <div>
                <label class="label">Logo principal (factures, devis, connexion)</label>
                @if ($logoPreviewUrl)
                    <div style="margin: 12px 0; padding: 12px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; text-align: center;">
                        <img src="{{ $logoPreviewUrl }}" alt="Logo" style="max-height: 80px; max-width: 100%; object-fit: contain;">
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeLogo">Supprimer le logo</button>
                @endif
                <input type="file" class="input" accept="image/png,image/jpeg,image/webp" wire:model="logoUpload" style="margin-top: 12px;">
                <div wire:loading wire:target="logoUpload" style="font-size: 12px; color: #6b7280; margin-top: 6px;">Transfert en cours…</div>
                @error('logoUpload') <span class="text-error">{{ $message }}</span> @enderror
                @if ($logoUpload)
                    <button type="button" class="btn btn-primary btn-sm" style="margin-top: 8px;" wire:click="uploadLogo">Enregistrer le logo</button>
                @endif
                <p style="font-size: 11px; color: #9ca3af; margin-top: 6px;">PNG, JPG ou WebP — max. {{ $uploadMaxKb }} Ko. Recommandé : fond transparent, ~400×120 px.</p>
            </div>
            <div>
                <label class="label">Icône compacte (barre de navigation)</label>
                @if ($iconPreviewUrl)
                    <div style="margin: 12px 0; padding: 12px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; text-align: center;">
                        <img src="{{ $iconPreviewUrl }}" alt="Icône" style="max-height: 48px; max-width: 100%; object-fit: contain;">
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeIcon">Supprimer l'icône</button>
                @endif
                <input type="file" class="input" accept="image/png,image/jpeg,image/webp" wire:model="iconUpload" style="margin-top: 12px;">
                <div wire:loading wire:target="iconUpload" style="font-size: 12px; color: #6b7280; margin-top: 6px;">Transfert en cours…</div>
                @error('iconUpload') <span class="text-error">{{ $message }}</span> @enderror
                @if ($iconUpload)
                    <button type="button" class="btn btn-primary btn-sm" style="margin-top: 8px;" wire:click="uploadIcon">Enregistrer l'icône</button>
                @endif
                <p style="font-size: 11px; color: #9ca3af; margin-top: 6px;">Carré ou rond — max. {{ min($uploadMaxKb, 2048) }} Ko. Si vide, le logo principal est utilisé.</p>
            </div>
        </div>
    </section>

    <form wire:submit="save" class="card" style="max-width: 640px; padding: 24px;">
        <div class="table-title" style="margin-bottom: 16px;">Paramètres système</div>
        <div style="display: grid; gap: 16px;">
            <div>
                <label class="label">Nom du magasin</label>
                <input class="input" wire:model="shop_name" placeholder="Ex: Ma Boutique">
                @error('shop_name') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Message de personnalisation (page de connexion)</label>
                <textarea class="input" rows="3" wire:model="login_welcome_message" placeholder="Bienvenue dans votre espace de gestion"></textarea>
                @error('login_welcome_message') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Devise</label>
                <select class="input" wire:model="currency">
                    <option value="XOF">XOF (FCFA)</option>
                    <option value="XAF">XAF</option>
                    <option value="EUR">EUR</option>
                    <option value="USD">USD</option>
                </select>
                @error('currency') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Langue</label>
                <select class="input" wire:model="locale">
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                </select>
                @error('locale') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Fuseau horaire</label>
                <input class="input" wire:model="timezone" placeholder="Africa/Abidjan">
                @error('timezone') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Taux de TVA par défaut (%)</label>
                <input class="input" type="number" step="0.01" min="0" wire:model="tax_rate">
                <p style="font-size: 11px; color: #9ca3af; margin-top: 6px;">Pour les devis. Sur les factures, ajoutez les taxes ligne par ligne (ex. IR 2,2 % et TVA 19,25 %).</p>
                @error('tax_rate') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Incrément numéros de ligne (devis / factures)</label>
                <input class="input" type="number" step="1" min="1" max="1000" wire:model="document_line_increment" placeholder="10">
                <p style="font-size: 11px; color: #9ca3af; margin-top: 6px;">Ex. 10 → lignes numérotées 10, 20, 30… ou 1 → 1, 2, 3…</p>
                @error('document_line_increment') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Préfixe factures (ventes POS)</label>
                <input class="input" wire:model="invoice_prefix" placeholder="INV">
                @error('invoice_prefix') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label class="label">Préfixe factures avec déclaration</label>
                    <input class="input" wire:model="invoice_prefix_declared" placeholder="FTH">
                    @error('invoice_prefix_declared') <span class="text-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Préfixe factures sans déclaration</label>
                    <input class="input" wire:model="invoice_prefix_non_declared" placeholder="FTN">
                    @error('invoice_prefix_non_declared') <span class="text-error">{{ $message }}</span> @enderror
                </div>
            </div>
            <p style="font-size: 12px; color: #6b7280;">Numérotation séquentielle par type, ex. FTH240011 et FTN240018.</p>

            <div style="margin-top: 8px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                <div class="table-title" style="margin-bottom: 12px;">Informations sur les factures imprimées</div>
                <p style="font-size: 12px; color: #6b7280; margin-bottom: 16px;">
                    Le <strong>logo principal</strong> s'affiche en haut au centre de la facture.
                    Le téléphone, l'adresse et l'e-mail apparaissent dans la bande latérale gauche.
                    Renseignez ces champs pour reproduire le modèle type FTM (ex. FTM250039).
                </p>
                <label style="display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; cursor:pointer;">
                    <input type="checkbox" wire:model="print_show_header_company_info" style="margin-top:3px;">
                    <span>
                        <span style="font-weight:600; display:block;">Afficher les informations de l'entreprise sous le logo (en-tête)</span>
                        <span style="font-size:12px; color:#6b7280;">
                            Bloc NIU, RCCM, CNPS, Adresse, B.P., Tél et Mail affiché juste sous le logo en haut des impressions.
                            Décochez si vous préférez ne les conserver qu'en pied de page (où elles restent toujours affichées).
                        </span>
                    </span>
                </label>
                @error('print_show_header_company_info') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Activité / slogan (sous le logo, si pas de logo seul)</label>
                <input class="input" wire:model="shop_tagline" placeholder="Distribution des pièces de rechange...">
                @error('shop_tagline') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label class="label">N° Contribuable / NIU</label>
                    <input class="input" wire:model="shop_tax_id" placeholder="Ex. P028712759043H">
                    @error('shop_tax_id') <span class="text-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">N° RCCM</label>
                    <input class="input" wire:model="shop_rccm" placeholder="Ex. RC/DLN/2019/A/893">
                    @error('shop_rccm') <span class="text-error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div>
                <label class="label">N° CNPS (optionnel)</label>
                <input class="input" wire:model="shop_cnps" placeholder="Ex. 356-001003-000-Q">
                @error('shop_cnps') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Modes de paiement par défaut (pied de facture)</label>
                <input class="input" wire:model="payment_modes_default" placeholder="chèque/Virement/Espèce">
                @error('payment_modes_default') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Téléphone(s) (bande latérale facture)</label>
                <input class="input" wire:model="shop_phone" placeholder="Ex. 675 968 982 / 695 795 945">
                <p style="font-size: 11px; color: #9ca3af; margin-top: 6px;">Séparez plusieurs numéros par « / ».</p>
                @error('shop_phone') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Adresse</label>
                <textarea class="input" rows="2" wire:model="shop_address" placeholder="Ex. Bépanda Bonabo, en face de la Cuvette"></textarea>
                @error('shop_address') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Boîte postale (B.P.)</label>
                <input class="input" wire:model="shop_bp" placeholder="Ex. B.P. 8816 Douala">
                <p style="font-size: 11px; color: #9ca3af; margin-top: 6px;">Affichée sur une ligne distincte de l'adresse.</p>
                @error('shop_bp') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Email</label>
                <input class="input" type="email" wire:model="shop_email" placeholder="Ex. etstrams@gmail.com">
                @error('shop_email') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Site web (optionnel)</label>
                <input class="input" wire:model="shop_website" placeholder="https://...">
                @error('shop_website') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Raison sociale / texte en pied de page (factures, devis, reçus)</label>
                <textarea class="input" rows="2" wire:model="invoice_footer" placeholder="Ex. ETS Trading & Multiservice"></textarea>
                <p style="font-size: 11px; color: #9ca3af; margin-top: 6px;">S'affiche en bas des documents imprimés avec les identifiants légaux.</p>
                @error('invoice_footer') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="label">Coordonnées bancaires (pied de page documents)</label>
                <textarea class="input" rows="3" wire:model="shop_bank_details" placeholder="Banque, IBAN, compte..."></textarea>
                @error('shop_bank_details') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="label">Message de relance (fiches clients)</label>
                <textarea class="input" rows="4" wire:model="collection_reminder_body" placeholder="Texte personnalisé ajouté sur les fiches de relance imprimées"></textarea>
                <p style="font-size: 11px; color: #9ca3af; margin-top: 6px;">Complète le corps de la lettre de relance des factures impayées.</p>
                @error('collection_reminder_body') <span class="text-error">{{ $message }}</span> @enderror
            </div>
        </div>
        <div style="margin-top: 24px;">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
    </form>

    <section class="card" style="margin-top: 16px; max-width: 900px;">
        <h2 class="card-title">Gestion des boutiques</h2>
        <div class="form-grid" style="margin-bottom: 14px;">
            <div class="field">
                <label class="field-label">Nom boutique</label>
                <input class="input" wire:model="store_name" placeholder="Ex: Magasin Plateau">
                @error('store_name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Code (optionnel)</label>
                <input class="input" wire:model="store_code" placeholder="PLATEAU">
                @error('store_code') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="page-actions" style="margin-bottom: 14px;">
            <button class="btn btn-primary" wire:click="saveStore">
                {{ $editingStoreId ? 'Mettre à jour boutique' : 'Créer boutique' }}
            </button>
            @if($editingStoreId)
                <button class="btn btn-secondary" wire:click="resetStoreForm">Annuler</button>
            @endif
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Code</th>
                        <th>Par défaut</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stores as $store)
                        <tr>
                            <td>{{ $store['name'] }}</td>
                            <td>{{ $store['code'] }}</td>
                            <td>
                                @if($store['is_default'])
                                    <span class="badge badge-success">Oui</span>
                                @else
                                    <span class="badge badge-warning">Non</span>
                                @endif
                            </td>
                            <td>
                                @if($store['is_active'])
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-warning">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-secondary" wire:click="editStore({{ $store['id'] }})">Modifier</button>
                                @if(!$store['is_default'])
                                    <button class="btn btn-secondary" wire:click="setDefaultStore({{ $store['id'] }})">Définir par défaut</button>
                                @endif
                                <button class="btn btn-secondary" wire:click="toggleStoreActive({{ $store['id'] }})">
                                    {{ $store['is_active'] ? 'Désactiver' : 'Activer' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Aucune boutique pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top: 16px; max-width: 1000px;">
        <h2 class="card-title">Affectation des utilisateurs aux boutiques</h2>
        <div class="form-grid" style="margin-bottom: 12px;">
            <div class="field">
                <label class="field-label">Affectation en masse - boutique cible</label>
                <select class="input" wire:model="bulk_store_id">
                    <option value="">Choisir une boutique</option>
                    @foreach($stores as $store)
                        <option value="{{ $store['id'] }}">{{ $store['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="page-actions" style="margin-bottom: 12px;">
            <button class="btn btn-secondary" wire:click="applyBulkStoreAssignment">Affecter sélection (bulk)</button>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Boutique assignée</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <input type="checkbox" wire:model="selectedUserIds" value="{{ $user['id'] }}">
                            </td>
                            <td>{{ $user['name'] }}</td>
                            <td>{{ $user['email'] }}</td>
                            <td>
                                <select class="input" wire:model="userStoreAssignments.{{ $user['id'] }}">
                                    <option value="">Choisir une boutique</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store['id'] }}">{{ $store['name'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <button class="btn btn-secondary" wire:click="updateUserStore({{ $user['id'] }})">Enregistrer</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucun utilisateur disponible.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
