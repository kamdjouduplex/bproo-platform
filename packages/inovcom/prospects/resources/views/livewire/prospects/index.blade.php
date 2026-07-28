<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div class="prospect-kpis">
        <div class="prospect-kpi">
            <div class="prospect-kpi__value">{{ $stats['total'] ?? 0 }}</div>
            <div class="prospect-kpi__label">Pipeline total</div>
        </div>
        <div class="prospect-kpi prospect-kpi--accent">
            <div class="prospect-kpi__value">{{ $stats['nouveau'] ?? 0 }}</div>
            <div class="prospect-kpi__label">Nouveaux</div>
        </div>
        <div class="prospect-kpi prospect-kpi--warm">
            <div class="prospect-kpi__value">{{ $stats['contacte'] ?? 0 }}</div>
            <div class="prospect-kpi__label">Contactés</div>
        </div>
        <div class="prospect-kpi prospect-kpi--violet">
            <div class="prospect-kpi__value">{{ $stats['qualifie'] ?? 0 }}</div>
            <div class="prospect-kpi__label">Qualifiés</div>
        </div>
        <div class="prospect-kpi prospect-kpi--good">
            <div class="prospect-kpi__value">{{ $stats['converti'] ?? 0 }}</div>
            <div class="prospect-kpi__label">Convertis</div>
        </div>
        <div class="prospect-kpi prospect-kpi--teal">
            <div class="prospect-kpi__value">{{ number_format((float) ($stats['conversion_rate'] ?? 0), 1, ',', ' ') }} %</div>
            <div class="prospect-kpi__label">Taux de conversion</div>
        </div>
    </div>

    <div class="prospect-pipeline" role="tablist" aria-label="Filtrer par statut">
        <button type="button" class="prospect-pipeline__step {{ $statusFilter === 'all' ? 'is-active' : '' }}" wire:click="$set('statusFilter', 'all')">
            <div class="prospect-pipeline__count">{{ $stats['total'] ?? 0 }}</div>
            <div class="prospect-pipeline__name">Tous</div>
        </button>
        @foreach (\InovCom\Prospects\Models\Prospect::statusOptions() as $value => $label)
            <button
                type="button"
                class="prospect-pipeline__step {{ $statusFilter === $value ? 'is-active' : '' }}"
                wire:click="$set('statusFilter', '{{ $value }}')"
                title="{{ \InovCom\Prospects\Models\Prospect::statusHints()[$value] ?? '' }}"
            >
                <div class="prospect-pipeline__count">{{ $stats[$value] ?? 0 }}</div>
                <div class="prospect-pipeline__name">{{ $label }}</div>
            </button>
        @endforeach
    </div>

    @if (count($conversionBySource) > 0)
        <div class="prospect-sources">
            @foreach ($conversionBySource as $row)
                <div class="prospect-source-chip">
                    {{ $row['label'] }}
                    <strong>{{ $row['converted'] }}/{{ $row['total'] }} · {{ number_format($row['rate'], 1, ',', ' ') }} %</strong>
                </div>
            @endforeach
        </div>
    @endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div>
                <div class="table-title">Prospects</div>
                <div class="prospect-row-meta">Qualifié = besoin, budget et intention d’achat confirmés</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input class="input input-sm" wire:model.live.debounce.300ms="search" placeholder="Réf, nom, tél, e-mail…" style="min-width:200px;" aria-label="Rechercher">
                <select class="input input-sm" wire:model.live="sourceFilter" aria-label="Source">
                    <option value="all">Toutes sources</option>
                    @foreach (\InovCom\Prospects\Models\Prospect::sourceOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select class="input input-sm" wire:model.live="ownerFilter" aria-label="Commercial">
                    <option value="all">Tous commerciaux</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.prospects.create', ['tenant' => $tenantCode]) }}">Nouveau prospect</a>
                @endif
            </div>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Prospect</th>
                        <th>Source</th>
                        <th>Statut</th>
                        <th>Coût lead</th>
                        <th>CA potentiel</th>
                        <th>Commercial</th>
                        <th>Créé</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($prospects as $p)
                        <tr wire:key="prospect-{{ $p->id }}">
                            <td>
                                <a href="{{ route('tenant.prospects.show', [$p->id, 'tenant' => $tenantCode]) }}" style="font-weight:600;color:inherit;text-decoration:none;">
                                    {{ \Illuminate\Support\Str::limit($p->name, 36) }}
                                </a>
                                <div class="prospect-row-meta">
                                    {{ $p->reference }}
                                    · {{ \InovCom\Prospects\Models\Prospect::typeOptions()[$p->type] ?? $p->type }}
                                    @if ($p->phone) · {{ $p->phone }} @endif
                                </div>
                            </td>
                            <td>{{ \InovCom\Prospects\Models\Prospect::sourceLabel($p->source) }}</td>
                            <td>
                                <span class="badge {{ \InovCom\Prospects\Models\Prospect::statusBadgeClass($p->status) }}">
                                    {{ \InovCom\Prospects\Models\Prospect::statusLabel($p->status) }}
                                </span>
                            </td>
                            <td class="prospect-money">{{ fmt_money((float) $p->cost) }}</td>
                            <td class="prospect-money {{ $p->expected_value === null ? 'prospect-money--muted' : '' }}">
                                {{ $p->expected_value !== null ? fmt_money((float) $p->expected_value) : '—' }}
                            </td>
                            <td>{{ $p->owner?->name ?? '—' }}</td>
                            <td>{{ optional($p->created_at)->format('d/m/Y') }}</td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end;">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.prospects.show', [$p->id, 'tenant' => $tenantCode]) }}">Ouvrir</a>
                                    @if ($p->isEditable())
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.prospects.edit', [$p->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                    @endif
                                    @if ($canDelete && ! $p->isConverted())
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $p->id }})" wire:confirm="Supprimer ce prospect ?">Suppr.</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:#94a3b8;padding:36px 16px;">
                                Aucun prospect pour ces filtres.
                                @if ($canCreate)
                                    <div style="margin-top:10px;">
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.prospects.create', ['tenant' => $tenantCode]) }}">Créer le premier</a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px;">{{ $prospects->links() }}</div>
    </section>
</div>
