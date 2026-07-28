<?php

namespace InovCom\Purchases\Concerns;

trait AuthorizesForeignPurchases
{
    protected function canForeignPurchase(string $permission): bool
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

        if ($user->hasPermission('foreign_purchases.manage')) {
            return in_array($permission, [
                'foreign_purchases.view',
                'foreign_purchases.create',
                'foreign_purchases.update',
                'foreign_purchases.confirm',
                'foreign_purchases.receive',
            ], true);
        }

        if ($user->hasPermission('foreign_purchases.create')) {
            return in_array($permission, [
                'foreign_purchases.view',
                'foreign_purchases.create',
                'foreign_purchases.update',
            ], true);
        }

        if ($user->hasPermission('foreign_purchases.view')) {
            return $permission === 'foreign_purchases.view';
        }

        if ($user->hasPermission('purchases.manage')) {
            return in_array($permission, [
                'foreign_purchases.view',
                'foreign_purchases.create',
                'foreign_purchases.update',
                'foreign_purchases.confirm',
                'foreign_purchases.receive',
            ], true);
        }

        if ($user->hasPermission('purchases.receive')) {
            return in_array($permission, [
                'foreign_purchases.view',
                'foreign_purchases.receive',
            ], true);
        }

        if ($user->hasPermission('purchases.create')) {
            return in_array($permission, [
                'foreign_purchases.view',
                'foreign_purchases.create',
                'foreign_purchases.update',
            ], true);
        }

        return false;
    }

    protected function canModifyForeignPurchase(): bool
    {
        return $this->canForeignPurchase('foreign_purchases.update')
            || $this->canForeignPurchase('foreign_purchases.create')
            || $this->canForeignPurchase('foreign_purchases.manage');
    }
}
