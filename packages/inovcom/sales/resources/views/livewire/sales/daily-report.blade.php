@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card" style="margin-bottom: 16px;">
        <div class="table-toolbar" style="flex-wrap: wrap; gap: 12px;">
            <div class="table-title">Médicaments / articles vendus</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input class="input input-sm" type="date" wire:model.live="date" style="width: 160px;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="setToday">Aujourd'hui</button>
                <a class="btn btn-primary btn-sm" target="_blank"
                   href="{{ route('tenant.sales.daily-report.print', ['tenant' => $tenantCode, 'date' => $date]) }}">
                    Imprimer
                </a>
                @if (\Illuminate\Support\Facades\Route::has('tenant.stock.index'))
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">
                        Stock restant (PDF)
                    </a>
                @endif
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.sales.index', ['tenant' => $tenantCode]) }}">Retour ventes</a>
            </div>
        </div>
        <p style="margin: 0; color: #64748b; font-size: 13px;">
            {{ $salesCount }} vente(s) le {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}.
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
        @if (! $hasCurrencyColumn)
            <p style="margin-top:10px; color:#b45309; font-size:12px;">
                Colonnes multi-devises absentes : lancez <code>php artisan tenant:migrate VOTRE_CODE</code> sur l’app produit.
            </p>
        @endif
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Détail articles</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Réf.</th>
                        <th>Qté</th>
                        <th>Devise</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $line['item_name'] }}</td>
                            <td>{{ $line['item_sku'] ?: '—' }}</td>
                            <td>{{ fmt_num($line['quantity']) }}</td>
                            <td>{{ $line['currency_label'] }}</td>
                            <td><strong>{{ fmt_money($line['amount']) }}</strong></td>
                        </tr>
                    @endforeach
                    @if ($lines->isEmpty())
                        <tr>
                            <td colspan="5">Aucun article vendu pour cette date.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>
</div>
