<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? 'A4'])
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }
        /* page-setup force body padding:0 en print — marges via ce wrapper */
        .report-page {
            padding: 18mm 16mm 16mm;
            max-width: 210mm;
            margin: 0 auto;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .brand { font-size: 18px; font-weight: 700; letter-spacing: -0.02em; }
        .brand-sub { color: #4b5563; margin-top: 2px; font-size: 11px; }
        .meta-block { text-align: right; color: #374151; font-size: 11px; }
        .meta-block strong { color: #111827; }
        .totals {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 18px;
        }
        .total-box {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 8px 12px;
            min-width: 120px;
        }
        .total-box .label { display: block; font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .total-box .value { display: block; font-size: 15px; font-weight: 700; margin-top: 2px; }
        h2 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #111827;
            margin: 18px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 7px; vertical-align: top; }
        thead th {
            background: #111827;
            color: #fff;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: none;
        }
        tbody td {
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        .right { text-align: right; }
        .mono { font-variant-numeric: tabular-nums; }
        .sale-no { font-weight: 700; white-space: nowrap; }
        .muted { color: #6b7280; }
        .item-name { font-weight: 600; }
        .item-sku { display: block; font-size: 10px; color: #6b7280; margin-top: 1px; }
        .empty { color: #6b7280; padding: 10px 0; }
        .footer-note {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 10px;
        }
        @media print {
            .no-print { display: none !important; }
            .report-page { padding: 14mm 16mm 16mm !important; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
        @media screen {
            .report-page { padding: 22px 26px 28px; }
        }
    </style>
</head>
<body>
    <div class="report-page">
    <div class="no-print" style="margin-bottom: 14px; display:flex; gap:10px; align-items:center;">
        <button onclick="window.print()">Imprimer</button>
        <a href="{{ route('tenant.sales.daily-report', ['tenant' => request('tenant'), 'date' => $date]) }}">Retour</a>
    </div>

    <header class="doc-header">
        <div>
            <div class="brand">{{ $settings['shop_name'] ?? 'Rapport journalier' }}</div>
            <div class="brand-sub">Rapport des ventes journalières</div>
        </div>
        <div class="meta-block">
            <div>Date : <strong>{{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</strong></div>
            <div>{{ $sales->count() }} vente(s) · {{ $detailLines->count() }} ligne(s) article</div>
            <div>Imprimé le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </header>

    <div class="totals">
        @forelse ($totalsByCurrency as $code => $amount)
            <div class="total-box">
                <span class="label">{{ \App\Services\TenantCurrencyService::label($code) }} ({{ $code }})</span>
                <span class="value mono">{{ fmt_money($amount) }}</span>
            </div>
        @empty
            <p class="empty">Aucune vente pour cette date.</p>
        @endforelse
    </div>

    <h2>1. Liste des ventes</h2>
    <table>
        <thead>
            <tr>
                <th>N° vente</th>
                <th>Client</th>
                <th>Vendeur</th>
                <th>Devise</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sales as $sale)
                @php $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency)); @endphp
                <tr>
                    <td class="sale-no">{{ $sale->sale_number }}</td>
                    <td>{{ $sale->client?->name ?? 'Client occasionnel' }}</td>
                    <td>{{ $sale->creator?->name ?? '—' }}</td>
                    <td>{{ \App\Services\TenantCurrencyService::label($code) }}</td>
                    <td class="right mono">{{ fmt_money($sale->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucune vente.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>2. Détail des articles</h2>
    <table>
        <thead>
            <tr>
                <th>N° vente</th>
                <th>Article</th>
                <th class="right">Qté</th>
                <th class="right">P.U.</th>
                <th class="right">Montant</th>
                <th>Devise</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($detailLines as $line)
                <tr>
                    <td class="sale-no">{{ $line['sale_number'] }}</td>
                    <td>
                        <span class="item-name">{{ $line['item_name'] }}</span>
                        @if (!empty($line['item_sku']))
                            <span class="item-sku">Réf. {{ $line['item_sku'] }}</span>
                        @endif
                    </td>
                    <td class="right mono">{{ fmt_num($line['quantity']) }}</td>
                    <td class="right mono">{{ fmt_money($line['unit_price']) }}</td>
                    <td class="right mono"><strong>{{ fmt_money($line['line_total']) }}</strong></td>
                    <td>{{ $line['currency_label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucun article vendu.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-note">
        Le N° de vente est repris sur chaque ligne article. Totaux par devise sans conversion.
    </div>
    </div>

    @include('partials.print.auto-print')
</body>
</html>
