@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;

    $items = [
        ['label' => 'Hub', 'route' => 'tenant.pressing_settings.index'],
        ['label' => 'Types', 'route' => 'tenant.pressing_settings.article_types'],
        ['label' => 'Tarifs', 'route' => 'tenant.pressing_settings.prices'],
        ['label' => 'Workflow', 'route' => 'tenant.pressing_workflow.stages'],
        ['label' => 'Délais', 'route' => 'tenant.pressing_settings.delays'],
        ['label' => 'Taxes', 'route' => 'tenant.pressing_settings.taxes'],
        ['label' => 'Messages', 'route' => 'tenant.pressing_settings.messages'],
        ['label' => 'Notifications', 'route' => 'tenant.pressing_settings.notifications'],
        ['label' => 'Paiements', 'route' => 'tenant.pressing_settings.payments'],
        ['label' => 'Fidélité', 'route' => 'tenant.pressing_settings.loyalty'],
    ];
@endphp

<nav class="reporting-nav" style="margin-bottom:16px;" aria-label="Paramétrage">
    <div class="reporting-nav__tabs">
        @foreach ($items as $item)
            @php $active = request()->routeIs($item['route']); @endphp
            <a class="reporting-nav__tab {{ $active ? 'reporting-nav__tab--active' : '' }}"
               href="{{ route($item['route'], ['tenant' => $tenantCode]) }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
