@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $colspan = 5
        + (! empty($hasPhoneColumn) ? 1 : 0)
        + (! empty($payrollEnabled) ? 1 : 0)
        + 1; // boutique
@endphp
<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Utilisateurs</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <a class="btn btn-secondary" href="{{ route('tenant.roles.index', ['tenant' => $tenantCode]) }}">Rôles</a>
                <a class="btn btn-secondary" href="{{ route('tenant.permissions.index', ['tenant' => $tenantCode]) }}">Permissions</a>
                <form wire:submit.prevent="applySearch" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="Nom, email{{ !empty($hasPhoneColumn) ? ', téléphone' : '' }}" style="min-width: 180px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model.live="perPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <a class="btn btn-primary" href="{{ route('tenant.users.create', ['tenant' => $tenantCode]) }}">Nouveau</a>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        @if (!empty($hasPhoneColumn))
                            <th>Téléphone</th>
                        @endif
                        @if (!empty($payrollEnabled))
                            <th>Matricule</th>
                        @endif
                        <th>Boutique</th>
                        <th>Rôles</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php $emp = ($matricules ?? collect())->get($user->id); @endphp
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            @if (!empty($hasPhoneColumn))
                                <td>{{ $user->phone ?: '—' }}</td>
                            @endif
                            @if (!empty($payrollEnabled))
                                <td>
                                    @if ($emp)
                                        <strong>{{ $emp->employee_number }}</strong>
                                        @if ($emp->position)
                                            <div style="font-size:11px;color:#64748b;">{{ $emp->position }}</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                            <td>{{ $user->assigned_store_name ?? '—' }}</td>
                            <td>{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge badge-success">Actif</span>
                                @else
                                    <span class="badge badge-warning">Inactif</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.users.edit', [$user->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                            </td>
                        </tr>
                    @endforeach
                    @if ($users->count() === 0)
                        <tr><td colspan="{{ $colspan }}">Aucun utilisateur.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">{{ $users->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
