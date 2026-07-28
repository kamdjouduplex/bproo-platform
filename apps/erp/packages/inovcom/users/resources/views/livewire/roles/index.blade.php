@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Rôles</div>
            <div style="display:flex; gap:8px; align-items:center;">
                <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">Utilisateurs</a>
                <a class="btn btn-secondary" href="{{ route('tenant.permissions.index', ['tenant' => $tenantCode]) }}" title="Comparer les droits entre rôles">Matrice</a>
                <form wire:submit.prevent="applySearch" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="Nom ou description" style="min-width: 180px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model="perPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <a class="btn btn-primary" href="{{ route('tenant.roles.create', ['tenant' => $tenantCode]) }}">Nouveau</a>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Description</th>
                        <th>Utilisateurs</th>
                        <th>Permissions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>{{ $role->name }}</td>
                            <td>{{ $role->description ?? '-' }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ $role->permissions_count }}</td>
                            <td>
                                <a class="btn btn-secondary" href="{{ route('tenant.roles.edit', [$role->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                            </td>
                        </tr>
                    @endforeach
                    @if ($roles->count() === 0)
                        <tr><td colspan="5">Aucun role.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">{{ $roles->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
