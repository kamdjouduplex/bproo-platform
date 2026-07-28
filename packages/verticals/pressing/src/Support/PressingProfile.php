<?php

namespace Pressing\Support;

use Illuminate\Support\Facades\Auth;
use InovCom\Users\Models\User;

/**
 * Resolves the operational pressing profile for the current tenant user.
 * Used to tailor dashboard, quick actions and default home focus.
 */
final class PressingProfile
{
    public const ADMIN = 'admin';

    public const RECEPTION = 'reception';

    public const PRODUCTION = 'production';

    public const DRIVER = 'driver';

    public static function current(?User $user = null): string
    {
        $user = $user ?? Auth::guard('tenant')->user();
        if (! $user) {
            return self::RECEPTION;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return self::ADMIN;
        }

        $user->loadMissing('roles');
        $roleNames = $user->roles->pluck('name')->map(fn ($n) => mb_strtolower((string) $n))->all();

        if (array_intersect($roleNames, ['driver', 'livreur', 'pressing_driver'])) {
            return self::DRIVER;
        }

        if (array_intersect($roleNames, ['production', 'pressing_production', 'presser', 'atelier', 'repassage'])) {
            return self::PRODUCTION;
        }

        if (array_intersect($roleNames, ['receptionist', 'reception', 'réception', 'pressing_reception', 'cashier'])) {
            return self::RECEPTION;
        }

        // Fallback from permissions when roles are custom
        $can = fn (string $key) => method_exists($user, 'hasPermission') && $user->hasPermission($key);

        $hasDeliveries = $can('pressing_deliveries.view') || $can('pressing_deliveries.update');
        $hasOrdersCreate = $can('pressing_orders.create');
        $hasWorkflow = $can('pressing_workflow.view') || $can('pressing_workflow.move');
        $hasReports = $can('pressing_settings.view');

        if ($hasDeliveries && ! $hasOrdersCreate && ! $hasWorkflow) {
            return self::DRIVER;
        }

        if ($hasWorkflow && ! $hasOrdersCreate) {
            return self::PRODUCTION;
        }

        if ($hasOrdersCreate || $hasReports) {
            return self::RECEPTION;
        }

        if ($hasDeliveries) {
            return self::DRIVER;
        }

        return self::RECEPTION;
    }

    public static function label(string $profile): string
    {
        return match ($profile) {
            self::ADMIN => __('Administrateur'),
            self::RECEPTION => __('Réception'),
            self::PRODUCTION => __('Production'),
            self::DRIVER => __('Livreur'),
            default => __('Équipe'),
        };
    }

    public static function canSeeFinance(string $profile): bool
    {
        return in_array($profile, [self::ADMIN, self::RECEPTION], true);
    }

    public static function canSeeFullOps(string $profile): bool
    {
        return in_array($profile, [self::ADMIN, self::RECEPTION, self::PRODUCTION], true);
    }

    /**
     * Active users suitable for production assignment (atelier / repassage / workflow.move).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function productionEmployees(?int $agenceId = null)
    {
        $roleNames = [
            'pressing_production', 'Production', 'production', 'presser', 'atelier',
            'Repassage', 'repassage',
        ];

        $query = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($roleNames) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roleNames))
                    ->orWhereHas('roles.permissions', fn ($p) => $p->where('key', 'pressing_workflow.move'));
            })
            ->orderBy('name');

        if ($agenceId) {
            $query->where(function ($q) use ($agenceId) {
                $q->whereNull('assigned_agence_id')
                    ->orWhere('assigned_agence_id', $agenceId);
            });
        }

        return $query->get(['id', 'name', 'assigned_agence_id']);
    }
}
