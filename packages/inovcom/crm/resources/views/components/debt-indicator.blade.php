@props([
    'summary' => [],
    'variant' => 'compact',
    'tenantCode' => null,
    'clientId' => null,
    'debtsModule' => false,
])

@php
    $level = $summary['level'] ?? 'clear';
    $outstanding = (float) ($summary['outstanding'] ?? 0);
    $openCount = (int) ($summary['open_count'] ?? 0);
    $overdueCount = (int) ($summary['overdue_count'] ?? 0);
    $hasDebt = (bool) ($summary['has_debt'] ?? false);
    $debtsUrl = ($debtsModule && $tenantCode && $clientId)
        ? route('tenant.debts.index', ['tenant' => $tenantCode, 'client' => $clientId])
        : null;
@endphp

@if (!$debtsModule)
    <span class="client-debt-indicator client-debt-indicator--na" title="Module dettes non disponible">—</span>
@elseif ($variant === 'hero')
    <div @class([
        'client-debt-hero',
        'client-debt-hero--clear' => $level === 'clear',
        'client-debt-hero--active' => $level === 'active',
        'client-debt-hero--overdue' => $level === 'overdue',
    ]) role="status">
        <div class="client-debt-hero__icon" aria-hidden="true">
            @if ($level === 'clear')
                ✓
            @elseif ($level === 'overdue')
                !
            @else
                ₣
            @endif
        </div>
        <div class="client-debt-hero__body">
            @if ($level === 'clear')
                <div class="client-debt-hero__title">Aucune dette en cours</div>
                <div class="client-debt-hero__subtitle">Ce client est à jour sur ses remboursements.</div>
            @elseif ($level === 'overdue')
                <div class="client-debt-hero__title">Dette en retard — action requise</div>
                <div class="client-debt-hero__amount">{{ fmt_money($outstanding) }} FCFA</div>
                <div class="client-debt-hero__subtitle">
                    {{ $overdueCount }} dette(s) en retard
                    @if ($openCount > $overdueCount)
                        · {{ $openCount }} dette(s) ouverte(s) au total
                    @endif
                </div>
            @else
                <div class="client-debt-hero__title">Dette client en cours</div>
                <div class="client-debt-hero__amount">{{ fmt_money($outstanding) }} FCFA</div>
                <div class="client-debt-hero__subtitle">{{ $openCount }} dette(s) à encaisser</div>
            @endif
        </div>
        @if ($debtsUrl && $hasDebt)
            <a class="btn {{ $level === 'overdue' ? 'btn-primary' : 'btn-secondary' }} client-debt-hero__action" href="{{ $debtsUrl }}">
                Voir les dettes
            </a>
        @endif
    </div>
@else
    @if ($level === 'clear')
        <span class="client-debt-badge client-debt-badge--clear" title="Aucune dette">À jour</span>
    @elseif ($level === 'overdue')
        <span class="client-debt-badge client-debt-badge--overdue" title="{{ $overdueCount }} dette(s) en retard">
            <span class="client-debt-badge__alert">RETARD</span>
            <strong>{{ fmt_money($outstanding) }}</strong>
        </span>
    @else
        <span class="client-debt-badge client-debt-badge--active" title="{{ $openCount }} dette(s) ouverte(s)">
            <strong>{{ fmt_money($outstanding) }}</strong>
        </span>
    @endif
@endif
