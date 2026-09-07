<div class="page-body">
    @php
        $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
        $today = now()->startOfDay();
    @endphp
    <style>
        .pay-tabs { display:flex; gap:4px; flex-wrap:wrap; border-bottom:1px solid #e5e7eb; margin-bottom:14px; }
        .pay-tab {
            border:none; background:none; padding:10px 14px; cursor:pointer;
            font-weight:600; font-size:14px; color:#6b7280;
            border-bottom:2px solid transparent; margin-bottom:-1px;
            display:inline-flex; align-items:center; gap:8px;
        }
        .pay-tab.is-active { color:#2563eb; border-bottom-color:#2563eb; }
        .pay-tab__count {
            font-size:11px; font-weight:700; background:#f1f5f9; color:#475569;
            border-radius:999px; padding:1px 8px;
        }
        .pay-tab.is-active .pay-tab__count { background:#dbeafe; color:#1d4ed8; }
        .pay-recap { margin:0 0 14px; }
        .pay-recap__hint { margin:0 0 10px; font-size:12px; color:#64748b; }
        .pay-recap__grid {
            display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px;
        }
        .pay-recap__card {
            border:1px solid #e2e8f0; background:#f8fafc; border-radius:10px; padding:12px 14px;
        }
        .pay-recap__label {
            font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.03em;
        }
        .pay-recap__prev { margin-top:4px; font-size:18px; font-weight:700; color:#0f172a; line-height:1.2; }
        .pay-recap__prev-label { font-size:11px; color:#64748b; margin-top:2px; }
        .pay-recap__curr { margin-top:8px; font-size:13px; color:#475569; }
        .pay-recap__curr-label { font-size:11px; color:#94a3b8; }
        .pay-recap__card--vat-in .pay-recap__prev { color:#166534; }
        .pay-recap__card--vat-out .pay-recap__prev { color:#1d4ed8; }
        @media (max-width: 900px) {
            .pay-recap__grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
    </style>
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif

    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <div>
                <h2 class="client-list-head__title">Encaissements</h2>
                <p class="invoice-list-subtitle">
                    @if ($tab === 'unpaid')
                        Factures encore ouvertes, à encaisser.
                    @else
                        Reçus des factures déjà encaissées — recherchez puis ouvrez le détail.
                    @endif
                </p>
            </div>
            <div class="client-list-head__actions">
                @if ($canManageWithholdings ?? false)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoice_payments.withholding_types', ['tenant' => $tenantCode]) }}">Types de retenues</a>
                @endif
            </div>
        </div>

        <div class="pay-tabs" role="tablist" aria-label="Encaissements">
            <button type="button" class="pay-tab {{ $tab === 'unpaid' ? 'is-active' : '' }}"
                    role="tab" aria-selected="{{ $tab === 'unpaid' ? 'true' : 'false' }}"
                    wire:click="setTab('unpaid')">
                Non encaissées
                <span class="pay-tab__count">{{ $unpaidCount }}</span>
            </button>
            <button type="button" class="pay-tab {{ $tab === 'collected' ? 'is-active' : '' }}"
                    role="tab" aria-selected="{{ $tab === 'collected' ? 'true' : 'false' }}"
                    wire:click="setTab('collected')">
                Déjà encaissées
                <span class="pay-tab__count">{{ $collectedCount }}</span>
            </button>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ $tab === 'collected' ? 'N° reçu, facture, devis, BC, client…' : 'N° facture, devis, BC, client…' }}"
                    aria-label="Rechercher">
            </div>
            <div class="client-filter-bar__tools">
                <label class="client-filter-bar__per-page">
                    <span class="sr-only">Résultats par page</span>
                    <select class="input input-sm" wire:model.live="perPage" aria-label="Par page">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
        </div>

        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
            <span style="font-size:12px;color:#64748b;font-weight:600;">Période</span>
            <input class="input input-sm" type="date" wire:model.live="dateFrom" aria-label="Du" title="Du" style="width:150px;">
            <span style="color:#94a3b8;" aria-hidden="true">→</span>
            <input class="input input-sm" type="date" wire:model.live="dateTo" aria-label="Au" title="Au" style="width:150px;">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('day')">Aujourd'hui</button>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('week')">Semaine</button>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('month')">Mois</button>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('year')">Année</button>
            @if ($dateFrom !== '' || $dateTo !== '')
                <button type="button" class="btn btn-secondary btn-sm" wire:click="clearPeriod">Tout</button>
            @endif
        </div>

        @if ($tab === 'unpaid')
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Facture</th>
                            <th>Client</th>
                            <th>Échéance</th>
                            <th>Total TTC</th>
                            <th>Déjà réglé</th>
                            <th>Solde dû</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($unpaidInvoices as $inv)
                            @php
                                $due = $inv->due_date;
                                $isLate = $due && $due->lt($today);
                            @endphp
                            <tr wire:key="unpaid-{{ $inv->id }}">
                                <td>
                                    <strong>{{ $inv->invoice_number }}</strong>
                                    <div style="font-size:11px;color:#6b7280;">
                                        {{ \InovCom\Invoicing\Models\Invoice::statusLabel($inv->status) }}
                                        @if ($inv->customer_reference)
                                            · BC {{ $inv->customer_reference }}
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $inv->client?->name ?? '—' }}</td>
                                <td class="{{ $isLate ? 'invoice-due--late' : '' }}">
                                    {{ $due?->format('d/m/Y') ?? '—' }}
                                    @if ($isLate)
                                        <div style="font-size:11px;">En retard</div>
                                    @endif
                                </td>
                                <td>{{ fmt_money($inv->total) }}</td>
                                <td>{{ fmt_money($inv->amount_paid) }}</td>
                                <td><strong>{{ fmt_money($inv->balance) }}</strong></td>
                                <td style="white-space:nowrap;text-align:right;">
                                    @if ($canReceive && $inv->canReceivePayment())
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoice_payments.pay', [$inv->id, 'tenant' => $tenantCode]) }}">Encaisser</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if ($unpaidInvoices->isEmpty())
                            <tr>
                                <td colspan="7" style="text-align:center;color:#6b7280;padding:20px;">
                                    {{ ($search !== '' || $dateFrom !== '' || $dateTo !== '') ? 'Aucune facture non encaissée pour ces critères.' : 'Aucune facture à encaisser.' }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="table-pagination">{{ $unpaidInvoices->links() }}</div>
        @else
            @if ($fiscalRecap)
                @php
                    $prev = $fiscalRecap['previous'];
                    $curr = $fiscalRecap['current'];
                    $recapCards = [
                        ['key' => 'ttc', 'label' => 'TTC', 'class' => ''],
                        ['key' => 'ht', 'label' => 'HT', 'class' => ''],
                        ['key' => 'vat_collected', 'label' => 'TVA encaissée', 'class' => 'pay-recap__card--vat-in'],
                        ['key' => 'vat_withheld', 'label' => 'TVA retenue', 'class' => 'pay-recap__card--vat-out'],
                    ];
                @endphp
                <div class="pay-recap">
                    <p class="pay-recap__hint">
                        Fiscalité à déclarer : <strong>{{ $fiscalRecap['previous_label'] }}</strong>
                        · en cours : {{ $fiscalRecap['current_label'] }}
                    </p>
                    <div class="pay-recap__grid">
                        @foreach ($recapCards as $card)
                            <div class="pay-recap__card {{ $card['class'] }}">
                                <div class="pay-recap__label">{{ $card['label'] }}</div>
                                <div class="pay-recap__prev">{{ fmt_money($prev[$card['key']]) }}</div>
                                <div class="pay-recap__prev-label">{{ $fiscalRecap['previous_label'] }}</div>
                                <div class="pay-recap__curr">{{ fmt_money($curr[$card['key']]) }}</div>
                                <div class="pay-recap__curr-label">{{ $fiscalRecap['current_label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Facture</th>
                            <th>Client</th>
                            <th>N° reçu</th>
                            <th>Date</th>
                            <th>Perçu</th>
                            <th>TTC réglé</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $p)
                            @php
                                $invoice = $p->invoice;
                                $quoteNumber = $invoice?->quotation?->number ?: ($invoice?->quotation_reference ?: null);
                                $statusLabel = \InovCom\InvoicePayments\Support\PaymentSettlementLines::statusLabelFromBalance(
                                    $p->balance_after !== null ? (float) $p->balance_after : null,
                                    $p->isCancelled(),
                                );
                                $statusPaid = ! $p->isCancelled() && $p->balance_after !== null && (float) $p->balance_after <= 0.5;
                            @endphp
                            <tr wire:key="collected-{{ $p->id }}">
                                <td>
                                    <strong>{{ $invoice?->invoice_number ?? '—' }}</strong>
                                    @if ($quoteNumber || $invoice?->customer_reference)
                                        <div style="font-size:11px;color:#9ca3af;">
                                            @if ($quoteNumber)Devis {{ $quoteNumber }}@endif
                                            @if ($quoteNumber && $invoice?->customer_reference) · @endif
                                            @if ($invoice?->customer_reference)BC {{ $invoice->customer_reference }}@endif
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $invoice?->client?->name ?? '—' }}</td>
                                <td><strong>{{ $p->reference }}</strong></td>
                                <td>
                                    {{ $p->payment_date?->format('d/m/Y') }}
                                    @if ($p->created_at)
                                        <div style="font-size:11px;color:#9ca3af;">{{ $p->created_at->format('H:i') }}</div>
                                    @endif
                                </td>
                                <td style="color:#166534;font-weight:600;">+ {{ fmt_money(abs((float) $p->amount)) }}</td>
                                <td><strong>{{ fmt_money($p->settledAmount()) }}</strong></td>
                                <td>
                                    <span class="badge {{ $statusPaid ? 'badge-success' : 'badge-warning' }}">{{ $statusLabel }}</span>
                                </td>
                                <td style="white-space:nowrap;text-align:right;">
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoice_payments.show', ['invoicePayment' => $p->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                </td>
                            </tr>
                        @endforeach
                        @if ($payments->isEmpty())
                            <tr>
                                <td colspan="8" style="text-align:center;color:#6b7280;padding:20px;">
                                    {{ ($search !== '' || $dateFrom !== '' || $dateTo !== '') ? 'Aucun encaissement pour ces critères.' : 'Aucun encaissement enregistré.' }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="table-pagination">{{ $payments->links() }}</div>
        @endif
    </section>
</div>
