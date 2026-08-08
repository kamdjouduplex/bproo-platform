<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Factures / abonnements: which companies are billed, status, plan, amounts paid.
 */
class BillingSubscriptions extends Component
{
    public string $search = '';
    public string $status = '';
    public string $planId = '';
    public string $activeOnly = '';

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->planId = '';
        $this->activeOnly = '';
    }

    public function render()
    {
        $paidSub = TenantPayment::query()
            ->select('tenant_id', DB::raw('SUM(amount) as total_paid'), DB::raw('COUNT(*) as payments_count'))
            ->groupBy('tenant_id');

        $latestSub = Subscription::query()
            ->select('subscriptions.*')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from('subscriptions')->groupBy('tenant_id');
            });

        $query = Tenant::query()
            ->leftJoinSub($paidSub, 'pay', 'pay.tenant_id', '=', 'tenants.id')
            ->select('tenants.*', 'pay.total_paid', 'pay.payments_count')
            ->orderBy('tenants.name');

        if (trim($this->search) !== '') {
            $term = '%' . strtolower(trim($this->search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(tenants.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(tenants.code) LIKE ?', [$term]);
            });
        }
        if ($this->activeOnly === '1') {
            $query->where('tenants.is_active', true);
        } elseif ($this->activeOnly === '0') {
            $query->where('tenants.is_active', false);
        }

        $tenants = $query->get();
        $subsByTenant = Subscription::query()
            ->with('plan')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from('subscriptions')->groupBy('tenant_id');
            })
            ->get()
            ->keyBy('tenant_id');

        if ($this->status !== '') {
            $tenants = $tenants->filter(function (Tenant $t) use ($subsByTenant) {
                $sub = $subsByTenant->get($t->id);
                if ($this->status === 'none') {
                    return ! $sub;
                }

                return $sub && $sub->status === $this->status;
            })->values();
        }
        if ($this->planId !== '') {
            $tenants = $tenants->filter(function (Tenant $t) use ($subsByTenant) {
                $sub = $subsByTenant->get($t->id);

                return $sub && (string) $sub->plan_id === $this->planId;
            })->values();
        }

        $mrr = 0.0;
        foreach ($subsByTenant as $sub) {
            if ($sub->status !== Subscription::STATUS_ACTIVE || ! $sub->plan) {
                continue;
            }
            $mrr += $sub->plan->billing_interval === 'yearly'
                ? ((float) $sub->plan->price / 12)
                : (float) $sub->plan->price;
        }

        return view('livewire.admin.billing-subscriptions', [
            'tenants' => $tenants,
            'subsByTenant' => $subsByTenant,
            'plans' => Plan::ordered()->get(),
            'statusLabels' => Subscription::statuses(),
            'kpis' => [
                'companies' => Tenant::count(),
                'active_subs' => $subsByTenant->where('status', Subscription::STATUS_ACTIVE)->count(),
                'suspended' => $subsByTenant->where('status', Subscription::STATUS_SUSPENDED)->count(),
                'mrr' => $mrr,
                'month_in' => (float) TenantPayment::where('paid_at', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            ],
        ])->layout('layouts.app', [
            'title' => 'Factures',
            'subtitle' => 'Abonnements clients · qui paie, combien, statut',
            'badge' => 'Facturation',
        ]);
    }
}
