<div class="page-body crm-page">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div class="crm-page__intro">
        <div>
            <h2 class="crm-page__title">Prospects</h2>
            <p class="crm-page__lead">
                @if ($crmEnabled)
                    Gérez vos prospects et avancez-les dans le pipeline d’opportunités.
                @else
                    Prospection commerciale et conversion en clients.
                @endif
            </p>
        </div>
        <div class="crm-page__actions">
            @if ($crmEnabled && Route::has('tenant.crm.opportunities'))
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.crm.opportunities', ['tenant' => $tenantCode]) }}">Ouvrir le pipeline</a>
            @endif
            @if ($canCreate)
                <a class="btn btn-primary btn-sm" href="{{ route('tenant.prospects.create', ['tenant' => $tenantCode]) }}">Nouveau prospect</a>
            @endif
        </div>
    </div>

    @unless ($crmEnabled)
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
    @endunless

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

    @unless ($crmEnabled)
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
    @endunless

    <section class="crm-panel" style="padding:0;overflow:hidden;">
        <div class="crm-list-toolbar">
            <div class="crm-list-toolbar__filters">
                <input class="input input-sm" wire:model.live.debounce.300ms="search" placeholder="Réf, nom, tél, e-mail…" style="min-width:180px;" aria-label="Rechercher">
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
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinit.</button>
            </div>
        </div>

        <div class="crm-prospect-list">
            @forelse ($prospects as $p)
                <article class="crm-prospect-row" wire:key="prospect-{{ $p->id }}">
                    <div class="crm-prospect-row__main">
                        <a class="crm-prospect-row__name" href="{{ route('tenant.prospects.show', [$p->id, 'tenant' => $tenantCode]) }}">
                            {{ $p->name }}
                        </a>
                        <div class="crm-muted">
                            {{ $p->reference }}
                            · {{ \InovCom\Prospects\Models\Prospect::typeOptions()[$p->type] ?? $p->type }}
                            · {{ \InovCom\Prospects\Models\Prospect::sourceLabel($p->source) }}
                            @if ($p->phone) · {{ $p->phone }} @endif
                        </div>
                    </div>
                    <div class="crm-prospect-row__status">
                        <span class="badge {{ \InovCom\Prospects\Models\Prospect::statusBadgeClass($p->status) }}">
                            {{ \InovCom\Prospects\Models\Prospect::statusLabel($p->status) }}
                        </span>
                        @if ($p->expected_value !== null)
                            <div class="crm-prospect-row__money">{{ fmt_money((float) $p->expected_value) }}</div>
                        @endif
                        <div class="crm-muted">{{ $p->owner?->name ?? 'Non affecté' }}</div>
                    </div>
                    <div class="crm-prospect-row__actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.prospects.show', [$p->id, 'tenant' => $tenantCode]) }}">Fiche</a>
                        @if ($crmEnabled && $canUpdate && ! $p->isConverted() && ! $p->isLost())
                            <button type="button" class="btn btn-primary btn-sm" wire:click="toOpportunity({{ $p->id }})">
                                → Opportunité
                            </button>
                        @endif
                        @if ($p->isEditable())
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.prospects.edit', [$p->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                        @endif
                        @if ($canDelete && ! $p->isConverted())
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $p->id }})" wire:confirm="Supprimer ce prospect ?">Suppr.</button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="crm-empty">
                    Aucun prospect pour ces filtres.
                    @if ($canCreate)
                        <div style="margin-top:10px;">
                            <a class="btn btn-primary btn-sm" href="{{ route('tenant.prospects.create', ['tenant' => $tenantCode]) }}">Créer le premier</a>
                        </div>
                    @endif
                </div>
            @endforelse
        </div>
        <div style="padding:12px 16px;">{{ $prospects->links() }}</div>
    </section>
</div>
