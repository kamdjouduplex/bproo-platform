<?php

namespace InovCom\Users;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Users\Http\Livewire\AccountProfile;
use InovCom\Users\Http\Livewire\PermissionsMatrix;
use InovCom\Users\Http\Livewire\RoleForm;
use InovCom\Users\Http\Livewire\RolesIndex;
use InovCom\Users\Http\Livewire\UserForm;
use InovCom\Users\Http\Livewire\UsersIndex;
use InovCom\Users\Models\Role;
use InovCom\Users\Models\User;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class UsersServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     * Users is a core module, so it will always boot, but we check for consistency
     */
    protected string $moduleKey = 'users';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        // Core modules always boot, but we check for consistency
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-users');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-users-migrations');

        Livewire::component('inovcom-users.users-index', UsersIndex::class);
        Livewire::component('inovcom-users.user-form', UserForm::class);
        Livewire::component('inovcom-users.roles-index', RolesIndex::class);
        Livewire::component('inovcom-users.role-form', RoleForm::class);
        Livewire::component('inovcom-users.permissions-matrix', PermissionsMatrix::class);
        Livewire::component('inovcom-users.account-profile', AccountProfile::class);

        Route::bind('user', fn ($value) => User::on('tenant')->findOrFail($value));
        Route::bind('role', fn ($value) => Role::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'tenant.active', 'tenant.store', 'auth:tenant'])
            ->group(function () {
                Route::get('/users', UsersIndex::class)
                    ->middleware(['module:users'])
                    ->name('tenant.users.index');
                Route::get('/users/create', UserForm::class)
                    ->middleware(['module:users'])
                    ->name('tenant.users.create');
                Route::get('/users/{user}/edit', UserForm::class)
                    ->middleware(['module:users'])
                    ->name('tenant.users.edit');

                Route::get('/roles', RolesIndex::class)
                    ->middleware(['module:users'])
                    ->name('tenant.roles.index');
                Route::get('/roles/create', RoleForm::class)
                    ->middleware(['module:users'])
                    ->name('tenant.roles.create');
                Route::get('/roles/{role}/edit', RoleForm::class)
                    ->middleware(['module:users'])
                    ->name('tenant.roles.edit');

                Route::get('/permissions', PermissionsMatrix::class)
                    ->middleware(['module:users'])
                    ->name('tenant.permissions.index');

                Route::get('/account', AccountProfile::class)
                    ->name('tenant.account.profile');
            });
    }
}
