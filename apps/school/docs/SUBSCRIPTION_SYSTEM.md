# Tenant Subscription System

This document describes the **tenant subscription system**: payment-driven periods, tenant balance, configurable plans, admin and tenant portals, cancellation, and automatic suspension.

---

## Overview

- **Payment or balance** is the only way to set or extend the active period. Periods are calculated from the amount paid (e.g. 30 000 FCFA on a 10 000 FCFA/month plan = 3 months) or from balance applied to a plan.
- **Balance**: Payments can be added to balance only (deposit). When balance is sufficient, the tenant or admin can **activate** or **renew** a subscription by applying balance to a plan. Partial payments and plan-change refunds go to balance.
- **Plans**: Configurable in admin (name, price, currency, is_demo). Demo plans are never auto-suspended.
- **Access control**: Without an active subscription, the tenant can only access login and the subscription page; dashboard, sales, stock, etc. are blocked.
- **Auto-suspension**: On the 5th of each month, a scheduled command suspends subscriptions whose period has ended (excluding demo and those in grace).
- **History**: Plans, subscriptions, payments, and balance transactions are kept for audit.

---

## Data Model (Central DB)

### Tables

| Table | Purpose |
|-------|--------|
| `plans` | name, slug, description, price, currency, billing_interval, is_active, is_demo, sort_order |
| `tenants` | balance (decimal), balance_currency (default XOF), is_active |
| `subscriptions` | tenant_id, plan_id, status (active/suspended/expired/cancelled), current_period_start, current_period_end, grace_ends_at, activated_at, suspended_at, cancelled_at, suspension_reason |
| `tenant_payments` | tenant_id, subscription_id (nullable), amount, currency, paid_at, method, reference, recorded_by, notes. subscription_id set = applied to that subscription; null = credit to balance only. |
| `tenant_balance_transactions` | tenant_id, amount (+ credit, - debit), type, reference_type, reference_id, description. Types: payment_credit, subscription_application, plan_change_refund, admin_adjustment. |

### Models

- **`App\Models\Plan`** – `periodMonths()`, `billingIntervals()`, `is_demo`, `scopeNotDemo()`, `scopeActive()`, `scopeOrdered()`.
- **`App\Models\Tenant`** – `balance`, `balance_currency`, `subscriptions()`, `payments()`, `balanceTransactions()`, `currentSubscription()`, `hasActiveSubscription()`.
- **`App\Models\Subscription`** – `tenant`, `plan`, `payments()`, `activate()`, `suspend()`, `cancel()`, `grantGrace()`, `inGrace()`, `clearGrace()`, `isActive()`, `isPeriodOver()`, `status_color`.
- **`App\Models\TenantPayment`** – `methods()`; relation to tenant and optional subscription.
- **`App\Models\TenantBalanceTransaction`** – `types()`; ledger for all balance changes.

---

## Period Calculation

- **Rule**: Period end = last day of the month that contains (start + N months − 1 day).  
  Formula: `$start->addMonths($months)->subDay()->endOfMonth()`.
- **Examples**: 1 month from Mar 1 → Mar 31; 1 month from Feb 28 → Mar 31; 2 months from Feb 28 → Apr 30.
- **Display**: Period is shown as **Jusqu'au DD/MM/YYYY** (single end date), not a range.

---

## Admin Dashboard

### Plans

- **Routes**: `/admin/plans` (list), `/admin/plans/create`, `/admin/plans/{id}/edit`.
- **Fields**: Name, slug, description, price, currency, billing interval, active, is_demo, sort order.

### Tenant subscription

- **Route**: `/admin/tenants/{tenant}/subscription`.
- **Balance** is shown at the top.
- **Actions** (when there is an active subscription):
  1. **Enregistrer un paiement**  
     Amount, currency, method, reference, notes. Option **Appliquer à un plan**:
     - With plan: `months = floor(amount / plan.price)`; subscription is created or extended; remainder to balance.
     - Without plan (solde uniquement): full amount to balance.
  2. **Utiliser le solde pour renouveler**  
     Number of months; deducts `months × plan.price` from balance and extends the current subscription. Only for non-cancelled subscriptions.
  3. **Changer de plan**  
     New plan selected. **Condition**: balance + prorata refund ≥ new plan price (at least 1 month). If not met, user is told to deposit the missing amount. On success: refund to balance, plan updated, period ends; tenant renews with balance or new payment.
  4. **Suspendre l'abonnement**  
     Suspends subscription and deactivates tenant.
  5. **Annuler l'abonnement**  
     Permanent: prorata refund to balance, status = cancelled, tenant deactivated. Next payment creates a new subscription (no extension of cancelled).
  6. **Accorder X jours de grâce**  
     Buttons: 5, 10, 15 days. Sets `grace_ends_at`; subscription is not auto-suspended until after that date (for non-demo plans).
- **Historique des paiements**: All tenant payments.
- **Mouvements de solde**: Last balance transactions (credits and debits).

---

## Tenant-Facing Subscription Portal

- **Route**: `/app/subscription?tenant={code}`. Only this page (and login/logout) is accessible without an active subscription.
- **Content**:
  - **Accès restreint** notice when there is no active subscription (explains that dashboard, sales, stock, etc. are unavailable).
  - **Solde** (balance).
  - **État de l'abonnement**: plan, statut, période (Jusqu'au …).
- **When there is no active subscription** (no subscription, or expired/cancelled):
  - **Activer votre abonnement avec le solde**: Choose plan, number of months, then **Activer avec le solde**. Deducts from balance and creates a new subscription from today.
- **When there is an active subscription**:
  - **Renouveler avec le solde**: If balance ≥ plan price, choose months and apply balance to extend.
  - **Changer de plan**: Same rule as admin (balance + refund ≥ new plan price); otherwise user is told to deposit more.
- **Liste des abonnements**: All subscriptions with plan, status, end date.
- **Historique des paiements**: All payments.
- **Mouvements de solde**: Last balance transactions (same structure as admin).

---

## Service Layer

**`App\Services\SubscriptionService`**

| Method | Description |
|--------|-------------|
| `recordPayment(...)` | If planId set: apply to plan (create/extend subscription), remainder to balance. Else: full amount to balance. |
| `applyBalanceToSubscription(Subscription, months)` | Deduct `months × plan.price` from balance, extend subscription. Throws if balance insufficient or subscription cancelled. |
| `subscribeFromBalance(Tenant, planId, months)` | Create new subscription from today using balance (for tenant with no active subscription). Deducts from balance, activates tenant. |
| `changePlan(Subscription, newPlanId)` | **Check**: balance + prorata refund ≥ new plan price; else throws with message (deposit required). Then: refund to balance, switch plan, end period. |
| `cancelSubscription(Subscription, ?reason)` | Prorata refund to balance, set status cancelled, deactivate tenant. Next payment creates a new subscription. |
| `suspendSubscription(Subscription, ?reason)` | Set status suspended, deactivate tenant. |
| `grantGrace(Subscription, days)` | Set grace_ends_at (e.g. 5, 10, 15 days). |
| `suspendOverdueSubscriptions(deadline)` | Suspend active subscriptions with period_end before deadline; skip demo and those with future grace_ends_at. |
| `adjustBalance(Tenant, amount, description)` | Admin adjustment (credit or debit). |

All balance changes go through `tenant_balance_transactions` and update `tenants.balance`.

**Subscription creation / extension**

- **New subscription**: Never extend a cancelled subscription; always create new via `createSubscriptionWithPeriod` when status is cancelled or plan differs.
- **Period**: `createSubscriptionWithPeriod` and `extendSubscriptionByMonths` use `addMonths(N)->subDay()->endOfMonth()` for the period end.

---

## Security (Access Without Subscription)

- **Enforcement**: In **`App\Http\Middleware\SetTenantConnection`**, if the tenant has no active subscription, only routes `tenant.login`, `tenant.login.submit`, `tenant.logout`, `tenant.subscription` are allowed. Any other route redirects to the subscription page and the tenant DB connection is not set.
- **No bypass**: All tenant app routes (including packages under `/app`) use middleware `tenant` and `tenant.active`. The check runs before the tenant connection is set.
- **Recommendation**: New tenant routes must use the same middleware group.

---

## Auto-Suspension (5th of Each Month)

- **Command**: `php artisan subscription:suspend-overdue`
- **Schedule**: `monthlyOn(5, '00:05')` in `app/Console/Kernel.php`.
- **Effect**: Suspends active subscriptions whose `current_period_end` is before the run date. Skips demo plans and subscriptions with `grace_ends_at` in the future.
- **Requirement**: Server cron must run `php artisan schedule:run` every minute. No admin or tenant action is required.

---

## Reset Tenant Subscription (Testing)

- **Command**: `php artisan subscription:reset-tenant {tenant_code}` (e.g. `DEMO`).
- **Effect**: Deletes all subscriptions, payments, and balance transactions for that tenant; sets balance to 0 and is_active to false. Use to re-test the flow from scratch.
- **Option**: `--force` to skip confirmation.

---

## Migrations

- `2026_02_28_000001_create_plans_table.php`
- `2026_02_28_000004_add_is_demo_to_plans_table.php`
- `2026_02_28_300000_restructure_subscription_system.php` – balance, tenant_balance_transactions, subscriptions, tenant_payments. Resets subscription/payment data.
- Related: `cancelled_at` on subscriptions, etc.

---

## Seeding Default Plans

Run `php artisan db:seed --class=PlansSeeder` (or full `db:seed`). Creates:

1. **Standard — 10 000 FCFA/mois** – 10 000 XOF/month, non-demo.
2. **Démo (essai)** – 0 FCFA, is_demo; never auto-suspended.

---

## Summary

| Need | Solution |
|------|----------|
| Set/extend period | Payment with plan, or apply balance to plan (create or extend subscription). |
| Deposit only | Record payment without plan → balance. Then activate/renew with “Activer avec le solde” or “Utiliser le solde pour renouveler”. |
| Activate with balance | No active subscription + balance ≥ plan price → “Activer votre abonnement avec le solde” (plan + months). |
| Change plan | Balance + prorata refund ≥ new plan price; else deposit first. Then change plan; refund to balance; renew with balance or payment. |
| Cancel | “Annuler l'abonnement”: refund to balance, status cancelled, tenant deactivated. Next payment creates new subscription. |
| Grace | Admin: 5, 10, or 15 days; postpones auto-suspension. |
| Access without subscription | Blocked; only login and subscription page allowed. |
| Auto-expiration | Scheduler runs `subscription:suspend-overdue` on the 5th; no admin link required. |
| History | subscriptions, tenant_payments, tenant_balance_transactions. |
| Period display | “Jusqu'au DD/MM/YYYY”. |
| Period end formula | `start->addMonths($months)->subDay()->endOfMonth()`. |
