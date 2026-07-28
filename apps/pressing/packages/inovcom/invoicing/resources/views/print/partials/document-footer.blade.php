{{-- Pied de page commun factures, devis, relances — variable : $settings --}}
@php
    $footerName = ($settings['invoice_footer'] ?? '') ?: ($settings['shop_name'] ?? '');
    $shopAddress = !empty($settings['shop_address']) ? trim(preg_replace('/\s+/', ' ', $settings['shop_address'])) : '';
    $shopBp = $settings['shop_bp'] ?? '';
    $hasLegal = !empty($settings['shop_tax_id']) || !empty($settings['shop_rccm']) || !empty($settings['shop_cnps']);
    $hasContact = $shopAddress !== '' || $shopBp !== '' || !empty($settings['shop_phone']) || !empty($settings['shop_email']);
@endphp
@if ($footerName || $hasLegal || $hasContact)
    <div class="doc-footer">
        <div class="footer-rule"></div>
        @if ($footerName)<div class="footer-name">{{ $footerName }}</div>@endif
        @if ($hasLegal)
            <div class="footer-legal">
                @if (!empty($settings['shop_tax_id']))<b>N° Cont.</b> {{ $settings['shop_tax_id'] }}<span class="footer-sep"></span>@endif
                @if (!empty($settings['shop_rccm']))<b>N° RCCM :</b> {{ $settings['shop_rccm'] }}<span class="footer-sep"></span>@endif
                @if (!empty($settings['shop_cnps']))<b>N° CNPS :</b> {{ $settings['shop_cnps'] }}@endif
            </div>
        @endif
        @if ($hasContact)
            <div class="footer-contact">
                @if ($shopAddress !== ''){{ $shopAddress }}<span class="footer-sep"></span>@endif
                @if ($shopBp !== '')<b>B.P. :</b> {{ $shopBp }}<span class="footer-sep"></span>@endif
                @if (!empty($settings['shop_phone']))<b>Tél :</b> {{ $settings['shop_phone'] }}<span class="footer-sep"></span>@endif
                @if (!empty($settings['shop_email']))<b>Mail :</b> {{ $settings['shop_email'] }}@endif
            </div>
        @endif
        <div class="print-stamp"><strong>Imprimer le :</strong> {{ now()->format('n/j/y, g:i A') }}</div>
    </div>
@else
    <div class="doc-footer">
        <div class="print-stamp"><strong>Imprimer le :</strong> {{ now()->format('n/j/y, g:i A') }}</div>
    </div>
@endif
