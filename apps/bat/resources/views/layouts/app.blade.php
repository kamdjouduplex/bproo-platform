<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @notifyCss
        @stack('styles')
        @livewireStyles
    </head>
    <body class="bg-slate-100 text-slate-800 text-[13px] leading-relaxed antialiased">
        @php
            $tenant      = app(App\Services\TenantManager::class)->tenant();
            $moduleLinks = $tenant ? app(App\Services\ModuleManager::class)->navLinksForTenant($tenant) : [];
            $shopName    = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : config('app.name');
            $isAdmin     = request()->is('admin*');
            $currentLocale = app()->getLocale();
        @endphp

        <div class="flex min-h-screen">

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{--  SIDEBAR                                                   --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <aside class="fixed inset-y-0 left-0 z-[100] flex flex-col w-16 md:w-60 bg-[#1e293b] text-slate-400 border-r border-slate-700 transition-[width] duration-200">

                {{-- Brand --}}
                <div class="flex items-center gap-3 px-4 py-[18px] border-b border-slate-700 min-h-[57px]">
                    {{-- Icon always visible --}}
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center flex-shrink-0 shadow-md">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    {{-- Name only on wide sidebar --}}
                    <div
                        class="hidden md:block text-[15px] font-bold tracking-wide text-slate-50 leading-tight truncate"
                        x-data="{ logoText: '{{ addslashes($isAdmin ? config('app.name') : $shopName) }}' }"
                        x-text="logoText"
                        @branding-updated.window="logoText = $event.detail.shopName"
                    ></div>
                </div>

                @php
                    /* ── TENANT NAV ─────────────────────────────────────── */
                    $tenantNavGroups = [
                        'commercial'  => ['label' => 'Commercial',           'icon' => 'folder'],
                        'operations'  => ['label' => 'Opérations & Services', 'icon' => 'tool'],
                        'finance'     => ['label' => 'Finance',              'icon' => 'chart'],
                        'resources'   => ['label' => 'Ressources',           'icon' => 'document'],
                        'rh'          => ['label' => 'RH & Paie',            'icon' => 'users'],
                        'analytics'   => ['label' => 'Analyse',              'icon' => 'chart'],
                        'system'      => ['label' => 'Administration',       'icon' => 'cog'],
                        'ungrouped'   => ['label' => 'Autres',               'icon' => 'folder'],
                    ];

                    $operationsSubgroups = [
                        'services_hub'    => null,
                        'services_exec'   => __('Exécution — Services'),
                        'services_pilot'  => __('Pilotage & terrain'),
                        'services_supply' => __('Moyens & logistique'),
                    ];

                    $tenantUser = $tenant ? auth('tenant')->user() : null;

                    $staticCommercial = [];
                    if ($tenantUser?->can('clients.view') && \Illuminate\Support\Facades\Route::has('tenant.clients.compte')) {
                        $staticCommercial[] = ['label' => __('Compte Client'), 'route' => 'tenant.clients.compte', 'icon' => 'account', 'permission' => 'clients.view', 'group' => 'commercial', 'menu_order' => 11];
                    }

                    $staticOperations = [];
                    if ($tenantUser && ($tenantUser->can('projets.view') || $tenantUser->can('maintenance.view'))
                        && \Illuminate\Support\Facades\Route::has('tenant.services.index')) {
                        $staticOperations[] = [
                            'label' => __('Centre des services'),
                            'route' => 'tenant.services.index',
                            'route_pattern' => 'tenant.services*',
                            'icon' => 'tool',
                            'permission_any' => ['projets.view', 'maintenance.view'],
                            'group' => 'operations',
                            'subgroup' => 'services_hub',
                            'menu_order' => 36,
                        ];
                    }

                    $staticAnalytics = [];
                    if ($tenantUser?->can('reports.view')) {
                        $staticAnalytics[] = ['label' => __('Rapports'), 'route' => 'tenant.reports', 'icon' => 'chart',  'permission' => 'reports.view', 'group' => 'analytics', 'menu_order' => 80];
                    }
                    if ($tenantUser?->can('audit.view')) {
                        $staticAnalytics[] = ['label' => __('Audit'),    'route' => 'tenant.audit',   'icon' => 'shield', 'permission' => 'audit.view',   'group' => 'analytics', 'menu_order' => 81];
                    }

                    $allLinks     = array_merge($moduleLinks, $staticCommercial, $staticOperations, $staticAnalytics);
                    usort($allLinks, fn($a, $b) => ($a['menu_order'] ?? 999) <=> ($b['menu_order'] ?? 999));

                    $visibleLinks = array_filter(
                        $allLinks,
                        function ($link) use ($tenantUser) {
                            if (!$tenantUser) {
                                return false;
                            }
                            if (!empty($link['permission_any'])) {
                                return collect($link['permission_any'])->contains(fn ($p) => $tenantUser->can($p));
                            }
                            return $tenantUser->can($link['permission'] ?? '');
                        }
                    );

                    $groupedLinks = [];
                    foreach ($visibleLinks as $link) {
                        $groupedLinks[$link['group'] ?? 'ungrouped'][] = $link;
                    }

                    $activeGroup = null;
                    foreach ($groupedLinks as $gKey => $gLinks) {
                        foreach ($gLinks as $gLink) {
                            $pattern = $gLink['route_pattern'] ?? $gLink['route'];
                            if (request()->routeIs($pattern)) {
                                $activeGroup = $gKey;
                                break 2;
                            }
                        }
                    }

                    /* ── ADMIN NAV ──────────────────────────────────────── */
                    $adminNavGroups = [
                        [
                            'label' => 'Gestion',
                            'key'   => 'admin_management',
                            'links' => [
                                ['route' => 'system.tenants',       'pattern' => 'system.tenants*', 'exclude' => ['system.packages','system.tenants.health'], 'icon' => 'building', 'label' => 'Entreprises'],
                                ['route' => 'system.packages',      'pattern' => 'system.packages', 'icon' => 'cube',   'label' => 'Packages'],
                                ['route' => 'system.modules',       'pattern' => 'system.modules*', 'icon' => 'puzzle', 'label' => 'Modules'],
                            ],
                        ],
                        [
                            'label' => 'Supervision',
                            'key'   => 'admin_supervision',
                            'links' => [
                                ['route' => 'system.tenants.health', 'pattern' => 'system.tenants.health',  'icon' => 'heart',    'label' => 'Santé'],
                                ['route' => 'system.module.events',  'pattern' => 'system.module.events*',  'icon' => 'calendar', 'label' => 'Événements'],
                            ],
                        ],
                        [
                            'label' => 'Analyse',
                            'key'   => 'admin_analytics',
                            'links' => [
                                ['route' => 'system.analytics', 'pattern' => 'system.analytics', 'icon' => 'chart', 'label' => 'Analytique'],
                            ],
                        ],
                    ];

                    $activeAdminGroup = null;
                    foreach ($adminNavGroups as $ag) {
                        foreach ($ag['links'] as $al) {
                            if (request()->routeIs($al['pattern'])) {
                                $activeAdminGroup = $ag['key'];
                                break 2;
                            }
                        }
                    }
                @endphp

                {{-- ── Nav ───────────────────────────────────────────── --}}
                <nav class="flex-1 overflow-y-auto py-2 scrollbar-hidden">

                    @if ($isAdmin)

                        {{-- Dashboard --}}
                        <a href="{{ route('system.dashboard') }}"
                           class="flex items-center gap-3 px-4 py-2.5 text-[13px] transition-colors duration-150 {{ request()->routeIs('system.dashboard') ? 'bg-slate-700 text-slate-50 border-l-[3px] border-blue-500 pl-[13px]' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-100' }}">
                            <x-sidebar-icon name="dashboard" />
                            <span class="hidden md:block">{{ __('Dashboard') }}</span>
                        </a>

                        @foreach ($adminNavGroups as $ag)
                            @php $agActive = ($activeAdminGroup === $ag['key']); @endphp
                            <div x-data="{ open: {{ $agActive ? 'true' : 'false' }} }" class="mt-1">
                                <button type="button"
                                        class="hidden md:flex items-center w-full px-3.5 py-[7px] bg-transparent border-none border-l-[3px] border-transparent cursor-pointer text-left gap-2 text-slate-500 hover:text-slate-400 transition-colors duration-150"
                                        @click="open = !open"
                                        :aria-expanded="open">
                                    <span class="flex-1 text-[10px] font-bold tracking-widest uppercase text-inherit whitespace-nowrap overflow-hidden">{{ $ag['label'] }}</span>
                                    <svg class="shrink-0 text-slate-600 transition-transform duration-200" :class="{ 'rotate-180': open }"
                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div class="overflow-hidden transition-all duration-200" :style="open ? 'max-height:400px' : 'max-height:0'" style="max-height: {{ $agActive ? '400px' : '0px' }}">
                                    @foreach ($ag['links'] as $al)
                                        @php
                                            $exclude  = $al['exclude'] ?? [];
                                            $isActive = request()->routeIs($al['pattern']) && !request()->routeIs($exclude);
                                        @endphp
                                        <a href="{{ route($al['route']) }}"
                                           class="flex items-center gap-3 px-4 py-2.5 text-[13px] transition-colors duration-150 {{ $isActive ? 'bg-slate-700 text-slate-50 border-l-[3px] border-blue-500 pl-[13px]' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-100' }}">
                                            <x-sidebar-icon :name="$al['icon']" />
                                            <span class="hidden md:block">{{ $al['label'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                    @else

                        {{-- Dashboard --}}
                        <a href="{{ route('tenant.dashboard', ['tenant' => $tenant->code]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 text-[13px] transition-colors duration-150 {{ request()->routeIs('tenant.dashboard') ? 'bg-slate-700 text-slate-50 border-l-[3px] border-blue-500 pl-[13px]' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-100' }}">
                            <x-sidebar-icon name="dashboard" />
                            <span class="hidden md:block">{{ __('Dashboard') }}</span>
                        </a>

                        @foreach ($tenantNavGroups as $groupKey => $groupMeta)
                            @if (!empty($groupedLinks[$groupKey]))
                                @php
                                    $isGroupActive = ($activeGroup === $groupKey);
                                    $isDefaultOpen = ($activeGroup === null && in_array($groupKey, ['commercial', 'operations'], true));
                                    $shouldBeOpen  = $isGroupActive || $isDefaultOpen;
                                @endphp
                                <div x-data="{ open: {{ $shouldBeOpen ? 'true' : 'false' }} }" class="mt-1">
                                    <button type="button"
                                            class="hidden md:flex items-center w-full px-3.5 py-[7px] bg-transparent border-none border-l-[3px] border-transparent cursor-pointer text-left gap-2 text-slate-500 hover:text-slate-400 transition-colors duration-150"
                                            @click="open = !open"
                                            :aria-expanded="open">
                                        <span class="flex-1 text-[10px] font-bold tracking-widest uppercase text-inherit whitespace-nowrap overflow-hidden">{{ $groupMeta['label'] }}</span>
                                        <svg class="shrink-0 text-slate-600 transition-transform duration-200" :class="{ 'rotate-180': open }"
                                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    {{-- On mobile (icon-only) always show links --}}
                                    <div class="overflow-hidden transition-all duration-200 md:overflow-hidden"
                                         :style="open ? 'max-height:800px' : 'max-height:0'"
                                         style="max-height: {{ $shouldBeOpen ? '800px' : '0px' }}">
                                        @php $lastSubgroup = null; @endphp
                                        @foreach ($groupedLinks[$groupKey] as $link)
                                            @php
                                                $subgroup = $link['subgroup'] ?? null;
                                                $subgroupLabel = $subgroup ? ($operationsSubgroups[$subgroup] ?? null) : null;
                                                $linkPattern = $link['route_pattern'] ?? $link['route'];
                                            @endphp
                                            @if($subgroupLabel && $subgroup !== $lastSubgroup)
                                                <div class="hidden md:block px-4 pt-2.5 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-600">
                                                    {{ $subgroupLabel }}
                                                </div>
                                                @php $lastSubgroup = $subgroup; @endphp
                                            @elseif(!$subgroupLabel && $subgroup && $subgroup !== $lastSubgroup)
                                                @php $lastSubgroup = $subgroup; @endphp
                                            @endif
                                            <a href="{{ route($link['route'], ['tenant' => $tenant->code]) }}"
                                               class="flex items-center gap-3 px-4 py-2.5 text-[13px] transition-colors duration-150 {{ request()->routeIs($linkPattern) ? 'bg-slate-700 text-slate-50 border-l-[3px] border-blue-500 pl-[13px]' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-100' }}">
                                                <x-sidebar-icon :name="$link['icon'] ?? 'folder'" />
                                                <span class="hidden md:block">{{ $link['label'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach

                    @endif
                </nav>

                {{-- ── Footer ─────────────────────────────────────────── --}}
                <div class="px-4 py-3 border-t border-slate-700 text-xs text-slate-400">
                    @if ($tenant)
                        @php try { $tenantAuthCheck = auth('tenant')->check(); } catch (\Throwable $e) { $tenantAuthCheck = false; } @endphp
                        @if ($tenantAuthCheck)
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="hidden md:block flex-1 min-w-0 truncate text-slate-400">{{ auth('tenant')->user()->name }}</span>
                                <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenant->code ?? request()->query('tenant') ?? session('tenant_code')]) }}" class="shrink-0">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-8 h-8 border border-slate-600 bg-transparent text-slate-400 cursor-pointer transition-colors duration-150 hover:bg-slate-700 hover:text-slate-100 hover:border-slate-500 rounded"
                                            title="{{ __('Déconnexion') }}" aria-label="{{ __('Déconnexion') }}">
                                        <x-sidebar-icon name="logout" />
                                    </button>
                                </form>
                            </div>
                        @else
                            <span class="hidden md:block truncate">{{ config('app.name') }}</span>
                        @endif
                    @else
                        @if (auth()->check())
                            <div class="flex items-center gap-2.5">
                                <span class="hidden md:block flex-1 min-w-0 truncate text-slate-400">{{ auth()->user()->name }}</span>
                                <form method="POST" action="{{ route('system.logout') }}" class="shrink-0">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-8 h-8 border border-slate-600 bg-transparent text-slate-400 cursor-pointer transition-colors duration-150 hover:bg-slate-700 hover:text-slate-100 hover:border-slate-500 rounded"
                                            title="{{ __('Déconnexion') }}">
                                        <x-sidebar-icon name="logout" />
                                    </button>
                                </form>
                            </div>
                        @else
                            <span class="hidden md:block truncate">{{ config('app.name') }}</span>
                        @endif
                    @endif
                </div>
            </aside>

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{--  MAIN AREA                                                  --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <main class="flex-1 ml-16 md:ml-60 min-w-0 flex flex-col bg-slate-100">

                {{-- Topbar --}}
                <header class="flex items-center justify-between gap-4 flex-wrap min-h-[52px] px-5 bg-white border-b border-slate-200 sticky top-0 z-10 shadow-sm">
                    <div class="flex items-center gap-2 text-[13px] text-slate-500">
                        <span class="font-semibold text-slate-800">{{ $title ?? __('Tableau de bord') }}</span>
                        @if (!empty($subtitle))
                            <span class="text-slate-300">/</span>
                            <span class="text-slate-500">{{ $subtitle }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 text-xs">
                        @if (!$isAdmin && isset($tenant) && $tenant && auth('tenant')->check())
                            <livewire:tenant.notification-bell />
                        @endif

                        {{-- Language switcher --}}
                        <details class="relative" x-data @click.outside="$el.removeAttribute('open')">
                            <summary class="flex items-center gap-2 px-3 py-1.5 border border-slate-200 bg-white text-slate-500 text-[13px] cursor-pointer rounded select-none list-none hover:bg-slate-50 hover:border-slate-300 hover:text-slate-800 transition-colors">
                                <span>{{ $currentLocale === 'fr' ? '🇫🇷' : '🇬🇧' }}</span>
                                <span class="font-medium">{{ $currentLocale === 'fr' ? 'Français' : 'English' }}</span>
                                <svg class="shrink-0 opacity-60 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </summary>
                            <div class="absolute top-full right-0 mt-1 min-w-[160px] bg-white border border-slate-200 shadow-lg rounded z-50">
                                <a href="{{ route('locale.switch', ['locale' => 'fr']) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2.5 text-[13px] text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors {{ $currentLocale === 'fr' ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                                    <span>🇫🇷</span><span>Français</span>
                                </a>
                                <a href="{{ route('locale.switch', ['locale' => 'en']) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2.5 text-[13px] text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors {{ $currentLocale === 'en' ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                                    <span>🇬🇧</span><span>English</span>
                                </a>
                            </div>
                        </details>
                    </div>
                </header>

                {{-- Page content --}}
                <div class="flex-1 p-5 sm:p-6">
                    @if (!empty($actions))
                        <div class="flex justify-end gap-2 mb-4">
                            {{ $actions }}
                        </div>
                    @endif
                    {{ $slot }}
                </div>
            </main>
        </div>

        @include('notify::components.notify')
        @notifyJs
        @livewireScripts
    </body>
</html>
