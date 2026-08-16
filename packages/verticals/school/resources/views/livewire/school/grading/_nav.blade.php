@php
    $nav = [
        ['label' => 'Systèmes & barèmes', 'route' => 'tenant.school.grading.systems.index', 'match' => 'tenant.school.grading.systems.*'],
        ['label' => 'Coefficients', 'route' => 'tenant.school.grading.coefficients.index', 'match' => 'tenant.school.grading.coefficients.*'],
        ['label' => 'Règles de calcul', 'route' => 'tenant.school.grading.rules.index', 'match' => 'tenant.school.grading.rules.*'],
    ];
@endphp
<div style="display:flex; gap:8px; flex-wrap:wrap; padding: 0 16px 12px;">
    @foreach($nav as $item)
        <a href="{{ route($item['route'], ['tenant' => $tenantCode]) }}"
           class="btn btn-sm {{ request()->routeIs($item['match']) ? 'btn-primary' : 'btn-secondary' }}">
            {{ $item['label'] }}
        </a>
    @endforeach
</div>
