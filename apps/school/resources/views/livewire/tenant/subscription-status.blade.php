@php
    $currencyLabel = ($currency ?? 'XOF') === 'XOF' ? 'FCFA' : ($currency ?? 'XOF');
    $selectedSubscribePlan = $plansForSubscribe->firstWhere('id', (int) $subscribe_plan_id);
    $subscribeCost = $selectedSubscribePlan
        ? (float) $selectedSubscribePlan->price * max(1, (int) $subscribe_months)
        : null;
    $renewCost = ($subscription && $canRenewWithBalance)
        ? (float) $subscription->plan->price * max(1, (int) $apply_balance_months)
        : null;
@endphp

<div class="page-body sub-page">
    @if(!$tenant)
        <section class="card">
            <p class="card-body">Aucun établissement sélectionné. Ouvrez l’application avec <code>?tenant=VOTRE_CODE</code>.</p>
        </section>
    @else
        {{-- 1. Status at a glance --}}
        <section class="sub-hero sub-hero--{{ $hasActiveSubscription ? ($expiresSoon ? 'warn' : 'ok') : 'blocked' }}">
            <div class="sub-hero__main">
                <p class="sub-hero__eyebrow">Votre accès Bproo</p>
                @if($hasActiveSubscription)
                    <h1 class="sub-hero__title">
                        {{ $expiresSoon ? 'Abonnement bientôt terminé' : 'Abonnement actif' }}
                    </h1>
                    <p class="sub-hero__text">
                        Plan <strong>{{ $subscription->plan->name }}</strong>
                        — valide jusqu’au <strong>{{ $subscription->current_period_end->format('d/m/Y') }}</strong>
                        @if($daysLeft !== null)
                            <span class="sub-hero__days">({{ $daysLeft === 0 ? 'dernier jour' : ($daysLeft === 1 ? '1 jour restant' : $daysLeft.' jours restants') }})</span>
                        @endif
                    </p>
                @else
                    <h1 class="sub-hero__title">Accès en pause</h1>
                    <p class="sub-hero__text">
                        L’application (ventes, stock, rapports…) est bloquée tant que l’abonnement n’est pas actif.
                        Suivez les 3 étapes ci-dessous pour reprendre.
                    </p>
                @endif
            </div>
            <div class="sub-hero__metrics">
                <div class="sub-metric">
                    <span class="sub-metric__label">Solde disponible</span>
                    <span class="sub-metric__value">{{ fmt_money($balance) }} <small>{{ $currencyLabel }}</small></span>
                    <span class="sub-metric__hint">Argent déjà payé, prêt à activer ou renouveler</span>
                </div>
                <div class="sub-metric">
                    <span class="sub-metric__label">Statut</span>
                    <span class="sub-metric__value sub-metric__value--sm">
                        @if($hasActiveSubscription)
                            {{ $statusLabel ?? 'Actif' }}
                        @elseif($subscription)
                            {{ $statusLabel ?? 'Inactif' }}
                        @else
                            Pas d’abonnement
                        @endif
                    </span>
                    <span class="sub-metric__hint">
                        @if($hasActiveSubscription)
                            Vous pouvez travailler normalement
                        @else
                            Paiement ou activation requis
                        @endif
                    </span>
                </div>
            </div>
        </section>

        {{-- 2. How it works --}}
        <section class="card sub-how">
            <h2 class="sub-how__title">Comment ça marche ?</h2>
            <p class="sub-how__intro">Trois étapes simples — comme un forfait téléphone.</p>
            <ol class="sub-steps">
                <li class="sub-step">
                    <span class="sub-step__num">1</span>
                    <div>
                        <strong>Vous payez</strong>
                        <p>Orange Money, MTN Money, espèces… auprès de votre fournisseur Bproo. Le paiement est enregistré pour votre pharmacie.</p>
                    </div>
                </li>
                <li class="sub-step">
                    <span class="sub-step__num">2</span>
                    <div>
                        <strong>L’argent arrive sur votre solde</strong>
                        <p>Le solde ci-dessus augmente. Ce n’est pas encore l’abonnement : c’est une réserve utilisable.</p>
                    </div>
                </li>
                <li class="sub-step">
                    <span class="sub-step__num">3</span>
                    <div>
                        <strong>Vous activez ou renouvelez ici</strong>
                        <p>Choisissez le plan et le nombre de mois. Le montant est déduit du solde, et l’accès se débloque jusqu’à la date indiquée.</p>
                    </div>
                </li>
            </ol>
        </section>

        {{-- 3. Primary action --}}
        <section class="card sub-action">
            @if($primaryAction === 'ok')
                <h2 class="card-title">Tout est en ordre</h2>
                <p class="sub-action__lead">
                    Aucune action urgente. Quand vous voudrez prolonger, assurez-vous d’avoir assez de solde
                    (prix actuel : <strong>{{ fmt_money($subscription->plan->price) }} {{ $currencyLabel }}/mois</strong>),
                    puis utilisez le renouvellement ci-dessous.
                </p>
                @if($balance < (float) $subscription->plan->price && (float) $subscription->plan->price > 0)
                    <div class="sub-callout sub-callout--info">
                        Solde insuffisant pour un mois supplémentaire.
                        Contactez le support pour créditer votre compte avant la date de fin.
                    </div>
                @endif
            @elseif($primaryAction === 'need_payment')
                <h2 class="card-title">Étape suivante : créditer le solde</h2>
                <p class="sub-action__lead">
                    Votre solde est à <strong>{{ fmt_money($balance) }} {{ $currencyLabel }}</strong>.
                    Pour activer l’abonnement, un paiement doit d’abord être enregistré par le support Bproo.
                </p>
                <div class="sub-callout sub-callout--warn">
                    <strong>Que faire maintenant ?</strong><br>
                    Contactez votre commercial / support, indiquez votre code pharmacie
                    <code>{{ $tenant->code }}</code>, payez le forfait souhaité, puis revenez sur cette page pour activer.
                </div>
            @elseif($primaryAction === 'activate')
                <h2 class="card-title">Activer l’abonnement</h2>
                <p class="sub-action__lead">
                    Vous avez du solde. Choisissez un plan et combien de mois vous voulez couvrir — l’accès s’ouvre immédiatement.
                </p>

                <div class="form-grid">
                    <div class="field">
                        <label class="field-label">Plan</label>
                        <select class="input" wire:model.live="subscribe_plan_id">
                            <option value="">— Choisir un plan —</option>
                            @foreach($plansForSubscribe as $plan)
                                <option value="{{ $plan->id }}">
                                    {{ $plan->name }}
                                    — {{ $plan->price > 0 ? fmt_money($plan->price).' '.$currencyLabel.'/mois' : 'Gratuit' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Nombre de mois</label>
                        <input class="input" type="number" min="1" max="120" wire:model.live="subscribe_months">
                    </div>
                </div>

                @if($selectedSubscribePlan)
                    <div class="sub-estimate {{ $subscribeCost !== null && $balance + 0.001 >= $subscribeCost ? 'sub-estimate--ok' : 'sub-estimate--short' }}">
                        <div>
                            <span class="sub-estimate__label">Coût de cette activation</span>
                            <strong class="sub-estimate__amount">
                                @if((float) $selectedSubscribePlan->price <= 0)
                                    Gratuit
                                @else
                                    {{ fmt_money($subscribeCost) }} {{ $currencyLabel }}
                                @endif
                            </strong>
                            <span class="sub-estimate__detail">
                                {{ (int) $subscribe_months }} × {{ fmt_money($selectedSubscribePlan->price) }} {{ $currencyLabel }}
                            </span>
                        </div>
                        <div class="sub-estimate__side">
                            Solde : {{ fmt_money($balance) }} {{ $currencyLabel }}
                            @if($subscribeCost !== null && (float) $selectedSubscribePlan->price > 0)
                                @if($balance + 0.001 >= $subscribeCost)
                                    <span class="sub-pill sub-pill--ok">Suffisant</span>
                                @else
                                    <span class="sub-pill sub-pill--short">Manque {{ fmt_money($subscribeCost - $balance) }} {{ $currencyLabel }}</span>
                                @endif
                            @endif
                        </div>
                    </div>
                @endif

                <div class="page-actions" style="margin-top: 16px;">
                    <button
                        class="btn btn-primary"
                        wire:click="subscribeFromBalance"
                        wire:loading.attr="disabled"
                        @disabled(!$subscribe_plan_id || ($subscribeCost !== null && (float) ($selectedSubscribePlan->price ?? 0) > 0 && $balance + 0.001 < $subscribeCost))
                    >
                        Activer maintenant
                    </button>
                </div>
            @elseif($primaryAction === 'renew')
                <h2 class="card-title">Renouveler avec le solde</h2>
                <p class="sub-action__lead">
                    Plan actuel : <strong>{{ $subscription->plan->name }}</strong>
                    ({{ fmt_money($subscription->plan->price) }} {{ $currencyLabel }}/mois).
                    Indiquez combien de mois ajouter — la date de fin sera repoussée.
                </p>

                <div class="form-grid">
                    <div class="field">
                        <label class="field-label">Mois à ajouter</label>
                        <input class="input" type="number" min="1" max="120" wire:model.live="apply_balance_months">
                    </div>
                </div>

                @if($renewCost !== null)
                    <div class="sub-estimate {{ $balance + 0.001 >= $renewCost ? 'sub-estimate--ok' : 'sub-estimate--short' }}">
                        <div>
                            <span class="sub-estimate__label">Coût du renouvellement</span>
                            <strong class="sub-estimate__amount">{{ fmt_money($renewCost) }} {{ $currencyLabel }}</strong>
                            <span class="sub-estimate__detail">
                                {{ (int) $apply_balance_months }} × {{ fmt_money($subscription->plan->price) }} {{ $currencyLabel }}
                            </span>
                        </div>
                        <div class="sub-estimate__side">
                            Solde : {{ fmt_money($balance) }} {{ $currencyLabel }}
                            @if($balance + 0.001 >= $renewCost)
                                <span class="sub-pill sub-pill--ok">Suffisant</span>
                            @else
                                <span class="sub-pill sub-pill--short">Manque {{ fmt_money($renewCost - $balance) }} {{ $currencyLabel }}</span>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="page-actions" style="margin-top: 16px;">
                    <button
                        class="btn btn-primary"
                        wire:click="applyBalance"
                        wire:loading.attr="disabled"
                        @disabled($renewCost !== null && $balance + 0.001 < $renewCost)
                    >
                        Prolonger l’abonnement
                    </button>
                </div>
            @endif
        </section>

        {{-- 4. Change plan (secondary) --}}
        @if($hasActiveSubscription && $otherPlans->isNotEmpty())
            <details class="card sub-details">
                <summary class="sub-details__summary">Changer de plan (optionnel)</summary>
                <div class="sub-details__body">
                    <p class="sub-action__lead">
                        Si vous changez de formule, le temps restant sur l’ancien plan est converti en argent sur votre solde.
                        Vous pourrez ensuite activer le nouveau plan avec ce solde (il faut au moins 1 mois du nouveau tarif).
                    </p>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">Nouveau plan</label>
                            <select class="input" wire:model="new_plan_id">
                                <option value="">— Choisir —</option>
                                @foreach($otherPlans as $plan)
                                    <option value="{{ $plan->id }}">
                                        {{ $plan->name }}
                                        — {{ $plan->price > 0 ? fmt_money($plan->price).' '.$currencyLabel.'/mois' : 'Gratuit' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="page-actions" style="margin-top: 12px;">
                        <button
                            class="btn btn-secondary"
                            wire:click="changePlan"
                            wire:loading.attr="disabled"
                            wire:confirm="Changer de plan ? Le temps restant sera remboursé sur votre solde."
                        >
                            Confirmer le changement
                        </button>
                    </div>
                </div>
            </details>
        @endif

        {{-- 5. Help --}}
        <section class="card sub-help">
            <h2 class="card-title">Besoin d’aide ?</h2>
            <ul class="sub-help__list">
                <li>Code de votre pharmacie : <code>{{ $tenant->code }}</code> (à communiquer lors du paiement)</li>
                <li>Les paiements sont enregistrés par l’équipe Bproo — ils apparaissent ensuite dans « Historique » ci-dessous</li>
                <li>Sans abonnement actif, seule cette page reste accessible</li>
            </ul>
        </section>

        {{-- 6. History --}}
        <details class="card sub-details" open>
            <summary class="sub-details__summary">Historique</summary>
            <div class="sub-details__body">
                <h3 class="sub-section-title">Abonnements</h3>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Statut</th>
                                <th>Valide jusqu’au</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subscriptions as $sub)
                                <tr>
                                    <td>{{ $sub->plan->name }}</td>
                                    <td><span class="badge badge-{{ $sub->status_color }}">{{ \App\Models\Subscription::statuses()[$sub->status] ?? $sub->status }}</span></td>
                                    <td>{{ $sub->current_period_end->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">Aucun abonnement pour l’instant.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h3 class="sub-section-title">Paiements enregistrés</h3>
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
                                    <td>{{ fmt_money($p->amount) }} {{ $p->currency === 'XOF' ? 'FCFA' : $p->currency }}</td>
                                    <td>{{ \App\Models\TenantPayment::methods()[$p->method] ?? $p->method }}</td>
                                    <td>{{ $p->reference ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">Aucun paiement enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($balanceTransactions->isNotEmpty())
                    <h3 class="sub-section-title">Mouvements de solde</h3>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Type</th>
                                    <th>Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($balanceTransactions as $t)
                                    <tr>
                                        <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ $t->amount >= 0 ? '+' : '' }}{{ fmt_money($t->amount) }} {{ $currencyLabel }}</td>
                                        <td>{{ \App\Models\TenantBalanceTransaction::types()[$t->type] ?? $t->type }}</td>
                                        <td>{{ $t->description ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </details>
    @endif

    <style>
.sub-page { max-width: 920px; }
.sub-hero {
    display: grid;
    gap: 20px;
    padding: 22px 24px;
    border-radius: 14px;
    margin-bottom: 16px;
    border: 1px solid;
}
@media (min-width: 720px) {
    .sub-hero { grid-template-columns: 1.4fr 1fr; align-items: stretch; }
}
.sub-hero--ok { background: linear-gradient(135deg, #f0fdf4, #ecfdf5); border-color: #bbf7d0; color: #14532d; }
.sub-hero--warn { background: linear-gradient(135deg, #fffbeb, #fef3c7); border-color: #fde68a; color: #78350f; }
.sub-hero--blocked { background: linear-gradient(135deg, #fef2f2, #fff1f2); border-color: #fecaca; color: #7f1d1d; }
.sub-hero__eyebrow { margin: 0 0 6px; font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; opacity: 0.75; }
.sub-hero__title { margin: 0 0 8px; font-size: clamp(1.35rem, 2.5vw, 1.75rem); font-weight: 700; line-height: 1.25; }
.sub-hero__text { margin: 0; font-size: 14px; line-height: 1.55; opacity: 0.95; }
.sub-hero__days { opacity: 0.85; }
.sub-hero__metrics { display: grid; gap: 10px; }
.sub-metric {
    background: rgba(255,255,255,0.72);
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 12px;
    padding: 12px 14px;
}
.sub-metric__label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; opacity: 0.7; margin-bottom: 4px; }
.sub-metric__value { display: block; font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
.sub-metric__value--sm { font-size: 1.1rem; }
.sub-metric__value small { font-size: 0.75em; font-weight: 600; }
.sub-metric__hint { display: block; margin-top: 4px; font-size: 12px; line-height: 1.35; opacity: 0.75; }

.sub-how { margin-bottom: 16px; }
.sub-how__title { margin: 0 0 4px; font-size: 1.1rem; }
.sub-how__intro { margin: 0 0 16px; color: #64748b; font-size: 14px; }
.sub-steps { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
@media (min-width: 720px) {
    .sub-steps { grid-template-columns: repeat(3, 1fr); gap: 14px; }
}
.sub-step {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 14px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0;
}
.sub-step__num {
    flex-shrink: 0; width: 28px; height: 28px; border-radius: 999px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #0f766e; color: #fff; font-weight: 700; font-size: 13px;
}
.sub-step strong { display: block; margin-bottom: 4px; font-size: 14px; color: #0f172a; }
.sub-step p { margin: 0; font-size: 13px; line-height: 1.45; color: #64748b; }

.sub-action { margin-bottom: 16px; }
.sub-action__lead { margin: 0 0 16px; font-size: 14px; line-height: 1.55; color: #334155; }

.sub-callout { padding: 14px 16px; border-radius: 10px; font-size: 14px; line-height: 1.5; border: 1px solid; }
.sub-callout--warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.sub-callout--info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; margin-top: 12px; }

.sub-estimate {
    display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; align-items: center;
    margin-top: 14px; padding: 14px 16px; border-radius: 10px; border: 1px solid;
}
.sub-estimate--ok { background: #f0fdf4; border-color: #bbf7d0; }
.sub-estimate--short { background: #fff7ed; border-color: #fed7aa; }
.sub-estimate__label { display: block; font-size: 12px; color: #64748b; margin-bottom: 2px; }
.sub-estimate__amount { display: block; font-size: 1.25rem; color: #0f172a; }
.sub-estimate__detail { display: block; font-size: 12px; color: #64748b; margin-top: 2px; }
.sub-estimate__side { font-size: 13px; color: #334155; display: flex; flex-direction: column; gap: 6px; align-items: flex-end; }
.sub-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.sub-pill--ok { background: #dcfce7; color: #166534; }
.sub-pill--short { background: #ffedd5; color: #9a3412; }

.sub-details { margin-bottom: 16px; padding: 0; overflow: hidden; }
.sub-details__summary {
    cursor: pointer; list-style: none; padding: 16px 20px; font-weight: 600; font-size: 15px; color: #0f172a;
    user-select: none;
}
.sub-details__summary::-webkit-details-marker { display: none; }
.sub-details__summary::after { content: '▾'; float: right; opacity: 0.5; }
.sub-details[open] > .sub-details__summary::after { content: '▴'; }
.sub-details__body { padding: 0 20px 20px; border-top: 1px solid #e2e8f0; }
.sub-section-title { margin: 18px 0 10px; font-size: 14px; font-weight: 700; color: #334155; }
.sub-section-title:first-child { margin-top: 16px; }

.sub-help { margin-bottom: 16px; }
.sub-help__list { margin: 0; padding-left: 1.15rem; color: #475569; font-size: 14px; line-height: 1.6; }
.sub-help__list code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; font-size: 12px; }
    </style>
</div>
