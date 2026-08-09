<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Clients</div>
            <div class="dashboard-kpi__value">{{ $kpis['total'] }}</div>
            <div class="dashboard-kpi__meta">{{ $kpis['active'] }} actif{{ $kpis['active'] !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Abonnements actifs</div>
            <div class="dashboard-kpi__value">{{ $kpis['with_sub'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Issus du CRM</div>
            <div class="dashboard-kpi__value">{{ $kpis['from_crm'] }}</div>
        </div>
        @if (($kpis['seats_exceeded'] ?? 0) > 0)
            <div class="dashboard-kpi dashboard-kpi--benefit">
                <div class="dashboard-kpi__label">Plafond users dépassé</div>
                <div class="dashboard-kpi__value dashboard-kpi__value--negative">{{ $kpis['seats_exceeded'] }}</div>
                <div class="dashboard-kpi__meta">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('seats', 'exceeded')">Filtrer</button>
                </div>
            </div>
        @endif
    </section>

    <section class="cc-card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Clients <span style="font-weight:500;color:#64748b;">· {{ $tenants->total() }}</span></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="refreshMetrics" wire:loading.attr="disabled">Actualiser users</button>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.prospects.create') }}">Nouveau prospect</a>
                <a class="btn btn-primary btn-sm" href="{{ route('system.tenants.create') }}">Nouveau client</a>
            </div>
        </div>
        <div class="form-grid">
            <div class="field">
                <input class="input" type="search" placeholder="Nom, code…" wire:model.live.debounce.300ms="search">
            </div>
            <div class="field">
                <select class="input" wire:model.live="product">
                    <option value="">Toutes apps</option>
                    @foreach ($productTypes as $key => $cfg)
                        <option value="{{ $key }}">{{ $cfg['label'] ?? $key }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="status">
                    <option value="">Tous abonnements</option>
                    <option value="active_sub">Abo. actif</option>
                    <option value="suspended">Suspendu</option>
                    <option value="none">Sans abo.</option>
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="active">
                    <option value="">Tous statuts</option>
                    <option value="1">Actif</option>
                    <option value="0">Inactif</option>
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="seats">
                    <option value="">Tous plafonds</option>
                    <option value="limited">Avec plafond</option>
                    <option value="exceeded">Dépassés</option>
                </select>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>App</th>
                        <th>Utilisateurs</th>
                        <th>Abonnement</th>
                        <th>Origine</th>
                        <th>Commercial</th>
                        <th>Total payé</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        @php
                            $sub = $subs->get($tenant->id);
                            $origin = $origins->get($tenant->id);
                        @endphp
                        <tr wire:key="tenant-{{ $tenant->id }}">
                            <td>
                                <a href="{{ route('system.tenants.show', $tenant) }}" style="font-weight:600;color:inherit;text-decoration:none;">
                                    {{ $tenant->name }}
                                </a>
                                <div style="font-size:11px;color:#64748b;">
                                    <code>{{ $tenant->code }}</code>
                                    @unless ($tenant->is_active)
                                        · <span style="color:#b45309;">inactif</span>
                                    @endunless
                                    @if ($tenant->isUsersLimitExceeded() || $tenant->users_limit_exceeded_at)
                                        · <span style="color:#b91c1c;font-weight:700;">plafond dépassé</span>
                                    @endif
                                </div>
                            </td>
                            <td><span class="badge badge-secondary">{{ $tenant->type_label }}</span></td>
                            <td>
                                <strong @if($tenant->isUsersLimitExceeded() || $tenant->users_limit_exceeded_at) style="color:#b91c1c;" @endif>
                                    {{ $tenant->usersQuotaLabel() }}
                                </strong>
                            </td>
                            <td>
                                @if ($sub)
                                    <span class="badge badge-{{ $sub->status_color }}">{{ $statusLabels[$sub->status] ?? $sub->status }}</span>
                                    <div style="font-size:11px;color:#64748b;">{{ $sub->plan?->name }}</div>
                                @else
                                    <span class="badge badge-warning">Aucun</span>
                                @endif
                            </td>
                            <td>
                                @if ($origin)
                                    <a href="{{ route('system.prospects.edit', $origin) }}">CRM</a>
                                @else
                                    <span style="color:#94a3b8;">Manuel</span>
                                @endif
                            </td>
                            <td>{{ $origin?->owner?->name ?: '—' }}</td>
                            <td><strong>{{ fmt_money($tenant->total_paid ?? 0) }}</strong></td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.show', $tenant) }}">Fiche</a>
                                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.users', $tenant) }}">Users</a>
                                <button
                                    type="button"
                                    class="btn btn-sm {{ $tenant->is_active ? 'btn-secondary' : 'btn-primary' }}"
                                    wire:click="toggleActive({{ $tenant->id }})"
                                    wire:confirm="{{ $tenant->is_active ? 'Désactiver cette entreprise ?' : 'Réactiver cette entreprise ?' }}"
                                >
                                    {{ $tenant->is_active ? 'Désactiver' : 'Activer' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="stock-empty">
                                Aucun client.
                                <a href="{{ route('system.prospects.create') }}">Démarrer un prospect</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tenants->hasPages())
            <div class="table-pagination" style="padding:12px 20px;">
                {{ $tenants->links() }}
            </div>
        @endif
    </section>
</div>
