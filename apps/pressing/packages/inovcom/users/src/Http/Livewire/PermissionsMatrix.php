<?php

namespace InovCom\Users\Http\Livewire;

use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;
use InovCom\Users\Support\PermissionCatalog;
use Livewire\Component;

class PermissionsMatrix extends Component
{
    /** @var array<string, array<string, bool>> role_id => [permission_id => bool] */
    public array $matrix = [];

    public string $search = '';

    public string $roleFilter = 'all';

    /** @var array<string, bool> groupKey => expanded */
    public array $expanded = [];

    public function mount(): void
    {
        $roles = Role::query()->with('permissions:id')->orderBy('name')->get();
        $permissions = Permission::query()->orderBy('key')->get();

        foreach ($roles as $role) {
            $assigned = $role->permissions->pluck('id')->flip()->all();
            $this->matrix[(string) $role->id] = [];
            foreach ($permissions as $perm) {
                $this->matrix[(string) $role->id][(string) $perm->id] = isset($assigned[$perm->id]);
            }
        }

        foreach (PermissionCatalog::grouped($permissions)->keys() as $groupKey) {
            $this->expanded[$groupKey] = false;
        }

        // Comparer un rôle à la fois est plus lisible ; « tous » reste disponible.
        if ($roles->isNotEmpty()) {
            $this->roleFilter = (string) $roles->first()->id;
        }
    }

    public function toggle(int $roleId, int $permissionId): void
    {
        $role = Role::find($roleId);
        $perm = Permission::find($permissionId);
        if (! $role || ! $perm) {
            return;
        }

        $key = (string) $permissionId;
        $current = $this->matrix[(string) $roleId][$key] ?? false;
        $this->matrix[(string) $roleId][$key] = ! $current;

        if ($current) {
            $role->permissions()->detach($permissionId);
        } else {
            $role->permissions()->attach($permissionId);
        }
    }

    public function toggleGroup(int $roleId, string $groupKey, bool $enable): void
    {
        $role = Role::find($roleId);
        if (! $role) {
            return;
        }

        $permissionIds = Permission::query()
            ->orderBy('key')
            ->get()
            ->filter(fn (Permission $p) => PermissionCatalog::groupKeyFor($p->key) === $groupKey)
            ->pluck('id')
            ->all();

        if ($permissionIds === []) {
            return;
        }

        if ($enable) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
            foreach ($permissionIds as $pid) {
                $this->matrix[(string) $roleId][(string) $pid] = true;
            }
        } else {
            $role->permissions()->detach($permissionIds);
            foreach ($permissionIds as $pid) {
                $this->matrix[(string) $roleId][(string) $pid] = false;
            }
        }
    }

    public function toggleSection(string $groupKey): void
    {
        $this->expanded[$groupKey] = ! ($this->expanded[$groupKey] ?? true);
    }

    public function expandAll(): void
    {
        foreach (array_keys($this->expanded) as $key) {
            $this->expanded[$key] = true;
        }
    }

    public function collapseAll(): void
    {
        foreach (array_keys($this->expanded) as $key) {
            $this->expanded[$key] = false;
        }
    }

    public function render()
    {
        $roles = Role::query()->orderBy('name')->get();
        $permissions = Permission::query()->orderBy('key')->get();
        $grouped = PermissionCatalog::filterGrouped(
            PermissionCatalog::grouped($permissions),
            $this->search
        );

        $visibleRoles = $this->roleFilter === 'all'
            ? $roles
            : $roles->where('id', (int) $this->roleFilter)->values();

        return view('inovcom-users::livewire.permissions.matrix')
            ->layout('layouts.app', [
                'title' => 'Permissions',
                'subtitle' => 'Droits par rôle, regroupés par module',
            ])
            ->with([
                'roles' => $roles,
                'visibleRoles' => $visibleRoles,
                'groupedPermissions' => $grouped,
                'permissionCount' => $permissions->count(),
                'tenantCode' => $this->tenantCode(),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
