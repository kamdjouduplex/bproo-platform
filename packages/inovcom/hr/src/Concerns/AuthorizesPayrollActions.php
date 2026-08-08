<?php

namespace InovCom\Payroll\Concerns;

use Illuminate\Support\Facades\Auth;
use InovCom\Payroll\Models\Employee;

trait AuthorizesPayrollActions
{
    protected function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
            return true;
        }

        // Compatibilité permission globale legacy
        if ($permission !== 'payroll.manage' && method_exists($user, 'hasPermission') && $user->hasPermission('payroll.manage')) {
            return true;
        }

        return false;
    }

    protected function authorizePayrollAction(string $permission): void
    {
        abort_unless($this->can($permission), 403, 'Action non autorisée.');
    }

    /**
     * Admin / RH avancé : voit toutes les fiches et bulletins.
     * Un employé avec seulement payroll.view ne voit que les siens.
     */
    protected function canViewAllPayroll(): bool
    {
        return $this->can('payroll.view_all')
            || $this->can('payroll.process')
            || $this->can('payroll.create')
            || $this->can('payroll.update')
            || $this->can('payroll.manage');
    }

    protected function ownEmployeeId(): ?int
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return null;
        }

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($employee) {
            return (int) $employee->id;
        }

        // Fallback email match (fiches legacy non liées)
        if (! empty($user->email)) {
            $byEmail = Employee::query()
                ->where('email', $user->email)
                ->where('is_active', true)
                ->first();

            return $byEmail ? (int) $byEmail->id : null;
        }

        return null;
    }

    protected function authorizePayslipLine(Employee|int|null $employeeOrId): void
    {
        if ($this->canViewAllPayroll()) {
            return;
        }

        $employeeId = $employeeOrId instanceof Employee
            ? (int) $employeeOrId->id
            : (int) $employeeOrId;

        $ownId = $this->ownEmployeeId();
        abort_unless(
            $ownId !== null && $ownId === $employeeId,
            403,
            'Vous ne pouvez consulter que votre propre bulletin de paie.'
        );
    }
}
