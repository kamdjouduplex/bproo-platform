<div class="page-body">
    <section class="card">
        <h2 class="card-title">{{ $tenantId ? 'Modifier un vendeur' : 'Créer un vendeur' }}</h2>
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
            <div class="field">
                <label class="field-label">Nom du vendeur</label>
                <input class="input" placeholder="Ex: Boutique Centrale" wire:model="name">
            </div>
            <div class="field">
                <label class="field-label">Code vendeur</label>
                <input class="input" placeholder="Ex: central" wire:model="code">
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
                    <span class="field-hint">Laissez désactivé sur ce serveur : l'application utilise automatiquement l'utilisateur <code>erp_app</code>.</span>
                </div>
            @else
                <div class="field" style="grid-column: 1 / -1; padding: 12px 14px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; font-size: 13px; color: #166534;">
                    <strong>Base de données :</strong> créée automatiquement (ex. <code>erp_{{ $code ?: 'code' }}_a1b2</code>) avec le compte <code>erp_app</code>.
                    Les modules déjà activés sur un autre vendeur seront repris pour le nouveau.
                </div>
            @endif

            @if ($tenantId && $showAdvancedDb)
            <div class="field">
                <label class="field-label">DB Host</label>
                <input class="input" placeholder="vps-db-erp_pg" wire:model="db_host" autocomplete="off">
            </div>
            <div class="field">
                <label class="field-label">DB Port</label>
                <input class="input" placeholder="5432" wire:model="db_port" autocomplete="off">
            </div>
            <div class="field">
                <label class="field-label">DB Username</label>
                <input class="input" placeholder="erp_app" wire:model="db_username" autocomplete="off">
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
            <label class="field-toggle">
                <input type="checkbox" wire:model="multi_store_enabled">
                Activer Multi-magasins
            </label>
            <div class="field">
                <label class="field-label">Type d'activité</label>
                <select class="input" wire:model="type">
                    @foreach(config('tenant_types.types', []) as $typeKey => $typeConfig)
                        <option value="{{ $typeKey }}">{{ $typeConfig['label'] ?? $typeKey }}</option>
                    @endforeach
                </select>
                <span class="field-hint">Influence les modules proposés (ex. Pharmacie, Restaurant).</span>
                <span class="field-hint">Par défaut le tenant est mono-magasin. Activez cette option pour lancer le setup multi-magasins en batch.</span>
            </div>
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
                <input class="input" placeholder="Ex: +225 07 00 00 00 00" wire:model="contact_key_phone">
            </div>
            <div class="field">
                <label class="field-label">Pays</label>
                <input class="input" placeholder="Ex: Côte d'Ivoire" wire:model="country">
            </div>
            <div class="field">
                <label class="field-label">Ville</label>
                <input class="input" placeholder="Ex: Abidjan" wire:model="city">
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-label">Adresse du contact clé</label>
                <input class="input" placeholder="Ex: Cocody, rue des Jardins" wire:model="contact_key_address">
            </div>
        </div>
        @if (!$tenantId)
            <div class="card-body">Utilisateur administrateur initial</div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Nom admin</label>
                    <input class="input" placeholder="Ex: Admin Boutique" wire:model="admin_name">
                </div>
                <div class="field">
                    <label class="field-label">Email admin</label>
                    <input class="input" placeholder="admin@boutique.africa" wire:model="admin_email" autocomplete="off">
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
            Provisionnement en cours... merci de patienter.
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('system.tenants') }}">Retour</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                {{ $tenantId ? 'Mettre à jour' : 'Créer' }}
            </button>
        </div>
    </section>
</div>
