@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif
    <section class="card" style="margin-bottom: 16px;">
        <div class="table-toolbar" style="flex-wrap: wrap; gap: 12px;">
            <div class="table-title">Rapport journalier</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input class="input input-sm" type="date" wire:model.live="date" style="width: 160px;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="setToday">Aujourd'hui</button>
                <x-export-btn format="excel" class="btn-sm" wire:click="exportExcel">Exporter Excel</x-export-btn>
                <x-export-btn format="pdf" class="btn-sm" wire:click="exportPdf">Exporter PDF</x-export-btn>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.sales.index', ['tenant' => $tenantCode]) }}">Retour ventes</a>
            </div>
        </div>
        <p style="margin: 0; color: #64748b; font-size: 13px;">
            {{ $salesCount }} vente(s) · {{ $detailLines->count() }} ligne(s) article le {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}.
            Totaux séparés par devise (aucune conversion).
        </p>
    </section>

    <section class="card app-table-card" style="margin-bottom: 16px;">
        <div class="table-title" style="margin-bottom: 12px;">Totaux encaissés (par devise)</div>
        <div style="display:flex; flex-wrap:wrap; gap:12px;">
            @forelse ($totalsByCurrency as $code => $amount)
                <div style="padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 8px; min-width: 160px; background:#f8fafc;">
                    <div style="font-size:12px; color:#64748b;">{{ \App\Services\TenantCurrencyService::label($code) }} ({{ $code }})</div>
                    <div style="font-size:22px; font-weight:700;">{{ fmt_money($amount) }}</div>
                </div>
            @empty
                <p style="color:#64748b;">Aucune vente ce jour.</p>
            @endforelse
        </div>
    </section>

    <section class="card app-table-card" style="margin-bottom: 16px;">
        <div class="table-toolbar">
            <div class="table-title">1. Liste des ventes</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° vente</th>
                        <th>Client</th>
                        <th>Vendeur</th>
                        <th>Devise</th>
                        <th>Total</th>
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
                            <td><strong>{{ fmt_money($sale->total) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucune vente pour cette date.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">2. Détail des articles</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° vente</th>
                        <th>Article</th>
                        <th>Réf.</th>
                        <th>Qté</th>
                        <th>P.U.</th>
                        <th>Montant</th>
                        <th>Devise</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($detailLines as $line)
                        <tr>
                            <td><strong>{{ $line['sale_number'] }}</strong></td>
                            <td>{{ $line['item_name'] }}</td>
                            <td>{{ $line['item_sku'] ?: '—' }}</td>
                            <td>{{ fmt_num($line['quantity']) }}</td>
                            <td>{{ fmt_money($line['unit_price']) }}</td>
                            <td><strong>{{ fmt_money($line['line_total']) }}</strong></td>
                            <td>{{ $line['currency_label'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucun article vendu pour cette date.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
