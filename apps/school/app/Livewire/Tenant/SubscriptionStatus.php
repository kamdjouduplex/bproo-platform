<?php

namespace App\Livewire\Tenant;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Services\TenantManager;
use Livewire\Component;

class SubscriptionStatus extends Component
{
    public int $apply_balance_months = 1;
    public ?int $new_plan_id = null;

    /** Subscribe from balance (when no active subscription) */
    public ?int $subscribe_plan_id = null;
    public int $subscribe_months = 1;

    public function mount(): void
    {
        $user = auth('tenant')->user();
        abort_unless(
            $user && method_exists($user, 'isAdmin') && $user->isAdmin(),
            403,
            'Seul un administrateur peut gérer l’abonnement.'
        );
    }

    public function applyBalance(): void
    {
        $this->authorizeAdmin();
        $tenant = app(TenantManager::class)->tenant();
        $sub = $tenant?->currentSubscription();
        if (! $tenant || ! $sub) {
            notify()->error('Aucun abonnement.');

            return;
        }
        $this->validate(['apply_balance_months' => 'required|integer|min:1|max:120']);
        try {
            app(SubscriptionService::class)->applyBalanceToSubscription($sub, $this->apply_balance_months);
        } catch (\InvalidArgumentException $e) {
            notify()->error($e->getMessage());

            return;
        }
        notify()->success('Abonnement prolongé de '.$this->apply_balance_months.' mois.');
        $this->redirect(route('tenant.subscription', ['tenant' => $tenant->code]), navigate: true);
    }

    public function subscribeFromBalance(): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'subscribe_plan_id' => 'required|exists:plans,id',
            'subscribe_months' => 'required|integer|min:1|max:120',
        ]);
        $tenant = app(TenantManager::class)->tenant();
        if (! $tenant) {
            notify()->error('Aucun vendeur.');

            return;
        }
        try {
            app(SubscriptionService::class)->subscribeFromBalance($tenant, (int) $this->subscribe_plan_id, $this->subscribe_months);
        } catch (\InvalidArgumentException $e) {
            notify()->error($e->getMessage());

            return;
        }
        notify()->success('Abonnement activé. Vous pouvez utiliser l’application.');
        $this->subscribe_plan_id = null;
        $this->subscribe_months = 1;
        $this->redirect(route('tenant.subscription', ['tenant' => $tenant->code]), navigate: true);
    }

    public function changePlan(): void
    {
        $this->authorizeAdmin();
        $this->validate(['new_plan_id' => 'required|exists:plans,id']);
        $tenant = app(TenantManager::class)->tenant();
        $sub = $tenant?->currentSubscription();
        if (! $tenant || ! $sub) {
            notify()->error('Aucun abonnement.');

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
        notify()->success('Plan changé. Le reste de période a été remis sur votre solde — utilisez-le pour vous réabonner.');
        $this->new_plan_id = null;
        $this->redirect(route('tenant.subscription', ['tenant' => $tenant->code]), navigate: true);
    }

    public function render()
    {
        $this->authorizeAdmin();
        $tenant = app(TenantManager::class)->tenant();
        $subscription = $tenant ? $tenant->currentSubscription() : null;
        $subscriptions = $tenant ? $tenant->subscriptions : collect();
        $statusLabel = $subscription ? (Subscription::statuses()[$subscription->status] ?? $subscription->status) : null;
        $statusColor = $subscription ? $subscription->status_color : 'secondary';
        $isActive = $tenant && $tenant->is_active;
        $hasActiveSubscription = $tenant && $tenant->hasActiveSubscription();
        $payments = $tenant ? $tenant->payments()->take(50)->get() : collect();
        $balanceTransactions = $tenant ? $tenant->balanceTransactions()->take(30)->get() : collect();
        $allPlans = Plan::active()->ordered()->get();
        $plansForSubscribe = $tenant && ! $hasActiveSubscription ? $allPlans : collect();
        $otherPlans = $subscription
            ? $allPlans->filter(fn ($p) => (int) $p->id !== (int) $subscription->plan_id)->values()
            : collect();

        $daysLeft = null;
        $expiresSoon = false;
        if ($subscription && $hasActiveSubscription && $subscription->current_period_end) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($subscription->current_period_end->copy()->startOfDay(), false);
            $expiresSoon = $daysLeft >= 0 && $daysLeft <= 7;
        }

        $balance = $tenant ? (float) $tenant->balance : 0;
        $currency = $tenant?->balance_currency ?? 'XOF';
        $canRenewWithBalance = $hasActiveSubscription
            && $subscription
            && (float) $subscription->plan->price > 0
            && $balance >= (float) $subscription->plan->price;

        $primaryAction = 'none';
        if ($tenant && ! $hasActiveSubscription && $plansForSubscribe->isNotEmpty() && $balance > 0) {
            $primaryAction = 'activate';
        } elseif ($tenant && ! $hasActiveSubscription) {
            $primaryAction = 'need_payment';
        } elseif ($canRenewWithBalance) {
            $primaryAction = 'renew';
        } elseif ($hasActiveSubscription) {
            $primaryAction = 'ok';
        }

        return view('livewire.tenant.subscription-status')
            ->layout('layouts.app', [
                'title' => 'Abonnement',
                'subtitle' => 'Comprendre et gérer votre accès',
            ])
            ->with([
                'tenant' => $tenant,
                'subscription' => $subscription,
                'subscriptions' => $subscriptions,
                'statusLabel' => $statusLabel,
                'statusColor' => $statusColor,
                'isActive' => $isActive,
                'hasActiveSubscription' => $hasActiveSubscription,
                'payments' => $payments,
                'balanceTransactions' => $balanceTransactions,
                'plansForSubscribe' => $plansForSubscribe,
                'otherPlans' => $otherPlans,
                'daysLeft' => $daysLeft,
                'expiresSoon' => $expiresSoon,
                'balance' => $balance,
                'currency' => $currency,
                'canRenewWithBalance' => $canRenewWithBalance,
                'primaryAction' => $primaryAction,
            ]);
    }

    private function authorizeAdmin(): void
    {
        $user = auth('tenant')->user();
        abort_unless(
            $user && method_exists($user, 'isAdmin') && $user->isAdmin(),
            403,
            'Seul un administrateur peut gérer l’abonnement.'
        );
    }
}
