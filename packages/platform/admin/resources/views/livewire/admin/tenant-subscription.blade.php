<div class="cc-billing">
    {{-- Context bar --}}
    <div class="cc-billing__context">
        <div class="cc-billing__context-main">
            <div class="cc-billing__company">
                <span class="cc-billing__code">{{ $tenant->code }}</span>
                <strong>{{ $tenant->name }}</strong>
                <span class="badge badge-secondary">{{ $tenant->type_label }}</span>
                @if ($tenant->is_active)
                    <span class="badge badge-success">Entreprise active</span>
                @else
                    <span class="badge badge-warning">Entreprise inactive</span>
                @endif
            </div>
            <div class="cc-billing__links">
                <a href="{{ route('system.tenants.show', $tenant) }}">Fiche</a>
                <a href="{{ route('system.tenants.edit', $tenant) }}">Modifier</a>
                <a href="{{ route('system.tenants.settings', $tenant) }}">Paramètres</a>
                <a href="{{ route('system.payments') }}?tenant={{ $tenant->code }}">Tous les encaissements</a>
            </div>
        </div>
        <div class="cc-billing__actions">
            <button type="button" class="btn btn-primary" wire:click="$toggle('showPaymentPanel')">
                {{ $showPaymentPanel ? 'Fermer' : 'Enregistrer un paiement' }}
            </button>
        </div>
    </div>

    {{-- KPI strip --}}
    <section class="dashboard-kpis cc-billing__kpis">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Statut abonnement</div>
            <div class="dashboard-kpi__value" style="font-size:1.15rem;">
                @if ($subscription)
                    <span class="badge badge-{{ $subscription->status_color }}">{{ $statusLabels[$subscription->status] ?? $subscription->status }}</span>
                @else
                    <span class="badge badge-warning">Aucun</span>
                @endif
            </div>
            <div class="dashboard-kpi__meta">
                @if ($subscription?->plan)
                    {{ $subscription->plan->name }}
                    · {{ $planRateLabel ?? $subscription->plan->rateLabel($tenant) }}
                @else
                    Pas de plan
                @endif
            </div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Solde prépayé</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ fmt_money($tenant->balance) }}</div>
            <div class="dashboard-kpi__meta">{{ $kpis['currency'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Encaissé (total)</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ fmt_money($kpis['total_paid']) }}</div>
            <div class="dashboard-kpi__meta">{{ $kpis['payments_count'] }} paiement{{ $kpis['payments_count'] !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Ce mois</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ fmt_money($kpis['paid_month']) }}</div>
            <div class="dashboard-kpi__meta">Année : {{ fmt_money($kpis['paid_year']) }} {{ $kpis['currency'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Fin de période</div>
            <div class="dashboard-kpi__value" style="font-size:1.15rem;">
                {{ $subscription?->current_period_end?->format('d/m/Y') ?? '—' }}
            </div>
            <div class="dashboard-kpi__meta">
                @if ($kpis['days_remaining'] !== null)
                    @if ($kpis['days_remaining'] < 0)
                        Expiré depuis {{ abs($kpis['days_remaining']) }} j
                    @elseif ($kpis['days_remaining'] === 0)
                        Expire aujourd’hui
                    @else
                        {{ $kpis['days_remaining'] }} j restants
                    @endif
                @else
                    —
                @endif
            </div>
        </div>
    </section>

    @if ($subscription && $kpis['period_progress'] !== null)
        <div class="cc-billing__progress cc-card">
            <div class="cc-billing__progress-head">
                <span>Période en cours</span>
                <span>{{ $subscription->current_period_start->format('d/m/Y') }} → {{ $subscription->current_period_end->format('d/m/Y') }}</span>
            </div>
            <div class="cc-billing__progress-bar">
                <div class="cc-billing__progress-fill" style="width: {{ $kpis['period_progress'] }}%"></div>
            </div>
            @if ($subscription->inGrace())
                <div class="cc-billing__grace">Grâce jusqu’au {{ $subscription->grace_ends_at->format('d/m/Y') }}</div>
            @endif
        </div>
    @endif

    {{-- Lifecycle actions --}}
    @if ($subscription?->isActive())
        <section class="cc-card cc-billing__lifecycle">
            <div class="cc-card__head">
                <h2 class="cc-card__title">Actions d’abonnement</h2>
            </div>
            <div class="cc-card__body" style="display:flex;flex-wrap:wrap;gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="$toggle('showBalancePanel')">Utiliser le solde</button>
                @if ($plans->count() > 1)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="$toggle('showPlanPanel')">Changer de plan</button>
                @endif
                @if (!($subscription->plan->is_demo ?? false))
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="grantGrace(5)">Grâce 5 j</button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="grantGrace(10)">Grâce 10 j</button>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="grantGrace(15)">Grâce 15 j</button>
                @endif
                <button type="button" class="btn btn-secondary btn-sm" wire:click="suspendSubscription" wire:confirm="Suspendre l’abonnement et désactiver l’entreprise ?">Suspendre</button>
                <button type="button" class="btn btn-danger btn-sm" wire:click="cancelSubscription" wire:confirm="Annuler définitivement ? Reliquat → solde, entreprise désactivée.">Annuler</button>
            </div>
        </section>
    @elseif (!$subscription)
        <section class="cc-card" style="border-color:#fde68a;background:#fffbeb;margin-bottom:14px;">
            <div class="cc-card__body">
                Aucun abonnement. Enregistrez un paiement avec un plan : précisez le nombre de mois ou laissez le calcul automatique (montant ÷ tarif mensuel).
            </div>
        </section>
    @endif

    {{-- Record payment panel --}}
    @if ($showPaymentPanel)
        <section class="cc-card cc-billing__panel">
            <div class="cc-card__head">
                <h2 class="cc-card__title">Enregistrer un paiement</h2>
            </div>
            <div class="cc-card__body">
                <p class="cc-billing__hint">
                    <strong>Forfait mensuel</strong> : montant ÷ prix/mois (ou précisez les mois).<br>
                    <strong>Par utilisateur</strong> : montant ÷ (prix/siège × sièges du client). Reliquat → solde.
                </p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label">Montant payé</label>
                        <input class="input" type="number" min="0" step="0.01" wire:model.live="payment_amount" placeholder="30000">
                    </div>
                    <div class="field">
                        <label class="field-label">Appliquer à un plan</label>
                        <select class="input" wire:model.live="payment_plan_id">
                            <option value="">— Solde uniquement —</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">
                                    {{ $plan->name }}
                                    · {{ $plan->isPerSeat() ? 'par user' : 'forfait' }}
                                    · {{ $plan->rateLabel($tenant) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Mois prépayés (optionnel)</label>
                        <input class="input" type="number" min="1" max="120" wire:model.live="payment_months" placeholder="Auto">
                        <span class="field-hint">Ex. 3 = forcer 3 mois. Vide = calcul auto depuis le montant.</span>
                    </div>
                    <div class="field">
                        <label class="field-label">Devise</label>
                        <input class="input" wire:model="payment_currency" maxlength="5">
                    </div>
                    <div class="field">
                        <label class="field-label">Méthode</label>
                        <select class="input" wire:model="payment_method">
                            @foreach ($methodLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Référence</label>
                        <input class="input" wire:model="payment_reference" placeholder="N° reçu, virement…">
                    </div>
                    <div class="field" style="grid-column:1/-1;">
                        <label class="field-label">Notes</label>
                        <input class="input" wire:model="payment_notes" placeholder="Optionnel">
                    </div>
                </div>

                @if ($paymentQuote)
                    <div style="margin-top:12px;padding:12px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;">
                        @if (!empty($paymentQuote['error']))
                            <strong style="color:#b91c1c;">{{ $paymentQuote['error'] }}</strong>
                        @else
                            <strong>Aperçu :</strong>
                            {{ $paymentQuote['months'] }} mois
                            @if ($paymentQuote['seats'])
                                · {{ $paymentQuote['seats'] }} siège(s)
                            @endif
                            · tarif {{ fmt_money($paymentQuote['unit_price']) }} {{ $payment_currency }}/mois
                            · consommé {{ fmt_money($paymentQuote['amount_used']) }}
                            @if ($paymentQuote['remainder'] > 0)
                                · reliquat solde {{ fmt_money($paymentQuote['remainder']) }}
                            @endif
                        @endif
                    </div>
                @endif

                <div class="page-actions" style="margin-top:12px;">
                    <button type="button" class="btn btn-secondary" wire:click="$set('showPaymentPanel', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="recordPayment" wire:loading.attr="disabled">Enregistrer le paiement</button>
                </div>
            </div>
        </section>
    @endif

    @if ($showBalancePanel && $subscription)
        <section class="cc-card cc-billing__panel">
            <div class="cc-card__head"><h2 class="cc-card__title">Utiliser le solde</h2></div>
            <div class="cc-card__body">
                <p class="cc-billing__hint">
                    Solde : <strong>{{ fmt_money($tenant->balance) }} {{ $tenant->balance_currency }}</strong>
                    · Tarif : {{ $planRateLabel ?? ($subscription->plan?->rateLabel($tenant) ?? '—') }}
                </p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label">Nombre de mois</label>
                        <input class="input" type="number" min="1" max="120" wire:model="apply_balance_months">
                    </div>
                </div>
                <div class="page-actions" style="margin-top:12px;">
                    <button type="button" class="btn btn-secondary" wire:click="$set('showBalancePanel', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="applyBalance" wire:loading.attr="disabled">Appliquer</button>
                </div>
            </div>
        </section>
    @endif

    @if ($showPlanPanel && $subscription)
        <section class="cc-card cc-billing__panel">
            <div class="cc-card__head"><h2 class="cc-card__title">Changer de plan</h2></div>
            <div class="cc-card__body">
                <p class="cc-billing__hint">Le reliquat de période est remboursé au solde. Le solde doit couvrir au moins 1 mois du nouveau plan.</p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label">Nouveau plan</label>
                        <select class="input" wire:model="new_plan_id">
                            <option value="">— Choisir —</option>
                            @foreach ($plans as $plan)
                                @if ($plan->id !== $subscription->plan_id)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} · {{ fmt_money($plan->price) }} {{ $plan->currency }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="page-actions" style="margin-top:12px;">
                    <button type="button" class="btn btn-secondary" wire:click="$set('showPlanPanel', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="changePlan" wire:loading.attr="disabled" wire:confirm="Confirmer le changement de plan ?">Changer</button>
                </div>
            </div>
        </section>
    @endif

    <div class="cc-billing__grid">
        {{-- Payment history --}}
        <section class="cc-card app-table-card">
            <div class="cc-card__head">
                <h2 class="cc-card__title">Historique des paiements</h2>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="clearPaymentFilters">Réinitialiser filtres</button>
            </div>
            <div class="cc-card__body" style="padding-bottom:0;">
                <div class="form-grid cc-billing__filters">
                    <div class="field">
                        <input class="input" type="search" placeholder="Référence, notes…" wire:model.live.debounce.300ms="paySearch">
                    </div>
                    <div class="field">
                        <select class="input" wire:model.live="payMethod">
                            <option value="">Toutes méthodes</option>
                            @foreach ($methodLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <select class="input" wire:model.live="payApplied">
                            <option value="">Tous les types</option>
                            <option value="subscription">Appliqué à l’abonnement</option>
                            <option value="balance">Crédit solde</option>
                        </select>
                    </div>
                    <div class="field">
                        <input class="input" type="date" wire:model.live="payFrom" title="Du">
                    </div>
                    <div class="field">
                        <input class="input" type="date" wire:model.live="payTo" title="Au">
                    </div>
                </div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Affectation</th>
                            <th>Référence</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $p)
                            <tr>
                                <td>{{ $p->paid_at?->format('d/m/Y') }}</td>
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
                                <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $p->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="stock-empty">Aucun paiement pour ces filtres.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="cc-billing__side">
            <section class="cc-card app-table-card">
                <div class="cc-card__head"><h2 class="cc-card__title">Abonnements</h2></div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Statut</th>
                                <th>Fin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subscriptions as $sub)
                                <tr>
                                    <td>
                                        {{ $sub->plan?->name ?? '—' }}
                                        @if ($sub->plan->is_demo ?? false)
                                            <span class="badge badge-secondary">Démo</span>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-{{ $sub->status_color }}">{{ $statusLabels[$sub->status] ?? $sub->status }}</span></td>
                                    <td>{{ $sub->current_period_end?->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">Aucun historique.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($balanceTransactions->isNotEmpty())
                <section class="cc-card app-table-card">
                    <div class="cc-card__head"><h2 class="cc-card__title">Mouvements de solde</h2></div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($balanceTransactions as $t)
                                    <tr>
                                        <td>{{ $t->created_at->format('d/m/Y') }}</td>
                                        <td style="color: {{ $t->amount >= 0 ? '#15803d' : '#b91c1c' }};">
                                            {{ $t->amount >= 0 ? '+' : '' }}{{ fmt_money($t->amount) }}
                                        </td>
                                        <td>
                                            <div>{{ \App\Models\TenantBalanceTransaction::types()[$t->type] ?? $t->type }}</div>
                                            <div style="font-size:11px;color:#64748b;">{{ $t->description }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
