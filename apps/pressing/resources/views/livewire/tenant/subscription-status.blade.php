<div class="page-body subscription-status-page">
    {{-- Access restricted notice: shown when user cannot use the app (no or inactive subscription) --}}
    @if($tenant && !$hasActiveSubscription)
        <div class="subscription-status-alert subscription-status-alert--restricted" role="alert">
            <div class="subscription-status-alert__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>
            <div class="subscription-status-alert__content">
                <h2 class="subscription-status-alert__title">Accès restreint</h2>
                <p class="subscription-status-alert__text">
                    Un <strong>abonnement actif</strong> est requis pour utiliser l'application. Sans abonnement valide, vous ne pouvez accéder à <strong>aucune fonctionnalité</strong> : tableau de bord, ventes, stock, achats, rapports, etc. sont désactivés.
                </p>
                <p class="subscription-status-alert__text subscription-status-alert__text--muted">
                    Cette page est la seule accessible tant que votre abonnement n'est pas actif. Contactez l'administrateur ou le support pour souscrire ou renouveler.
                </p>
            </div>
        </div>
    @endif

    <section class="card subscription-status-card">
        <h2 class="card-title">État de l'abonnement</h2>

        @if(!$tenant)
            <p class="card-body">Aucun vendeur sélectionné. Accédez à l'application avec le paramètre <code>?tenant=VOTRE_CODE</code>.</p>
        @elseif(!($canManageSubscription ?? false))
            <div class="subscription-status-message subscription-status-message--warning">
                <strong>{{ __('Accès restreint') }}.</strong><br>
                {{ __('Seul un administrateur peut consulter et gérer l’abonnement.') }}
                {{ __('Contactez votre administrateur pour renouveler ou réactiver l’accès.') }}
            </div>
        @else
            <div class="subscription-status-balance">
                <span class="subscription-status-balance__label">Votre solde</span>
                <span class="subscription-status-balance__value">{{ fmt_money($tenant->balance) }} {{ $tenant->balance_currency }}</span>
            </div>

        @if(!$subscription)
            <div class="subscription-status-message subscription-status-message--warning">
                <strong>Aucun abonnement enregistré.</strong><br>
                Vous ne pouvez pas utiliser l'application. Contactez le support pour activer votre abonnement.
            </div>

            @if($plansForSubscribe->isNotEmpty())
                <section class="subscription-status-block">
                    <h3 class="card-title">Activer votre abonnement avec le solde</h3>
                    <p class="card-body">Votre solde : <strong>{{ fmt_money($tenant->balance) }} {{ $tenant->balance_currency }}</strong>. Choisissez un plan et le nombre de mois pour activer votre abonnement. Le montant sera déduit de votre solde.</p>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">Plan</label>
                            <select class="input" wire:model="subscribe_plan_id">
                                <option value="">— Choisir —</option>
                                @foreach($plansForSubscribe as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->price > 0 ? fmt_money($plan->price) . ' ' . $plan->currency . '/mois' : 'Gratuit' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Nombre de mois</label>
                            <input class="input" type="number" min="1" wire:model="subscribe_months">
                        </div>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-primary" wire:click="subscribeFromBalance" wire:loading.attr="disabled">Activer avec le solde</button>
                    </div>
                </section>
            @endif
        @else
            <div class="subscription-status-summary">
                <p>
                    <strong>Plan :</strong> {{ $subscription->plan->name }}
                    @if($subscription->plan->is_demo ?? false) <span class="badge badge-secondary">Démo</span> @endif<br>
                    <strong>Statut :</strong> <span class="badge badge-{{ $statusColor }}">{{ $statusLabel }}</span><br>
                    <strong>Période :</strong> Jusqu'au {{ $subscription->current_period_end->format('d/m/Y') }}
                </p>
                @if($hasActiveSubscription)
                    <p class="subscription-status-message subscription-status-message--success">Votre abonnement est actif. Vous avez accès à toutes les fonctionnalités de l'application.</p>
                @else
                    <div class="subscription-status-message subscription-status-message--danger">
                        <strong>Accès suspendu.</strong> Votre abonnement n'est pas actif ou la période est échue. Aucune partie de l'application (tableau de bord, ventes, stock, etc.) n'est accessible.<br><br>
                        Pour réactiver votre accès, réglez votre abonnement et contactez le support. Une fois le paiement enregistré, votre compte sera réactivé.
                    </div>
                @endif
            </div>

            @if(!$hasActiveSubscription && $plansForSubscribe->isNotEmpty())
                <section class="subscription-status-block">
                    <h3 class="card-title">Activer votre abonnement avec le solde</h3>
                    <p class="card-body">Votre solde : <strong>{{ fmt_money($tenant->balance) }} {{ $tenant->balance_currency }}</strong>. Choisissez un plan et le nombre de mois pour activer (ou réactiver) votre abonnement. Le montant sera déduit de votre solde.</p>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">Plan</label>
                            <select class="input" wire:model="subscribe_plan_id">
                                <option value="">— Choisir —</option>
                                @foreach($plansForSubscribe as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->price > 0 ? fmt_money($plan->price) . ' ' . $plan->currency . '/mois' : 'Gratuit' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Nombre de mois</label>
                            <input class="input" type="number" min="1" wire:model="subscribe_months">
                        </div>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-primary" wire:click="subscribeFromBalance" wire:loading.attr="disabled">Activer avec le solde</button>
                    </div>
                </section>
            @endif

            @if($subscription->isActive() && $subscription->plan->price > 0 && $tenant->balance >= $subscription->plan->price)
                <section class="subscription-status-block">
                    <h3 class="card-title">Renouveler avec le solde</h3>
                    <p class="card-body">Utilisez votre solde ({{ fmt_money($tenant->balance) }} {{ $tenant->balance_currency }}) pour prolonger l'abonnement. Prix : {{ fmt_money($subscription->plan->price) }} {{ $subscription->plan->currency }}/mois.</p>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">Nombre de mois</label>
                            <input class="input" type="number" min="1" wire:model="apply_balance_months">
                        </div>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-primary" wire:click="applyBalance" wire:loading.attr="disabled">Appliquer le solde</button>
                    </div>
                </section>
            @endif

            @php $otherPlans = \App\Models\Plan::active()->ordered()->get()->filter(fn($p) => $p->id !== $subscription->plan_id); @endphp
            @if($otherPlans->isNotEmpty())
                <section class="subscription-status-block">
                    <h3 class="card-title">Changer de plan</h3>
                    <p class="card-body">Le reliquat de la période en cours sera remboursé au solde. Votre solde actuel + ce remboursement doit couvrir au moins 1 mois du nouveau plan. Sinon, effectuez d'abord un dépôt pour compléter.</p>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">Nouveau plan</label>
                            <select class="input" wire:model="new_plan_id">
                                <option value="">— Choisir —</option>
                                @foreach($otherPlans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->price > 0 ? fmt_money($plan->price) . ' ' . $plan->currency . '/mois' : 'Gratuit' }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-secondary" wire:click="changePlan" wire:loading.attr="disabled" wire:confirm="Changer de plan ? Le reliquat sera remboursé au solde.">Changer de plan</button>
                    </div>
                </section>
            @endif
        @endif

        @endif

        <div class="subscription-status-support">
            <strong>Besoin d'aide ?</strong><br>
            Contactez l'administrateur ou le support pour toute question, pour souscrire ou renouveler votre abonnement.
        </div>
    </section>

    @if($tenant && ($canManageSubscription ?? false))
        <section class="card app-table-card">
            <h3 class="card-title">Liste des abonnements</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Statut</th>
                            <th>Jusqu'au</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscriptions as $sub)
                            <tr>
                                <td>{{ $sub->plan->name }}</td>
                                <td><span class="badge badge-{{ $sub->status_color }}">{{ \App\Models\Subscription::statuses()[$sub->status] ?? $sub->status }}</span></td>
                                <td>Jusqu'au {{ $sub->current_period_end->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">Aucun abonnement enregistré.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card app-table-card">
            <h3 class="card-title">Historique des paiements</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Référence</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $p)
                            <tr>
                                <td>{{ $p->paid_at->format('d/m/Y') }}</td>
                                <td>{{ fmt_money($p->amount) }} {{ $p->currency }}</td>
                                <td>{{ \App\Models\TenantPayment::methods()[$p->method] ?? $p->method }}</td>
                                <td>{{ $p->reference }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">Aucun paiement enregistré.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($balanceTransactions->isNotEmpty())
            <section class="card app-table-card">
                <h3 class="card-title">Mouvements de solde</h3>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Type</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($balanceTransactions as $t)
                                <tr>
                                    <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $t->amount >= 0 ? '+' : '' }}{{ fmt_money($t->amount) }} {{ $tenant->balance_currency }}</td>
                                    <td>{{ \App\Models\TenantBalanceTransaction::types()[$t->type] ?? $t->type }}</td>
                                    <td>{{ $t->description }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif

    <style>
.subscription-status-alert { display: flex; gap: 16px; align-items: flex-start; padding: 20px 24px; border-radius: 12px; margin-bottom: 24px; border: 1px solid; }
.subscription-status-alert--restricted { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.subscription-status-alert__icon { flex-shrink: 0; color: #dc2626; }
.subscription-status-alert__content { flex: 1; min-width: 0; }
.subscription-status-alert__title { margin: 0 0 8px; font-size: 18px; font-weight: 600; }
.subscription-status-alert__text { margin: 0 0 8px; font-size: 14px; line-height: 1.5; }
.subscription-status-alert__text:last-child { margin-bottom: 0; }
.subscription-status-alert__text--muted { color: #b91c1c; font-size: 13px; }
.subscription-status-card { margin-bottom: 24px; }
.subscription-status-balance { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 20px; }
.subscription-status-balance__label { font-size: 13px; color: #166534; }
.subscription-status-balance__value { font-weight: 600; font-size: 16px; color: #15803d; }
.subscription-status-message { padding: 14px 16px; border-radius: 8px; margin: 12px 0; font-size: 14px; line-height: 1.5; border: 1px solid; }
.subscription-status-message--warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.subscription-status-message--success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.subscription-status-message--danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.subscription-status-summary { margin-bottom: 8px; }
.subscription-status-block { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
.subscription-status-support { margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280; }
    </style>
</div>
