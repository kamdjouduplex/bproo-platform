<?php

namespace InovCom\Returns\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Vérification de permissions alignée sur la convention du projet
 * (guard "tenant" + rôle admin court-circuit + hasPermission()).
 */
trait AuthorizesReturnActions
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

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    protected function tenantUserId(): ?int
    {
        return Auth::guard('tenant')->id();
    }
}
