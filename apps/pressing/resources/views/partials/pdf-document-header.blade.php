{{-- En-tête PDF (DomPDF) : logo uniquement si GD est dispo (requis par DomPDF pour les images) --}}
@php
    $shopName = $settings['shop_name'] ?? 'Inov-Com';
    $canRenderLogo = extension_loaded('gd');
    $logoSrc = $canRenderLogo
        ? ($settings['logo_embed_src'] ?? $settings['logo_absolute_path'] ?? $settings['logo_url'] ?? null)
        : null;
@endphp
<div style="margin-bottom: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="vertical-align: top; width: 55%;">
                @if ($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $shopName }}" style="max-height: 56px; max-width: 200px;">
                @else
                    <div style="font-size: 18px; font-weight: bold;">{{ $shopName }}</div>
                @endif
                @if (!empty($settings['shop_tagline']))
                    <div style="font-size: 10px; color: #4b5563; margin-top: 4px;">{{ $settings['shop_tagline'] }}</div>
                @endif
            </td>
            <td style="vertical-align: top; text-align: right; font-size: 10px; color: #374151;">
                @if (($settings['print_show_header_company_info'] ?? true))
                    @if (!empty($settings['shop_address']))<div>{{ $settings['shop_address'] }}</div>@endif
                    @if (!empty($settings['shop_phone']))<div>Tél: {{ $settings['shop_phone'] }}</div>@endif
                    @if (!empty($settings['shop_email']))<div>{{ $settings['shop_email'] }}</div>@endif
                    @if (!empty($settings['shop_tax_id']))<div>NIU: {{ $settings['shop_tax_id'] }}</div>@endif
                    @if (!empty($settings['shop_rccm']))<div>RCCM: {{ $settings['shop_rccm'] }}</div>@endif
                @endif
            </td>
        </tr>
    </table>
</div>
