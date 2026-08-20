@php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code');
    $periodQuery = $periodQuery ?? ['tenant' => $tenantCode];
    $pages = [
        'dashboard' => [
            'label' => 'Tableau de bord',
            'route' => 'tenant.pharma-reporting.dashboard',
            'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        ],
        'sales' => [
            'label' => 'Analyse des ventes',
            'route' => 'tenant.pharma-reporting.sales',
            'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        'profitability' => [
            'label' => 'Rentabilité',
            'route' => 'tenant.pharma-reporting.profitability',
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'stock' => [
            'label' => 'Stock',
            'route' => 'tenant.pharma-reporting.stock',
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        ],
        'purchases' => [
            'label' => 'Achats & fournisseurs',
            'route' => 'tenant.pharma-reporting.purchases',
            'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        ],
        'finance' => [
            'label' => 'Finance',
            'route' => 'tenant.pharma-reporting.finance',
            'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        ],
        'alerts' => [
            'label' => 'Alertes',
            'route' => 'tenant.pharma-reporting.alerts',
            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        ],
    ];
@endphp
<nav class="pa-nav" aria-label="Rapport Pharmacie">
    @foreach ($pages as $key => $page)
        <a
            href="{{ route($page['route'], $periodQuery) }}"
            class="pa-nav__item {{ ($activePage ?? '') === $key ? 'is-active' : '' }}"
        >
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $page['icon'] }}"/>
            </svg>
            {{ $page['label'] }}
        </a>
    @endforeach
</nav>
