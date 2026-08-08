<?php

namespace App\Livewire\Admin;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Livewire\Component;

/**
 * Platform-wide payment ledger (Control Center / ops admin).
 */
class BillingPayments extends Component
{
    public string $search = '';
    public string $tenantCode = '';
    public string $method = '';
    public string $applied = '';
    public string $from = '';
    public string $to = '';

    public function mount(): void
    {
        $code = request()->query('tenant');
        if (is_string($code) && $code !== '') {
            $this->tenantCode = $code;
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->tenantCode = '';
        $this->method = '';
        $this->applied = '';
        $this->from = '';
        $this->to = '';
    }

    public function render()
    {
        $query = TenantPayment::query()
            ->with(['tenant', 'subscription.plan'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');

        if (trim($this->search) !== '') {
            $term = '%' . strtolower(trim($this->search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(COALESCE(reference, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', [$term])
                    ->orWhereHas('tenant', function ($tq) use ($term) {
                        $tq->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                    });
            });
        }
        if ($this->tenantCode !== '') {
            $query->whereHas('tenant', fn ($q) => $q->where('code', $this->tenantCode));
        }
        if ($this->method !== '') {
            $query->where('method', $this->method);
        }
        if ($this->applied === 'subscription') {
            $query->whereNotNull('subscription_id');
        } elseif ($this->applied === 'balance') {
            $query->whereNull('subscription_id');
        }
        if ($this->from !== '') {
            $query->whereDate('paid_at', '>=', $this->from);
        }
        if ($this->to !== '') {
            $query->whereDate('paid_at', '<=', $this->to);
        }

        $payments = $query->limit(300)->get();

        $base = TenantPayment::query();
        if ($this->from !== '') {
            $base->whereDate('paid_at', '>=', $this->from);
        }
        if ($this->to !== '') {
            $base->whereDate('paid_at', '<=', $this->to);
        }

        $periodTotal = (float) (clone $base)->sum('amount');
        $monthTotal = (float) TenantPayment::where('paid_at', '>=', now()->startOfMonth()->toDateString())->sum('amount');
        $activePaying = Tenant::where('is_active', true)
            ->whereHas('subscriptions', fn ($q) => $q->where('status', Subscription::STATUS_ACTIVE))
            ->count();

        return view('livewire.admin.billing-payments', [
            'payments' => $payments,
            'tenants' => Tenant::orderBy('name')->get(['id', 'code', 'name']),
            'methodLabels' => TenantPayment::methods(),
            'statusLabels' => Subscription::statuses(),
            'kpis' => [
                'period_total' => $periodTotal,
                'month_total' => $monthTotal,
                'count' => $payments->count(),
                'active_paying' => $activePaying,
            ],
        ])->layout('layouts.app', [
            'title' => 'Encaissements',
            'subtitle' => 'Ledger facturation plateforme',
        ]);
    }
}
