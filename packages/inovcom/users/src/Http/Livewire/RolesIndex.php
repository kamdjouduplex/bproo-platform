<?php

namespace InovCom\Users\Http\Livewire;

use InovCom\Users\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;

class RolesIndex extends Component
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
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%');
                });
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
