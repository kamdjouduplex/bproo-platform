<?php

namespace InovCom\Crm\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthorizesCrmActions
{
    /**
     * True if the tenant user may perform the action.
     * Accepts CRM keys and legacy prospects.* equivalents.
     */
    protected function canCrm(string $permission): bool
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

        foreach ($this->crmPermissionAliases($permission) as $alias) {
            if ($user->hasPermission($alias)) {
                return true;
            }
        }

        return false;
    }

    protected function authorizeCrm(string $permission): void
    {
        if (! $this->canCrm($permission)) {
            abort(403, 'Permission refusée.');
        }
    }

    /**
     * @return list<string>
     */
    protected function crmPermissionAliases(string $permission): array
    {
        return match ($permission) {
            'crm.view' => ['crm.prospects.view', 'crm.opportunities.view', 'crm.activities.view', 'prospects.view'],
            'crm.prospects.view' => ['prospects.view', 'crm.view'],
            'crm.prospects.create' => ['prospects.create'],
            'crm.prospects.update' => ['prospects.update'],
            'crm.prospects.delete' => ['prospects.delete'],
            'crm.prospects.convert' => ['prospects.convert'],
            'crm.prospects.assign' => ['prospects.update', 'crm.prospects.update'],
            'crm.opportunities.view' => ['crm.view', 'prospects.view'],
            'crm.opportunities.manage' => ['crm.prospects.update', 'prospects.update'],
            'crm.activities.view' => ['crm.view', 'prospects.view'],
            'crm.activities.create' => ['crm.prospects.update', 'prospects.update'],
            'crm.manage' => [],
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
