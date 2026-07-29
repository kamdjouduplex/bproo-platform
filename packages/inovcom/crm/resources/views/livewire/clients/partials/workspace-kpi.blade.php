@php
    $creditLimit = (float) $client->credit_limit;
    $outstanding = (float) $stats['outstanding'];
    $caTotal = (float) $stats['invoicedTotal'];
    $caUnpaid = (float) $stats['invoicedUnpaid'];
    $usage = $creditLimit > 0 ? min(100, round($outstanding / $creditLimit * 100)) : 0;
@endphp

<div class="client-kpi-grid">
    <div class="client-kpi client-kpi--neutral">
        <span class="client-kpi__label">CA facturé</span>
        <strong class="client-kpi__value">{{ fmt_money($caTotal) }}</strong>
        <span class="client-kpi__hint">{{ $stats['invoiceCount'] }} facture(s)</span>
    </div>
    <div class="client-kpi client-kpi--warn">
        <span class="client-kpi__label">Impayé</span>
        <strong class="client-kpi__value">{{ fmt_money($caUnpaid) }}</strong>
        <span class="client-kpi__hint">{{ $stats['unpaidInvoiceCount'] }} facture(s)</span>
    </div>
    <div class="client-kpi client-kpi--info">
        <span class="client-kpi__label">Devis</span>
        <strong class="client-kpi__value">{{ fmt_money((float) $stats['quotationTotal']) }}</strong>
        <span class="client-kpi__hint">{{ $stats['quotationCount'] }} devis</span>
    </div>
    <div class="client-kpi client-kpi--success">
        <span class="client-kpi__label">Avoir client</span>
        <strong class="client-kpi__value">{{ fmt_money((float) $stats['clientCredit']) }}</strong>
        <span class="client-kpi__hint">Crédit disponible</span>
    </div>
</div>

@if ($creditLimit > 0)
    <div class="client-credit-meter">
        <div class="client-credit-meter__labels">
            <span>Limite crédit : {{ fmt_money($creditLimit) }}</span>
            <span>{{ $usage }}% utilisé</span>
        </div>
        <div class="client-credit-meter__track">
            <div class="client-credit-meter__bar client-credit-meter__bar--{{ $usage >= 100 ? 'danger' : ($usage >= 80 ? 'warn' : 'ok') }}"
                 style="width:{{ $usage }}%;"></div>
        </div>
    </div>
@endif
