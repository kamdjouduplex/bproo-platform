@php
  // Préférer l'embed base64 pour l'impression (évite les images cassées si APP_URL ≠ hôte courant).
    $logoUrl = $settings['logo_embed_src'] ?? $settings['logo_url'] ?? null;
    $shopName = $settings['shop_name'] ?? 'Inov-Com';
    $showName = $showName ?? true;
    $showIds = $showIds ?? $showName;
@endphp
<div class="tenant-doc-brand">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $shopName }}" class="tenant-doc-logo">
    @endif
    <div class="tenant-doc-brand-text">
        @if ($showName || !$logoUrl)
            <div class="tenant-doc-shop-name">{{ $shopName }}</div>
        @endif
        @if (!empty($settings['shop_tagline']))
            <div class="tenant-doc-tagline">{{ $settings['shop_tagline'] }}</div>
        @endif
        @if ($showIds)
        <div class="tenant-doc-ids">
            @if (!empty($settings['shop_tax_id']))<div>NIU: {{ $settings['shop_tax_id'] }}</div>@endif
            @if (!empty($settings['shop_rccm']))<div>RCCM: {{ $settings['shop_rccm'] }}</div>@endif
            @if (!empty($settings['shop_cnps']))<div>CNPS: {{ $settings['shop_cnps'] }}</div>@endif
        </div>
        @endif
        @if (!empty($settings['invoice_footer']))
            <div class="tenant-doc-slogan">{{ $settings['invoice_footer'] }}</div>
        @endif
    </div>
</div>
