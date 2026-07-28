<?php

namespace InovCom\Users\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;
use Livewire\Component;

class PermissionsMatrix extends Component
{
    use AuthorizesWithTenant;
    /** @var array<string, array<int, bool>> role_id => [permission_id => true] */
    public array $matrix = [];

    public function mount(): void
    {
        $this->tenantAuthorize('users.view');

        $roles = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('key')->get();
        foreach ($roles as $role) {
            $this->matrix[(string) $role->id] = [];
            foreach ($permissions as $perm) {
                $this->matrix[(string) $role->id][(string) $perm->id] = $role->permissions->contains('id', $perm->id);
            }
        }
    }

    public function toggle(int $roleId, int $permissionId): void
    {
        $role = Role::find($roleId);
        $perm = Permission::find($permissionId);
        if (!$role || !$perm) {
            return;
        }
        $key = (string) $permissionId;
        $current = $this->matrix[(string) $roleId][$key] ?? false;
        $this->matrix[(string) $roleId][$key] = !$current;

        if ($current) {
            $role->permissions()->detach($permissionId);
        } else {
            $role->permissions()->attach($permissionId);
        }
    }

    public function render()
    {
        $roles = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('key')->get();

        return view('inovcom-users::livewire.permissions.matrix')
            ->layout('layouts.app', [
                'title' => 'Matrice des permissions',
                'subtitle' => 'Cocher les permissions par rôle',
            ])
            ->with([
                'roles' => $roles,
                'permissions' => $permissions,
            ]);
    }
}
