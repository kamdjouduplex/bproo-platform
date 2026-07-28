<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Services\SubscriptionService;
use Livewire\Component;

class TenantSubscription extends Component
{
    public Tenant $tenant;

    /** Record payment: apply to plan or balance only */
    public string $payment_amount = '';
    public string $payment_currency = 'XOF';
    public string $payment_method = 'cash';
    public string $payment_reference = '';
    public string $payment_notes = '';
    public ?int $payment_plan_id = null;

    /** Apply balance to subscription */
    public int $apply_balance_months = 1;

    /** Change plan */
    public ?int $new_plan_id = null;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function recordPayment(): void
    {
        $this->validate([
            'payment_amount' => 'required|numeric|min:0',
            'payment_currency' => 'required|string|max:5',
            'payment_method' => 'required|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
            'payment_notes' => 'nullable|string|max:1000',
            'payment_plan_id' => 'nullable|exists:plans,id',
        ]);

        if ((float) $this->payment_amount <= 0 && !$this->payment_plan_id) {
            notify()->error('Montant requis ou sélectionnez un plan à appliquer.');
            return;
        }

        $service = app(SubscriptionService::class);
        try {
            $service->recordPayment(
                $this->tenant,
                (float) $this->payment_amount,
                $this->payment_currency,
                $this->payment_method,
                $this->payment_reference ?: null,
                $this->payment_notes ?: null,
                auth()->id(),
                $this->payment_plan_id,
                $this->tenant->currentSubscription()?->id
            );
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
            return;
        }

        $msg = $this->payment_plan_id
            ? 'Paiement enregistré et appliqué à l\'abonnement (période calculée selon le montant).'
            : 'Paiement enregistré. Montant ajouté au solde.';
        notify()->success($msg);
        $this->payment_amount = '';
        $this->payment_reference = '';
        $this->payment_notes = '';
        $this->payment_plan_id = null;
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function applyBalance(): void
    {
        $sub = $this->tenant->currentSubscription();
        if (!$sub) {
            notify()->error('Aucun abonnement. Enregistrez d\'abord un paiement appliqué à un plan.');
            return;
        }
        $this->validate(['apply_balance_months' => 'required|integer|min:1|max:120']);

        $service = app(SubscriptionService::class);
        try {
            $service->applyBalanceToSubscription($sub, $this->apply_balance_months);
        } catch (\InvalidArgumentException $e) {
            notify()->error($e->getMessage());
            return;
        }
        notify()->success('Solde appliqué. Abonnement prolongé de ' . $this->apply_balance_months . ' mois.');
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function changePlan(): void
    {
        $this->validate(['new_plan_id' => 'required|exists:plans,id']);
        $sub = $this->tenant->currentSubscription();
        if (!$sub) {
            notify()->error('Aucun abonnement à modifier.');
            return;
        }
        if ((int) $this->new_plan_id === $sub->plan_id) {
            notify()->warning('Sélectionnez un plan différent.');
            return;
        }

        try {
            app(SubscriptionService::class)->changePlan($sub, (int) $this->new_plan_id);
        } catch (\InvalidArgumentException $e) {
            notify()->error($e->getMessage());
            return;
        }
        notify()->success('Plan modifié. Le reliquat de période a été remboursé au solde. Utilisez le solde pour renouveler avec le nouveau plan.');
        $this->new_plan_id = null;
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function suspendSubscription(): void
    {
        $sub = $this->tenant->currentSubscription();
        if (!$sub) {
            notify()->error('Aucun abonnement à suspendre.');
            return;
        }
        app(SubscriptionService::class)->suspendSubscription($sub, 'Suspendu depuis l\'admin');
        notify()->success('Abonnement suspendu, vendeur désactivé.');
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function grantGrace(int $days = 15): void
    {
        $sub = $this->tenant->currentSubscription();
        if (!$sub) {
            notify()->error('Aucun abonnement.');
            return;
        }
        if (!in_array($days, [5, 10, 15], true)) {
            notify()->error('Durée de grâce invalide.');
            return;
        }
        app(SubscriptionService::class)->grantGrace($sub, $days);
        notify()->success("Période de grâce de {$days} jours accordée.");
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function cancelSubscription(): void
    {
        $sub = $this->tenant->currentSubscription();
        if (!$sub) {
            notify()->error('Aucun abonnement à annuler.');
            return;
        }
        app(SubscriptionService::class)->cancelSubscription($sub, 'Annulé depuis l\'admin');
        notify()->success('Abonnement annulé. Le reliquat a été remboursé au solde. Le vendeur est désactivé.');
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function render()
    {
        $subscription = $this->tenant->currentSubscription();
        $subscriptions = $this->tenant->subscriptions;
        $plans = Plan::active()->ordered()->get();
        $payments = $this->tenant->payments()->take(50)->get();
        $balanceTransactions = $this->tenant->balanceTransactions()->take(30)->get();

        return view('livewire.admin.tenant-subscription', [
            'subscription' => $subscription,
            'subscriptions' => $subscriptions,
            'plans' => $plans,
            'payments' => $payments,
            'balanceTransactions' => $balanceTransactions,
        ])->layout('layouts.app', [
            'title' => 'Abonnement vendeur',
            'subtitle' => $this->tenant->name,
        ]);
    }
}
