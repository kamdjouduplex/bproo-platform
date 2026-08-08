<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Modules</div>
            <div class="dashboard-kpi__value">{{ $kpis['total'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Core</div>
            <div class="dashboard-kpi__value">{{ $kpis['core'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Optionnels</div>
            <div class="dashboard-kpi__value">{{ $kpis['optional'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Clients</div>
            <div class="dashboard-kpi__value">{{ $kpis['tenants'] }}</div>
            <div class="dashboard-kpi__meta"><a href="{{ route('system.tenant.modules') }}">Gérer l’activation →</a></div>
        </div>
    </section>

    <section class="cc-card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Catalogue modules <span style="font-weight:500;color:#64748b;">· {{ $modules->count() }}</span></div>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="syncCatalog">Synchroniser</button>
        </div>

        <div class="form-grid">
            <div class="field">
                <input class="input" type="search" placeholder="Rechercher un module…" wire:model.live.debounce.300ms="search">
            </div>
            <div class="field">
                <select class="input" wire:model.live="group">
                    <option value="">Tous groupes</option>
                    @foreach ($groups as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="type">
                    <option value="">Tous types</option>
                    <option value="core">Core</option>
                    <option value="optional">Optionnels</option>
                </select>
            </div>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Groupe</th>
                        <th>Type</th>
                        <th>Défaut</th>
                        <th>Clients actifs</th>
                        <th>Version</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        <tr wire:key="mod-{{ $module['key'] }}">
                            <td>
                                <a href="{{ route('system.modules.show', $module['key']) }}" style="font-weight:600;color:inherit;text-decoration:none;">
                                    {{ $module['label'] }}
                                </a>
                                <div style="font-size:11px;color:#94a3b8;"><code>{{ $module['key'] }}</code></div>
                            </td>
                            <td>{{ $module['group_label'] }}</td>
                            <td>
                                @if ($module['core'])
                                    <span class="badge badge-success">Core</span>
                                @else
                                    <span class="badge badge-secondary">Optionnel</span>
                                @endif
                            </td>
                            <td>{{ $module['enabled_by_default'] ? 'Oui' : 'Non' }}</td>
                            <td>
                                <strong>{{ $module['active_tenants'] }}</strong>
                                <span style="color:#94a3b8;">/ {{ $tenantTotal }}</span>
                            </td>
                            <td>{{ $module['version'] ?: '—' }}</td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-primary btn-sm" href="{{ route('system.modules.show', $module['key']) }}">Fiche</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="stock-empty">
                                Aucun module.
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="syncCatalog">Synchroniser le catalogue</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
