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

        if (! method_exists($user, 'hasPermission')) {
            return false;
        }

        if ($user->hasPermission($permission)) {
            return true;
        }

        // CRM suite equivalents (when Prospects is used under CRM)
        foreach ($this->prospectCrmAliases($permission) as $alias) {
            if ($user->hasPermission($alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function prospectCrmAliases(string $permission): array
    {
        return match ($permission) {
            'prospects.view' => ['crm.prospects.view', 'crm.view'],
            'prospects.create' => ['crm.prospects.create'],
            'prospects.update' => ['crm.prospects.update', 'crm.prospects.assign'],
            'prospects.convert' => ['crm.prospects.convert'],
            'prospects.delete' => ['crm.prospects.delete'],
            'prospects.activities' => ['crm.activities.create', 'crm.prospects.update'],
            default => [],
        };
    }

    protected function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
