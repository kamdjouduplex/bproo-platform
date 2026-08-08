<div class="page-body">
    <section class="cc-card" style="margin-bottom:14px;">
        <div class="cc-card__head">
            <div>
                <h2 class="cc-card__title" style="margin:0;">{{ $meta['label'] }}</h2>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                    <span class="badge badge-secondary"><code>{{ $meta['key'] }}</code></span>
                    <span class="badge badge-secondary">{{ $meta['group_label'] }}</span>
                    @if ($meta['core'])
                        <span class="badge badge-success">Core</span>
                    @else
                        <span class="badge badge-secondary">Optionnel</span>
                    @endif
                    @if ($meta['module_family'])
                        <span class="badge badge-secondary">Famille · {{ $meta['module_family'] }}</span>
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-secondary btn-sm" href="{{ route('system.modules') }}">Catalogue</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenant.modules') }}">Activation</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.module.events', ['moduleKey' => $meta['key']]) }}">Événements</a>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="syncCatalog">Sync</button>
            </div>
        </div>
        <div class="cc-card__body">
            <p style="margin:0 0 16px;color:#475569;line-height:1.5;">{{ $meta['description'] ?: '—' }}</p>

            <div class="dashboard-kpis" style="margin-bottom:0;">
                <div class="dashboard-kpi">
                    <div class="dashboard-kpi__label">Clients actifs</div>
                    <div class="dashboard-kpi__value">{{ $activeCount }}</div>
                    <div class="dashboard-kpi__meta">sur {{ $tenantTotal }}</div>
                </div>
                <div class="dashboard-kpi">
                    <div class="dashboard-kpi__label">Défaut</div>
                    <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ $meta['enabled_by_default'] ? 'Oui' : 'Non' }}</div>
                </div>
                <div class="dashboard-kpi">
                    <div class="dashboard-kpi__label">Version</div>
                    <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ $meta['version'] ?: '—' }}</div>
                </div>
            </div>
        </div>
    </section>

    <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin-bottom:14px;">
        <section class="cc-card">
            <div class="cc-card__head"><h2 class="cc-card__title">Technique</h2></div>
            <div class="cc-card__body">
                <div class="form-grid">
                    <div class="field">
                        <span class="field-label">Clé</span>
                        <div><code>{{ $meta['key'] }}</code></div>
                    </div>
                    <div class="field">
                        <span class="field-label">Version</span>
                        <div><code>{{ $meta['version'] ?: '—' }}</code></div>
                    </div>
                    <div class="field">
                        <span class="field-label">Package</span>
                        <div><code>{{ $meta['package_name'] ?: '—' }}</code></div>
                    </div>
                    <div class="field">
                        <span class="field-label">Route tenant</span>
                        <div><code>{{ $meta['route_name'] ?: '—' }}</code></div>
                    </div>
                    <div class="field">
                        <span class="field-label">Permission</span>
                        <div><code>{{ $meta['permission'] ?: '—' }}</code></div>
                    </div>
                    <div class="field" style="grid-column:1/-1;">
                        <span class="field-label">Lifecycle handler</span>
                        <div style="font-size:12px;word-break:break-all;"><code>{{ $meta['lifecycle_handler'] ?: '—' }}</code></div>
                    </div>
                    <div class="field">
                        <span class="field-label">Migration tag</span>
                        <div><code>{{ $meta['migration_tag'] ?: '—' }}</code></div>
                    </div>
                    <div class="field">
                        <span class="field-label">Icône</span>
                        <div>{{ $meta['icon'] ?: '—' }}</div>
                    </div>
                    @if (!empty($meta['tenant_types']))
                        <div class="field" style="grid-column:1/-1;">
                            <span class="field-label">Apps ciblées</span>
                            <div>
                                @foreach ($meta['tenant_types'] as $t)
                                    <span class="badge badge-secondary">{{ $typeLabels[$t]['label'] ?? $t }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($meta['family_siblings'])
                        <div class="field" style="grid-column:1/-1;">
                            <span class="field-label">Autres de la famille</span>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                @foreach ($meta['family_siblings'] as $sib)
                                    <a class="badge badge-secondary" href="{{ route('system.modules.show', $sib) }}">{{ config("modules.{$sib}.label", $sib) }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($meta['compatibility'])
                        <div class="field" style="grid-column:1/-1;">
                            <span class="field-label">Compatibilité</span>
                            <div style="font-size:12px;"><code>{{ is_array($meta['compatibility']) ? json_encode($meta['compatibility']) : $meta['compatibility'] }}</code></div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="cc-card">
            <div class="cc-card__head"><h2 class="cc-card__title">Actions plateforme</h2></div>
            <div class="cc-card__body">
                <p style="margin:0 0 14px;font-size:13px;color:#64748b;line-height:1.45;">
                    Opérations globales sur tous les clients. Pour un client précis, utilisez la liste ci-dessous ou
                    <a href="{{ route('system.tenant.modules') }}">Activation</a>.
                </p>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @unless ($meta['core'])
                        <button type="button" class="btn btn-primary" wire:click="installForAll" wire:confirm="Installer « {{ $meta['label'] }} » pour tous les clients ?">
                            Installer pour tous les clients
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="uninstallForAll" wire:confirm="Désinstaller « {{ $meta['label'] }} » pour tous les clients ?">
                            Désinstaller pour tous
                        </button>
                    @else
                        <p style="margin:0;font-size:13px;color:#64748b;">Module core — toujours disponible, non désinstallable.</p>
                    @endunless
                    <button type="button" class="btn btn-secondary" wire:click="clearStuckPending">Débloquer états « en cours »</button>
                </div>
            </div>
        </section>
    </div>

    <section class="cc-card app-table-card" style="margin-bottom:14px;">
        <div class="table-toolbar">
            <div class="table-title">Clients</div>
            <input class="input input-sm" type="search" placeholder="Filtrer client…" wire:model.live.debounce.300ms="tenantSearch" style="min-width:180px;">
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>App</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        @php $on = $meta['core'] || isset($enabledLookup[$tenant->id]); @endphp
                        <tr wire:key="mt-{{ $tenant->id }}">
                            <td>
                                <a href="{{ route('system.tenants.show', $tenant) }}" style="font-weight:600;color:inherit;text-decoration:none;">{{ $tenant->name }}</a>
                                <div style="font-size:11px;color:#94a3b8;"><code>{{ $tenant->code }}</code></div>
                            </td>
                            <td><span class="badge badge-secondary">{{ $tenant->type_label }}</span></td>
                            <td>
                                @if ($on)
                                    <span class="badge badge-success">{{ $meta['core'] ? 'Core' : 'Actif' }}</span>
                                @else
                                    <span class="badge badge-warning">Inactif</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenant.modules', ['tenant' => $tenant->id]) }}">Activation</a>
                                @unless ($meta['core'])
                                    @if ($on)
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="uninstallForTenant({{ $tenant->id }})">Désinstaller</button>
                                    @else
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="installForTenant({{ $tenant->id }})">Installer</button>
                                    @endif
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="stock-empty">Aucun client.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tenants->hasPages())
            <div class="table-pagination">{{ $tenants->links() }}</div>
        @endif
    </section>

    <section class="cc-card">
        <div class="cc-card__head">
            <h2 class="cc-card__title">Derniers événements</h2>
            <a class="btn btn-secondary btn-sm" href="{{ route('system.module.events', ['moduleKey' => $meta['key']]) }}">Tout voir</a>
        </div>
        <div class="cc-card__body">
            @forelse ($events as $event)
                <article style="padding:10px 0;border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:12px;color:#94a3b8;">
                        {{ $event->created_at?->format('d/m/Y H:i') }}
                        · {{ $event->action }}
                        · {{ $event->tenant?->name ?? '—' }}
                        · {{ $event->performer?->name ?? 'Système' }}
                    </div>
                </article>
            @empty
                <p style="margin:0;color:#94a3b8;">Aucun événement pour ce module.</p>
            @endforelse
        </div>
    </section>
</div>
