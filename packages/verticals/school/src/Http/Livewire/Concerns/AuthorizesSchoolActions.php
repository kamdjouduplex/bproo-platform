<?php

namespace School\Http\Livewire\Concerns;

trait AuthorizesSchoolActions
{
    protected function canSchool(string $permission): bool
    {
        try {
            $user = auth('tenant')->user();
        } catch (\Throwable) {
            return false;
        }

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    protected function authorizeSchool(string $permission): bool
    {
        if ($this->canSchool($permission)) {
            return true;
        }

        notify()->error('Permission refusée pour cette action.');

        return false;
    }
}
