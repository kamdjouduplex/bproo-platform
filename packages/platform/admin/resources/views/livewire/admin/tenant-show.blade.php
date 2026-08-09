<div class="page-body">
    @if ($tenant->users_limit_exceeded_at)
        <div class="cc-card" style="margin-bottom:16px;padding:14px 16px;border-left:4px solid #dc2626;background:#fef2f2;">
            <strong style="color:#b91c1c;">Plafond utilisateurs dépassé</strong>
            <p style="margin:6px 0 0;color:#7f1d1d;">
                {{ $tenant->name }} ({{ $tenant->code }}) a
                <strong>{{ $tenant->users_count ?? '—' }}</strong> utilisateurs pour un plafond de
                <strong>{{ $tenant->max_users }}</strong>.
                Contactez le client pour régulariser, ou augmentez le plafond / désactivez l’accès.
            </p>
            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-primary btn-sm" href="{{ route('system.tenants.users', $tenant) }}">Voir les utilisateurs</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.edit', $tenant) }}">Modifier le plafond</a>
            </div>
        </div>
    @endif

    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Utilisateurs</div>
            <div class="dashboard-kpi__value" @if($tenant->users_limit_exceeded_at) style="color:#b91c1c;" @endif>
                {{ $tenant->usersQuotaLabel() }}
            </div>
            <div class="dashboard-kpi__meta">
                @if ($tenant->metrics_cached_at)
                    maj {{ $tenant->metrics_cached_at->diffForHumans() }}
                @else
                    non synchronisé
                @endif
                @if ($tenant->hasUsersLimit())
                    · plafond {{ $tenant->max_users }}
                @else
                    · illimité
                @endif
            </div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Modules actifs</div>
            <div class="dashboard-kpi__value">{{ $metrics['modules_enabled_count'] ?? $tenant->modules_enabled_count ?? $enabledModules->count() }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Abonnement</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">
                {{ $subscription?->plan?->name ?? 'Aucun' }}
            </div>
            <div class="dashboard-kpi__meta">{{ $subscription ? (\App\Models\Subscription::statuses()[$subscription->status] ?? $subscription->status) : '—' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Solde</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ number_format((float) $tenant->balance, 0, ',', ' ') }}</div>
            <div class="dashboard-kpi__meta">{{ $tenant->balance_currency ?? 'XOF' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Statut accès</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">
                {{ $tenant->is_active ? 'Actif' : 'Inactif' }}
            </div>
            <div class="dashboard-kpi__meta">
                <button
                    type="button"
                    class="btn btn-sm {{ $tenant->is_active ? 'btn-secondary' : 'btn-primary' }}"
                    wire:click="toggleActive"
                    wire:confirm="{{ $tenant->is_active ? 'Désactiver l’accès de cette entreprise ?' : 'Réactiver l’accès ?' }}"
                >
                    {{ $tenant->is_active ? 'Désactiver' : 'Activer' }}
                </button>
            </div>
        </div>
    </section>

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <div class="table-toolbar" style="padding:0;margin-bottom:12px;">
            <div class="table-title">{{ $tenant->name }}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="refreshMetrics">Actualiser indicateurs</button>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.users', $tenant) }}">Utilisateurs</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.subscription', $tenant) }}">Facturation</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenant.modules', ['tenant' => $tenant->id]) }}">Modules</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.settings', $tenant) }}">Paramètres</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.edit', $tenant) }}">Modifier</a>
                @if ($loginUrl)
                    <a class="btn btn-primary btn-sm" href="{{ $loginUrl }}" target="_blank" rel="noopener">Ouvrir l’app</a>
                @endif
            </div>
        </div>

        <div class="form-grid">
            <div class="field"><span class="field-label">Code</span><div><code>{{ $tenant->code }}</code></div></div>
            <div class="field"><span class="field-label">Application</span><div><span class="badge badge-secondary">{{ $typeLabel }}</span></div></div>
            <div class="field"><span class="field-label">Base</span><div><code>{{ $tenant->db_name }}</code></div></div>
            <div class="field"><span class="field-label">Provisionnement</span><div>{{ $tenant->provisioning_status }}</div></div>
            <div class="field"><span class="field-label">Contact</span><div>{{ trim(($tenant->contact_key_first_name ?? '').' '.($tenant->contact_key_last_name ?? '')) ?: '—' }}</div></div>
            <div class="field"><span class="field-label">Téléphone</span><div>{{ $tenant->contact_key_phone ?: '—' }}</div></div>
            <div class="field"><span class="field-label">Ville</span><div>{{ $tenant->city ?: '—' }}</div></div>
            <div class="field"><span class="field-label">Pays</span><div>{{ $tenant->country ?: '—' }}</div></div>
            <div class="field">
                <span class="field-label">Plafond utilisateurs</span>
                <div>{{ $tenant->hasUsersLimit() ? $tenant->max_users : 'Illimité' }}</div>
            </div>
            @if ($loginUrl)
                <div class="field" style="grid-column:1/-1;">
                    <span class="field-label">URL de connexion</span>
                    <div><code>{{ $loginUrl }}</code></div>
                </div>
            @endif
            @if (!empty($metrics['error']))
                <div class="field" style="grid-column:1/-1;color:#b91c1c;">Erreur métriques : {{ $metrics['error'] }}</div>
            @endif
        </div>
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Modules activés ({{ $enabledModules->count() }})</div>
            <a class="btn btn-primary btn-sm" href="{{ route('system.tenant.modules', ['tenant' => $tenant->id]) }}">Gérer l’activation</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Module</th><th>Clé</th><th></th></tr></thead>
                <tbody>
                    @forelse ($enabledModules as $module)
                        <tr>
                            <td>{{ $module->label }}</td>
                            <td><code>{{ $module->key }}</code></td>
                            <td><a class="btn btn-secondary btn-sm" href="{{ route('system.modules.show', $module->key) }}">Fiche</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Aucun module activé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="page-actions" style="margin-top:16px;">
        <a class="btn btn-secondary" href="{{ route('system.tenants') }}">Retour aux entreprises</a>
    </div>
</div>
