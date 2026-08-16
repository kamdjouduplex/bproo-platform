<?php

namespace School\Http\Controllers\Concerns;

trait AuthorizesSchoolHttp
{
    protected function authorizeSchoolPermission(string $permission): void
    {
        try {
            $user = auth('tenant')->user();
        } catch (\Throwable) {
            abort(403, 'Permission refusée.');
        }

        if (! $user) {
            abort(403, 'Permission refusée.');
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
            return;
        }

        abort(403, 'Permission refusée.');
    }
}
