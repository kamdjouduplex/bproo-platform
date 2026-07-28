@php
    use InovCom\Users\Support\PermissionCatalog;
    $tenantCode = $tenantCode
        ?? request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <style>
        .perm-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:14px; }
        .perm-toolbar__left { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .perm-toolbar__right { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .perm-meta { color:var(--muted, #64748b); font-size:0.9rem; margin:0 0 12px; }
        .perm-group { border:1px solid var(--border, #e2e8f0); border-radius:10px; margin-bottom:10px; overflow:hidden; background:#fff; }
        .perm-group__head {
            display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;
            padding:10px 14px; background:#f8fafc; cursor:pointer; user-select:none;
        }
        .perm-group__title { display:flex; align-items:center; gap:10px; font-weight:600; margin:0; }
        .perm-group__count { font-weight:500; font-size:0.8rem; color:#64748b; background:#e2e8f0; border-radius:999px; padding:2px 8px; }
        .perm-group__actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
        .perm-group__body { padding:0; }
        .perm-matrix { width:100%; border-collapse:collapse; table-layout:fixed; }
        .perm-matrix th, .perm-matrix td { padding:8px 12px; border-top:1px solid #eef2f7; vertical-align:middle; }
        .perm-matrix th { font-size:0.8rem; text-transform:none; letter-spacing:0; background:#fff; }
        .perm-matrix__sticky { position:sticky; left:0; background:#fff; z-index:1; width:45%; }
        .perm-matrix__key { display:block; font-size:0.75rem; color:#94a3b8; margin-top:2px; word-break:break-all; }
        .perm-matrix__check { text-align:center; width:110px; }
        .perm-empty { padding:24px; text-align:center; color:#64748b; }
        .perm-hint { margin-top:12px; font-size:0.85rem; color:#64748b; }
    </style>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Permissions par rôle</div>
            <div class="perm-toolbar__right">
                <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">Utilisateurs</a>
                <a class="btn btn-secondary" href="{{ route('tenant.roles.index', ['tenant' => $tenantCode]) }}">Rôles</a>
            </div>
        </div>

        <p class="perm-meta">
            {{ $permissionCount }} permissions regroupées par module.
            Cochez pour accorder un droit — enregistrement immédiat.
            Pour un nouveau rôle, préférez l’écran <a href="{{ route('tenant.roles.create', ['tenant' => $tenantCode]) }}">Nouveau rôle</a>.
        </p>

        <div class="perm-toolbar">
            <div class="perm-toolbar__left">
                <input
                    class="input input-sm"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher une permission…"
                    style="min-width:220px;"
                    aria-label="Rechercher"
                >
                <select class="input input-sm" wire:model.live="roleFilter" aria-label="Filtrer par rôle">
                    <option value="all">Tous les rôles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="perm-toolbar__right">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="expandAll">Tout déplier</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="collapseAll">Tout replier</button>
            </div>
        </div>

        @if ($roles->isEmpty() || $permissionCount === 0)
            <p class="perm-empty">Créez des rôles et assurez-vous que les modules installés ont synchronisé leurs permissions.</p>
        @elseif ($groupedPermissions->isEmpty())
            <p class="perm-empty">Aucune permission ne correspond à « {{ $search }} ».</p>
        @else
            @foreach ($groupedPermissions as $groupKey => $perms)
                @php
                    $label = PermissionCatalog::groupLabel($groupKey);
                    $isOpen = $expanded[$groupKey] ?? false;
                    $groupIds = $perms->pluck('id')->all();
                @endphp
                <div class="perm-group" wire:key="group-{{ $groupKey }}">
                    <div class="perm-group__head" wire:click="toggleSection('{{ $groupKey }}')">
                        <h3 class="perm-group__title">
                            <span aria-hidden="true">{{ $isOpen ? '▾' : '▸' }}</span>
                            {{ $label }}
                            <span class="perm-group__count">{{ $perms->count() }}</span>
                        </h3>
                        <div class="perm-group__actions" onclick="event.stopPropagation()">
                            @foreach ($visibleRoles as $role)
                                @php
                                    $rid = (string) $role->id;
                                    $allOn = collect($groupIds)->every(fn ($pid) => !empty($matrix[$rid][(string) $pid]));
                                    $someOn = !$allOn && collect($groupIds)->contains(fn ($pid) => !empty($matrix[$rid][(string) $pid]));
                                @endphp
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    title="{{ $allOn ? 'Retirer' : 'Accorder' }} tout le module pour {{ $role->name }}"
                                    wire:click="toggleGroup({{ $role->id }}, '{{ $groupKey }}', {{ $allOn ? 'false' : 'true' }})"
                                >
                                    {{ $visibleRoles->count() > 1 ? Str::limit($role->name, 12) . ' · ' : '' }}{{ $allOn ? 'Tout retirer' : ($someOn ? 'Compléter' : 'Tout cocher') }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if ($isOpen)
                        <div class="perm-group__body table-scroll">
                            <table class="perm-matrix">
                                <thead>
                                    <tr>
                                        <th class="perm-matrix__sticky">Permission</th>
                                        @foreach ($visibleRoles as $role)
                                            <th class="perm-matrix__check">{{ $role->name }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($perms as $perm)
                                        <tr wire:key="perm-{{ $perm->id }}">
                                            <td class="perm-matrix__sticky">
                                                {{ $perm->name }}
                                                <span class="perm-matrix__key">{{ $perm->key }}</span>
                                            </td>
                                            @foreach ($visibleRoles as $role)
                                                @php
                                                    $checked = $matrix[(string) $role->id][(string) $perm->id] ?? false;
                                                @endphp
                                                <td class="perm-matrix__check">
                                                    <input
                                                        type="checkbox"
                                                        @checked($checked)
                                                        wire:click="toggle({{ $role->id }}, {{ $perm->id }})"
                                                        aria-label="{{ $perm->name }} — {{ $role->name }}"
                                                    >
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

        <p class="perm-hint">Astuce : filtrez un rôle pour comparer moins de colonnes, ou assignez les droits depuis la fiche du rôle.</p>
    </section>
</div>
