<div class="dashboard admin-dashboard">
    <section class="dashboard-welcome">
        <div class="dashboard-welcome-text">
            <h1 class="dashboard-greeting">Tableau de bord admin</h1>
            <p class="dashboard-date">{{ now()->translatedFormat('l d F Y') }}</p>
        </div>
    </section>

    <section class="dashboard-kpis">
        <div class="dashboard-kpi admin-dashboard-kpi--tenants">
            <div class="dashboard-kpi__label">Vendeurs</div>
            <div class="dashboard-kpi__value">{{ $tenantsTotal }}</div>
            <div class="dashboard-kpi__meta">{{ $tenantsActive }} actif{{ $tenantsActive !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi admin-dashboard-kpi--modules">
            <div class="dashboard-kpi__label">Modules</div>
            <div class="dashboard-kpi__value">{{ $modulesCount }}</div>
            <div class="dashboard-kpi__meta">disponibles</div>
        </div>
        @if ($tenantsProvisioning > 0)
        <div class="dashboard-kpi dashboard-kpi--expenses">
            <div class="dashboard-kpi__label">En cours</div>
            <div class="dashboard-kpi__value">{{ $tenantsProvisioning }}</div>
            <div class="dashboard-kpi__meta">provisionnement</div>
        </div>
        @endif
        @if ($tenantsFailed > 0)
        <div class="dashboard-kpi dashboard-kpi--benefit">
            <div class="dashboard-kpi__label">Échecs</div>
            <div class="dashboard-kpi__value dashboard-kpi__value--negative">{{ $tenantsFailed }}</div>
            <div class="dashboard-kpi__meta">vendeurs</div>
        </div>
        @endif
    </section>

    <section class="admin-dashboard-quick-links">
        <h2 class="admin-dashboard-section-title">Accès rapide</h2>
        <div class="grid-cards admin-dashboard-cards">
            <a href="{{ route('system.tenants') }}" class="card admin-dashboard-card admin-dashboard-card--tenants">
                <div class="card-title">Vendeurs</div>
                <div class="card-body">Créer et gérer les boutiques, bases et statuts.</div>
                <span class="admin-dashboard-card__cta">Voir la liste →</span>
            </a>
            <a href="{{ route('system.packages') }}" class="card admin-dashboard-card admin-dashboard-card--packages">
                <div class="card-title">Packages</div>
                <div class="card-body">Activer ou désactiver les modules par vendeur.</div>
                <span class="admin-dashboard-card__cta">Gérer les modules →</span>
            </a>
            <a href="{{ route('system.tenants.health') }}" class="card admin-dashboard-card admin-dashboard-card--health">
                <div class="card-title">Santé</div>
                <div class="card-body">État des vendeurs et connexions BDD.</div>
                <span class="admin-dashboard-card__cta">Vérifier →</span>
            </a>
            <a href="{{ route('system.module.events') }}" class="card admin-dashboard-card admin-dashboard-card--events">
                <div class="card-title">Historique modules</div>
                <div class="card-body">Installations et désinstallations par vendeur.</div>
                <span class="admin-dashboard-card__cta">Voir l'historique →</span>
            </a>
        </div>
    </section>

    <section class="dashboard-grid">
        <div class="dashboard-panel">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Activité récente (modules)</h2>
                <a href="{{ route('system.module.events') }}" class="dashboard-panel__link">Tout voir</a>
            </div>
            <div class="dashboard-panel__body">
                @if ($recentEvents->isNotEmpty())
                    <div class="table-scroll">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Vendeur</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Par</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentEvents as $event)
                                    <tr>
                                        <td>{{ $event->created_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ $event->tenant?->name ?? '—' }}</td>
                                        <td><code>{{ $event->module_key }}</code></td>
                                        <td>
                                            @if ($event->action === 'install')
                                                <span class="badge badge-success">Installé</span>
                                            @else
                                                <span class="badge badge-warning">Désinstallé</span>
                                            @endif
                                        </td>
                                        <td>{{ $event->performer?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="dashboard-empty">Aucune activité récente.</p>
                @endif
            </div>
        </div>
    </section>
</div>
