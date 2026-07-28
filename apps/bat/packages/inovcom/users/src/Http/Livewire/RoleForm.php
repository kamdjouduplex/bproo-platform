<?php

namespace InovCom\Users\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Illuminate\Validation\Rule;
use InovCom\Users\Models\Role;
use Livewire\Component;

class RoleForm extends Component
{
    use AuthorizesWithTenant;
    public ?int $roleId = null;
    public string $name = '';
    public string $description = '';

    public function mount(?Role $role = null): void
    {
        $this->tenantAuthorize('users.view');

        if (!$role) {
            return;
        }
        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->description = $role->description ?? '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Role::class, 'name')->ignore($this->roleId)],
            'description' => 'nullable|string|max:500',
        ]);

        $role = $this->roleId ? Role::find($this->roleId) : new Role();
        $role->fill($data);
        $role->save();

        $this->redirect(route('tenant.roles.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    public function render()
    {
        return view('inovcom-users::livewire.roles.form')
            ->layout('layouts.app', [
                'title' => $this->roleId ? 'Modifier le rôle' : 'Nouveau rôle',
                'subtitle' => '',
            ]);
    }
}
