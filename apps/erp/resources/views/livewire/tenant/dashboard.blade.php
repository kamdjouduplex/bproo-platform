@php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code');
    $currency = $currency ?? 'XOF';
    $hasInvoicing = $hasInvoicing ?? false;
    $overview = $overview ?? [];
    $quickActions = $quickActions ?? [];
    $kpis = $overview['kpis'] ?? [];
    $vat = $overview['vat'] ?? null;
    $pending = $overview['pending_invoices'] ?? [];
    $recent = $overview['recent_invoices'] ?? [];
    $alerts = $overview['alerts'] ?? [];
    $activity = $overview['activity'] ?? [];
    $monthLabel = $overview['month_label'] ?? now()->translatedFormat('F Y');

    $urgencyLabel = [
        'urgent' => 'Urgent',
        'watch' => 'À surveiller',
        'normal' => 'Normal',
    ];
    $statusLabel = [
        'paid' => 'Payée',
        'partial' => 'Partielle',
        'issued' => 'Émise',
        'draft' => 'Brouillon',
    ];
@endphp

<div class="dash" wire:loading.class="dash--loading">
    <header class="dash-head">
        <div>
            <h1 class="dash-head__title">Tableau de bord</h1>
            <p class="dash-head__sub">Vue d’ensemble de votre activité</p>
        </div>
        <label class="dash-month">
            <svg class="dash-month__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="dash-month__label">{{ $monthLabel }}</span>
            <input
                class="dash-month__input"
                type="month"
                wire:model.live="month"
                max="{{ now()->format('Y-m') }}"
                min="{{ now()->subYears(3)->format('Y-m') }}"
                aria-label="Période"
            >
        </label>
    </header>

    <section class="dash-kpis" aria-label="Indicateurs du mois">
        @foreach ($kpis as $kpi)
            <article class="dash-kpi dash-kpi--{{ $kpi['tone'] }}">
                <div class="dash-kpi__top">
                    <span class="dash-kpi__label">{{ $kpi['label'] }}</span>
                    <x-ui-icon-box :tone="$kpi['tone']" :icon="$kpi['icon']" />
                </div>
                <div class="dash-kpi__value">
                    {{ fmt_money($kpi['value']) }}
                    <span class="dash-kpi__ccy">{{ $currency }}</span>
                </div>
                <div class="dash-kpi__trend-row">
                    @if ($kpi['trend'] !== null)
                        <span @class([
                            'dash-kpi__trend',
                            'dash-kpi__trend--up' => $kpi['trend'] >= 0,
                            'dash-kpi__trend--down' => $kpi['trend'] < 0,
                        ])>
                            {{ $kpi['trend'] >= 0 ? '↗' : '↘' }}
                            {{ number_format(abs($kpi['trend']), 1, ',', ' ') }}%
                        </span>
                    @else
                        <span class="dash-kpi__trend dash-kpi__trend--flat">—</span>
                    @endif
                    <span class="dash-kpi__vs">{{ $kpi['previous_label'] }} : {{ fmt_money($kpi['previous']) }} {{ $currency }}</span>
                </div>
                @if (!empty($kpi['sparkline']))
                    <svg class="dash-kpi__spark" viewBox="0 0 120 36" preserveAspectRatio="none" aria-hidden="true">
                        <polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $kpi['sparkline'] }}" />
                    </svg>
                @endif
            </article>
        @endforeach
    </section>

    @if ($hasInvoicing && $vat)
        <section class="dash-vat" aria-label="Vue sur la TVA">
            <div class="dash-vat__title">Vue sur la TVA</div>
            <div class="dash-vat__grid">
                @foreach ([
                    ['key' => 'collected', 'label' => 'TVA facturée'],
                    ['key' => 'received', 'label' => 'TVA encaissée'],
                    ['key' => 'withheld', 'label' => 'TVA retenue par les clients'],
                    ['key' => 'to_declare', 'label' => 'TVA à déclarer (facturée − retenue)'],
                ] as $item)
                    @php $cell = $vat[$item['key']] ?? ['value' => 0, 'previous' => 0, 'previous_label' => '']; @endphp
                    <div class="dash-vat__cell {{ $item['key'] === 'to_declare' ? 'dash-vat__cell--accent' : '' }}">
                        <span class="dash-vat__label">{{ $item['label'] }}</span>
                        <strong class="dash-vat__value">{{ fmt_money($cell['value']) }} {{ $currency }}</strong>
                        <span class="dash-vat__vs">{{ $cell['previous_label'] }} : {{ fmt_money($cell['previous']) }} {{ $currency }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="dash-mid">
        <article class="dash-panel">
            <div class="dash-panel__head">
                <h2 class="dash-panel__title">Factures en attente de paiement</h2>
                @if ($hasInvoicing && \Illuminate\Support\Facades\Route::has('tenant.invoicing.index'))
                    <a href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}" class="dash-panel__link">Tout voir</a>
                @endif
            </div>
            <div class="dash-panel__body">
                @if (count($pending) > 0)
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>N° facture</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Échéance</th>
                                <th class="dash-table__num">Montant (TTC)</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $invoice)
                                <tr>
                                    <td>
                                        @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.edit'))
                                            <a href="{{ route('tenant.invoicing.edit', ['invoice' => $invoice['id'], 'tenant' => $tenantCode]) }}" class="dash-table__link">{{ $invoice['invoice_number'] }}</a>
                                        @else
                                            <strong>{{ $invoice['invoice_number'] }}</strong>
                                        @endif
                                    </td>
                                    <td class="dash-table__client">{{ $invoice['client_name'] ?? '—' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($invoice['invoice_date'])->format('d/m/Y') }}</td>
                                    <td>{{ $invoice['due_date'] ? \Carbon\Carbon::parse($invoice['due_date'])->format('d/m/Y') : '—' }}</td>
                                    <td class="dash-table__num">{{ fmt_money($invoice['total']) }}</td>
                                    <td>
                                        <span class="dash-tag dash-tag--{{ $invoice['urgency'] }}">{{ $urgencyLabel[$invoice['urgency']] ?? $invoice['urgency'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4">Total</td>
                                <td class="dash-table__num">{{ fmt_money($overview['pending_total_ttc'] ?? 0) }} {{ $currency }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                @else
                    <p class="dash-empty">Aucune facture en attente de paiement.</p>
                @endif
            </div>
        </article>

        <article class="dash-panel">
            <div class="dash-panel__head">
                <h2 class="dash-panel__title">Dernières factures</h2>
                @if ($hasInvoicing && \Illuminate\Support\Facades\Route::has('tenant.invoicing.index'))
                    <a href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}" class="dash-panel__link">Tout voir</a>
                @endif
            </div>
            <div class="dash-panel__body">
                @if (count($recent) > 0)
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>N° facture</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th class="dash-table__num">Montant (TTC)</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $invoice)
                                <tr>
                                    <td>
                                        @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.edit'))
                                            <a href="{{ route('tenant.invoicing.edit', ['invoice' => $invoice['id'], 'tenant' => $tenantCode]) }}" class="dash-table__link">{{ $invoice['invoice_number'] }}</a>
                                        @else
                                            <strong>{{ $invoice['invoice_number'] }}</strong>
                                        @endif
                                    </td>
                                    <td class="dash-table__client">{{ $invoice['client_name'] ?? '—' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($invoice['invoice_date'])->format('d/m/Y') }}</td>
                                    <td class="dash-table__num">{{ fmt_money($invoice['total']) }}</td>
                                    <td>
                                        <span class="dash-tag dash-tag--status-{{ $invoice['status'] }}">{{ $statusLabel[$invoice['status']] ?? $invoice['status'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">Total</td>
                                <td class="dash-table__num">{{ fmt_money($overview['recent_total_ttc'] ?? 0) }} {{ $currency }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                @else
                    <p class="dash-empty">
                        Aucune facture pour le moment.
                        @if ($hasInvoicing && \Illuminate\Support\Facades\Route::has('tenant.invoicing.create'))
                            <a href="{{ route('tenant.invoicing.create', ['tenant' => $tenantCode]) }}" class="dash-panel__link">Créer une facture</a>
                        @endif
                    </p>
                @endif
            </div>
        </article>
    </section>

    <section class="dash-bottom">
        <article class="dash-panel">
            <div class="dash-panel__head">
                <h2 class="dash-panel__title">Alertes &amp; rappels</h2>
            </div>
            <div class="dash-panel__body">
                @if (count($alerts) > 0)
                    <ul class="dash-alerts">
                        @foreach ($alerts as $alert)
                            <li class="dash-alert dash-alert--{{ $alert['tone'] }}">
                                <span class="dash-alert__icon" aria-hidden="true"></span>
                                <span>{{ $alert['title'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="dash-empty">Aucune alerte pour le moment.</p>
                @endif
            </div>
        </article>

        <article class="dash-panel">
            <div class="dash-panel__head">
                <h2 class="dash-panel__title">Accès rapides</h2>
            </div>
            <div class="dash-panel__body">
                @if (count($quickActions) > 0)
                    <div class="dash-quick">
                        @foreach ($quickActions as $action)
                            <a href="{{ route($action['route'], ['tenant' => $tenantCode]) }}" class="dash-quick__item">
                                <x-ui-icon-box tone="blue" :icon="$action['icon']" />
                                <span>{{ $action['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="dash-empty">Aucun raccourci disponible.</p>
                @endif
            </div>
        </article>

        <article class="dash-panel">
            <div class="dash-panel__head">
                <h2 class="dash-panel__title">Activité récente</h2>
            </div>
            <div class="dash-panel__body">
                @if (count($activity) > 0)
                    <ol class="dash-activity">
                        @foreach ($activity as $event)
                            <li class="dash-activity__item dash-activity__item--{{ $event['tone'] }}">
                                <span class="dash-activity__dot" aria-hidden="true"></span>
                                <div>
                                    <p class="dash-activity__title">{{ $event['title'] }}</p>
                                    <p class="dash-activity__meta">
                                        {{ $event['meta'] }}
                                        @if (!empty($event['amount']))
                                            · {{ fmt_money($event['amount']) }} {{ $currency }}
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="dash-empty">Pas encore d’activité récente.</p>
                @endif
            </div>
        </article>
    </section>
</div>
