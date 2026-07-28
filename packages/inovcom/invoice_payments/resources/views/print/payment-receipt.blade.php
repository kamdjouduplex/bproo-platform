@php
    $paidBefore = (float) ($payment->amount_paid_before ?? 0);
    $paidAfter = $payment->amountPaidAfter();
    $balanceAfter = $payment->balance_after !== null
        ? (float) $payment->balance_after
        : max(0, round((float) $invoice->total - $paidAfter, 2));
    $tenantCode = request()->query('tenant');
    $shopName = $settings['shop_name'] ?? $settings['invoice_footer'] ?? 'Entreprise';
    $logoSrc = $settings['logo_embed_src'] ?? $settings['logo_url'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup')
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            padding-bottom: 90px;
        }
        .page { padding: 12mm 12mm 0; max-width: 210mm; margin: 0 auto; }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        .brand-block { flex: 1; text-align: left; padding-right: 20px; }
        .tenant-doc-logo { max-height: 72px; max-width: 240px; object-fit: contain; margin-bottom: 6px; }
        .brand-name { font-weight: 800; font-size: 16px; color: #111; margin-bottom: 4px; }
        .brand-tagline { font-size: 10px; color: #4b5563; font-style: italic; margin-bottom: 6px; }
        .brand-ids { font-size: 9.5px; line-height: 1.55; color: #1f2937; }
        .brand-ids b { font-weight: 700; }
        .doc-box {
            border: 2px solid #111;
            width: 280px;
            font-size: 11px;
            flex-shrink: 0;
        }
        .doc-box table { width: 100%; border-collapse: collapse; }
        .doc-box td, .doc-box th {
            border: 1px solid #111;
            padding: 6px 8px;
            text-align: center;
        }
        .doc-box th { font-weight: 700; background: #f8f8f8; font-size: 10px; }
        .doc-number { font-size: 14px; font-weight: 800; letter-spacing: 0.5px; }
        .doc-sub { padding: 5px 8px; font-size: 9px; text-align: center; border-top: 1px solid #111; background: #f0fdf4; font-weight: 700; color: #166534; }
        .doc-sub.cancelled { background: #fef2f2; color: #b91c1c; }
        .receipt-title {
            text-align: center;
            margin: 0 0 16px;
            padding: 10px 0;
            border-top: 1px solid #d1d5db;
            border-bottom: 2px solid #111;
        }
        .receipt-title h1 { font-size: 15px; letter-spacing: 0.12em; font-weight: 800; }
        .receipt-title p { font-size: 10px; color: #6b7280; margin-top: 4px; }
        .two-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        @media (max-width: 600px) { .two-cols { grid-template-columns: 1fr; } }
        .panel {
            border: 1px solid #111;
            padding: 10px 12px;
        }
        .panel-title {
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #374151;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.info { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.info td { padding: 4px 0; vertical-align: top; }
        table.info td:first-child { width: 44%; color: #6b7280; }
        .amount-box {
            margin: 16px 0;
            padding: 16px;
            border: 2px solid #166534;
            background: #f0fdf4;
            text-align: center;
        }
        .amount-box .label { font-size: 10px; text-transform: uppercase; color: #166534; font-weight: 700; }
        .amount-box .value { font-size: 22px; font-weight: 800; color: #166534; margin-top: 6px; }
        .amount-box.cancelled { border-color: #b91c1c; background: #fef2f2; }
        .amount-box.cancelled .label, .amount-box.cancelled .value { color: #b91c1c; }
        .totals-wrap { display: flex; justify-content: flex-end; margin-top: 8px; }
        .totals-table { border-collapse: collapse; min-width: 300px; font-size: 11px; }
        .totals-table td { border: 1px solid #111; padding: 6px 12px; }
        .totals-table .label { font-weight: 700; text-align: left; }
        .totals-table .value { text-align: right; font-weight: 700; min-width: 120px; }
        .totals-table tr.highlight td { background: #f0fdf4; }
        .totals-table tr.total td { background: #111; color: #fff; font-weight: 800; font-size: 12px; }
        .signature {
            margin-top: 28px;
            text-align: right;
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 0.05em;
        }
        .doc-footer {
            margin-top: 24px;
            padding-top: 10px;
            font-size: 9.5px;
            line-height: 1.5;
            color: #1f2937;
        }
        .doc-footer .footer-rule {
            height: 3px;
            width: 230px;
            margin-bottom: 6px;
            background: linear-gradient(90deg, #7c3aed 0%, #7c3aed 55%, #65a30d 55%, #65a30d 100%);
            border-radius: 2px;
        }
        .doc-footer .footer-name { font-weight: 800; font-size: 12px; color: #4d7c0f; margin-bottom: 2px; }
        .doc-footer .footer-sep { display: inline-block; width: 10px; }
        .no-print { margin-top: 20px; text-align: center; color: #666; font-size: 11px; }
        @media print { .no-print { display: none !important; } body { padding-bottom: 0; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="header-top">
            <div class="brand-block">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $shopName }}" class="tenant-doc-logo">
                @else
                    <div class="brand-name">{{ $shopName }}</div>
                @endif
                @if(!empty($settings['shop_tagline']))
                    <div class="brand-tagline">{{ $settings['shop_tagline'] }}</div>
                @endif
                @if(($settings['print_show_header_company_info'] ?? true) && (!empty($settings['shop_tax_id']) || !empty($settings['shop_rccm']) || !empty($settings['shop_cnps']) || !empty($settings['shop_address']) || !empty($settings['shop_bp']) || !empty($settings['shop_phone']) || !empty($settings['shop_email']) || !empty($settings['shop_website'])))
                    <div class="brand-ids">
                        @if(!empty($settings['shop_tax_id']))<div><b>NIU :</b> {{ $settings['shop_tax_id'] }}</div>@endif
                        @if(!empty($settings['shop_rccm']))<div><b>RCCM :</b> {{ $settings['shop_rccm'] }}</div>@endif
                        @if(!empty($settings['shop_cnps']))<div><b>CNPS :</b> {{ $settings['shop_cnps'] }}</div>@endif
                        @if(!empty($settings['shop_address']))<div><b>Adresse :</b> {{ trim(preg_replace('/\s+/', ' ', $settings['shop_address'])) }}</div>@endif
                        @if(!empty($settings['shop_bp']))<div><b>B.P. :</b> {{ $settings['shop_bp'] }}</div>@endif
                        @if(!empty($settings['shop_phone']))<div><b>Tél :</b> {{ $settings['shop_phone'] }}</div>@endif
                        @if(!empty($settings['shop_email']))<div><b>Mail :</b> {{ $settings['shop_email'] }}</div>@endif
                        @if(!empty($settings['shop_website']))<div><b>Web :</b> {{ $settings['shop_website'] }}</div>@endif
                    </div>
                @endif
            </div>
            <div class="doc-box">
                <table>
                    <tr>
                        <th>DATE</th>
                        <th>REÇU N°</th>
                    </tr>
                    <tr>
                        <td>{{ $payment->payment_date->format('d/m/y') }}<br><span style="font-size:9px;">{{ $payment->created_at?->format('H:i') }}</span></td>
                        <td class="doc-number">{{ $payment->reference }}</td>
                    </tr>
                </table>
                <div class="doc-sub {{ $payment->isCancelled() ? 'cancelled' : '' }}">
                    REÇU D'ENCAISSEMENT
                    @if ($payment->isCancelled())
                        — ANNULÉ
                    @endif
                </div>
            </div>
        </div>

        <div class="receipt-title">
            <h1>REÇU D'ENCAISSEMENT</h1>
            <p>Document de confirmation de paiement — {{ $payment->reference }}</p>
        </div>

        <div class="two-cols">
            <div class="panel">
                <div class="panel-title">Facture concernée</div>
                <table class="info">
                    <tr><td>N° facture</td><td><strong>{{ $invoice->invoice_number }}</strong></td></tr>
                    <tr><td>Client</td><td><strong>{{ $invoice->client?->name ?? '—' }}</strong></td></tr>
                    <tr><td>Date facture</td><td>{{ $invoice->invoice_date->format('d/m/Y') }}</td></tr>
                    @if ($invoice->due_date)
                        <tr><td>Échéance</td><td>{{ $invoice->due_date->format('d/m/Y') }}</td></tr>
                    @endif
                    <tr><td>Montant facture</td><td><strong>{{ fmt_money($invoice->total) }} FCFA</strong></td></tr>
                </table>
            </div>
            <div class="panel">
                <div class="panel-title">Détail de l'encaissement</div>
                <table class="info">
                    <tr><td>Date</td><td>{{ $payment->payment_date->format('d/m/Y') }}</td></tr>
                    <tr><td>Heure</td><td>{{ $payment->created_at?->format('H:i') ?? '—' }}</td></tr>
                    <tr><td>Mode</td><td>{{ \InovCom\InvoicePayments\Models\InvoicePayment::methodLabel($payment->payment_method) }}</td></tr>
                    @if ($payment->external_reference)
                        <tr><td>Réf. transaction</td><td>{{ $payment->external_reference }}</td></tr>
                    @endif
                    <tr><td>Enregistré par</td><td>{{ $payment->creator?->name ?? '—' }}</td></tr>
                    @if ($payment->notes)
                        <tr><td>Notes</td><td>{{ $payment->notes }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        @if ($payment->isActive())
            <div class="amount-box">
                <div class="label">Montant encaissé</div>
                <div class="value">+ {{ fmt_money($payment->amount) }} FCFA</div>
            </div>
        @elseif ($payment->isCancelled())
            <div class="amount-box cancelled">
                <div class="label">Encaissement annulé</div>
                <div class="value">{{ fmt_money($payment->amount) }} FCFA</div>
            </div>
        @endif

        <div class="totals-wrap">
            <table class="totals-table">
                <tr>
                    <td class="label">Montant total facture</td>
                    <td class="value">{{ fmt_money($invoice->total) }} FCFA</td>
                </tr>
                <tr>
                    <td class="label">Total encaissé avant ce paiement</td>
                    <td class="value">{{ fmt_money($paidBefore) }} FCFA</td>
                </tr>
                <tr class="highlight">
                    <td class="label">Montant de cet encaissement</td>
                    <td class="value" style="color:#166534;">+ {{ fmt_money($payment->amount) }} FCFA</td>
                </tr>
                <tr>
                    <td class="label">Total encaissé après ce paiement</td>
                    <td class="value">{{ fmt_money($paidAfter) }} FCFA</td>
                </tr>
                <tr class="total">
                    <td class="label">Solde restant à payer</td>
                    <td class="value">{{ fmt_money(max(0, $balanceAfter)) }} FCFA</td>
                </tr>
            </table>
        </div>

        @if ($payment->isCancelled() && $payment->cancellation_reason)
            <div class="panel" style="margin-top:16px;border-color:#b91c1c;">
                <div class="panel-title" style="color:#b91c1c;">Annulation</div>
                <p>{{ $payment->cancellation_reason }}</p>
                <p style="margin-top:6px;font-size:10px;color:#6b7280;">
                    Annulé le {{ $payment->cancelled_at?->format('d/m/Y à H:i') }} par {{ $payment->canceller?->name ?? '—' }}
                </p>
            </div>
        @endif

        <div class="signature">LA DIRECTION</div>

        @php
            $footerName = $settings['invoice_footer'] ?: $shopName;
            $shopAddress = !empty($settings['shop_address']) ? trim(preg_replace('/\s+/', ' ', $settings['shop_address'])) : '';
            $hasLegal = !empty($settings['shop_tax_id']) || !empty($settings['shop_rccm']) || !empty($settings['shop_cnps']);
            $hasContact = $shopAddress !== '' || !empty($settings['shop_bp']) || !empty($settings['shop_phone']) || !empty($settings['shop_email']);
        @endphp
        @if($footerName || $hasLegal || $hasContact)
        <div class="doc-footer">
            <div class="footer-rule"></div>
            @if($footerName)<div class="footer-name">{{ $footerName }}</div>@endif
            @if($hasLegal)
                <div class="footer-legal">
                    @if(!empty($settings['shop_tax_id']))<b>N° Cont.</b> {{ $settings['shop_tax_id'] }}<span class="footer-sep"></span>@endif
                    @if(!empty($settings['shop_rccm']))<b>N° RCCM :</b> {{ $settings['shop_rccm'] }}<span class="footer-sep"></span>@endif
                    @if(!empty($settings['shop_cnps']))<b>N° CNPS :</b> {{ $settings['shop_cnps'] }}@endif
                </div>
            @endif
            @if($hasContact)
                <div class="footer-contact">
                    @if($shopAddress !== ''){{ $shopAddress }}<span class="footer-sep"></span>@endif
                    @if(!empty($settings['shop_bp']))<b>B.P. :</b> {{ $settings['shop_bp'] }}<span class="footer-sep"></span>@endif
                    @if(!empty($settings['shop_phone']))<b>Tél :</b> {{ $settings['shop_phone'] }}<span class="footer-sep"></span>@endif
                    @if(!empty($settings['shop_email']))<b>Mail :</b> {{ $settings['shop_email'] }}@endif
                </div>
            @endif
            <div style="margin-top:8px;font-size:9px;color:#9ca3af;">Imprimé le {{ now()->format('d/m/Y à H:i') }}</div>
        </div>
        @endif
    </div>

    @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
