<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup')
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            font-size: 10.5px;
            color: #111;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        @media screen {
            body { background: #e5e7eb; }
            .page {
                margin: 16px auto;
                background: #fff;
                box-shadow: 0 2px 12px rgba(0,0,0,.1);
            }
        }
        .page {
            max-width: 210mm;
            width: 100%;
        }
        .print-page-inner {
            padding: 10mm 12mm 8mm;
            min-height: 277mm;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }
        .print-page-content { flex: 0 0 auto; }
        .print-page-footer {
            margin-top: auto;
            padding-top: 10px;
            width: 100%;
        }

        /* —— Header —— */
        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 12px;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
        }
        .brand-block {
            display: table-cell;
            width: 58%;
            vertical-align: top;
            padding-right: 16px;
        }
        .brand-block .brand-name {
            font-weight: 800;
            font-size: 16px;
            line-height: 1.2;
        }
        .tenant-doc-logo {
            max-height: 64px;
            max-width: 220px;
            object-fit: contain;
            display: block;
        }
        .brand-ids {
            margin-top: 6px;
            font-size: 8.5px;
            line-height: 1.45;
            color: #1f2937;
        }
        .brand-ids b { font-weight: 700; }
        .doc-box {
            display: table-cell;
            width: 42%;
            vertical-align: top;
            border: 2px solid #111;
        }
        .doc-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .doc-box th,
        .doc-box td {
            border: 1px solid #111;
            padding: 5px 8px;
            text-align: center;
        }
        .doc-box th {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .doc-box .doc-number {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        .doc-box .doc-validity {
            padding: 4px 8px;
            font-size: 9px;
            font-weight: 700;
            text-align: center;
            border-top: 1px solid #111;
        }

        /* —— Band titre —— */
        .slip-band {
            display: table;
            width: 100%;
            border: 1.5px solid #111;
            margin-bottom: 10px;
        }
        .slip-band__main,
        .slip-band__meta {
            display: table-cell;
            vertical-align: middle;
            padding: 8px 10px;
        }
        .slip-band__main {
            width: 62%;
            border-right: 1.5px solid #111;
        }
        .slip-band__title {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        .slip-band__sub {
            margin-top: 2px;
            font-size: 10px;
            color: #374151;
        }
        .slip-band__meta {
            width: 38%;
            text-align: right;
            font-size: 9.5px;
            line-height: 1.5;
        }
        .slip-band__meta strong { font-weight: 800; }
        .status-mark {
            display: inline-block;
            border: 1.5px solid #111;
            padding: 1px 7px;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-left: 4px;
        }

        /* —— Infos salarié / emploi —— */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }
        .info-table > tbody > tr > td {
            width: 50%;
            border: 1px solid #111;
            vertical-align: top;
            padding: 0;
        }
        .info-table .panel-head {
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 5px 8px;
            border-bottom: 1px solid #111;
            background: #f3f4f6;
        }
        .info-table .panel-body { padding: 7px 8px 8px; }
        .info-table .emp-name {
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 5px;
        }
        .kv {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .kv td {
            padding: 1.5px 0;
            vertical-align: top;
        }
        .kv td.k {
            width: 92px;
            color: #4b5563;
            white-space: nowrap;
        }
        .kv td.v { font-weight: 600; }

        /* —— Rubriques —— */
        .pay-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            table-layout: fixed;
            font-size: 9.5px;
        }
        .pay-table th,
        .pay-table td {
            border: 1px solid #111;
            padding: 5px 7px;
            vertical-align: middle;
        }
        .pay-table thead th {
            background: #f3f4f6;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: center;
        }
        .pay-table td.lbl { text-align: left; }
        .pay-table td.amt {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            width: 18%;
        }
        .pay-table td.blank { height: 22px; }
        .pay-table tfoot td {
            font-weight: 800;
            background: #f3f4f6;
        }
        .pay-table .type-tag {
            display: block;
            font-size: 7.5px;
            color: #6b7280;
            font-weight: 500;
            margin-top: 1px;
        }

        /* —— Totaux —— */
        .totals-row {
            display: table;
            width: 100%;
            margin: 4px 0 10px;
        }
        .totals-words {
            display: table-cell;
            width: 55%;
            vertical-align: top;
            padding-right: 12px;
            font-size: 10px;
            line-height: 1.45;
        }
        .totals-words .box {
            border: 1.5px solid #111;
            padding: 8px 10px;
            min-height: 58px;
        }
        .totals-words .box .cap {
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            color: #374151;
        }
        .totals-wrap {
            display: table-cell;
            width: 45%;
            vertical-align: top;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .totals-table td {
            border: 1px solid #111;
            padding: 6px 10px;
        }
        .totals-table .label {
            font-weight: 700;
            text-align: left;
        }
        .totals-table .value {
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
            min-width: 100px;
            font-variant-numeric: tabular-nums;
        }
        .totals-table tr.net td {
            font-size: 12px;
            font-weight: 800;
            border-width: 2px;
        }

        .notes-box {
            border: 1px solid #111;
            border-style: dashed;
            padding: 7px 9px;
            margin-bottom: 10px;
            font-size: 9px;
            line-height: 1.4;
        }

        /* —— Signatures —— */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            table-layout: fixed;
        }
        .sign-table td {
            width: 50%;
            border: 1px solid #111;
            vertical-align: top;
            height: 92px;
            padding: 8px 10px;
        }
        .sign-table .sign-title {
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .sign-table .sign-hint {
            font-size: 8px;
            color: #6b7280;
        }

        .legal-note {
            margin-top: 10px;
            font-size: 7.5px;
            color: #6b7280;
            line-height: 1.4;
            text-align: justify;
        }

        .doc-footer {
            margin-top: 8px;
            padding-top: 6px;
            font-size: 9px;
            line-height: 1.5;
            color: #1f2937;
        }
        .doc-footer .footer-rule {
            height: 0;
            border-top: 2px solid #111;
            width: 140px;
            margin-bottom: 5px;
        }
        .doc-footer .footer-name {
            font-weight: 800;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .doc-footer .print-stamp {
            margin-top: 3px;
            font-size: 8px;
            color: #6b7280;
            font-weight: 700;
        }
        .doc-footer .footer-sep { display: inline-block; width: 10px; }

        .no-print { margin-top: 16px; text-align: center; font-size: 11px; color: #666; }
        @media print {
            .no-print { display: none !important; }
            .page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
@php
    $logoSrc = $settings['logo_embed_src'] ?? $settings['logo_url'] ?? null;
    $shopName = $settings['shop_name'] ?? config('app.name');
    $minRows = max(5, (int) $rowCount);
    $paymentMode = (!empty($employee->bank_name) || !empty($employee->bank_account))
        ? 'Virement bancaire'
        : 'À préciser';
@endphp
    <div class="page">
        <div class="print-page-inner">
            <div class="print-page-content">

                {{-- En-tête entreprise + cartouche document --}}
                <div class="header-top">
                    <div class="brand-block">
                        @if ($logoSrc)
                            <img src="{{ $logoSrc }}" alt="{{ $shopName }}" class="tenant-doc-logo">
                        @else
                            <div class="brand-name">{{ $shopName }}</div>
                        @endif
                        @if (($settings['print_show_header_company_info'] ?? true))
                            <div class="brand-ids">
                                @if (!empty($settings['shop_tax_id']))<div><b>NIU :</b> {{ $settings['shop_tax_id'] }}</div>@endif
                                @if (!empty($settings['shop_rccm']))<div><b>RCCM :</b> {{ $settings['shop_rccm'] }}</div>@endif
                                @if (!empty($settings['shop_cnps']))<div><b>CNPS employeur :</b> {{ $settings['shop_cnps'] }}</div>@endif
                                @if (!empty($settings['shop_address']))<div><b>Adresse :</b> {{ trim(preg_replace('/\s+/', ' ', $settings['shop_address'])) }}</div>@endif
                                @if (!empty($settings['shop_phone']))<div><b>Tél :</b> {{ $settings['shop_phone'] }}</div>@endif
                            </div>
                        @endif
                    </div>
                    <div class="doc-box">
                        <table>
                            <tr>
                                <th>Date d’édition</th>
                                <th>Référence</th>
                            </tr>
                            <tr>
                                <td>{{ now()->format('d/m/Y') }}</td>
                                <td class="doc-number">{{ $run->reference ?: 'PAIE' }}</td>
                            </tr>
                        </table>
                        <div class="doc-validity">{{ $periodMonth }}</div>
                    </div>
                </div>

                {{-- Bandeau titre unique --}}
                <div class="slip-band">
                    <div class="slip-band__main">
                        <div class="slip-band__title">Bulletin de paie</div>
                        <div class="slip-band__sub">Période du {{ $periodLabel }}</div>
                    </div>
                    <div class="slip-band__meta">
                        <div><strong>{{ $currencyLabel }}</strong></div>
                        <div>
                            Statut
                            <span class="status-mark">{{ $run->status_label }}</span>
                        </div>
                    </div>
                </div>

                {{-- Salarié / Paiement --}}
                <table class="info-table">
                    <tr>
                        <td>
                            <div class="panel-head">Salarié</div>
                            <div class="panel-body">
                                <div class="emp-name">{{ $employee->full_name }}</div>
                                <table class="kv">
                                    <tr><td class="k">Matricule</td><td class="v">{{ $employee->employee_number ?? '—' }}</td></tr>
                                    <tr><td class="k">Poste</td><td class="v">{{ $employee->position ?: '—' }}</td></tr>
                                    <tr><td class="k">Département</td><td class="v">{{ $employee->department ?: '—' }}</td></tr>
                                    <tr><td class="k">Contrat</td><td class="v">{{ $contractLabel ?: '—' }}</td></tr>
                                    <tr><td class="k">N° CNPS</td><td class="v">{{ $employee->cnps_number ?: '—' }}</td></tr>
                                    <tr>
                                        <td class="k">Embauche</td>
                                        <td class="v">{{ $employee->hire_date?->format('d/m/Y') ?: '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                        <td>
                            <div class="panel-head">Coordonnées de paiement</div>
                            <div class="panel-body">
                                <div class="emp-name" style="font-size:11px;">Mode : {{ $paymentMode }}</div>
                                <table class="kv">
                                    <tr><td class="k">Banque</td><td class="v">{{ $employee->bank_name ?: '—' }}</td></tr>
                                    <tr><td class="k">N° compte</td><td class="v">{{ $employee->bank_account ?: '—' }}</td></tr>
                                    <tr><td class="k">Téléphone</td><td class="v">{{ $employee->phone ?: '—' }}</td></tr>
                                    <tr><td class="k">E-mail</td><td class="v">{{ $employee->email ?: '—' }}</td></tr>
                                    <tr><td class="k">Adresse</td><td class="v">{{ $employee->address ?: '—' }}</td></tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>

                {{-- Tableau gains / retenues --}}
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th style="width:32%;">Libellé des gains</th>
                            <th style="width:18%;">Montant ({{ $currencyLabel }})</th>
                            <th style="width:32%;">Libellé des retenues</th>
                            <th style="width:18%;">Montant ({{ $currencyLabel }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < $minRows; $i++)
                            @php
                                $earn = $earnings[$i] ?? null;
                                $ded = $deductions[$i] ?? null;
                            @endphp
                            <tr>
                                <td class="lbl {{ $earn ? '' : 'blank' }}">
                                    @if ($earn)
                                        {{ $earn->label }}
                                        @if (!empty($earn->type_label) && ($earn->type ?? '') !== 'base')
                                            <span class="type-tag">{{ $earn->type_label }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="amt">
                                    @if ($earn){{ fmt_money((float) $earn->amount) }}@endif
                                </td>
                                <td class="lbl {{ $ded ? '' : 'blank' }}">
                                    @if ($ded)
                                        {{ $ded->label }}
                                        @if (!empty($ded->type_label))
                                            <span class="type-tag">{{ $ded->type_label }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="amt">
                                    @if ($ded){{ fmt_money(abs((float) $ded->amount)) }}@endif
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="lbl">Total des gains (brut)</td>
                            <td class="amt">{{ fmt_money($gross) }}</td>
                            <td class="lbl">Total des retenues</td>
                            <td class="amt">{{ fmt_money($totalDeductions) }}</td>
                        </tr>
                    </tfoot>
                </table>

                {{-- Net en lettres + tableau récap --}}
                <div class="totals-row">
                    <div class="totals-words">
                        <div class="box">
                            <div class="cap">Net à payer en lettres</div>
                            <strong>{{ $netInWords }}</strong>
                        </div>
                    </div>
                    <div class="totals-wrap">
                        <table class="totals-table">
                            <tr>
                                <td class="label">Salaire brut</td>
                                <td class="value">{{ fmt_money($gross) }}</td>
                            </tr>
                            <tr>
                                <td class="label">Retenues</td>
                                <td class="value">− {{ fmt_money($totalDeductions) }}</td>
                            </tr>
                            <tr class="net">
                                <td class="label">Net à payer</td>
                                <td class="value">{{ fmt_money($net) }} {{ $currencyLabel }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if (!empty($line->notes))
                    <div class="notes-box">
                        <strong>Observations :</strong> {{ $line->notes }}
                    </div>
                @endif

                <table class="sign-table">
                    <tr>
                        <td>
                            <div class="sign-title">Signature de l’employeur</div>
                            <div class="sign-hint">Cachet et signature</div>
                        </td>
                        <td>
                            <div class="sign-title">Signature du salarié</div>
                            <div class="sign-hint">« Lu et approuvé » — Date : _______________</div>
                        </td>
                    </tr>
                </table>

                <p class="legal-note">
                    Bulletin établi par {{ $shopName }} pour la période indiquée.
                    Document à conserver par le salarié. Les montants sont exprimés en {{ $currencyLabel }}.
                </p>
            </div>

            <div class="print-page-footer">
                @include('inovcom-invoicing::print.partials.document-footer', ['settings' => $settings])
            </div>
        </div>
    </div>

    @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
