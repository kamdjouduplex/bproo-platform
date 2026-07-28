<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantBalanceTransaction;
use App\Models\TenantPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Record a payment. Either apply to subscription (plan) or add to balance only.
     * When applying to subscription: period is set/extended from amount (months = floor(amount/plan.price)), remainder to balance.
     */
    public function recordPayment(
        Tenant $tenant,
        float $amount,
        string $currency = 'XOF',
        ?string $method = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $recordedBy = null,
        ?int $planId = null,
        ?int $subscriptionId = null
    ): TenantPayment {
        $method = $method ?? TenantPayment::METHOD_CASH;
        $paidAt = now()->toDateString();

        if ($planId) {
            return $this->recordPaymentAndApplyToPlan($tenant, $amount, $currency, $method, $reference, $notes, $recordedBy, $paidAt, $planId, $subscriptionId);
        }

        return DB::transaction(function () use ($tenant, $amount, $currency, $method, $reference, $notes, $recordedBy, $paidAt) {
            $payment = TenantPayment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => null,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => $paidAt,
                'method' => $method,
                'reference' => $reference,
                'recorded_by' => $recordedBy,
                'notes' => $notes,
            ]);
            $this->creditBalance($tenant, $amount, TenantBalanceTransaction::TYPE_PAYMENT_CREDIT, TenantPayment::class, $payment->id, 'Versement');
            return $payment;
        });
    }

    /**
     * Apply payment to a plan: create or extend subscription by months from amount; remainder to balance.
     */
    private function recordPaymentAndApplyToPlan(
        Tenant $tenant,
        float $amount,
        string $currency,
        string $method,
        ?string $reference,
        ?string $notes,
        ?int $recordedBy,
        string $paidAt,
        int $planId,
        ?int $subscriptionId
    ): TenantPayment {
        $plan = Plan::findOrFail($planId);
        $price = (float) $plan->price;
        if ($price <= 0) {
            $months = 1;
            $amountUsed = 0;
            $remainder = $amount;
        } else {
            $months = (int) floor($amount / $price);
            $amountUsed = $months * $price;
            $remainder = $amount - $amountUsed;
        }

        if ($months <= 0) {
            return DB::transaction(function () use ($tenant, $amount, $currency, $method, $reference, $notes, $recordedBy, $paidAt) {
                $payment = TenantPayment::create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => null,
                    'amount' => $amount,
                    'currency' => $currency,
                    'paid_at' => $paidAt,
                    'method' => $method,
                    'reference' => $reference,
                    'recorded_by' => $recordedBy,
                    'notes' => $notes,
                ]);
                $this->creditBalance($tenant, $amount, TenantBalanceTransaction::TYPE_PAYMENT_CREDIT, TenantPayment::class, $payment->id, 'Versement (solde)');
                return $payment;
            });
        }

        return DB::transaction(function () use ($tenant, $amount, $currency, $method, $reference, $notes, $recordedBy, $paidAt, $plan, $subscriptionId, $months, $remainder) {
            $sub = $subscriptionId ? Subscription::where('tenant_id', $tenant->id)->find($subscriptionId) : $tenant->currentSubscription();
            // Never extend a cancelled subscription — create a fresh one so period starts from the new payment date
            if (!$sub || $sub->plan_id !== $plan->id || $sub->status === Subscription::STATUS_CANCELLED) {
                $sub = $this->createSubscriptionWithPeriod($tenant, $plan, $months, $paidAt);
            } else {
                $this->extendSubscriptionByMonths($sub, $months);
            }
            $sub->activate(activateTenant: true);
            $sub->clearGrace();

            $payment = TenantPayment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $sub->id,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => $paidAt,
                'method' => $method,
                'reference' => $reference,
                'recorded_by' => $recordedBy,
                'notes' => $notes,
            ]);

            if ($remainder > 0) {
                $this->creditBalance($tenant, $remainder, TenantBalanceTransaction::TYPE_PAYMENT_CREDIT, TenantPayment::class, $payment->id, 'Reliquat versement');
            }
            return $payment;
        });
    }

    /**
     * Create a new subscription with period from payment date for N months.
     * Period ends on the last day of the Nth full month (1 month from Mar 1 → Mar 31; 2 months from Feb 28 → Apr 30).
     */
    private function createSubscriptionWithPeriod(Tenant $tenant, Plan $plan, int $months, string $fromDate): Subscription
    {
        $start = Carbon::parse($fromDate)->startOfDay();
        // End = last day of the month containing (start + N months - 1 day)
        $end = $start->copy()->addMonths($months)->subDay()->endOfMonth();
        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'activated_at' => now(),
        ]);
    }

    /**
     * Extend subscription by N months from day after current period_end.
     * New period ends on the last day of the Nth full month.
     */
    private function extendSubscriptionByMonths(Subscription $subscription, int $months): void
    {
        if ($months <= 0) {
            return;
        }
        $currentEnd = $subscription->current_period_end->copy();
        $newStart = $currentEnd->copy()->addDay();
        $newEnd = $newStart->copy()->addMonths($months)->subDay()->endOfMonth();
        $subscription->update([
            'current_period_start' => $newStart,
            'current_period_end' => $newEnd,
            'grace_ends_at' => null,
        ]);
    }

    /**
     * Apply balance to subscription: deduct from tenant balance and extend period by N months.
     */
    public function applyBalanceToSubscription(Subscription $subscription, int $months = 1): void
    {
        if ($subscription->status === Subscription::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Impossible de renouveler un abonnement annulé. Enregistrez un nouveau paiement pour créer un nouvel abonnement.');
        }
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;
        $price = (float) $plan->price;
        $total = $price * $months;
        if ($total <= 0) {
            $months = 1;
            $total = 0;
        }
        $balance = (float) $tenant->balance;
        if ($balance < $total) {
            throw new \InvalidArgumentException('Solde insuffisant. Solde: ' . fmt_money($balance) . ' ' . $tenant->balance_currency . ', requis: ' . fmt_money($total) . ' ' . $tenant->balance_currency);
        }

        DB::transaction(function () use ($tenant, $subscription, $months, $total) {
            $this->debitBalance($tenant, $total, TenantBalanceTransaction::TYPE_SUBSCRIPTION_APPLICATION, Subscription::class, $subscription->id, 'Renouvellement ' . $months . ' mois');
            $this->extendSubscriptionByMonths($subscription, $months);
            $subscription->activate(activateTenant: true);
            $subscription->clearGrace();
        });
    }

    /**
     * Create a new subscription using tenant balance (when they have no active subscription).
     * Deducts plan.price * months from balance and creates subscription from today.
     */
    public function subscribeFromBalance(Tenant $tenant, int $planId, int $months = 1): void
    {
        $plan = Plan::findOrFail($planId);
        $price = (float) $plan->price;
        $total = $price * $months;
        if ($total <= 0) {
            $months = 1;
            $total = 0;
        }
        $balance = (float) $tenant->balance;
        if ($balance < $total) {
            throw new \InvalidArgumentException(
                'Solde insuffisant. Solde : ' . fmt_money($balance) . ' ' . $tenant->balance_currency
                . ', requis pour ' . $months . ' mois : ' . fmt_money($total) . ' ' . ($plan->currency ?: $tenant->balance_currency) . '.'
            );
        }

        DB::transaction(function () use ($tenant, $plan, $months, $total) {
            $sub = $this->createSubscriptionWithPeriod($tenant, $plan, $months, now()->toDateString());
            $this->debitBalance($tenant, $total, TenantBalanceTransaction::TYPE_SUBSCRIPTION_APPLICATION, Subscription::class, $sub->id, 'Souscription ' . $months . ' mois (solde)');
            $sub->activate(activateTenant: true);
        });
    }

    /**
     * Change plan: refund prorata of unused period to balance, switch plan, end current period.
     * Requires: balance + refund >= new plan price (at least 1 month). Otherwise throws.
     */
    public function changePlan(Subscription $subscription, int $newPlanId): void
    {
        $newPlan = Plan::findOrFail($newPlanId);
        $tenant = $subscription->tenant;
        $newPlanPrice = (float) $newPlan->price;

        if ($newPlanPrice > 0) {
            $refund = $this->calculateProrataRefund($subscription);
            $balance = (float) $tenant->balance;
            $totalAfterRefund = $balance + $refund;

            if ($totalAfterRefund < $newPlanPrice) {
                $depositNeeded = round($newPlanPrice - $totalAfterRefund, 2);
                $currency = $newPlan->currency ?: $tenant->balance_currency ?: 'FCFA';
                throw new \InvalidArgumentException(
                    'Solde insuffisant pour changer de plan. '
                    . 'Votre solde actuel : ' . fmt_money($balance) . ' ' . $currency . '. '
                    . 'Remboursement du reliquat : ' . fmt_money($refund) . ' ' . $currency . '. '
                    . 'Total après changement : ' . fmt_money($totalAfterRefund) . ' ' . $currency . '. '
                    . 'Le plan « ' . $newPlan->name . ' » coûte ' . fmt_money($newPlanPrice) . ' ' . $currency . '/mois. '
                    . 'Effectuez un dépôt d\'au moins ' . fmt_money($depositNeeded) . ' ' . $currency . ' pour pouvoir changer de plan.'
                );
            }
        }

        DB::transaction(function () use ($subscription, $newPlan, $tenant) {
            $refund = $this->calculateProrataRefund($subscription);
            if ($refund > 0) {
                $this->creditBalance($tenant, $refund, TenantBalanceTransaction::TYPE_PLAN_CHANGE_REFUND, Subscription::class, $subscription->id, 'Remboursement changement de plan');
            }
            $subscription->update([
                'plan_id' => $newPlan->id,
                'current_period_end' => now()->startOfDay(),
                'grace_ends_at' => null,
            ]);
        });
    }

    /**
     * Prorata refund: (days left / days in period) * plan price.
     */
    private function calculateProrataRefund(Subscription $subscription): float
    {
        $plan = $subscription->plan;
        $price = (float) $plan->price;
        if ($price <= 0) {
            return 0;
        }
        $end = $subscription->current_period_end->startOfDay();
        $start = $subscription->current_period_start->startOfDay();
        if ($end->lte(now())) {
            return 0;
        }
        $daysTotal = $start->diffInDays($end) ?: 1;
        $daysLeft = now()->startOfDay()->diffInDays($end);
        if ($daysLeft <= 0) {
            return 0;
        }
        return round($price * ($daysLeft / $daysTotal), 2);
    }

    private function creditBalance(Tenant $tenant, float $amount, string $type, ?string $refType = null, ?int $refId = null, ?string $description = null): void
    {
        TenantBalanceTransaction::create([
            'tenant_id' => $tenant->id,
            'amount' => $amount,
            'type' => $type,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'description' => $description,
        ]);
        $tenant->increment('balance', $amount);
    }

    private function debitBalance(Tenant $tenant, float $amount, string $type, ?string $refType = null, ?int $refId = null, ?string $description = null): void
    {
        TenantBalanceTransaction::create([
            'tenant_id' => $tenant->id,
            'amount' => -$amount,
            'type' => $type,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'description' => $description,
        ]);
        $tenant->decrement('balance', $amount);
    }

    /**
     * Admin adjustment to tenant balance.
     */
    public function adjustBalance(Tenant $tenant, float $amount, string $description = 'Ajustement admin'): void
    {
        $type = $amount >= 0 ? TenantBalanceTransaction::TYPE_ADMIN_ADJUSTMENT : TenantBalanceTransaction::TYPE_ADMIN_ADJUSTMENT;
        if ($amount >= 0) {
            $this->creditBalance($tenant, $amount, $type, null, null, $description);
        } else {
            $this->debitBalance($tenant, -$amount, $type, null, null, $description);
        }
    }

    public function suspendSubscription(Subscription $subscription, ?string $reason = null): void
    {
        $subscription->suspend($reason, deactivateTenant: true);
    }

    public function grantGrace(Subscription $subscription, int $days = 15): void
    {
        $subscription->grantGrace($days);
    }

    /**
     * Cancel subscription permanently: refund prorata to balance, set status cancelled, deactivate tenant.
     */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): void
    {
        $tenant = $subscription->tenant;

        DB::transaction(function () use ($subscription, $tenant, $reason) {
            $refund = $this->calculateProrataRefund($subscription);
            if ($refund > 0) {
                $this->creditBalance($tenant, $refund, TenantBalanceTransaction::TYPE_PLAN_CHANGE_REFUND, Subscription::class, $subscription->id, 'Remboursement annulation abonnement');
            }
            $subscription->cancel($reason, deactivateTenant: true);
        });
    }

    /**
     * Suspend all active subscriptions whose period has ended (e.g. run on the 5th of each month).
     */
    public function suspendOverdueSubscriptions(Carbon $deadline): int
    {
        $deadline = $deadline->copy()->startOfDay();
        $count = 0;
        Subscription::query()
            ->with(['plan'])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('current_period_end', '<', $deadline)
            ->whereHas('plan', fn ($q) => $q->where('is_demo', false))
            ->where(function ($q) use ($deadline) {
                $q->whereNull('grace_ends_at')->orWhere('grace_ends_at', '<', $deadline);
            })
            ->each(function (Subscription $sub) use (&$count) {
                $sub->suspend('Période échue (auto-suspension le ' . now()->format('d/m/Y') . ')', deactivateTenant: true);
                $count++;
            });
        return $count;
    }
}
