<?php

namespace InovCom\Users\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Users\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UsersIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public function mount(): void
    {
        $this->tenantAuthorize('users.view');
    }

    public string $search = '';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('inovcom-users::livewire.users.index')
            ->layout('layouts.app', [
                'title' => 'Utilisateurs',
                'subtitle' => 'Gestion des utilisateurs du tenant',
            ])
            ->with(['users' => $users]);
    }
}
