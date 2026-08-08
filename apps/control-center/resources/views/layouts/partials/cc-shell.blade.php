{{-- Control Center ops shell (product apps keep their own layouts/app). --}}
@php
    $routeName = request()->route()?->getName() ?? '';
    $user = auth()->user();

    $navActive = function (string ...$names) use ($routeName): string {
        foreach ($names as $name) {
            if ($routeName === $name || str_starts_with($routeName, $name . '.')) {
                return 'cc-nav__link--active';
            }
            if (str_starts_with($routeName, $name)) {
                return 'cc-nav__link--active';
            }
        }
        return '';
    };

    $companiesActive = str_starts_with($routeName, 'system.tenants')
        && $routeName !== 'system.tenants.health'
        && ! str_starts_with($routeName, 'system.tenant.modules');
@endphp

<aside class="cc-sidebar" :class="{ 'cc-sidebar--open': sidebarOpen }">
    <div class="cc-sidebar__brand">
        <a href="{{ route('system.dashboard') }}" class="cc-sidebar__brand-link" @click="sidebarOpen = false">
            <div class="cc-sidebar__mark" aria-hidden="true">B</div>
            <div>
                <div class="cc-sidebar__name">BPROO</div>
                <div class="cc-sidebar__tag">CONTROL CENTER</div>
            </div>
        </a>
    </div>

    <nav class="cc-sidebar__nav" aria-label="Navigation Control Center">
        <div class="cc-sidebar__group">
            <div class="cc-sidebar__label">Vue d'ensemble</div>
            <a href="{{ route('system.dashboard') }}" class="cc-nav__link {{ $navActive('system.dashboard') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z"/></svg>
                </span>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="cc-sidebar__group">
            <div class="cc-sidebar__label">Relation client</div>
            <a href="{{ route('system.prospects') }}" class="cc-nav__link {{ $navActive('system.prospects') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <span>Prospects</span>
            </a>
            <a href="{{ route('system.opportunities') }}" class="cc-nav__link {{ $navActive('system.opportunities') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 14v4M12 10v8M17 6v12"/></svg>
                </span>
                <span>Opportunités</span>
            </a>
            <a href="{{ route('system.tenants') }}" class="cc-nav__link {{ $companiesActive ? 'cc-nav__link--active' : '' }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M6 21V5a2 2 0 0 1 2-2h3v18M13 21V9a2 2 0 0 1 2-2h3v14"/></svg>
                </span>
                <span>Clients</span>
            </a>
            <a href="{{ route('system.activities') }}" class="cc-nav__link {{ $navActive('system.activities') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </span>
                <span>Activités</span>
            </a>
        </div>

        <div class="cc-sidebar__group">
            <div class="cc-sidebar__label">Organisation</div>
            <a href="{{ route('system.users') }}" class="cc-nav__link {{ $navActive('system.users') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <span>Utilisateurs</span>
            </a>
            <a href="{{ route('system.roles') }}" class="cc-nav__link {{ $navActive('system.roles') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
                </span>
                <span>Rôles & permissions</span>
            </a>
        </div>

        <div class="cc-sidebar__group">
            <div class="cc-sidebar__label">Facturation</div>
            <a href="{{ route('system.invoices') }}" class="cc-nav__link {{ $navActive('system.invoices', 'system.billing') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M8 13h8M8 17h5"/></svg>
                </span>
                <span>Factures</span>
            </a>
            <a href="{{ route('system.payments') }}" class="cc-nav__link {{ $navActive('system.payments') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path stroke-linecap="round" d="M2 10h20"/></svg>
                </span>
                <span>Encaissements</span>
            </a>
            <a href="{{ route('system.plans') }}" class="cc-nav__link {{ $navActive('system.plans') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10"/><path stroke-linecap="round" d="M16 17h4"/></svg>
                </span>
                <span>Plans</span>
            </a>
        </div>

        <div class="cc-sidebar__group">
            <div class="cc-sidebar__label">Plateforme</div>
            <a href="{{ route('system.modules') }}" class="cc-nav__link {{ $navActive('system.modules') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12 4 7.5M12 12l8-4.5M12 12v9"/></svg>
                </span>
                <span>Modules</span>
            </a>
            <a href="{{ route('system.tenant.modules') }}" class="cc-nav__link {{ $navActive('system.tenant.modules') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <span>Activation</span>
            </a>
            <a href="{{ route('system.tenants.health') }}" class="cc-nav__link {{ $routeName === 'system.tenants.health' ? 'cc-nav__link--active' : '' }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </span>
                <span>Santé</span>
            </a>
            <a href="{{ route('system.module.events') }}" class="cc-nav__link {{ $navActive('system.module.events') }}" @click="sidebarOpen = false">
                <span class="cc-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M8 13h8M8 17h8"/></svg>
                </span>
                <span>Événements</span>
            </a>
        </div>
    </nav>
</aside>

<div class="cc-frame">
    <header class="cc-topbar">
        <div class="cc-topbar__left">
            <button type="button" class="cc-icon-btn cc-topbar__menu" aria-label="Menu" @click="sidebarOpen = !sidebarOpen">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
        <div class="cc-topbar__right">
            <select class="cc-select" aria-label="Langue">
                <option value="fr">FR</option>
                <option value="en">EN</option>
            </select>
            <button type="button" class="cc-icon-btn cc-topbar__bell" aria-label="Notifications">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
            </button>
            @if(auth()->check())
                <div class="cc-user">
                    <div class="cc-user__meta">
                        <div class="cc-user__name">{{ $user->name }}</div>
                        <div class="cc-user__role">Super Admin</div>
                    </div>
                    <div class="cc-user__avatar" aria-hidden="true">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    <form method="POST" action="{{ route('system.logout') }}">
                        @csrf
                        <button class="cc-icon-btn" type="submit" title="Déconnexion" aria-label="Déconnexion">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </header>

    <main class="cc-content">
        <div class="cc-page-header">
            <div>
                <div class="cc-page-title-row">
                    <h1 class="cc-page-title">{{ $title ?? 'Control Center' }}</h1>
                    @if (!empty($badge))
                        <span class="cc-badge">{{ $badge }}</span>
                    @endif
                </div>
                @if (!empty($subtitle))
                    <p class="cc-page-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            <div class="cc-page-actions">{{ $actions ?? '' }}</div>
        </div>
        <div class="cc-page-body">
            {{ $slot }}
        </div>
    </main>
</div>
<div class="cc-sidebar-backdrop" @click="sidebarOpen = false" x-show="sidebarOpen" x-cloak></div>
