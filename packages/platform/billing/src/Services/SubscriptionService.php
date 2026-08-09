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
     *
     * @param  int|null  $months  Optional explicit prepaid months (overrides amount÷price).
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
        ?int $subscriptionId = null,
        ?int $months = null
    ): TenantPayment {
        $method = $method ?? TenantPayment::METHOD_CASH;
        $paidAt = now()->toDateString();

        if ($planId) {
            return $this->recordPaymentAndApplyToPlan(
                $tenant,
                $amount,
                $currency,
                $method,
                $reference,
                $notes,
                $recordedBy,
                $paidAt,
                $planId,
                $subscriptionId,
                $months
            );
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
     * Quote how a payment would apply to a plan (preview for UI).
     *
     * @return array{
     *   seats: int|null,
     *   unit_price: float,
     *   months: int,
     *   amount_used: float,
     *   remainder: float,
     *   billing_mode: string
     * }
     */
    public function quotePlanPayment(Tenant $tenant, Plan $plan, float $amount, ?int $forcedMonths = null): array
    {
        $seats = $plan->isPerSeat() ? $plan->resolveSeats($tenant) : null;
        $unit = $plan->monthlyRateFor($tenant, $seats);
        $mode = $plan->isPerSeat() ? Plan::MODE_PER_SEAT : Plan::MODE_FLAT;

        if ($forcedMonths !== null && $forcedMonths > 0) {
            $months = $forcedMonths;
            $amountUsed = round($unit * $months, 2);
            if ($unit > 0 && $amount + 0.0001 < $amountUsed) {
                throw new \InvalidArgumentException(
                    'Montant insuffisant pour '.$months.' mois. '
                    .'Tarif : '.fmt_money($unit).' '.($plan->currency ?: 'XOF').'/mois'
                    .($seats ? ' ('.$seats.' siège(s))' : '')
                    .' → requis '.fmt_money($amountUsed).', reçu '.fmt_money($amount).'.'
                );
            }
            $remainder = round(max(0, $amount - $amountUsed), 2);

            return [
                'seats' => $seats,
                'unit_price' => $unit,
                'months' => $months,
                'amount_used' => $amountUsed,
                'remainder' => $remainder,
                'billing_mode' => $mode,
            ];
        }

        if ($unit <= 0) {
            return [
                'seats' => $seats,
                'unit_price' => 0.0,
                'months' => 1,
                'amount_used' => 0.0,
                'remainder' => $amount,
                'billing_mode' => $mode,
            ];
        }

        $months = (int) floor($amount / $unit);
        $amountUsed = round($months * $unit, 2);
        $remainder = round($amount - $amountUsed, 2);

        return [
            'seats' => $seats,
            'unit_price' => $unit,
            'months' => $months,
            'amount_used' => $amountUsed,
            'remainder' => $remainder,
            'billing_mode' => $mode,
        ];
    }

    /**
     * Apply payment to a plan: create or extend subscription by months; remainder to balance.
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
        ?int $subscriptionId,
        ?int $forcedMonths
    ): TenantPayment {
        $plan = Plan::findOrFail($planId);
        $quote = $this->quotePlanPayment($tenant, $plan, $amount, $forcedMonths);
        $months = (int) $quote['months'];
        $remainder = (float) $quote['remainder'];
        $unit = (float) $quote['unit_price'];
        $seats = $quote['seats'];
        $mode = (string) $quote['billing_mode'];

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

        $autoNote = $months.' mois'
            .($seats ? ' · '.$seats.' siège(s)' : '')
            .' · '.fmt_money($unit).' '.($currency).'/mois';
        $mergedNotes = trim(($notes ? $notes.' — ' : '').$autoNote);

        return DB::transaction(function () use ($tenant, $amount, $currency, $method, $reference, $mergedNotes, $recordedBy, $paidAt, $plan, $subscriptionId, $months, $remainder, $unit, $seats, $mode) {
            $sub = $subscriptionId ? Subscription::where('tenant_id', $tenant->id)->find($subscriptionId) : $tenant->currentSubscription();
            if (! $sub || $sub->plan_id !== $plan->id || $sub->status === Subscription::STATUS_CANCELLED) {
                $sub = $this->createSubscriptionWithPeriod($tenant, $plan, $months, $paidAt, $mode, $seats, $unit);
            } else {
                $this->extendSubscriptionByMonths($sub, $months);
                $sub->update([
                    'billing_mode' => $mode,
                    'seats_billed' => $seats,
                    'unit_price' => $unit,
                ]);
            }
            $sub->activate(activateTenant: true);
            $sub->clearGrace();

            $payment = TenantPayment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $sub->id,
                'amount' => $amount,
                'months_applied' => $months,
                'seats_billed' => $seats,
                'unit_price' => $unit,
                'currency' => $currency,
                'paid_at' => $paidAt,
                'method' => $method,
                'reference' => $reference,
                'recorded_by' => $recordedBy,
                'notes' => $mergedNotes,
            ]);

            if ($remainder > 0) {
                $this->creditBalance($tenant, $remainder, TenantBalanceTransaction::TYPE_PAYMENT_CREDIT, TenantPayment::class, $payment->id, 'Reliquat versement');
            }

            return $payment;
        });
    }

    private function createSubscriptionWithPeriod(
        Tenant $tenant,
        Plan $plan,
        int $months,
        string $fromDate,
        ?string $billingMode = null,
        ?int $seats = null,
        ?float $unitPrice = null
    ): Subscription {
        $start = Carbon::parse($fromDate)->startOfDay();
        $end = $start->copy()->addMonths($months)->subDay()->endOfMonth();

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_mode' => $billingMode ?? ($plan->isPerSeat() ? Plan::MODE_PER_SEAT : Plan::MODE_FLAT),
            'seats_billed' => $seats,
            'unit_price' => $unitPrice ?? $plan->monthlyRateFor($tenant, $seats),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'activated_at' => now(),
        ]);
    }

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

    public function applyBalanceToSubscription(Subscription $subscription, int $months = 1): void
    {
        if ($subscription->status === Subscription::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Impossible de renouveler un abonnement annulé. Enregistrez un nouveau paiement pour créer un nouvel abonnement.');
        }
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;
        $seats = $plan->isPerSeat() ? $plan->resolveSeats($tenant) : null;
        $unit = $plan->monthlyRateFor($tenant, $seats);
        $total = round($unit * $months, 2);
        if ($total <= 0) {
            $months = 1;
            $total = 0;
        }
        $balance = (float) $tenant->balance;
        if ($balance < $total) {
            throw new \InvalidArgumentException('Solde insuffisant. Solde: '.fmt_money($balance).' '.$tenant->balance_currency.', requis: '.fmt_money($total).' '.$tenant->balance_currency);
        }

        DB::transaction(function () use ($tenant, $subscription, $months, $total, $seats, $unit, $plan) {
            $this->debitBalance($tenant, $total, TenantBalanceTransaction::TYPE_SUBSCRIPTION_APPLICATION, Subscription::class, $subscription->id, 'Renouvellement '.$months.' mois');
            $this->extendSubscriptionByMonths($subscription, $months);
            $subscription->update([
                'billing_mode' => $plan->isPerSeat() ? Plan::MODE_PER_SEAT : Plan::MODE_FLAT,
                'seats_billed' => $seats,
                'unit_price' => $unit,
            ]);
            $subscription->activate(activateTenant: true);
            $subscription->clearGrace();
        });
    }

    public function subscribeFromBalance(Tenant $tenant, int $planId, int $months = 1): void
    {
        $plan = Plan::findOrFail($planId);
        $seats = $plan->isPerSeat() ? $plan->resolveSeats($tenant) : null;
        $unit = $plan->monthlyRateFor($tenant, $seats);
        $total = round($unit * $months, 2);
        if ($total <= 0) {
            $months = 1;
            $total = 0;
        }
        $balance = (float) $tenant->balance;
        if ($balance < $total) {
            throw new \InvalidArgumentException(
                'Solde insuffisant. Solde : '.fmt_money($balance).' '.$tenant->balance_currency
                .', requis pour '.$months.' mois : '.fmt_money($total).' '.($plan->currency ?: $tenant->balance_currency).'.'
            );
        }

        DB::transaction(function () use ($tenant, $plan, $months, $total, $seats, $unit) {
            $sub = $this->createSubscriptionWithPeriod(
                $tenant,
                $plan,
                $months,
                now()->toDateString(),
                $plan->isPerSeat() ? Plan::MODE_PER_SEAT : Plan::MODE_FLAT,
                $seats,
                $unit
            );
            $this->debitBalance($tenant, $total, TenantBalanceTransaction::TYPE_SUBSCRIPTION_APPLICATION, Subscription::class, $sub->id, 'Souscription '.$months.' mois (solde)');
            $sub->activate(activateTenant: true);
        });
    }

    public function changePlan(Subscription $subscription, int $newPlanId): void
    {
        $newPlan = Plan::findOrFail($newPlanId);
        $tenant = $subscription->tenant;
        $newPlanPrice = $newPlan->monthlyRateFor($tenant);

        if ($newPlanPrice > 0) {
            $refund = $this->calculateProrataRefund($subscription);
            $balance = (float) $tenant->balance;
            $totalAfterRefund = $balance + $refund;

            if ($totalAfterRefund < $newPlanPrice) {
                $depositNeeded = round($newPlanPrice - $totalAfterRefund, 2);
                $currency = $newPlan->currency ?: $tenant->balance_currency ?: 'FCFA';
                throw new \InvalidArgumentException(
                    'Solde insuffisant pour changer de plan. '
                    .'Votre solde actuel : '.fmt_money($balance).' '.$currency.'. '
                    .'Remboursement du reliquat : '.fmt_money($refund).' '.$currency.'. '
                    .'Total après changement : '.fmt_money($totalAfterRefund).' '.$currency.'. '
                    .'Le plan « '.$newPlan->name.' » coûte '.fmt_money($newPlanPrice).' '.$currency.'/mois. '
                    .'Effectuez un dépôt d\'au moins '.fmt_money($depositNeeded).' '.$currency.' pour pouvoir changer de plan.'
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
                'billing_mode' => $newPlan->isPerSeat() ? Plan::MODE_PER_SEAT : Plan::MODE_FLAT,
                'seats_billed' => $newPlan->isPerSeat() ? $newPlan->resolveSeats($tenant) : null,
                'unit_price' => $newPlan->monthlyRateFor($tenant),
                'current_period_end' => now()->startOfDay(),
                'grace_ends_at' => null,
            ]);
        });
    }

    private function calculateProrataRefund(Subscription $subscription): float
    {
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;
        $price = $subscription->unit_price !== null
            ? (float) $subscription->unit_price
            : $plan->monthlyRateFor($tenant, $subscription->seats_billed);
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

    public function adjustBalance(Tenant $tenant, float $amount, string $description = 'Ajustement admin'): void
    {
        $type = TenantBalanceTransaction::TYPE_ADMIN_ADJUSTMENT;
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
                $sub->suspend('Période échue (auto-suspension le '.now()->format('d/m/Y').')', deactivateTenant: true);
                $count++;
            });

        return $count;
    }
}

