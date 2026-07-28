<div class="page-body">
    <section class="card">
        <h2 class="card-title">Abonnement — {{ $tenant->name }}</h2>
        <p class="card-body">
            <a href="{{ route('system.tenants.edit', $tenant->code) }}">Fiche vendeur</a> ·
            <a href="{{ route('system.tenants.settings', $tenant->code) }}">Paramètres</a>
        </p>

        <div class="card-body" style="margin-bottom: 16px; background: #f0fdf4; border: 1px solid #22c55e; border-radius: 6px;">
            <strong>Solde :</strong> {{ fmt_money($tenant->balance) }} {{ $tenant->balance_currency }}
        </div>

        @if($subscription)
            <div class="card-body" style="margin-bottom: 16px;">
                <strong>Abonnement actuel :</strong>
                <span class="badge badge-{{ $subscription->status_color }}">{{ \App\Models\Subscription::statuses()[$subscription->status] ?? $subscription->status }}</span>
                &nbsp;
                <strong>Plan :</strong> {{ $subscription->plan->name }}
                @if($subscription->plan->is_demo ?? false)
                    <span class="badge badge-secondary">Démo</span>
                @endif
                &nbsp;
                <strong>Période :</strong> Jusqu'au {{ $subscription->current_period_end->format('d/m/Y') }}
                &nbsp;
                <strong>Vendeur actif :</strong>
                @if($tenant->is_active)
                    <span class="badge badge-success">Oui</span>
                @else
                    <span class="badge badge-warning">Non</span>
                @endif
                @if($subscription->inGrace())
                    <br><span style="color: #0d9488;">Période de grâce jusqu'au {{ $subscription->grace_ends_at->format('d/m/Y') }}.</span>
                @endif
            </div>

            @if($subscription->isActive())
                <div class="page-actions" style="margin-bottom: 16px;">
                    <button class="btn btn-secondary" wire:click="suspendSubscription" wire:confirm="Suspendre l'abonnement et désactiver le vendeur ?">Suspendre l'abonnement</button>
                    <button class="btn btn-danger" wire:click="cancelSubscription" wire:confirm="Annuler définitivement l'abonnement ? Le reliquat sera remboursé au solde et le vendeur sera désactivé.">Annuler l'abonnement</button>
                    @if(!$subscription->plan->is_demo)
                        <button class="btn btn-secondary" wire:click="grantGrace(5)" wire:loading.attr="disabled">Accorder 5 jours de grâce</button>
                        <button class="btn btn-secondary" wire:click="grantGrace(10)" wire:loading.attr="disabled">Accorder 10 jours de grâce</button>
                        <button class="btn btn-secondary" wire:click="grantGrace(15)" wire:loading.attr="disabled">Accorder 15 jours de grâce</button>
                    @endif
                </div>
            @endif
        @else
            <div class="card-body" style="margin-bottom: 16px; background: #fef3c7; border: 1px solid #d97706;">
                Aucun abonnement actif. Enregistrez un paiement en choisissant un plan pour créer l'abonnement (la période est calculée selon le montant), ou ajoutez au solde.
            </div>
        @endif
    </section>

    @if($subscriptions->isNotEmpty())
        <section class="card app-table-card">
            <h3 class="card-title">Liste des abonnements</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Statut</th>
                            <th>Jusqu'au</th>
                            <th>Grâce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subscriptions as $sub)
                            <tr>
                                <td>{{ $sub->plan->name }} @if($sub->plan->is_demo ?? false)<span class="badge badge-secondary">Démo</span>@endif</td>
                                <td><span class="badge badge-{{ $sub->status_color }}">{{ \App\Models\Subscription::statuses()[$sub->status] ?? $sub->status }}</span></td>
                                <td>Jusqu'au {{ $sub->current_period_end->format('d/m/Y') }}</td>
                                <td>@if($sub->inGrace()) Jusqu'au {{ $sub->grace_ends_at->format('d/m/Y') }} @else — @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <details class="card" style="margin-bottom: 16px;">
        <summary class="card-title" style="cursor: pointer; list-style: none; padding: 16px; margin: 0; display: flex; align-items: center; gap: 8px; user-select: none;">
            <span class="details-arrow-payment" style="display: inline-block; transition: transform 0.2s;">▸</span>
            <span>Enregistrer un paiement</span>
        </summary>
        <style>details[open] .details-arrow-payment { transform: rotate(90deg); }</style>
        <div class="card-body" style="border-top: 1px solid #e5e7eb;">
            <p class="card-body" style="margin-top: 0;">Choisissez un plan pour appliquer le montant à l'abonnement (période calculée automatiquement : montant ÷ prix du plan). Sinon, le montant est ajouté au solde.</p>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Montant</label>
                    <input class="input" type="number" min="0" step="0.01" wire:model="payment_amount" placeholder="0">
                </div>
                <div class="field">
                    <label class="field-label">Appliquer à un plan (optionnel)</label>
                    <select class="input" wire:model="payment_plan_id">
                        <option value="">— Ajouter au solde uniquement —</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->price > 0 ? fmt_money($plan->price) . ' ' . $plan->currency . '/' . ($plan->billing_interval === 'yearly' ? 'an' : 'mois') : 'Gratuit' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Devise</label>
                    <input class="input" wire:model="payment_currency" maxlength="5">
                </div>
                <div class="field">
                    <label class="field-label">Méthode</label>
                    <select class="input" wire:model="payment_method">
                        @foreach(\App\Models\TenantPayment::methods() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Référence</label>
                    <input class="input" wire:model="payment_reference" placeholder="N° reçu...">
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Notes</label>
                    <input class="input" wire:model="payment_notes" placeholder="Optionnel">
                </div>
            </div>
            <div class="page-actions">
                <button class="btn btn-primary" wire:click="recordPayment" wire:loading.attr="disabled">Enregistrer</button>
            </div>
        </div>
    </details>

    @if($subscription)
        <details class="card" style="margin-bottom: 16px;">
            <summary class="card-title" style="cursor: pointer; list-style: none; padding: 16px; margin: 0; display: flex; align-items: center; gap: 8px; user-select: none;">
                <span class="details-arrow-balance" style="display: inline-block; transition: transform 0.2s;">▸</span>
                <span>Utiliser le solde pour renouveler</span>
            </summary>
            <style>details[open] .details-arrow-balance { transform: rotate(90deg); }</style>
            <div class="card-body" style="border-top: 1px solid #e5e7eb;">
                <p class="card-body" style="margin-top: 0;">Solde actuel : <strong>{{ fmt_money($tenant->balance) }} {{ $tenant->balance_currency }}</strong>. Prix du plan : {{ fmt_money($subscription->plan->price) }} {{ $subscription->plan->currency }}/mois.</p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label">Nombre de mois</label>
                        <input class="input" type="number" min="1" wire:model="apply_balance_months">
                    </div>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary" wire:click="applyBalance" wire:loading.attr="disabled">Appliquer le solde</button>
                </div>
            </div>
        </details>

        @if($plans->count() > 1)
            <details class="card" style="margin-bottom: 16px;">
                <summary class="card-title" style="cursor: pointer; list-style: none; padding: 16px; margin: 0; display: flex; align-items: center; gap: 8px; user-select: none;">
                    <span class="details-arrow-plan" style="display: inline-block; transition: transform 0.2s;">▸</span>
                    <span>Changer de plan</span>
                </summary>
                <style>details[open] .details-arrow-plan { transform: rotate(90deg); }</style>
                <div class="card-body" style="border-top: 1px solid #e5e7eb;">
                    <p class="card-body" style="margin-top: 0;">Le reliquat de la période en cours sera remboursé au solde. Le solde actuel + ce remboursement doit couvrir au moins 1 mois du nouveau plan. Sinon, enregistrez d'abord un dépôt pour compléter.</p>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">Nouveau plan</label>
                            <select class="input" wire:model="new_plan_id">
                                <option value="">— Choisir —</option>
                                @foreach($plans as $plan)
                                    @if($plan->id !== $subscription->plan_id)
                                        <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-secondary" wire:click="changePlan" wire:loading.attr="disabled" wire:confirm="Changer de plan ? Le reliquat sera remboursé au solde.">Changer de plan</button>
                    </div>
                </div>
            </details>
        @endif
    @endif

    <section class="card app-table-card">
        <h3 class="card-title">Historique des paiements</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Montant</th>
                        <th>Méthode</th>
                        <th>Appliqué à</th>
                        <th>Référence</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td>{{ $p->paid_at->format('d/m/Y') }}</td>
                            <td>{{ fmt_money($p->amount) }} {{ $p->currency }}</td>
                            <td>{{ \App\Models\TenantPayment::methods()[$p->method] ?? $p->method }}</td>
                            <td>{{ $p->subscription_id ? 'Abonnement' : 'Solde' }}</td>
                            <td>{{ $p->reference }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Aucun paiement.</td>
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
                                <td>{{ $t->amount >= 0 ? '+' : '' }}{{ fmt_money($t->amount) }}</td>
                                <td>{{ \App\Models\TenantBalanceTransaction::types()[$t->type] ?? $t->type }}</td>
                                <td>{{ $t->description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="page-actions">
        <a class="btn btn-secondary" href="{{ route('system.tenants') }}">Retour à la liste des vendeurs</a>
    </div>
</div>
