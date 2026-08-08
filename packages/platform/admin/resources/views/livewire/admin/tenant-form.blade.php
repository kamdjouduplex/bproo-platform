<div class="page-body">
    <section class="card">
        <h2 class="card-title">
            @if ($tenantId)
                Modifier une entreprise
            @elseif ($fromProspect)
                Convertir en client
            @else
                Créer une entreprise
            @endif
        </h2>
        @if ($errors->any())
            <div class="card-body" style="margin-bottom: 16px; background: #fef2f2; border: 1px solid #dc2626; color: #b91c1c; padding: 12px 16px; border-radius: 6px;">
                <strong>Veuillez corriger les erreurs :</strong>
                <ul style="margin: 8px 0 0 16px; padding: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-grid">
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-label">Application produit</label>
                <select class="input" wire:model.live="type" @disabled($tenantId)>
                    @foreach ($productTypes as $typeKey => $typeConfig)
                        <option value="{{ $typeKey }}">{{ $typeConfig['label'] ?? $typeKey }}</option>
                    @endforeach
                </select>
                @php $selected = $productTypes[$type] ?? []; @endphp
                <span class="field-hint">
                    {{ $selected['description'] ?? 'Choisit l’application dans laquelle cette entreprise se connectera.' }}
                </span>
                @if ($loginUrlPreview)
                    <span class="field-hint">
                        Connexion : <code>{{ $loginUrlPreview }}</code>
                    </span>
                @endif
                @if ($tenantId)
                    <span class="field-hint">L’application ne peut pas être changée après création (schéma & modules liés).</span>
                @endif
            </div>

            <div class="field">
                <label class="field-label">Nom de l’entreprise</label>
                <input class="input" placeholder="Ex: KREOBAT SARL" wire:model="name">
            </div>
            <div class="field">
                <label class="field-label">Code entreprise</label>
                <input class="input" placeholder="Ex: kreobat" wire:model.live="code">
            </div>

            @if ($tenantId)
            <div class="field">
                <label class="field-label">Nom base de données</label>
                <input class="input" placeholder="Ex: erp_demo" wire:model="db_name">
            </div>
            @endif

            @if ($tenantId)
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-toggle">
                        <input type="checkbox" wire:model.live="showAdvancedDb">
                        Connexion PostgreSQL personnalisée (hébergeurs avancés uniquement)
                    </label>
                    <span class="field-hint">Laissez désactivé : l’application utilise les identifiants système par défaut.</span>
                </div>
            @endif

            @if ($tenantId && $showAdvancedDb)
            <div class="field">
                <label class="field-label">DB Host</label>
                <input class="input" placeholder="127.0.0.1" wire:model="db_host" autocomplete="off">
            </div>
            <div class="field">
                <label class="field-label">DB Port</label>
                <input class="input" placeholder="5432" wire:model="db_port" autocomplete="off">
            </div>
            <div class="field">
                <label class="field-label">DB Username</label>
                <input class="input" placeholder="postgres" wire:model="db_username" autocomplete="off">
            </div>
            <div class="field">
                <label class="field-label">DB Password</label>
                <input class="input" type="password" placeholder="********" wire:model="db_password" autocomplete="new-password">
            </div>
            @endif

            <label class="field-toggle">
                <input type="checkbox" wire:model="is_active">
                Actif
            </label>

            @if ($supportsMultiStore)
                <label class="field-toggle">
                    <input type="checkbox" wire:model="multi_store_enabled">
                    Activer multi-magasins
                </label>
                <div class="field" style="grid-column: 1 / -1;">
                    <span class="field-hint">Disponible pour ERP / POS. Par défaut mono-magasin ; activez pour lancer le setup multi-magasins.</span>
                </div>
            @endif

            <div class="field" style="grid-column: 1 / -1;">
                <h3 class="card-title" style="margin-bottom:8px;">Contact clé</h3>
            </div>
            <div class="field">
                <label class="field-label">Nom du contact clé</label>
                <input class="input" placeholder="Ex: Dupont" wire:model="contact_key_last_name">
            </div>
            <div class="field">
                <label class="field-label">Prénom du contact clé</label>
                <input class="input" placeholder="Ex: Jean" wire:model="contact_key_first_name">
            </div>
            <div class="field">
                <label class="field-label">Téléphone contact clé</label>
                <input class="input" placeholder="Ex: +237 6XX XX XX XX" wire:model="contact_key_phone">
            </div>
            <div class="field">
                <label class="field-label">Pays</label>
                <input class="input" placeholder="Ex: Cameroun" wire:model="country">
            </div>
            <div class="field">
                <label class="field-label">Ville</label>
                <input class="input" placeholder="Ex: Douala" wire:model="city">
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-label">Adresse du contact clé</label>
                <input class="input" placeholder="Ex: Akwa, rue…" wire:model="contact_key_address">
            </div>
        </div>

        @if (!$tenantId)
            <div class="card-body">Administrateur initial (dans l’application choisie)</div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Nom admin</label>
                    <input class="input" placeholder="Ex: Admin Kreobat" wire:model="admin_name">
                </div>
                <div class="field">
                    <label class="field-label">Email admin</label>
                    <input class="input" placeholder="admin@entreprise.com" wire:model="admin_email" autocomplete="off">
                </div>
                <div class="field">
                    <label class="field-label">Mot de passe</label>
                    <input class="input" type="password" wire:model="admin_password" autocomplete="new-password">
                </div>
            </div>
        @endif

        @if ($provisioningError)
            <div class="card-body" style="color:#dc2626;">
                {{ $provisioningError }}
            </div>
        @endif
        <div class="card-body" wire:loading wire:target="save">
            Provisionnement en cours… merci de patienter.
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ $fromProspect ? route('system.opportunities') : route('system.tenants') }}">Retour</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                {{ $tenantId ? 'Mettre à jour' : ($fromProspect ? 'Créer le client' : 'Créer') }}
            </button>
        </div>
    </section>
</div>
