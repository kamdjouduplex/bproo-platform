<?php

namespace InovCom\Payroll\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthorizesPayrollActions
{
    protected function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
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
}
