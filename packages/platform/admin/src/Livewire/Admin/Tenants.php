<?php

namespace App\Livewire\Admin;

use App\Models\PlatformProspect;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Services\CompanyIntelligenceService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Unified Clients = Companies list (one tenant entity).
 * Combines commercial CRM filters with ops provisioning actions.
 */
class Tenants extends Component
{
    use WithPagination;

    public string $search = '';
    public string $product = '';
    public string $status = ''; // '' | active_sub | suspended | none
    public string $active = '';

    protected $paginationTheme = 'cc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingProduct(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingActive(): void
    {
        $this->resetPage();
    }

    public function delete(int $tenantId): void
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        $tenant->delete();
        notify()->success('Entreprise supprimée.');
    }

    public function refreshMetrics(): void
    {
        $n = app(CompanyIntelligenceService::class)->refreshAll();
        notify()->success("Indicateurs actualisés pour {$n} entreprise(s).");
    }

    public function render()
    {
        $paidSub = TenantPayment::query()
            ->select('tenant_id', DB::raw('SUM(amount) as total_paid'))
            ->groupBy('tenant_id');

        $latestSubIds = Subscription::query()
            ->selectRaw('MAX(id)')
            ->groupBy('tenant_id');

        $query = Tenant::query()
            ->leftJoinSub($paidSub, 'pay', 'pay.tenant_id', '=', 'tenants.id')
            ->select('tenants.*', 'pay.total_paid')
            ->with(['subscriptions' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderBy('tenants.name');

        if (trim($this->search) !== '') {
            $term = '%'.strtolower(trim($this->search)).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(tenants.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(tenants.code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(tenants.contact_key_first_name, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(tenants.contact_key_last_name, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(tenants.contact_key_phone, \'\')) LIKE ?', [$term]);
            });
        }
        if ($this->product !== '') {
            $query->where('tenants.type', $this->product);
        }
        if ($this->active === '1') {
            $query->where('tenants.is_active', true);
        } elseif ($this->active === '0') {
            $query->where('tenants.is_active', false);
        }

        if ($this->status === 'active_sub') {
            $query->whereIn('tenants.id', function ($q) use ($latestSubIds) {
                $q->select('tenant_id')
                    ->from('subscriptions')
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->whereIn('id', $latestSubIds);
            });
        } elseif ($this->status === 'suspended') {
            $query->whereIn('tenants.id', function ($q) use ($latestSubIds) {
                $q->select('tenant_id')
                    ->from('subscriptions')
                    ->where('status', Subscription::STATUS_SUSPENDED)
                    ->whereIn('id', $latestSubIds);
            });
        } elseif ($this->status === 'none') {
            $query->whereNotIn('tenants.id', function ($q) {
                $q->select('tenant_id')->from('subscriptions');
            });
        }

        $tenants = $query->paginate(25);
        $tenantIds = $tenants->getCollection()->pluck('id');

        $subs = Subscription::query()
            ->with('plan')
            ->whereIn('id', function ($q) use ($tenantIds) {
                $q->selectRaw('MAX(id)')
                    ->from('subscriptions')
                    ->whereIn('tenant_id', $tenantIds)
                    ->groupBy('tenant_id');
            })
            ->get()
            ->keyBy('tenant_id');

        $origins = PlatformProspect::query()
            ->with('owner:id,name')
            ->whereIn('converted_tenant_id', $tenantIds)
            ->get(['id', 'converted_tenant_id', 'company_name', 'source', 'owner_user_id'])
            ->keyBy('converted_tenant_id');

        return view('livewire.admin.tenants', [
            'tenants' => $tenants,
            'subs' => $subs,
            'origins' => $origins,
            'productTypes' => config('tenant_types.types', []),
            'statusLabels' => Subscription::statuses(),
            'kpis' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('is_active', true)->count(),
                'with_sub' => Subscription::query()
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->whereIn('id', Subscription::query()->selectRaw('MAX(id)')->groupBy('tenant_id'))
                    ->count(),
                'from_crm' => PlatformProspect::whereNotNull('converted_tenant_id')->count(),
            ],
        ])->layout('layouts.app', [
            'title' => 'Clients',
            'subtitle' => 'Entreprises actives',
        ]);
    }
}
