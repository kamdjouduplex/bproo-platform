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
        $this->ensureAdminOrForcedAccess();
    }

    public function applyBalance(): void
    {
        $this->ensureAdminCanManage();
        $tenant = app(TenantManager::class)->tenant();
        $sub = $tenant?->currentSubscription();
        if (!$tenant || !$sub) {
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
        notify()->success('Solde appliqué. Abonnement prolongé de ' . $this->apply_balance_months . ' mois.');
        $this->redirect(route('tenant.subscription', ['tenant' => $tenant->code]), navigate: true);
    }

    public function subscribeFromBalance(): void
    {
        $this->ensureAdminCanManage();
        $this->validate([
            'subscribe_plan_id' => 'required|exists:plans,id',
            'subscribe_months' => 'required|integer|min:1|max:120',
        ]);
        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            notify()->error('Aucun vendeur.');
            return;
        }
        try {
            app(SubscriptionService::class)->subscribeFromBalance($tenant, (int) $this->subscribe_plan_id, $this->subscribe_months);
        } catch (\InvalidArgumentException $e) {
            notify()->error($e->getMessage());
            return;
        }
        notify()->success('Abonnement activé avec le solde. Vous avez maintenant accès à l\'application.');
        $this->subscribe_plan_id = null;
        $this->subscribe_months = 1;
        $this->redirect(route('tenant.subscription', ['tenant' => $tenant->code]), navigate: true);
    }

    public function changePlan(): void
    {
        $this->ensureAdminCanManage();
        $this->validate(['new_plan_id' => 'required|exists:plans,id']);
        $tenant = app(TenantManager::class)->tenant();
        $sub = $tenant?->currentSubscription();
        if (!$tenant || !$sub) {
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
        notify()->success('Plan modifié. Le reliquat a été remboursé au solde. Utilisez le solde pour renouveler avec le nouveau plan.');
        $this->new_plan_id = null;
        $this->redirect(route('tenant.subscription', ['tenant' => $tenant->code]), navigate: true);
    }

    public function render()
    {
        $tenant = app(TenantManager::class)->tenant();
        $user = auth('tenant')->user();
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        $subscription = $tenant ? $tenant->currentSubscription() : null;
        $subscriptions = $tenant ? $tenant->subscriptions : collect();
        $statusLabel = $subscription ? (Subscription::statuses()[$subscription->status] ?? $subscription->status) : null;
        $statusColor = $subscription ? $subscription->status_color : 'secondary';
        $isActive = $tenant && $tenant->is_active;
        $hasActiveSubscription = $tenant && $tenant->hasActiveSubscription();
        $payments = $isAdmin && $tenant ? $tenant->payments()->take(50)->get() : collect();
        $balanceTransactions = $isAdmin && $tenant ? $tenant->balanceTransactions()->take(30)->get() : collect();
        $plansForSubscribe = $isAdmin && $tenant && !$hasActiveSubscription
            ? Plan::active()->ordered()->get()
            : collect();

        return view('livewire.tenant.subscription-status')
            ->layout('layouts.app', [
                'title' => 'Abonnement',
                'subtitle' => $isAdmin ? 'État de votre abonnement' : 'Accès restreint',
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
                'canManageSubscription' => $isAdmin,
            ]);
    }

    private function ensureAdminOrForcedAccess(): void
    {
        $user = auth('tenant')->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return;
        }

        $tenant = app(TenantManager::class)->tenant();
        // Non-admins may land here only when middleware forces them (inactive / no subscription).
        if ($tenant && (! $tenant->is_active || ! $tenant->hasActiveSubscription())) {
            return;
        }

        $tenantCode = $tenant?->code ?? request()->query('tenant');
        $this->redirect(route('tenant.dashboard', ['tenant' => $tenantCode]), navigate: true);
    }

    private function ensureAdminCanManage(): void
    {
        $user = auth('tenant')->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return;
        }

        abort(403, 'Seul un administrateur peut gérer l’abonnement.');
    }
}
