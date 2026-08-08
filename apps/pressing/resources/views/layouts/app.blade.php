<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @include('partials.favicon')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @notifyCss
        @livewireStyles
    </head>
    <body>
        @php
            $tenant = app(App\Services\TenantManager::class)->tenant();
            $storeContext = app(App\Services\StoreContextService::class);
            $tenantUser = $tenant && auth('tenant')->check() ? auth('tenant')->user() : null;
            $moduleLinks = $tenant ? app(App\Services\ModuleManager::class)->navLinksForTenant($tenant, $tenantUser) : [];
            $shopName = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : config('app.name', 'Pressing');
            $tenantLogoUrl = $tenant ? app(\App\Services\TenantBrandingService::class)->url($tenant, 'icon') : null;
            if (!$tenantLogoUrl && $tenant) {
                $tenantLogoUrl = app(\App\Services\TenantBrandingService::class)->url($tenant, 'main');
            }
            $isTenantApp = request()->is('app*');
            $hasSales = $tenant && collect($moduleLinks)->contains('key', 'sales');
            $canCreateSale = $tenantUser && method_exists($tenantUser, 'hasPermission') && $tenantUser->hasPermission('sales.create');
            $tenantCode = $tenant?->code ?? request()->query('tenant') ?? session('tenant_code');
            // Always use the white sidebar for tenant apps (pressing & retail).
            // Links stay filtered by role permissions via ModuleManager::navLinksForTenant.
            $activeStores = $tenant ? $storeContext->activeStores($tenant) : collect();
            $currentStoreId = $storeContext->currentStoreId();
            $canViewAllStores = $tenantUser && method_exists($tenantUser, 'canViewAllStores') && $tenantUser->canViewAllStores();
            $hasAttendanceModule = $tenant && app(\App\Services\ModuleRegistry::class)->isEnabled('attendance', $tenant);
        @endphp
        <div class="app-shell {{ $isTenantApp ? 'app-shell--sidebar' : '' }}" @if($isTenantApp) x-data="{ sidebarOpen: false }" @endif>
            <header class="app-header">
                <div class="app-header-inner {{ $isTenantApp && $tenant ? 'app-header-inner--tenant' : '' }}">
                    @if ($isTenantApp && $tenant)
                        <div class="app-header-left">
                            <button type="button" class="app-sidebar-toggle" aria-label="Menu" @click="sidebarOpen = !sidebarOpen">
                                <svg class="app-sidebar-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </button>
                            <a href="{{ route('tenant.dashboard', ['tenant' => $tenantCode]) }}" class="app-logo-link">
                                @if ($tenantLogoUrl)
                                    <img src="{{ $tenantLogoUrl }}" alt="{{ $shopName }}" class="tenant-logo-img app-logo-img" @configuration-updated.window="location.reload()">
                                @else
                                    <div class="app-logo" x-data="{ logoText: '{{ $shopName }}' }" x-text="logoText" @configuration-updated.window="logoText = $event.detail.shopName"></div>
                                @endif
                            </a>
                        </div>
                        <div class="app-header-right">
                            @php
                                $isPressingTenant = $tenant && (
                                    ($tenant->type ?? null) === 'pressing'
                                    || collect($moduleLinks)->contains('key', 'pressing_orders')
                                );
                            @endphp
                            {{-- Pressing uses multi-agences, not multi-stores — hide store selector. --}}
                            @if ($tenant && ! $isPressingTenant && $tenant->multi_store_enabled && $activeStores->count() > 0)
                                <form method="GET" action="{{ url()->current() }}">
                                    @if ($tenantCode)
                                        <input type="hidden" name="tenant" value="{{ $tenantCode }}">
                                    @endif
                                    <select class="app-select" name="store" onchange="this.form.submit()">
                                        @if ($canViewAllStores)
                                            <option value="all" {{ $currentStoreId === null ? 'selected' : '' }}>{{ __('Toutes les boutiques') }}</option>
                                        @endif
                                        @foreach ($activeStores as $store)
                                            <option value="{{ $store->id }}" {{ (int) $currentStoreId === (int) $store->id ? 'selected' : '' }}>
                                                {{ $store->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                            @if ($hasSales && $canCreateSale && Route::has('tenant.sales.create'))
                                <a href="{{ route('tenant.sales.create', ['tenant' => $tenantCode]) }}" class="btn btn-primary app-nav-new-sale">{{ __('Nouvelle vente') }}</a>
                            @endif
                            <form method="POST" action="{{ route('locale.update', array_filter(['tenant' => $tenantCode])) }}" class="app-locale-form">
                                @csrf
                                <select class="app-select" name="locale" onchange="this.form.submit()" aria-label="{{ __('Langue') }}">
                                    <option value="fr" @selected(app()->getLocale() === 'fr')>FR</option>
                                    <option value="en" @selected(app()->getLocale() === 'en')>EN</option>
                                </select>
                            </form>
                            @php
                                try { $tenantAuthCheck = auth('tenant')->check(); } catch (\Throwable $e) { $tenantAuthCheck = false; }
                            @endphp
                            @if($tenantAuthCheck)
                                @if ($hasAttendanceModule && class_exists(\InovCom\Attendance\Http\Livewire\AttendancePunchWidget::class))
                                    <livewire:inovcom-attendance.punch-widget wire:key="header-attendance-punch" />
                                @endif
                                <livewire:tenant.notification-bell />
                                <span class="app-user">
                                    <a href="{{ route('tenant.account.profile', ['tenant' => $tenantCode]) }}" style="color:inherit;text-decoration:none;font-weight:600;">
                                        {{ auth('tenant')->user()->name }}
                                    </a>
                                </span>
                                <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenantCode]) }}">
                                    @csrf
                                    <button class="btn btn-secondary" type="submit">{{ __('Déconnexion') }}</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <div class="app-brand">
                            <a href="{{ route('system.dashboard') }}" class="app-logo-link">
                                <div class="app-logo">{{ config('app.name', 'Pressing') }}</div>
                            </a>
                            <nav class="app-nav">
                                <a href="{{ route('system.dashboard') }}">{{ __('Admin') }}</a>
                                <a href="{{ route('system.tenants') }}">{{ __('Vendeurs') }}</a>
                                <a href="{{ route('system.plans') }}">{{ __('Plans') }}</a>
                                <a href="{{ route('system.packages') }}">{{ __('Packages') }}</a>
                                <a href="{{ route('system.tenants.health') }}">{{ __('Santé') }}</a>
                                <a href="{{ route('system.module.events') }}">{{ __('Events') }}</a>
                            </nav>
                        </div>
                        <div class="app-actions">
                            <form method="POST" action="{{ route('locale.update') }}" class="app-locale-form">
                                @csrf
                                <select class="app-select" name="locale" onchange="this.form.submit()" aria-label="{{ __('Langue') }}">
                                    <option value="fr" @selected(app()->getLocale() === 'fr')>FR</option>
                                    <option value="en" @selected(app()->getLocale() === 'en')>EN</option>
                                </select>
                            </form>
                            @if ($tenant)
                                @php
                                    try { $tenantAuthCheck = auth('tenant')->check(); } catch (\Throwable $e) { $tenantAuthCheck = false; }
                                @endphp
                                @if($tenantAuthCheck)
                                    <span class="app-user">
                                    <a href="{{ route('tenant.account.profile', ['tenant' => $tenantCode]) }}" style="color:inherit;text-decoration:none;font-weight:600;">
                                        {{ auth('tenant')->user()->name }}
                                    </a>
                                </span>
                                    <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenantCode]) }}">
                                        @csrf
                                    <button class="btn btn-secondary" type="submit">{{ __('Déconnexion') }}</button>
                                </form>
                            @endif
                        @else
                            @if(auth()->check())
                                <span class="app-user">{{ auth()->user()->name }}</span>
                                <form method="POST" action="{{ route('system.logout') }}">
                                    @csrf
                                    <button class="btn btn-secondary" type="submit">{{ __('Déconnexion') }}</button>
                                </form>
                            @else
                                <span class="app-user">{{ __('Super Admin') }}</span>
                            @endif
                        @endif
                        </div>
                    @endif
                </div>
            </header>

            @if ($isTenantApp && $tenant)
                @php
                    $currentRoute = request()->route()?->getName() ?? '';
                    $groupOrder = array_keys(config('modules.sidebar_groups', []));
                    $groupLabels = config('modules.sidebar_groups', []);
                    $grouped = [];
                    foreach ($moduleLinks as $link) {
                        $g = $link['group'] ?? 'system';
                        if (!isset($grouped[$g])) $grouped[$g] = [];
                        $grouped[$g][] = $link;
                    }
                    $orderedGroups = [];
                    foreach ($groupOrder as $gk) {
                        if (!empty($grouped[$gk])) {
                            $items = $grouped[$gk];
                            usort($items, function ($a, $b) {
                                $order = ($a['sidebar_order'] ?? 100) <=> ($b['sidebar_order'] ?? 100);
                                return $order !== 0 ? $order : strcmp($a['label'], $b['label']);
                            });
                            $orderedGroups[$gk] = $items;
                        }
                    }
                    foreach ($grouped as $gk => $items) {
                        if (!isset($orderedGroups[$gk])) {
                            usort($items, function ($a, $b) {
                                $order = ($a['sidebar_order'] ?? 100) <=> ($b['sidebar_order'] ?? 100);
                                return $order !== 0 ? $order : strcmp($a['label'], $b['label']);
                            });
                            $orderedGroups[$gk] = $items;
                        }
                    }

                    // Abonnement : visible uniquement pour les admins du tenant
                    if (
                        \Illuminate\Support\Facades\Route::has('tenant.subscription')
                        && $tenantUser
                        && method_exists($tenantUser, 'isAdmin')
                        && $tenantUser->isAdmin()
                    ) {
                        if (!isset($orderedGroups['system'])) {
                            $orderedGroups['system'] = [];
                        }
                        array_unshift($orderedGroups['system'], [
                            'key' => 'subscription',
                            'label' => 'Abonnement',
                            'route' => 'tenant.subscription',
                            'icon' => 'wallet',
                        ]);
                    }
                @endphp
                <div class="app-body">
                    <aside class="app-sidebar" :class="{ 'app-sidebar--open': sidebarOpen }" @click.outside="sidebarOpen = false">
                        <div class="app-sidebar-inner">
                            <nav class="app-sidebar-nav">
                                <a href="{{ route('tenant.dashboard', ['tenant' => $tenant->code]) }}" class="app-sidebar-link {{ $currentRoute === 'tenant.dashboard' ? 'app-sidebar-link--active' : '' }}" @click="sidebarOpen = false">
                                    <x-sidebar-icon icon="dashboard" class="app-sidebar-link-icon" />
                                    <span>{{ __('Tableau de bord') }}</span>
                                </a>
                                @foreach ($orderedGroups as $groupKey => $links)
                                    <div class="app-sidebar-label">{{ __($groupLabels[$groupKey] ?? $groupKey) }}</div>
                                    @foreach ($links as $link)
                                        @php
                                            $children = $link['children'] ?? [];
                                            $routeBase = str_replace('.index', '.', (string) ($link['route'] ?? ''));
                                            $childActive = false;
                                            foreach ($children as $child) {
                                                $childBase = str_replace('.index', '.', $child['route']);
                                                if ($currentRoute === $child['route'] || str_starts_with($currentRoute, $childBase)) {
                                                    $childActive = true;
                                                    break;
                                                }
                                            }
                                            $isActive = (($link['route'] ?? null) !== null && (
                                                    $currentRoute === $link['route']
                                                    || ($link['route'] !== 'tenant.subscription' && $routeBase !== '' && str_starts_with($currentRoute, $routeBase))
                                                ))
                                                || $childActive;
                                        @endphp
                                        @if (!empty($children))
                                            <div class="app-sidebar-group" x-data="{ open: {{ $childActive || $isActive ? 'true' : 'false' }} }">
                                                <button type="button" class="app-sidebar-link app-sidebar-link--parent {{ $isActive ? 'app-sidebar-link--active' : '' }}" @click="open = !open">
                                                    <x-sidebar-icon :icon="$link['icon'] ?? 'cog'" class="app-sidebar-link-icon" />
                                                    <span>{{ __($link['label']) }}</span>
                                                    <svg class="app-sidebar-caret" :class="{ 'app-sidebar-caret--open': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                                                </button>
                                                <div class="app-sidebar-sub" x-show="open" x-cloak>
                                                    @foreach ($children as $child)
                                                        @php
                                                            $childBase = str_replace('.index', '.', $child['route']);
                                                            $childIsActive = $currentRoute === $child['route'] || str_starts_with($currentRoute, $childBase);
                                                        @endphp
                                                        <a href="{{ route($child['route'], ['tenant' => $tenant->code]) }}" class="app-sidebar-link app-sidebar-link--child {{ $childIsActive ? 'app-sidebar-link--active' : '' }}" @click="sidebarOpen = false">
                                                            <x-sidebar-icon :icon="$child['icon'] ?? 'cog'" class="app-sidebar-link-icon" />
                                                            <span>{{ __($child['label']) }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            @continue(empty($link['route']) || ! Route::has($link['route']))
                                            <a href="{{ route($link['route'], ['tenant' => $tenant->code]) }}" class="app-sidebar-link {{ $isActive ? 'app-sidebar-link--active' : '' }}" @click="sidebarOpen = false">
                                                <x-sidebar-icon :icon="$link['icon'] ?? 'cog'" class="app-sidebar-link-icon" />
                                                <span>{{ __($link['label']) }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                @endforeach
                            </nav>
                        </div>
                        <div class="app-sidebar-backdrop" @click="sidebarOpen = false" aria-hidden="true"></div>
                    </aside>
                    <main class="app-main">
                        <div class="app-main-inner">
                            <div class="page-header">
                                <div>
                                    <div class="page-title">{{ is_string($title ?? null) ? $title : 'Tableau de bord' }}</div>
                                    <div class="page-subtitle">{{ is_string($subtitle ?? null) ? $subtitle : '' }}</div>
                                </div>
                                <div class="page-actions">{{ $actions ?? '' }}</div>
                            </div>
                            <div class="page-body">{{ $slot }}</div>
                        </div>
                    </main>
                </div>
            @elseif ($isTenantApp)
                <main class="app-main">
                    <div class="app-main-inner">
                        <div class="page-header">
                            <div>
                                <div class="page-title">{{ is_string($title ?? null) ? $title : 'Tableau de bord' }}</div>
                                <div class="page-subtitle">{{ is_string($subtitle ?? null) ? $subtitle : '' }}</div>
                            </div>
                            <div class="page-actions">{{ $actions ?? '' }}</div>
                        </div>
                        <div class="page-body">{{ $slot }}</div>
                    </div>
                </main>
            @else
                <main class="app-content">
                    <div class="page-header">
                        <div>
                            <div class="page-title">{{ is_string($title ?? null) ? $title : 'Tableau de bord' }}</div>
                            <div class="page-subtitle">{{ is_string($subtitle ?? null) ? $subtitle : '' }}</div>
                        </div>
                        <div class="page-actions">{{ $actions ?? '' }}</div>
                    </div>
                    <div class="page-body">{{ $slot }}</div>
                </main>
            @endif
        </div>
        @include('notify::components.notify')
        @notifyJs
        @livewireScripts
    </body>
</html>

