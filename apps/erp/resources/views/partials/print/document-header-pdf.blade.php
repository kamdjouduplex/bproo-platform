{{-- En-tête compatible DomPDF (tableau, pas de flex) --}}
<table class="header-top-table">
    <tr>
        <td class="brand-cell">
            @php
                $canRenderLogo = extension_loaded('gd');
                $logoSrc = $canRenderLogo ? ($settings['logo_embed_src'] ?? $settings['logo_url'] ?? null) : null;
            @endphp
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="{{ $settings['shop_name'] ?? '' }}" class="tenant-doc-logo">
            @else
                <div class="brand-name">{{ $settings['shop_name'] ?? '' }}</div>
            @endif
            @if (($settings['print_show_header_company_info'] ?? true) && (!empty($settings['shop_tax_id']) || !empty($settings['shop_rccm']) || !empty($settings['shop_cnps']) || !empty($settings['shop_address']) || !empty($settings['shop_bp']) || !empty($settings['shop_phone']) || !empty($settings['shop_email'])))
                <div class="brand-ids">
                    @if (!empty($settings['shop_tax_id']))<div><b>NIU :</b> {{ $settings['shop_tax_id'] }}</div>@endif
                    @if (!empty($settings['shop_rccm']))<div><b>RCCM :</b> {{ $settings['shop_rccm'] }}</div>@endif
                    @if (!empty($settings['shop_cnps']))<div><b>CNPS :</b> {{ $settings['shop_cnps'] }}</div>@endif
                    @if (!empty($settings['shop_address']))<div><b>Adresse :</b> {{ trim(preg_replace('/\s+/', ' ', $settings['shop_address'])) }}</div>@endif
                    @if (!empty($settings['shop_bp']))<div><b>B.P. :</b> {{ $settings['shop_bp'] }}</div>@endif
                    @if (!empty($settings['shop_phone']))<div><b>Tél :</b> {{ $settings['shop_phone'] }}</div>@endif
                    @if (!empty($settings['shop_email']))<div><b>Mail :</b> {{ $settings['shop_email'] }}</div>@endif
                </div>
            @endif
        </td>
        <td class="doc-cell">
            <div class="doc-box">
                <table>
                    <tr>
                        <th>DATE</th>
                        <th>{{ $docLabel }}</th>
                    </tr>
                    <tr>
                        <td>{{ $docDate }}</td>
                        <td class="doc-number">{{ $docNumber }}</td>
                    </tr>
                </table>
                @if (!empty($docSubtitle))
                    <div class="doc-validity">{{ $docSubtitle }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>
