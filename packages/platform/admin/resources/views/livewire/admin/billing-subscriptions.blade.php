<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Entreprises</div>
            <div class="dashboard-kpi__value">{{ $kpis['companies'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Abonnements actifs</div>
            <div class="dashboard-kpi__value">{{ $kpis['active_subs'] }}</div>
            <div class="dashboard-kpi__meta">{{ $kpis['suspended'] }} suspendu{{ $kpis['suspended'] !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">MRR estimé</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ fmt_money($kpis['mrr']) }}</div>
            <div class="dashboard-kpi__meta">XOF / mois</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Encaissé ce mois</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ fmt_money($kpis['month_in']) }}</div>
        </div>
    </section>

    <section class="cc-card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Factures & abonnements</div>
            <div style="display:flex;gap:8px;">
                <a class="btn btn-secondary btn-sm" href="{{ route('system.payments') }}">Encaissements</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.plans') }}">Plans</a>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="clearFilters">Réinitialiser</button>
            </div>
        </div>
        <div class="form-grid">
            <div class="field">
                <input class="input" type="search" placeholder="Nom ou code…" wire:model.live.debounce.300ms="search">
            </div>
            <div class="field">
                <select class="input" wire:model.live="status">
                    <option value="">Tous statuts abo.</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                    <option value="none">Sans abonnement</option>
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="planId">
                    <option value="">Tous les plans</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="activeOnly">
                    <option value="">Actives & inactives</option>
                    <option value="1">Entreprises actives</option>
                    <option value="0">Entreprises inactives</option>
                </select>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>App</th>
                        <th>Statut abo.</th>
                        <th>Plan</th>
                        <th>Prix</th>
                        <th>Fin période</th>
                        <th>Solde</th>
                        <th>Total payé</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        @php $sub = $subsByTenant->get($tenant->id); @endphp
                        <tr>
                            <td>
                                <strong>{{ $tenant->name }}</strong>
                                <div style="font-size:11px;color:#64748b;"><code>{{ $tenant->code }}</code>
                                    @if ($tenant->is_active)
                                        · <span style="color:#15803d;">active</span>
                                    @else
                                        · <span style="color:#b45309;">inactive</span>
                                    @endif
                                </div>
                            </td>
                            <td><span class="badge badge-secondary">{{ $tenant->type_label }}</span></td>
                            <td>
                                @if ($sub)
                                    <span class="badge badge-{{ $sub->status_color }}">{{ $statusLabels[$sub->status] ?? $sub->status }}</span>
                                @else
                                    <span class="badge badge-warning">Aucun</span>
                                @endif
                            </td>
                            <td>{{ $sub?->plan?->name ?? '—' }}</td>
                            <td>
                                @if ($sub?->plan)
                                    {{ fmt_money($sub->plan->price) }}
                                    <div style="font-size:11px;color:#64748b;">{{ $sub->plan->currency }}/{{ $sub->plan->billing_interval === 'yearly' ? 'an' : 'mois' }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $sub?->current_period_end?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ fmt_money($tenant->balance) }}</td>
                            <td>
                                <strong>{{ fmt_money($tenant->total_paid ?? 0) }}</strong>
                                <div style="font-size:11px;color:#64748b;">{{ (int) ($tenant->payments_count ?? 0) }} paiement(s)</div>
                            </td>
                            <td>
                                <a class="btn btn-primary btn-sm" href="{{ route('system.tenants.subscription', $tenant) }}">Facturer</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="stock-empty">Aucun client.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
