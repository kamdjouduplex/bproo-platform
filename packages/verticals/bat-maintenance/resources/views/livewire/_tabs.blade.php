@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $onContracts = request()->routeIs('tenant.maintenance.contracts*');
    $onOrders    = request()->routeIs('tenant.maintenance.orders*') || request()->routeIs('tenant.maintenance.interventions*');
@endphp
<div class="flex border-b border-slate-200 mb-4">
    <a href="{{ route('tenant.maintenance.contracts.index', ['tenant' => $tenantCode]) }}"
       class="flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium border-b-2 transition-colors
              {{ $onContracts ? 'text-blue-700 border-blue-600' : 'text-slate-500 border-transparent hover:text-slate-700' }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        {{ __('Contrats') }}
    </a>
    <a href="{{ route('tenant.maintenance.orders.index', ['tenant' => $tenantCode]) }}"
       class="flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-medium border-b-2 transition-colors
              {{ $onOrders ? 'text-blue-700 border-blue-600' : 'text-slate-500 border-transparent hover:text-slate-700' }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
        </svg>
        {{ __('Ordres') }}
    </a>
</div>
