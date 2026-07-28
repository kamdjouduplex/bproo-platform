@props([
    'tenant' => null,
    'type' => 'main',
    'alt' => null,
    'class' => '',
    'textClass' => 'app-logo',
    'fallback' => null,
])

@php
    $tenant = $tenant ?? app(\App\Services\TenantManager::class)->tenant();
    $branding = app(\App\Services\TenantBrandingService::class);
    $logoUrl = $branding->url($tenant, $type);
    $label = $alt ?? ($tenant ? $tenant->getSetting('shop_name', $tenant->name) : ($fallback ?? 'Inov-Com'));
@endphp

@if ($logoUrl)
    <img src="{{ $logoUrl }}" alt="{{ $label }}" {{ $attributes->merge(['class' => trim('tenant-logo-img ' . $class)]) }}>
@else
    <span {{ $attributes->merge(['class' => $textClass]) }}>{{ $label }}</span>
@endif
