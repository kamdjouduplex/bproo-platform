<?php

namespace InovCom\Users\Http\Livewire;

use Illuminate\Support\Facades\Schema;
use InovCom\Users\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UsersIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $payrollOn = $this->payrollEnabled();

        $usersQuery = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                    if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
                        $q2->orWhere('phone', 'like', '%'.$this->search.'%');
                    }
                });
            })
            ->orderBy('name');

        if (Schema::connection('tenant')->hasTable('stores') && Schema::connection('tenant')->hasColumn('users', 'assigned_store_id')) {
            $usersQuery->leftJoin('stores', 'users.assigned_store_id', '=', 'stores.id')
                ->addSelect('stores.name as assigned_store_name', 'users.*');
        }

        $users = $usersQuery->paginate($this->perPage);

        $matricules = collect();
        if ($payrollOn && $users->count() > 0) {
            $matricules = \InovCom\Payroll\Models\Employee::query()
                ->whereIn('user_id', $users->pluck('id'))
                ->get(['user_id', 'employee_number', 'position'])
                ->keyBy('user_id');
        }

        return view('inovcom-users::livewire.users.index')
            ->layout('layouts.app', [
                'title' => 'Utilisateurs',
                'subtitle' => $payrollOn ? 'Comptes, rôles et fiches employés' : 'Gestion des utilisateurs du tenant',
            ])
            ->with([
                'users' => $users,
                'payrollEnabled' => $payrollOn,
                'matricules' => $matricules,
                'hasPhoneColumn' => Schema::connection('tenant')->hasColumn('users', 'phone'),
            ]);
    }

    private function payrollEnabled(): bool
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        if (! $tenant || ! class_exists(\App\Services\ModuleRegistry::class)) {
            return false;
        }

        return app(\App\Services\ModuleRegistry::class)->isEnabled('payroll', $tenant)
            && Schema::connection('tenant')->hasTable('employees');
    }
}
