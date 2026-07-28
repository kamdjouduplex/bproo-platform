<?php

namespace InovCom\Users\Http\Livewire;

use InovCom\Users\Models\User;
use Illuminate\Support\Facades\Schema;
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
        $usersQuery = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name');

        if (Schema::connection('tenant')->hasTable('stores') && Schema::connection('tenant')->hasColumn('users', 'assigned_store_id')) {
            $usersQuery->leftJoin('stores', 'users.assigned_store_id', '=', 'stores.id')
                ->addSelect('stores.name as assigned_store_name', 'users.*');
        }

        $users = $usersQuery->paginate($this->perPage);

        return view('inovcom-users::livewire.users.index')
            ->layout('layouts.app', [
                'title' => 'Utilisateurs',
                'subtitle' => 'Gestion des utilisateurs du tenant',
            ])
            ->with(['users' => $users]);
    }
}
