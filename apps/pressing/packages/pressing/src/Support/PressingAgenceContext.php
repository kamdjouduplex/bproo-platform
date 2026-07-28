<?php

namespace Pressing\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Pressing\Models\Agence;

final class PressingAgenceContext
{
    public static function canViewAllAgences(): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && (
            $user->hasPermission('agences.view') || $user->hasPermission('agences.manage')
        );
    }

    public static function userAgenceId(): ?int
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return null;
        }

        if (Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id') && ! empty($user->assigned_agence_id)) {
            return (int) $user->assigned_agence_id;
        }

        $managed = Agence::query()
            ->where('is_active', true)
            ->where('manager_user_id', $user->id)
            ->value('id');

        if ($managed) {
            return (int) $managed;
        }

        $id = Agence::query()->where('is_active', true)->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    public static function userAgence(): ?Agence
    {
        $id = self::userAgenceId();

        return $id ? Agence::find($id) : null;
    }

    /**
     * Scope a query that has agence_id to the connected user's agency
     * (admins / agences.view see all unless $forceAgenceId is set).
     */
    public static function applyAgenceScope($query, string $column = 'agence_id', ?int $forceAgenceId = null)
    {
        if ($forceAgenceId !== null) {
            return $query->where($column, $forceAgenceId);
        }

        if (self::canViewAllAgences()) {
            return $query;
        }

        $agenceId = self::userAgenceId();
        if ($agenceId) {
            return $query->where($column, $agenceId);
        }

        return $query->whereRaw('1 = 0');
    }

    /** Effective agency filter for dashboards: null = all (admin), else locked agency id. */
    public static function scopedAgenceId(): ?int
    {
        return self::canViewAllAgences() ? null : self::userAgenceId();
    }

    public static function scopeLabel(): string
    {
        if (self::canViewAllAgences()) {
            return __('Toutes les agences');
        }

        return self::userAgence()?->name ?? __('Mon agence');
    }
}
