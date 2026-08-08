<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Total période filtrée</div>
            <div class="dashboard-kpi__value" style="font-size:1.3rem;">{{ fmt_money($kpis['period_total']) }}</div>
            <div class="dashboard-kpi__meta">XOF · {{ $kpis['count'] }} ligne{{ $kpis['count'] !== 1 ? 's' : '' }} affichée{{ $kpis['count'] !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Encaissements du mois</div>
            <div class="dashboard-kpi__value" style="font-size:1.3rem;">{{ fmt_money($kpis['month_total']) }}</div>
            <div class="dashboard-kpi__meta">Tous clients</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Clients actifs abonnés</div>
            <div class="dashboard-kpi__value">{{ $kpis['active_paying'] }}</div>
        </div>
    </section>

    <section class="cc-card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Ledger des paiements</div>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="clearFilters">Réinitialiser</button>
        </div>
        <div class="form-grid">
            <div class="field">
                <input class="input" type="search" placeholder="Client, code, référence…" wire:model.live.debounce.300ms="search">
            </div>
            <div class="field">
                <select class="input" wire:model.live="tenantCode">
                    <option value="">Toutes les entreprises</option>
                    @foreach ($tenants as $t)
                        <option value="{{ $t->code }}">{{ $t->name }} ({{ $t->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="method">
                    <option value="">Toutes méthodes</option>
                    @foreach ($methodLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="applied">
                    <option value="">Abonnement ou solde</option>
                    <option value="subscription">Abonnement</option>
                    <option value="balance">Solde</option>
                </select>
            </div>
            <div class="field">
                <input class="input" type="date" wire:model.live="from">
            </div>
            <div class="field">
                <input class="input" type="date" wire:model.live="to">
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Méthode</th>
                        <th>Affectation</th>
                        <th>Référence</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td>{{ $p->paid_at?->format('d/m/Y') }}</td>
                            <td>
                                <strong>{{ $p->tenant?->name }}</strong>
                                <div style="font-size:11px;color:#64748b;"><code>{{ $p->tenant?->code }}</code></div>
                            </td>
                            <td><strong>{{ fmt_money($p->amount) }}</strong> {{ $p->currency }}</td>
                            <td>{{ $methodLabels[$p->method] ?? $p->method }}</td>
                            <td>
                                @if ($p->subscription_id)
                                    <span class="badge badge-success">Abonnement</span>
                                    @if ($p->subscription?->plan)
                                        <div style="font-size:11px;color:#64748b;">{{ $p->subscription->plan->name }}</div>
                                    @endif
                                @else
                                    <span class="badge badge-secondary">Solde</span>
                                @endif
                            </td>
                            <td><code>{{ $p->reference ?: '—' }}</code></td>
                            <td>
                                @if ($p->tenant)
                                    <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.subscription', $p->tenant) }}">Fiche</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="stock-empty">Aucun encaissement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
