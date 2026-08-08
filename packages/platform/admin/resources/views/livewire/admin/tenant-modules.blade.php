<div class="page-body">
    <section class="cc-card" style="margin-bottom:14px;">
        <div class="cc-card__head">
            <div>
                <h2 class="cc-card__title" style="margin:0;">Activation modules</h2>
                <p style="margin:6px 0 0;font-size:13px;color:#64748b;">
                    Allumez ou éteignez les packages pour un client. Le catalogue complet est dans
                    <a href="{{ route('system.modules') }}">Modules</a>.
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-secondary btn-sm" href="{{ route('system.modules') }}">Catalogue</a>
                @if ($tenant)
                    <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.show', $tenant) }}">Fiche client</a>
                @endif
            </div>
        </div>
        <div class="cc-card__body">
            <div class="form-grid">
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label" for="activation-tenant">Client</label>
                    <select id="activation-tenant" class="input" wire:model.live="tenantId">
                        <option value="">— Choisir un client —</option>
                        @foreach ($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </section>

    @if (!$tenantId)
        <section class="cc-card">
            <div class="cc-card__body">
                <p class="stock-empty" style="margin:0;">Sélectionnez un client pour gérer ses modules.</p>
            </div>
        </section>
    @else
        <section class="dashboard-kpis" style="margin-bottom:16px;">
            <div class="dashboard-kpi">
                <div class="dashboard-kpi__label">Actifs</div>
                <div class="dashboard-kpi__value">{{ $kpis['active'] }}</div>
            </div>
            <div class="dashboard-kpi">
                <div class="dashboard-kpi__label">Inactifs</div>
                <div class="dashboard-kpi__value">{{ $kpis['inactive'] }}</div>
            </div>
            <div class="dashboard-kpi">
                <div class="dashboard-kpi__label">Core</div>
                <div class="dashboard-kpi__value">{{ $kpis['core'] }}</div>
            </div>
            <div class="dashboard-kpi">
                <div class="dashboard-kpi__label">Total catalogue</div>
                <div class="dashboard-kpi__value">{{ $kpis['total'] }}</div>
            </div>
        </section>

        <section class="cc-card app-table-card">
            <div class="table-toolbar">
                <div class="table-title">
                    Modules · {{ $tenant?->name }}
                    <span style="font-weight:500;color:#64748b;">· {{ $modules->count() }} affiché(s)</span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="activateDefaults">Activer les défauts</button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="clearStuckPending">Débloquer</button>
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <input class="input" type="search" placeholder="Rechercher…" wire:model.live.debounce.300ms="search">
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
                    <select class="input" wire:model.live="status">
                        <option value="">Tous statuts</option>
                        <option value="active">Actifs</option>
                        <option value="inactive">Inactifs</option>
                    </select>
                </div>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Groupe</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($modules as $module)
                            <tr wire:key="act-{{ $module['key'] }}">
                                <td>
                                    <a href="{{ route('system.modules.show', $module['key']) }}" style="font-weight:600;color:inherit;text-decoration:none;">
                                        {{ $module['label'] }}
                                    </a>
                                    <div style="font-size:11px;color:#94a3b8;">
                                        <code>{{ $module['key'] }}</code>
                                        @if ($module['module_family'])
                                            · famille {{ $module['module_family'] }}
                                        @endif
                                        @if ($module['enabled_by_default'])
                                            · défaut
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $module['group_label'] }}</td>
                                <td>
                                    @if ($module['pending'])
                                        <span class="badge badge-warning">En cours…</span>
                                    @elseif ($module['core'])
                                        <span class="badge badge-success">Core</span>
                                    @elseif ($module['enabled'])
                                        <span class="badge badge-success">Actif</span>
                                    @else
                                        <span class="badge badge-warning">Inactif</span>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('system.modules.show', $module['key']) }}">Fiche</a>
                                    @if ($module['core'])
                                        —
                                    @elseif ($module['pending'])
                                        <span class="btn btn-secondary btn-sm" disabled>…</span>
                                    @elseif ($module['enabled'])
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="uninstall('{{ $module['key'] }}')" wire:confirm="Désactiver {{ $module['label'] }} ?">
                                            Désactiver
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="install('{{ $module['key'] }}')" wire:loading.attr="disabled" wire:target="install('{{ $module['key'] }}')">
                                            <span wire:loading.remove wire:target="install('{{ $module['key'] }}')">Activer</span>
                                            <span wire:loading wire:target="install('{{ $module['key'] }}')">…</span>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="stock-empty">Aucun module pour ces filtres.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
