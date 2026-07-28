@php
    use InovCom\Users\Support\PermissionCatalog;
    $tenantCode = $tenantCode
        ?? request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $selectedLookup = array_fill_keys(array_map('strval', $permission_ids), true);
@endphp

<div class="page-body">
    <style>
        .role-perm-meta { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin:16px 0 10px; }
        .role-perm-meta__count { font-size:0.9rem; color:#64748b; }
        .role-perm-meta__actions { display:flex; flex-wrap:wrap; gap:8px; }
        .role-perm-group { border:1px solid #e2e8f0; border-radius:10px; margin-bottom:10px; overflow:hidden; background:#fff; }
        .role-perm-group__head {
            display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;
            padding:10px 14px; background:#f8fafc; cursor:pointer; user-select:none;
        }
        .role-perm-group__title { display:flex; align-items:center; gap:10px; font-weight:600; margin:0; }
        .role-perm-group__count { font-weight:500; font-size:0.8rem; color:#64748b; background:#e2e8f0; border-radius:999px; padding:2px 8px; }
        .role-perm-group__body { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:4px 12px; padding:10px 14px 14px; }
        .role-perm-item { display:flex; gap:10px; align-items:flex-start; padding:8px; border-radius:8px; }
        .role-perm-item:hover { background:#f8fafc; }
        .role-perm-item__label { display:block; font-size:0.92rem; line-height:1.3; }
        .role-perm-item__key { display:block; font-size:0.72rem; color:#94a3b8; margin-top:2px; word-break:break-all; }
        .role-perm-empty { padding:20px; text-align:center; color:#64748b; border:1px dashed #cbd5e1; border-radius:10px; }
    </style>

    <section class="card">
        <h2 class="card-title">{{ $roleId ? 'Modifier le rôle' : 'Nouveau rôle' }}</h2>

        <div class="form-grid">
            <div class="field">
                <label class="field-label">Nom</label>
                <input class="input" wire:model="name" placeholder="Ex: Caissier">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Description</label>
                <textarea class="input" wire:model="description" rows="2" placeholder="Rôle et périmètre"></textarea>
                @error('description') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="role-perm-meta">
            <div>
                <strong>Permissions</strong>
                <div class="role-perm-meta__count">{{ $selectedCount }} / {{ $totalCount }} sélectionnées</div>
            </div>
            <div class="role-perm-meta__actions">
                <input
                    class="input input-sm"
                    type="search"
                    wire:model.live.debounce.300ms="permissionSearch"
                    placeholder="Filtrer les permissions…"
                    style="min-width:200px;"
                    aria-label="Filtrer les permissions"
                >
                <button type="button" class="btn btn-secondary btn-sm" wire:click="selectAllVisible">Tout (filtre)</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="clearAll">Tout retirer</button>
            </div>
        </div>

        @if ($groupedPermissions->isEmpty())
            <p class="role-perm-empty">
                @if ($permissionSearch !== '')
                    Aucune permission ne correspond à « {{ $permissionSearch }} ».
                @else
                    Aucune permission disponible. Activez des modules ou synchronisez les permissions.
                @endif
            </p>
        @else
            @foreach ($groupedPermissions as $groupKey => $perms)
                @php
                    $label = PermissionCatalog::groupLabel($groupKey);
                    $isOpen = $expanded[$groupKey] ?? false;
                    $groupIdStrings = $perms->pluck('id')->map(fn ($id) => (string) $id)->all();
                    $selectedInGroup = collect($groupIdStrings)->filter(fn ($id) => isset($selectedLookup[$id]))->count();
                    $allSelected = $selectedInGroup === count($groupIdStrings) && count($groupIdStrings) > 0;
                @endphp
                <div class="role-perm-group" wire:key="role-group-{{ $groupKey }}">
                    <div class="role-perm-group__head" wire:click="toggleSection('{{ $groupKey }}')">
                        <h3 class="role-perm-group__title">
                            <span aria-hidden="true">{{ $isOpen ? '▾' : '▸' }}</span>
                            {{ $label }}
                            <span class="role-perm-group__count">{{ $selectedInGroup }}/{{ $perms->count() }}</span>
                        </h3>
                        <div onclick="event.stopPropagation()">
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm"
                                wire:click="toggleGroup('{{ $groupKey }}', {{ $allSelected ? 'false' : 'true' }})"
                            >
                                {{ $allSelected ? 'Tout retirer' : 'Tout cocher' }}
                            </button>
                        </div>
                    </div>

                    @if ($isOpen)
                        <div class="role-perm-group__body">
                            @foreach ($perms as $perm)
                                <label class="role-perm-item" wire:key="role-perm-{{ $perm->id }}">
                                    <input
                                        type="checkbox"
                                        value="{{ $perm->id }}"
                                        wire:model="permission_ids"
                                    >
                                    <span>
                                        <span class="role-perm-item__label">{{ $perm->name }}</span>
                                        <span class="role-perm-item__key">{{ $perm->key }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

        @error('permission_ids') <span class="field-error">{{ $message }}</span> @enderror
        @error('permission_ids.*') <span class="field-error">{{ $message }}</span> @enderror

        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('tenant.roles.index', ['tenant' => $tenantCode]) }}">Retour</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                {{ $roleId ? 'Mettre à jour' : 'Enregistrer' }}
            </button>
        </div>
    </section>
</div>
