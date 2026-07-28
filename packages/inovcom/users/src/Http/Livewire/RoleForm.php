<?php

namespace InovCom\Users\Http\Livewire;

use Illuminate\Validation\Rule;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;
use InovCom\Users\Support\PermissionCatalog;
use Livewire\Component;

class RoleForm extends Component
{
    public ?int $roleId = null;

    public string $name = '';

    public string $description = '';

    /** @var list<int|string> */
    public array $permission_ids = [];

    public string $permissionSearch = '';

    /** @var array<string, bool> */
    public array $expanded = [];

    public function mount(?Role $role = null): void
    {
        $groups = PermissionCatalog::grouped()->keys();
        foreach ($groups as $groupKey) {
            $this->expanded[$groupKey] = false;
        }

        if (! $role) {
            return;
        }

        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->description = $role->description ?? '';
        $this->permission_ids = $role->permissions()->pluck('permissions.id')->map(fn ($id) => (string) $id)->all();
    }

    public function toggleGroup(string $groupKey, bool $enable): void
    {
        $ids = Permission::query()
            ->orderBy('key')
            ->get()
            ->filter(fn (Permission $p) => PermissionCatalog::groupKeyFor($p->key) === $groupKey)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($enable) {
            $this->permission_ids = array_values(array_unique(array_merge($this->permission_ids, $ids)));
        } else {
            $this->permission_ids = array_values(array_diff($this->permission_ids, $ids));
        }
    }

    public function toggleSection(string $groupKey): void
    {
        $this->expanded[$groupKey] = ! ($this->expanded[$groupKey] ?? true);
    }

    public function selectAllVisible(): void
    {
        $grouped = PermissionCatalog::filterGrouped(
            PermissionCatalog::grouped(),
            $this->permissionSearch
        );
        $ids = $grouped->flatten()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->permission_ids = array_values(array_unique(array_merge($this->permission_ids, $ids)));
    }

    public function clearAll(): void
    {
        $this->permission_ids = [];
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Role::class, 'name')->ignore($this->roleId)],
            'description' => 'nullable|string|max:500',
            'permission_ids' => 'array',
            'permission_ids.*' => Rule::exists(Permission::class, 'id'),
        ]);

        $role = $this->roleId ? Role::findOrFail($this->roleId) : new Role();
        $role->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
        $role->save();

        $role->permissions()->sync(
            collect($data['permission_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        session()->flash('success', $this->roleId ? 'Rôle mis à jour.' : 'Rôle créé.');

        $this->redirect(route('tenant.roles.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function render()
    {
        $grouped = PermissionCatalog::filterGrouped(
            PermissionCatalog::grouped(),
            $this->permissionSearch
        );

        $selectedCount = count($this->permission_ids);

        return view('inovcom-users::livewire.roles.form')
            ->layout('layouts.app', [
                'title' => $this->roleId ? 'Modifier le rôle' : 'Nouveau rôle',
                'subtitle' => 'Identité et droits d’accès',
            ])
            ->with([
                'groupedPermissions' => $grouped,
                'selectedCount' => $selectedCount,
                'totalCount' => Permission::query()->count(),
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
