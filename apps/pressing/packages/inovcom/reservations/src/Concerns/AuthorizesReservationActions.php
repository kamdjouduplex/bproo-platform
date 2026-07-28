<?php

namespace InovCom\Reservations\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthorizesReservationActions
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

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    protected function authorizeReservationAction(string $permission): void
    {
        abort_unless($this->can($permission), 403, 'Action non autorisée.');
    }
}
