<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? 'A4'])
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 20px 0 8px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .totals { display: flex; flex-wrap: wrap; gap: 12px; margin: 12px 0 20px; }
        .total-box { border: 1px solid #ccc; padding: 10px 14px; min-width: 140px; }
        .total-box strong { display: block; font-size: 16px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 12px; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button onclick="window.print()">Imprimer</button>
        <a href="{{ route('tenant.sales.daily-report', ['tenant' => request('tenant'), 'date' => $date]) }}">Retour</a>
    </div>

    <h1>{{ $settings['shop_name'] ?? 'Rapport journalier' }}</h1>
    <div class="meta">
        Rapport des ventes du {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}
        — {{ $sales->count() }} vente(s)
        — Imprimé le {{ now()->format('d/m/Y H:i') }}
    </div>

    <h2>Totaux par devise</h2>
    <div class="totals">
        @forelse ($totalsByCurrency as $code => $amount)
            <div class="total-box">
                <span>{{ \App\Services\TenantCurrencyService::label($code) }} ({{ $code }})</span>
                <strong>{{ fmt_money($amount) }}</strong>
            </div>
        @empty
            <p>Aucune vente.</p>
        @endforelse
    </div>

    <h2>Articles vendus</h2>
    <table>
        <thead>
            <tr>
                <th>Article</th>
                <th>Réf.</th>
                <th class="right">Qté</th>
                <th>Devise</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['item_name'] }}</td>
                    <td>{{ $line['item_sku'] ?: '—' }}</td>
                    <td class="right">{{ fmt_num($line['quantity']) }}</td>
                    <td>{{ $line['currency_label'] }}</td>
                    <td class="right">{{ fmt_money($line['amount']) }}</td>
                </tr>
            @endforeach
            @if ($lines->isEmpty())
                <tr><td colspan="5">Aucun article.</td></tr>
            @endif
        </tbody>
    </table>

    <h2>Ventes (détail)</h2>
    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th>Client</th>
                <th>Devise</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sales as $sale)
                @php $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency)); @endphp
                <tr>
                    <td>{{ $sale->sale_number }}</td>
                    <td>{{ $sale->client?->name ?? 'Client occasionnel' }}</td>
                    <td>{{ \App\Services\TenantCurrencyService::label($code) }}</td>
                    <td class="right">{{ fmt_money($sale->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('partials.print.auto-print')
</body>
</html>
