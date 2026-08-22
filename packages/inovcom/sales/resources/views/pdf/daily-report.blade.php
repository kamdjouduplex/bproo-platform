<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport ventes {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</title>
    <style>
        @page { margin: 16px 18px 24px 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9px; margin: 0; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .header td { vertical-align: top; border: none; padding: 0; }
        .brand-name { font-size: 14px; font-weight: bold; color: #0f172a; margin: 0 0 3px; }
        .brand-meta { font-size: 8px; color: #64748b; line-height: 1.4; }
        .doc-box { border: 1.5px solid #111; }
        .doc-box table { width: 100%; border-collapse: collapse; }
        .doc-box th, .doc-box td { border: 1px solid #111; padding: 5px 7px; text-align: center; }
        .doc-box th { background-color: #0f766e; color: #ffffff; font-size: 8px; text-transform: uppercase; font-weight: bold; }
        .doc-title { font-size: 12px; font-weight: bold; }
        .summary { margin: 0 0 10px; font-size: 9px; color: #334155; }
        .summary strong { color: #0f172a; }
        .totals { width: 100%; border-collapse: collapse; margin: 0 0 12px; }
        .totals td { border: 1px solid #e2e8f0; background: #f8fafc; padding: 6px 8px; width: 25%; }
        .totals .label { font-size: 7.5px; color: #64748b; text-transform: uppercase; }
        .totals .value { font-size: 12px; font-weight: bold; margin-top: 2px; }
        h2 { font-size: 10px; text-transform: uppercase; margin: 14px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; }
        table.data { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { padding: 4px 5px; text-align: left; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        th { background: #0f766e; color: #fff; font-size: 7.5px; text-transform: uppercase; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .muted { color: #64748b; font-size: 7.5px; }
        .footer { margin-top: 10px; font-size: 8px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
@php
    $settings = $settings ?? [];
    $shopName = $settings['shop_name'] ?? ($shopName ?? 'Bproo Pharma');
    $generatedAt = $generatedAt ?? now();
    $defaultCurrency = $defaultCurrency ?? 'XOF';
@endphp

<table class="header">
    <tr>
        <td style="width:62%;padding-right:12px;">
            <div class="brand-name">{{ $shopName }}</div>
            <div class="brand-meta">
                Rapport des ventes journalières
                @if (!empty($settings['shop_address']))<br>{{ $settings['shop_address'] }}@endif
            </div>
        </td>
        <td style="width:38%;">
            <div class="doc-box">
                <table>
                    <tr><th>Date du rapport</th><th>Document</th></tr>
                    <tr>
                        <td class="doc-title">{{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</td>
                        <td class="doc-title">Rapport journalier</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="summary">
    <strong>{{ $sales->count() }}</strong> vente(s)
    · <strong>{{ $detailLines->count() }}</strong> ligne(s) article
    · Généré le {{ $generatedAt->format('d/m/Y à H:i') }}
</div>

@if ((is_countable($totalsByCurrency) ? count($totalsByCurrency) : 0) > 0)
    <table class="totals">
        <tr>
            @foreach ($totalsByCurrency as $code => $amount)
                <td>
                    <div class="label">{{ \App\Services\TenantCurrencyService::label($code) }} ({{ $code }})</div>
                    <div class="value">{{ number_format((float) $amount, 0, ',', ' ') }}</div>
                </td>
            @endforeach
        </tr>
    </table>
@endif

<h2>1. Liste des ventes</h2>
<table class="data">
    <thead>
        <tr>
            <th style="width:16%;">N° vente</th>
            <th style="width:28%;">Client</th>
            <th style="width:22%;">Vendeur</th>
            <th style="width:14%;">Devise</th>
            <th class="right" style="width:20%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($sales as $sale)
            @php $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency)); @endphp
            <tr>
                <td><strong>{{ $sale->sale_number }}</strong></td>
                <td>{{ $sale->client?->name ?? 'Client occasionnel' }}</td>
                <td>{{ $sale->creator?->name ?? '—' }}</td>
                <td>{{ \App\Services\TenantCurrencyService::label($code) }}</td>
                <td class="right"><strong>{{ number_format((float) $sale->total, 0, ',', ' ') }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="5">Aucune vente pour cette date.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>2. Détail des articles</h2>
<table class="data">
    <thead>
        <tr>
            <th style="width:14%;">N° vente</th>
            <th style="width:32%;">Article</th>
            <th class="right" style="width:10%;">Qté</th>
            <th class="right" style="width:14%;">P.U.</th>
            <th class="right" style="width:16%;">Montant</th>
            <th style="width:14%;">Devise</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($detailLines as $line)
            <tr>
                <td><strong>{{ $line['sale_number'] }}</strong></td>
                <td>
                    {{ $line['item_name'] }}
                    @if (!empty($line['item_sku']))
                        <div class="muted">Réf. {{ $line['item_sku'] }}</div>
                    @endif
                </td>
                <td class="right">{{ number_format((float) $line['quantity'], 2, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $line['unit_price'], 0, ',', ' ') }}</td>
                <td class="right"><strong>{{ number_format((float) $line['line_total'], 0, ',', ' ') }}</strong></td>
                <td>{{ $line['currency_label'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Aucun article vendu pour cette date.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ $shopName }} · Totaux par devise sans conversion · Archive administration
</div>
</body>
</html>
