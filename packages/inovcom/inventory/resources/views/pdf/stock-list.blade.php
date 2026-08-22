<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'État du stock' }}</title>
    <style>
        @page { margin: 22px 24px 28px 24px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 10px;
            margin: 0;
        }
        .header-top-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-top-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }
        .brand-cell { width: 58%; padding-right: 16px; }
        .doc-cell { width: 42%; }
        .brand-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 4px;
        }
        .brand-tagline {
            font-size: 9px;
            color: #64748b;
            margin: 0 0 6px;
        }
        .tenant-doc-logo {
            max-height: 56px;
            max-width: 200px;
            margin-bottom: 6px;
        }
        .brand-ids {
            font-size: 8.5px;
            line-height: 1.45;
            color: #1f2937;
        }
        .brand-ids b { font-weight: bold; }
        .doc-box {
            border: 2px solid #111;
            width: 100%;
            font-size: 10px;
        }
        .doc-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .doc-box th,
        .doc-box td {
            border: 1px solid #111;
            padding: 6px 8px;
            text-align: center;
        }
        .doc-box th {
            font-weight: bold;
            background-color: #0f766e;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
        }
        .doc-number {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.4px;
        }
        .doc-meta {
            padding: 5px 8px;
            font-size: 8px;
            text-align: center;
            border-top: 1px solid #111;
            color: #374151;
        }
        table.stock {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td {
            border: none;
            padding: 5px 7px;
            text-align: left;
            vertical-align: middle;
            word-wrap: break-word;
        }
        tr.cols th {
            background: #1f6f8b;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 7px 8px;
        }
        tbody tr:nth-child(even) td { background: #eef7f5; }
        tbody tr:nth-child(odd) td { background: #ffffff; }
        td.num-line {
            width: 8%;
            font-weight: bold;
            color: #1f6f8b;
        }
        td.ref { width: 20%; font-weight: bold; }
        td.name { width: 52%; }
        td.num {
            width: 20%;
            text-align: right;
            white-space: nowrap;
        }
        .meta {
            margin: 8px 0 0;
            font-size: 8px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $settings = $settings ?? [];
        $shopName = $settings['shop_name'] ?? ($shopName ?? 'Inov-Com');
        $showCompany = $settings['print_show_header_company_info'] ?? true;
        $canRenderLogo = extension_loaded('gd');
        $logoSrc = $canRenderLogo ? ($settings['logo_embed_src'] ?? $settings['logo_url'] ?? null) : null;
        $docDate = ($generatedAt ?? now())->format('d/m/Y');
        $docTime = ($generatedAt ?? now())->format('H:i');
        $itemCount = is_countable($items) ? count($items) : 0;
    @endphp

    <table class="header-top-table">
        <tr>
            <td class="brand-cell">
                @if ($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $shopName }}" class="tenant-doc-logo">
                @endif
                <div class="brand-name">{{ $shopName }}</div>
                @if (!empty($settings['shop_tagline']))
                    <div class="brand-tagline">{{ $settings['shop_tagline'] }}</div>
                @endif
                <div class="brand-ids">
                    @if ($showCompany)
                        @if (!empty($settings['shop_tax_id']))<div><b>NIU :</b> {{ $settings['shop_tax_id'] }}</div>@endif
                        @if (!empty($settings['shop_rccm']))<div><b>RCCM :</b> {{ $settings['shop_rccm'] }}</div>@endif
                        @if (!empty($settings['shop_cnps']))<div><b>CNPS :</b> {{ $settings['shop_cnps'] }}</div>@endif
                    @endif
                    @if (!empty($settings['shop_address']))
                        <div><b>Adresse :</b> {{ trim(preg_replace('/\s+/', ' ', $settings['shop_address'])) }}</div>
                    @endif
                    @if (!empty($settings['shop_bp']))
                        <div><b>B.P. :</b> {{ $settings['shop_bp'] }}</div>
                    @endif
                    @if (!empty($settings['shop_phone']))
                        <div><b>Tél :</b> {{ $settings['shop_phone'] }}</div>
                    @endif
                    @if (!empty($settings['shop_email']))
                        <div><b>Mail :</b> {{ $settings['shop_email'] }}</div>
                    @endif
                    @if (!empty($settings['shop_website']))
                        <div><b>Web :</b> {{ $settings['shop_website'] }}</div>
                    @endif
                </div>
            </td>
            <td class="doc-cell">
                <div class="doc-box">
                    <table>
                        <tr>
                            <th>Date</th>
                            <th>Document</th>
                        </tr>
                        <tr>
                            <td class="doc-number">{{ $docDate }}</td>
                            <td class="doc-number">État de stock</td>
                        </tr>
                    </table>
                    <div class="doc-meta">Édité à {{ $docTime }} · {{ number_format($itemCount, 0, ',', ' ') }} article(s)</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="stock">
        <thead>
            <tr class="cols">
                <th style="width:8%;">N°</th>
                <th style="width:20%;">Référence</th>
                <th style="width:52%;">Désignation</th>
                <th style="width:20%; text-align:right;">Qté disponible</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $index => $item)
                <tr>
                    <td class="num-line">{{ ($index + 1) * 10 }}</td>
                    <td class="ref">{{ $item->sku ?: '—' }}</td>
                    <td class="name">{{ $item->name }}</td>
                    <td class="num">{{ fmt_num((float) ($item->available_quantity ?? 0)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Aucun article pour ces filtres.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="meta">
        {{ $shopName }} · Document généré le {{ $docDate }} à {{ $docTime }}
        · {{ number_format($itemCount, 0, ',', ' ') }} article(s)
    </div>
</body>
</html>
