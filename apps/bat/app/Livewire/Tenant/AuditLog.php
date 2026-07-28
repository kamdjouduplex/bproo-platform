<?php

namespace App\Livewire\Tenant;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLog extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $filterTable  = '';
    public string $filterUser   = '';
    public string $filterEvent  = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';
    public int    $perPage      = 25;

    public function mount(): void
    {
        $this->tenantAuthorize('audit.view');
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');
    }

    public function updatedFilterTable(): void  { $this->resetPage(); }
    public function updatedFilterUser(): void   { $this->resetPage(); }
    public function updatedFilterEvent(): void  { $this->resetPage(); }
    public function updatedDateFrom(): void     { $this->resetPage(); }
    public function updatedDateTo(): void       { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->filterTable = '';
        $this->filterUser  = '';
        $this->filterEvent = '';
        $this->dateFrom    = now()->subDays(30)->format('Y-m-d');
        $this->dateTo      = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function render()
    {
        $query = DB::connection('tenant')->table('audit_logs')
            ->when($this->filterTable !== '', fn($q) => $q->where('auditable_type', $this->filterTable))
            ->when($this->filterUser  !== '', fn($q) => $q->where('user_id', $this->filterUser))
            ->when($this->filterEvent !== '', fn($q) => $q->where('event', $this->filterEvent))
            ->when($this->dateFrom    !== '', fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo      !== '', fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderBy('created_at', 'desc');

        $logs = $query->paginate($this->perPage);

        // Distinct auditable_type values for filter dropdown
        $tables = DB::connection('tenant')->table('audit_logs')
            ->distinct()->pluck('auditable_type')->sort()->values();

        // All tenant users for filter dropdown
        $users = DB::connection('tenant')->table('users')
            ->orderBy('name')->get(['id', 'name']);

        // Map user_id → name for display
        $userMap = $users->pluck('name', 'id')->toArray();

        return view('livewire.tenant.audit-log', [
            'logs'    => $logs,
            'tables'  => $tables,
            'users'   => $users,
            'userMap' => $userMap,
        ])->layout('layouts.app', [
            'title'    => __('Journal d\'audit'),
            'subtitle' => __('Historique des modifications sur toutes les entités.'),
        ]);
    }
}
