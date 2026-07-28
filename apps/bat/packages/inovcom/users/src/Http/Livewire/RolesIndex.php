<?php

namespace InovCom\Users\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Users\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;

class RolesIndex extends Component
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
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($this->search !== '', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('inovcom-users::livewire.roles.index')
            ->layout('layouts.app', [
                'title' => 'Rôles',
                'subtitle' => 'Gestion des rôles et permissions',
            ])
            ->with(['roles' => $roles]);
    }
}
