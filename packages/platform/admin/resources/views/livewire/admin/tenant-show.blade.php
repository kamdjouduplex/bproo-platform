<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Utilisateurs</div>
            <div class="dashboard-kpi__value">{{ $metrics['users_count'] ?? $tenant->users_count ?? '—' }}</div>
            <div class="dashboard-kpi__meta">
                @if ($tenant->metrics_cached_at)
                    maj {{ $tenant->metrics_cached_at->diffForHumans() }}
                @else
                    non synchronisé
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
    </section>

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <div class="table-toolbar" style="padding:0;margin-bottom:12px;">
            <div class="table-title">{{ $tenant->name }}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="refreshMetrics">Actualiser indicateurs</button>
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
