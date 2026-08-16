<?php

namespace School\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ResolvesTenantCode;

class SchoolAuditIndex extends Component
{
    use ResolvesTenantCode;
    use WithPagination;

    public string $filterTable = '';

    public string $filterUser = '';

    public string $filterEvent = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 25;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedFilterTable(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUser(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEvent(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filterTable = '';
        $this->filterUser = '';
        $this->filterEvent = '';
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function render()
    {
        $hasTable = false;
        try {
            $hasTable = \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('audit_logs');
        } catch (\Throwable) {
            $hasTable = false;
        }

        $logs = null;
        $tables = collect();
        $users = collect();
        $userMap = [];

        if ($hasTable) {
            $query = DB::connection('tenant')->table('audit_logs')
                ->when($this->filterTable !== '', fn ($q) => $q->where('auditable_type', $this->filterTable))
                ->when($this->filterUser !== '', fn ($q) => $q->where('user_id', $this->filterUser))
                ->when($this->filterEvent !== '', fn ($q) => $q->where('event', $this->filterEvent))
                ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
                ->when($this->dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
                ->orderByDesc('created_at');

            $logs = $query->paginate($this->perPage);
            $tables = DB::connection('tenant')->table('audit_logs')
                ->distinct()->pluck('auditable_type')->sort()->values();
            $users = DB::connection('tenant')->table('users')->orderBy('name')->get(['id', 'name']);
            $userMap = $users->pluck('name', 'id')->all();
        }

        return view('school::livewire.school.audit.index', [
            'hasTable' => $hasTable,
            'logs' => $logs,
            'tables' => $tables,
            'users' => $users,
            'userMap' => $userMap,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Journal d’audit',
            'subtitle' => 'Traçabilité des actions critiques.',
        ]);
    }
}
