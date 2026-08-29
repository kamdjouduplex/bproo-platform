<div class="page-body cc-mods">
    <section class="dashboard-kpis cc-mods__kpis">
        <div class="dashboard-kpi">
            <div class="cc-mods__kpi-top">
                <span class="cc-mod-icon cc-mod-icon--system" aria-hidden="true">
                    <x-sidebar-icon icon="package" />
                </span>
                <div>
                    <div class="dashboard-kpi__label">Modules</div>
                    <div class="dashboard-kpi__value">{{ $kpis['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="dashboard-kpi">
            <div class="cc-mods__kpi-top">
                <span class="cc-mod-icon cc-mod-icon--ventes" aria-hidden="true">
                    <x-sidebar-icon icon="clipboard-check" />
                </span>
                <div>
                    <div class="dashboard-kpi__label">Core</div>
                    <div class="dashboard-kpi__value">{{ $kpis['core'] }}</div>
                </div>
            </div>
        </div>
        <div class="dashboard-kpi">
            <div class="cc-mods__kpi-top">
                <span class="cc-mod-icon cc-mod-icon--catalogue" aria-hidden="true">
                    <x-sidebar-icon icon="view-columns" />
                </span>
                <div>
                    <div class="dashboard-kpi__label">Optionnels</div>
                    <div class="dashboard-kpi__value">{{ $kpis['optional'] }}</div>
                </div>
            </div>
        </div>
        <div class="dashboard-kpi">
            <div class="cc-mods__kpi-top">
                <span class="cc-mod-icon cc-mod-icon--rh" aria-hidden="true">
                    <x-sidebar-icon icon="building-office" />
                </span>
                <div>
                    <div class="dashboard-kpi__label">Clients</div>
                    <div class="dashboard-kpi__value">{{ $kpis['tenants'] }}</div>
                    <div class="dashboard-kpi__meta"><a href="{{ route('system.tenant.modules') }}">Gérer l’activation →</a></div>
                </div>
            </div>
        </div>
    </section>

    <section class="cc-card cc-mods__toolbar">
        <div class="cc-mods__toolbar-row">
            <label class="cc-mods__search">
                <span class="cc-mods__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/>
                    </svg>
                </span>
                <input class="input" type="search" placeholder="Rechercher un module, une clé, une description…" wire:model.live.debounce.300ms="search">
            </label>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="syncCatalog" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="syncCatalog">Synchroniser</span>
                <span wire:loading wire:target="syncCatalog">Sync…</span>
            </button>
        </div>

        <div class="cc-mods__chips" role="tablist" aria-label="Type de module">
            <span class="cc-mods__chips-label">Type</span>
            <button type="button" class="cc-mods__chip {{ $type === '' ? 'is-active' : '' }}" wire:click="$set('type', '')">
                Tous <span class="cc-mods__chip-count">{{ $kpis['total'] }}</span>
            </button>
            <button type="button" class="cc-mods__chip {{ $type === 'core' ? 'is-active' : '' }}" wire:click="filterType('core')">
                Core <span class="cc-mods__chip-count">{{ $kpis['core'] }}</span>
            </button>
            <button type="button" class="cc-mods__chip {{ $type === 'optional' ? 'is-active' : '' }}" wire:click="filterType('optional')">
                Optionnels <span class="cc-mods__chip-count">{{ $kpis['optional'] }}</span>
            </button>
        </div>

        <div class="cc-mods__chips cc-mods__chips--groups" role="tablist" aria-label="Groupe">
            <span class="cc-mods__chips-label">Groupe</span>
            <button type="button" class="cc-mods__chip {{ $group === '' ? 'is-active' : '' }}" wire:click="$set('group', '')">
                Tous
            </button>
            @foreach ($groups as $key => $label)
                <button type="button" class="cc-mods__chip {{ $group === $key ? 'is-active' : '' }}" wire:click="filterGroup('{{ $key }}')">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </section>

    @forelse ($grouped as $groupKey => $items)
        <section class="cc-mod-group" wire:key="grp-{{ $groupKey }}">
            <header class="cc-mod-group__head">
                <h2 class="cc-mod-group__title">{{ $items->first()['group_label'] }}</h2>
                <span class="cc-mod-group__count">{{ $items->count() }}</span>
            </header>
            <div class="cc-mod-grid">
                @foreach ($items as $module)
                    @php
                        $pct = $tenantTotal > 0
                            ? (int) round(100 * $module['active_tenants'] / $tenantTotal)
                            : 0;
                    @endphp
                    <a href="{{ route('system.modules.show', $module['key']) }}" class="cc-mod-card {{ $module['core'] ? 'cc-mod-card--core' : '' }}" wire:key="mod-{{ $module['key'] }}">
                        <div class="cc-mod-card__top">
                            <span class="cc-mod-icon cc-mod-icon--{{ $module['group'] }}" aria-hidden="true">
                                <x-sidebar-icon :icon="$module['icon']" />
                            </span>
                            <div class="cc-mod-card__titles">
                                <div class="cc-mod-card__name">{{ $module['label'] }}</div>
                                <code class="cc-mod-card__key">{{ $module['key'] }}</code>
                            </div>
                            <div class="cc-mod-card__badges">
                                @if ($module['core'])
                                    <span class="badge badge-success">Core</span>
                                @else
                                    <span class="badge badge-secondary">Optionnel</span>
                                @endif
                            </div>
                        </div>

                        <p class="cc-mod-card__desc">{{ $module['description'] ?: 'Aucune description.' }}</p>

                        <div class="cc-mod-card__meta">
                            @if ($module['enabled_by_default'])
                                <span class="cc-mod-pill">Activé par défaut</span>
                            @endif
                            @if (! $module['in_db'])
                                <span class="cc-mod-pill cc-mod-pill--warn">Hors catalogue DB</span>
                            @endif
                            @if ($module['version'])
                                <span class="cc-mod-pill">v{{ $module['version'] }}</span>
                            @endif
                        </div>

                        <div class="cc-mod-adopt">
                            <div class="cc-mod-adopt__track" aria-hidden="true">
                                <span class="cc-mod-adopt__fill" style="width: {{ $pct }}%"></span>
                            </div>
                            <span class="cc-mod-adopt__label">
                                <strong>{{ $module['active_tenants'] }}</strong> / {{ $tenantTotal }} clients
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <section class="cc-card cc-mods__empty">
            <p>Aucun module ne correspond à ces filtres.</p>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="syncCatalog">Synchroniser le catalogue</button>
        </section>
    @endforelse
</div>
