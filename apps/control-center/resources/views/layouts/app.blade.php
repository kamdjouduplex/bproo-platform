<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }} · Bproo Control Center</title>
        @include('partials.favicon')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/css/control-center.css', 'resources/js/app.js'])
        @livewireStyles
        <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="{{ request()->is('admin*') ? 'cc-body' : '' }}">
        @php
            $tenant = app(App\Services\TenantManager::class)->tenant();
            $storeContext = app(App\Services\StoreContextService::class);
            $tenantUser = $tenant && auth('tenant')->check() ? auth('tenant')->user() : null;
            $moduleLinks = $tenant ? app(App\Services\ModuleManager::class)->navLinksForTenant($tenant, $tenantUser) : [];
            $shopName = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : 'Inov-Com';
            $tenantLogoUrl = $tenant ? app(\App\Services\TenantBrandingService::class)->url($tenant, 'icon') : null;
            if (!$tenantLogoUrl && $tenant) {
                $tenantLogoUrl = app(\App\Services\TenantBrandingService::class)->url($tenant, 'main');
            }
            $isTenantApp = request()->is('app*');
            $hasSales = $tenant && collect($moduleLinks)->contains('key', 'sales');
            $canCreateSale = $tenantUser && method_exists($tenantUser, 'hasPermission') && $tenantUser->hasPermission('sales.create');
            $tenantCode = $tenant?->code ?? request()->query('tenant') ?? session('tenant_code');
            $isCashierView = $tenantUser
                && $tenantUser->roles->contains('name', 'cashier')
                && !$tenantUser->roles->contains('name', 'admin');
            $activeStores = $tenant ? $storeContext->activeStores($tenant) : collect();
            $currentStoreId = $storeContext->currentStoreId();
            $canViewAllStores = $tenantUser && method_exists($tenantUser, 'canViewAllStores') && $tenantUser->canViewAllStores();
            $hasAttendanceModule = $tenant && app(\App\Services\ModuleRegistry::class)->isEnabled('attendance', $tenant);
        @endphp

        @if ($isTenantApp)
        <div class="app-shell {{ $isCashierView ? 'app-shell--cashier' : 'app-shell--sidebar' }}" x-data="{ sidebarOpen: false }">
            <header class="app-header">
                <div class="app-header-inner {{ $tenant ? 'app-header-inner--tenant' : '' }}">
                    @if ($tenant)
                        <div class="app-header-left">
                            @if (!$isCashierView)
                                <button type="button" class="app-sidebar-toggle" aria-label="Menu" @click="sidebarOpen = !sidebarOpen">
                                    <svg class="app-sidebar-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                                </button>
                            @endif
                            <a href="{{ route('tenant.dashboard', ['tenant' => $tenantCode]) }}" class="app-logo-link">
                                @if ($tenantLogoUrl)
                                    <img src="{{ $tenantLogoUrl }}" alt="{{ $shopName }}" class="tenant-logo-img app-logo-img">
                                @else
                                    <div class="app-logo">{{ $shopName }}</div>
                                @endif
                            </a>
                        </div>
                        <div class="app-header-right">
                            <select class="app-select">
                                <option value="fr">FR</option>
                                <option value="en">EN</option>
                            </select>
                            @php
                                try { $tenantAuthCheck = auth('tenant')->check(); } catch (\Throwable $e) { $tenantAuthCheck = false; }
                            @endphp
                            @if($tenantAuthCheck)
                                <span class="app-user">{{ auth('tenant')->user()->name }}</span>
                                <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenantCode]) }}">
                                    @csrf
                                    <button class="btn btn-secondary" type="submit">Déconnexion</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </header>
            @if ($tenant && !$isCashierView)
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
                            usort($items, fn ($a, $b) => (($a['sidebar_order'] ?? 100) <=> ($b['sidebar_order'] ?? 100)) ?: strcmp($a['label'], $b['label']));
                            $orderedGroups[$gk] = $items;
                        }
                    }
                    foreach ($grouped as $gk => $items) {
                        if (!isset($orderedGroups[$gk])) {
                            usort($items, fn ($a, $b) => (($a['sidebar_order'] ?? 100) <=> ($b['sidebar_order'] ?? 100)) ?: strcmp($a['label'], $b['label']));
                            $orderedGroups[$gk] = $items;
                        }
                    }
                @endphp
                <div class="app-body">
                    <aside class="app-sidebar" :class="{ 'app-sidebar--open': sidebarOpen }" @click.outside="sidebarOpen = false">
                        <div class="app-sidebar-inner">
                            <nav class="app-sidebar-nav">
                                <a href="{{ route('tenant.dashboard', ['tenant' => $tenant->code]) }}" class="app-sidebar-link {{ $currentRoute === 'tenant.dashboard' ? 'app-sidebar-link--active' : '' }}" @click="sidebarOpen = false">
                                    <span>Tableau de bord</span>
                                </a>
                                @foreach ($orderedGroups as $groupKey => $links)
                                    <div class="app-sidebar-label">{{ $groupLabels[$groupKey] ?? $groupKey }}</div>
                                    @foreach ($links as $link)
                                        <a href="{{ route($link['route'], ['tenant' => $tenant->code]) }}" class="app-sidebar-link" @click="sidebarOpen = false">{{ $link['label'] }}</a>
                                    @endforeach
                                @endforeach
                            </nav>
                        </div>
                    </aside>
                    <main class="app-main">
                        <div class="app-main-inner">
                            <div class="page-header">
                                <div>
                                    <div class="page-title">{{ $title ?? 'Tableau de bord' }}</div>
                                    <div class="page-subtitle">{{ $subtitle ?? '' }}</div>
                                </div>
                                <div class="page-actions">{{ $actions ?? '' }}</div>
                            </div>
                            <div class="page-body">{{ $slot }}</div>
                        </div>
                    </main>
                </div>
            @else
                <main class="app-main app-main--full">
                    <div class="app-main-inner">
                        <div class="page-header">
                            <div>
                                <div class="page-title">{{ $title ?? 'Tableau de bord' }}</div>
                                <div class="page-subtitle">{{ $subtitle ?? '' }}</div>
                            </div>
                            <div class="page-actions">{{ $actions ?? '' }}</div>
                        </div>
                        <div class="page-body">{{ $slot }}</div>
                    </div>
                </main>
            @endif
        </div>
        @else
        {{-- Bproo Control Center — landlord ops brain only --}}
        <div class="cc-shell" x-data="{ sidebarOpen: false }">
            @include('layouts.partials.cc-shell')
        </div>
        @endif

        @include('notify::components.notify')
        @livewireScripts
    </body>
</html>
