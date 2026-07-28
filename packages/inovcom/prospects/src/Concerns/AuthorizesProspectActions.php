<?php

namespace InovCom\Prospects\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthorizesProspectActions
{
    protected function authorizeProspectAction(string $permission): void
    {
        if (! $this->canProspect($permission)) {
            abort(403, 'Permission refusée.');
        }
    }

    protected function canProspect(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    protected function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
