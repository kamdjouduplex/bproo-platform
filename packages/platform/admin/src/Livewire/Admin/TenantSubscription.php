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

    public string $payment_amount = '';
    public string $payment_currency = 'XOF';
    public string $payment_method = 'cash';
    public string $payment_reference = '';
    public string $payment_notes = '';
    public ?int $payment_plan_id = null;

    public int $apply_balance_months = 1;
    public ?int $new_plan_id = null;

    public bool $showPaymentPanel = false;
    public bool $showBalancePanel = false;
    public bool $showPlanPanel = false;

    /** Payment history filters */
    public string $paySearch = '';
    public string $payMethod = '';
    public string $payApplied = ''; // '' | subscription | balance
    public string $payFrom = '';
    public string $payTo = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant->loadMissing([]);
        $this->payment_currency = $tenant->balance_currency ?: 'XOF';
        $current = $tenant->currentSubscription();
        if ($current?->plan_id) {
            $this->payment_plan_id = $current->plan_id;
        }
    }

    public function updatedPaySearch(): void
    {
        // live filter — no-op, render recomputes
    }

    public function clearPaymentFilters(): void
    {
        $this->paySearch = '';
        $this->payMethod = '';
        $this->payApplied = '';
        $this->payFrom = '';
        $this->payTo = '';
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

        if ((float) $this->payment_amount <= 0 && ! $this->payment_plan_id) {
            notify()->error('Montant requis ou sélectionnez un plan à appliquer.');

            return;
        }

        try {
            app(SubscriptionService::class)->recordPayment(
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

        notify()->success(
            $this->payment_plan_id
                ? 'Paiement enregistré et appliqué à l’abonnement.'
                : 'Paiement enregistré sur le solde.'
        );

        $this->payment_amount = '';
        $this->payment_reference = '';
        $this->payment_notes = '';
        $this->showPaymentPanel = false;
        $this->tenant->refresh();
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function applyBalance(): void
    {
        $sub = $this->tenant->currentSubscription();
        if (! $sub) {
            notify()->error('Aucun abonnement. Enregistrez d’abord un paiement appliqué à un plan.');

            return;
        }
        $this->validate(['apply_balance_months' => 'required|integer|min:1|max:120']);

        try {
            app(SubscriptionService::class)->applyBalanceToSubscription($sub, $this->apply_balance_months);
        } catch (\InvalidArgumentException $e) {
            notify()->error($e->getMessage());

            return;
        }
        notify()->success('Solde appliqué. Abonnement prolongé de ' . $this->apply_balance_months . ' mois.');
        $this->showBalancePanel = false;
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function changePlan(): void
    {
        $this->validate(['new_plan_id' => 'required|exists:plans,id']);
        $sub = $this->tenant->currentSubscription();
        if (! $sub) {
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
        notify()->success('Plan modifié. Reliquat remboursé au solde.');
        $this->new_plan_id = null;
        $this->showPlanPanel = false;
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function suspendSubscription(): void
    {
        $sub = $this->tenant->currentSubscription();
        if (! $sub) {
            notify()->error('Aucun abonnement à suspendre.');

            return;
        }
        app(SubscriptionService::class)->suspendSubscription($sub, 'Suspendu depuis l\'admin');
        notify()->success('Abonnement suspendu, entreprise désactivée.');
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function grantGrace(int $days = 15): void
    {
        $sub = $this->tenant->currentSubscription();
        if (! $sub) {
            notify()->error('Aucun abonnement.');

            return;
        }
        if (! in_array($days, [5, 10, 15], true)) {
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
        if (! $sub) {
            notify()->error('Aucun abonnement à annuler.');

            return;
        }
        app(SubscriptionService::class)->cancelSubscription($sub, 'Annulé depuis l\'admin');
        notify()->success('Abonnement annulé. Reliquat remboursé au solde.');
        $this->redirect(route('system.tenants.subscription', $this->tenant->code), navigate: true);
    }

    public function render()
    {
        $this->tenant->refresh();
        $subscription = $this->tenant->currentSubscription();
        $subscription?->loadMissing('plan');
        $subscriptions = $this->tenant->subscriptions()->with('plan')->orderByDesc('id')->get();
        $plans = Plan::active()->ordered()->get();

        $paymentsQuery = $this->tenant->payments()->with('subscription.plan')->orderByDesc('paid_at')->orderByDesc('id');

        if (trim($this->paySearch) !== '') {
            $term = '%' . strtolower(trim($this->paySearch)) . '%';
            $paymentsQuery->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(COALESCE(reference, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', [$term]);
            });
        }
        if ($this->payMethod !== '') {
            $paymentsQuery->where('method', $this->payMethod);
        }
        if ($this->payApplied === 'subscription') {
            $paymentsQuery->whereNotNull('subscription_id');
        } elseif ($this->payApplied === 'balance') {
            $paymentsQuery->whereNull('subscription_id');
        }
        if ($this->payFrom !== '') {
            $paymentsQuery->whereDate('paid_at', '>=', $this->payFrom);
        }
        if ($this->payTo !== '') {
            $paymentsQuery->whereDate('paid_at', '<=', $this->payTo);
        }

        $payments = $paymentsQuery->limit(200)->get();
        $balanceTransactions = $this->tenant->balanceTransactions()->orderByDesc('id')->limit(50)->get();

        $totalPaid = (float) $this->tenant->payments()->sum('amount');
        $paidThisMonth = (float) $this->tenant->payments()
            ->where('paid_at', '>=', now()->startOfMonth()->toDateString())
            ->sum('amount');
        $paidThisYear = (float) $this->tenant->payments()
            ->where('paid_at', '>=', now()->startOfYear()->toDateString())
            ->sum('amount');
        $paymentsCount = (int) $this->tenant->payments()->count();

        $monthlyRate = null;
        $daysRemaining = null;
        $periodProgress = null;
        if ($subscription?->plan) {
            $monthlyRate = $subscription->plan->billing_interval === 'yearly'
                ? ((float) $subscription->plan->price / 12)
                : (float) $subscription->plan->price;
            if ($subscription->current_period_end) {
                $daysRemaining = (int) now()->startOfDay()->diffInDays($subscription->current_period_end, false);
            }
            if ($subscription->current_period_start && $subscription->current_period_end) {
                $totalDays = max(1, $subscription->current_period_start->diffInDays($subscription->current_period_end));
                $elapsed = $subscription->current_period_start->diffInDays(now()->startOfDay());
                $periodProgress = (int) min(100, max(0, round(($elapsed / $totalDays) * 100)));
            }
        }

        return view('livewire.admin.tenant-subscription', [
            'subscription' => $subscription,
            'subscriptions' => $subscriptions,
            'plans' => $plans,
            'payments' => $payments,
            'balanceTransactions' => $balanceTransactions,
            'kpis' => [
                'total_paid' => $totalPaid,
                'paid_month' => $paidThisMonth,
                'paid_year' => $paidThisYear,
                'payments_count' => $paymentsCount,
                'monthly_rate' => $monthlyRate,
                'days_remaining' => $daysRemaining,
                'period_progress' => $periodProgress,
                'currency' => $this->tenant->balance_currency ?: 'XOF',
            ],
            'statusLabels' => Subscription::statuses(),
            'methodLabels' => TenantPayment::methods(),
        ])->layout('layouts.app', [
            'title' => 'Facturation',
            'subtitle' => $this->tenant->name . ' · ' . $this->tenant->code,
        ]);
    }
}
