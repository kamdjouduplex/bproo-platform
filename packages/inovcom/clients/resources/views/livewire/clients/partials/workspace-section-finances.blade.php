@if ($agingAvailable)
    @if ($aging['total'] > 0)
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        @foreach ($agingLabels as $key => $label)
                            <th style="text-align:right;">{{ $label }}</th>
                        @endforeach
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach ($agingLabels as $key => $label)
                            <td style="text-align:right; {{ in_array($key, ['d90','over90']) && $aging[$key] > 0 ? 'color:#b91c1c; font-weight:600;' : '' }}">
                                {{ fmt_money((float) $aging[$key]) }}
                            </td>
                        @endforeach
                        <td style="text-align:right; font-weight:700;">{{ fmt_money((float) $aging['total']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @if ($aging['over90'] > 0)
            <div class="alert alert-danger" style="margin:12px 0 0;">Attention : {{ fmt_money((float) $aging['over90']) }} FCFA en retard de plus de 90 jours.</div>
        @endif
    @else
        <div class="alert" style="margin:0;">Aucun encours à ventiler.</div>
    @endif
@else
    <div class="alert" style="margin:0;">Balance âgée non disponible.</div>
@endif

@php
    $creditLimit = (float) $client->credit_limit;
    $outstanding = (float) $stats['outstanding'];
    $usage = $creditLimit > 0 ? min(100, round($outstanding / $creditLimit * 100)) : 0;
    $caPaid = (float) $stats['invoicedPaid'];
    $caTotal = (float) $stats['invoicedTotal'];
    $paidPct = $caTotal > 0 ? min(100, round($caPaid / $caTotal * 100)) : 0;
@endphp

<div class="client-finance-grid" style="margin-top:20px;">
    <div class="client-finance-card">
        <span class="client-finance-card__label">Encaissement factures</span>
        <strong>{{ fmt_money($caPaid) }}</strong>
        <div class="client-credit-meter__track" style="margin-top:8px;">
            <div class="client-credit-meter__bar client-credit-meter__bar--ok" style="width:{{ $paidPct }}%;"></div>
        </div>
        <small>{{ $paidPct }}% du CA facturé</small>
    </div>
    <div class="client-finance-card">
        <span class="client-finance-card__label">Encours dettes</span>
        <strong style="color:#b91c1c;">{{ fmt_money($outstanding) }}</strong>
        <small>{{ $stats['openDebts'] }} dette(s) ouverte(s)</small>
    </div>
    <div class="client-finance-card">
        <span class="client-finance-card__label">Limite crédit</span>
        <strong>{{ fmt_money($creditLimit) }}</strong>
        <small>{{ $usage }}% utilisé</small>
    </div>
    <div class="client-finance-card">
        <span class="client-finance-card__label">CA ventes caisse</span>
        <strong>{{ fmt_money((float) $stats['totalSales']) }}</strong>
        <small>{{ $stats['salesCount'] }} vente(s)</small>
    </div>
</div>

@if ($debtsModule && !empty($debtSummary))
    <div class="alert" style="margin-top:16px;margin-bottom:0;">
        Module dettes :
        @if (($debtSummary['has_overdue'] ?? false))
            <span style="color:#b91c1c;font-weight:600;">Retards détectés</span>
        @else
            Aucun retard signalé.
        @endif
    </div>
@endif
