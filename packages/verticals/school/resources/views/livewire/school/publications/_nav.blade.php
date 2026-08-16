@php
    $nav = [
        ['label' => 'Publications', 'route' => 'tenant.school.publications.index', 'match' => ['tenant.school.publications.index', 'tenant.school.publications.show', 'tenant.school.publications.manage']],
        ['label' => 'Règles', 'route' => 'tenant.school.publications.rules.index', 'match' => 'tenant.school.publications.rules.*'],
    ];
@endphp
<div style="display:flex; gap:8px; flex-wrap:wrap; padding: 0 16px 12px;">
    @foreach($nav as $item)
        @php
            $active = is_array($item['match'])
                ? request()->routeIs(...$item['match'])
                : request()->routeIs($item['match']);
        @endphp
        <a href="{{ route($item['route'], ['tenant' => $tenantCode]) }}"
           class="btn btn-sm {{ $active ? 'btn-primary' : 'btn-secondary' }}">
            {{ $item['label'] }}
        </a>
    @endforeach
</div>
