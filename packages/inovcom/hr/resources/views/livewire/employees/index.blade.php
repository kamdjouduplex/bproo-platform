@php
    use InovCom\Payroll\Support\EmployeeRules;
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Employés</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <form wire:submit.prevent="applySearch" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="Nom, n° ou email" style="min-width: 200px;">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="all">Tous</option>
                    <option value="active">Actifs</option>
                    <option value="inactive">Inactifs</option>
                </select>
                @if ($canManage ?? false)
                    <a class="btn btn-primary" href="{{ route('tenant.payroll.employees.create', ['tenant' => $tenantCode]) }}">Nouvel employé</a>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('tenant.payroll.leaves.index'))
                    <a class="btn btn-secondary" href="{{ route('tenant.payroll.leaves.index', ['tenant' => $tenantCode]) }}">Congés</a>
                @endif
                <a class="btn btn-secondary" href="{{ route('tenant.payroll.index', ['tenant' => $tenantCode]) }}">← Paie</a>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Nom</th>
                        <th>Poste</th>
                        <th>Contrat</th>
                        <th>Compte système</th>
                        <th>Salaire base</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $emp)
                        <tr>
                            <td>{{ $emp->employee_number }}</td>
                            <td><strong>{{ $emp->full_name }}</strong></td>
                            <td>{{ $emp->position ?? '—' }}</td>
                            <td>{{ EmployeeRules::contractTypeLabel($emp->contract_type) }}</td>
                            <td>
                                @if ($emp->user)
                                    <span class="badge badge-success">{{ $emp->user->name }}</span>
                                @else
                                    <span class="badge badge-secondary">Sans compte</span>
                                @endif
                            </td>
                            <td>{{ fmt_money($emp->base_salary) }} FCFA</td>
                            <td>
                                @if ($emp->is_active)
                                    <span class="badge badge-success">Actif</span>
                                @else
                                    <span class="badge badge-secondary">Inactif</span>
                                @endif
                            </td>
                            <td style="display:flex; gap:4px; flex-wrap:wrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.payroll.employees.show', [$emp->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @if ($canManage ?? false)
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.payroll.employees.edit', [$emp->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                @endif
                                @if (\Illuminate\Support\Facades\Route::has('tenant.attendance.show'))
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.attendance.show', ['tenant' => $tenantCode, 'employeeId' => $emp->id]) }}">Présence</a>
                                @endif
                                @if (($canManage ?? false) && $emp->is_active)
                                    <button class="btn btn-error btn-sm" wire:click="deactivate({{ $emp->id }})" onclick="return confirm('Désactiver cet employé ?')">Désactiver</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($employees->count() === 0)
                        <tr><td colspan="8">Aucun employé.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">{{ $employees->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
