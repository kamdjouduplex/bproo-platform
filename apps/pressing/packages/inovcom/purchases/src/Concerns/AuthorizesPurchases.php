<?php

namespace InovCom\Purchases\Concerns;

trait AuthorizesPurchases
{
    /**
     * Vérifie une permission achats, avec repli sur purchases.manage et admin.
     */
    protected function canPurchase(string $permission): bool
    {
        $user = auth('tenant')->user();
        if (!$user || !method_exists($user, 'hasPermission')) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if ($user->hasPermission($permission)) {
            return true;
        }

        if ($user->hasPermission('purchases.manage')) {
            return in_array($permission, [
                'purchases.view',
                'purchases.create',
                'purchases.update',
                'purchases.receive',
                'purchases.confirm',
                'purchases.cancel',
                'purchases.delete',
            ], true);
        }

        if ($user->hasPermission('purchases.create')) {
            return in_array($permission, [
                'purchases.view',
                'purchases.create',
                'purchases.update',
            ], true);
        }

        return false;
    }
}
