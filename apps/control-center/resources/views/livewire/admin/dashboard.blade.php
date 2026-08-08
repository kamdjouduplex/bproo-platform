<div class="dashboard admin-dashboard">
    <section class="dashboard-welcome cc-card" style="padding:18px 20px;margin-bottom:16px;">
        <div class="dashboard-welcome-text">
            <h1 class="dashboard-greeting" style="font-family:Manrope,sans-serif;font-weight:800;margin:0;">Control Center</h1>
            <p class="dashboard-date" style="margin:4px 0 0;">{{ now()->translatedFormat('l d F Y') }} · cerveau commercial & ops</p>
        </div>
    </section>

    <section class="dashboard-kpis">
        <div class="dashboard-kpi admin-dashboard-kpi--tenants">
            <div class="dashboard-kpi__label">Entreprises</div>
            <div class="dashboard-kpi__value">{{ $tenantsTotal }}</div>
            <div class="dashboard-kpi__meta">{{ $tenantsActive }} active{{ $tenantsActive !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">CRM ouvert</div>
            <div class="dashboard-kpi__value">{{ $prospectsOpen }}</div>
            <div class="dashboard-kpi__meta">{{ $followUpsDue }} suivi{{ $followUpsDue !== 1 ? 's' : '' }} dû{{ $followUpsDue !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Abonnements actifs</div>
            <div class="dashboard-kpi__value">{{ $subsActive }}</div>
            <div class="dashboard-kpi__meta">{{ $subsSuspended }} suspendu{{ $subsSuspended !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Encaissements du mois</div>
            <div class="dashboard-kpi__value" style="font-size:1.35rem;">{{ number_format($paymentsMonth, 0, ',', ' ') }}</div>
            <div class="dashboard-kpi__meta">XOF</div>
        </div>
        @if ($tenantsProvisioning > 0)
        <div class="dashboard-kpi dashboard-kpi--expenses">
            <div class="dashboard-kpi__label">Provisionnement</div>
            <div class="dashboard-kpi__value">{{ $tenantsProvisioning }}</div>
        </div>
        @endif
        @if ($tenantsFailed > 0)
        <div class="dashboard-kpi dashboard-kpi--benefit">
            <div class="dashboard-kpi__label">Échecs</div>
            <div class="dashboard-kpi__value dashboard-kpi__value--negative">{{ $tenantsFailed }}</div>
        </div>
        @endif
    </section>

    <section class="admin-dashboard-quick-links" style="margin-top:8px;">
        <h2 class="admin-dashboard-section-title">Pilotage rapide</h2>
        <div class="grid-cards admin-dashboard-cards">
            <a href="{{ route('system.prospects') }}" class="card admin-dashboard-card">
                <div class="card-title">CRM plateforme</div>
                <div class="card-body">Prospects, pipeline, conversion en client.</div>
                <span class="admin-dashboard-card__cta">Ouvrir les prospects →</span>
            </a>
            <a href="{{ route('system.tenants') }}" class="card admin-dashboard-card admin-dashboard-card--tenants">
                <div class="card-title">Clients</div>
                <div class="card-body">Fiches, abonnements, facturation.</div>
                <span class="admin-dashboard-card__cta">Voir la liste →</span>
            </a>
            <a href="{{ route('system.invoices') }}" class="card admin-dashboard-card">
                <div class="card-title">Facturation</div>
                <div class="card-body">Factures, abonnements, MRR, encaissements et plans.</div>
                <span class="admin-dashboard-card__cta">Ouvrir →</span>
            </a>
            <a href="{{ route('system.tenants.health') }}" class="card admin-dashboard-card admin-dashboard-card--health">
                <div class="card-title">Santé</div>
                <div class="card-body">Provisionnement et connexions BDD.</div>
                <span class="admin-dashboard-card__cta">Vérifier →</span>
            </a>
        </div>
    </section>

    <section class="dashboard-grid" style="margin-top:16px;">
        <div class="dashboard-panel cc-card">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Répartition par application</h2>
            </div>
            <div class="dashboard-panel__body">
                <div class="table-scroll">
                    <table class="dashboard-table">
                        <thead><tr><th>Application</th><th>Entreprises</th></tr></thead>
                        <tbody>
                            @forelse ($productTypes as $key => $cfg)
                                <tr>
                                    <td>{{ $cfg['label'] ?? $key }}</td>
                                    <td>{{ $byApp[$key] ?? 0 }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="dashboard-panel cc-card">
            <div class="dashboard-panel__head">
                <h2 class="dashboard-panel__title">Prospects récents</h2>
                <a href="{{ route('system.prospects') }}" class="dashboard-panel__link">Tout voir</a>
            </div>
            <div class="dashboard-panel__body">
                @if ($recentProspects->isNotEmpty())
                    <div class="table-scroll">
                        <table class="dashboard-table">
                            <thead><tr><th>Entreprise</th><th>Étape</th><th>App</th></tr></thead>
                            <tbody>
                                @foreach ($recentProspects as $p)
                                    <tr>
                                        <td><a href="{{ route('system.prospects.edit', $p) }}">{{ $p->company_name }}</a></td>
                                        <td>{{ $p->stageLabel() }}</td>
                                        <td>{{ $p->productLabel() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="dashboard-empty">Aucun prospect. <a href="{{ route('system.prospects.create') }}">Créer le premier</a>.</p>
                @endif
            </div>
        </div>
    </section>
</div>
