<div class="page-body cc-apps">
    <section class="dashboard-kpis cc-mods__kpis">
        <div class="dashboard-kpi">
            <div class="cc-mods__kpi-top">
                <span class="cc-mod-icon cc-app-icon--erp" aria-hidden="true">
                    <x-sidebar-icon icon="view-columns" />
                </span>
                <div>
                    <div class="dashboard-kpi__label">Applications</div>
                    <div class="dashboard-kpi__value">{{ $kpis['total'] }}</div>
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
                    <div class="dashboard-kpi__meta"><a href="{{ route('system.tenants') }}">Voir tous →</a></div>
                </div>
            </div>
        </div>
        <div class="dashboard-kpi">
            <div class="cc-mods__kpi-top">
                <span class="cc-mod-icon cc-mod-icon--ventes" aria-hidden="true">
                    <x-sidebar-icon icon="clipboard-check" />
                </span>
                <div>
                    <div class="dashboard-kpi__label">Apps utilisées</div>
                    <div class="dashboard-kpi__value">{{ $kpis['with_clients'] }}</div>
                    <div class="dashboard-kpi__meta">sur {{ $kpis['total'] }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="cc-mod-group">
        <header class="cc-mod-group__head">
            <h2 class="cc-mod-group__title">Catalogue applications</h2>
            <span class="cc-mod-group__count">{{ $apps->count() }}</span>
        </header>
        <div class="cc-mod-grid">
            @foreach ($apps as $app)
                <article class="cc-mod-card cc-app-card" wire:key="app-{{ $app['key'] }}">
                    <div class="cc-mod-card__top">
                        <span class="cc-mod-icon cc-app-icon--{{ $app['key'] }}" aria-hidden="true">
                            <x-sidebar-icon :icon="$app['icon']" />
                        </span>
                        <div class="cc-mod-card__titles">
                            <div class="cc-mod-card__name">{{ $app['label'] }}</div>
                            <code class="cc-mod-card__key">{{ $app['key'] }}</code>
                        </div>
                        <div class="cc-mod-card__badges">
                            @if ($app['supports_multi_store'])
                                <span class="badge badge-success">Multi-magasins</span>
                            @else
                                <span class="badge badge-secondary">Mono-site</span>
                            @endif
                        </div>
                    </div>

                    <p class="cc-mod-card__desc">{{ $app['description'] ?: 'Aucune description.' }}</p>

                    <div class="cc-mod-card__meta">
                        <span class="cc-mod-pill">{{ $app['modules'] }} modules</span>
                        @if ($app['base_url'])
                            <span class="cc-mod-pill" title="{{ $app['base_url'] }}">{{ parse_url($app['base_url'], PHP_URL_HOST) ?: $app['base_url'] }}</span>
                        @endif
                    </div>

                    <div class="cc-mod-adopt">
                        <div class="cc-mod-adopt__track" aria-hidden="true">
                            @php
                                $max = max(1, (int) $kpis['tenants']);
                                $pct = (int) round(100 * $app['tenants'] / $max);
                            @endphp
                            <span class="cc-mod-adopt__fill" style="width: {{ $pct }}%"></span>
                        </div>
                        <span class="cc-mod-adopt__label">
                            <strong>{{ $app['tenants'] }}</strong>
                            client{{ $app['tenants'] !== 1 ? 's' : '' }}
                            @if ($app['active'] !== $app['tenants'])
                                · {{ $app['active'] }} actif{{ $app['active'] !== 1 ? 's' : '' }}
                            @endif
                        </span>
                    </div>

                    <div class="cc-app-card__actions">
                        <a class="btn btn-primary btn-sm" href="{{ route('system.tenants', ['product' => $app['key']]) }}">Clients</a>
                        <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.create', ['type' => $app['key']]) }}">Nouveau client</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
