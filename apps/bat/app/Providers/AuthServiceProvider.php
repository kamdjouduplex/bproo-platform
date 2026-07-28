<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        /*
        |----------------------------------------------------------------------
        | Tenant Permission Gates
        |----------------------------------------------------------------------
        | A single catch-all Gate that delegates every ability check to the
        | authenticated tenant user's hasPermission() method.
        |
        | Usage in Livewire components:
        |   $this->authorize('clients.view');   // throws AuthorizationException
        |   Gate::allows('devis.send')          // returns bool
        |
        | Usage in Blade views:
        |   @can('facturation.create') … @endcan
        |
        | The Gate::before callback runs BEFORE all other checks, so if the
        | user has the permission it returns true immediately. If not, it
        | returns null (letting other gates run or ultimately denying).
        |
        | Note: this only fires when a tenant user is the authenticated guard.
        | Admin (system) users use the default guard and are not subject to
        | these tenant-level permission checks.
        */
        Gate::before(function ($user, string $ability) {
            // Only apply to tenant users (those with hasPermission()).
            if (!method_exists($user, 'hasPermission')) {
                return null;
            }

            return $user->hasPermission($ability) ?: null;
        });
    }
}
