<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use App\Support\TenantSettingsApplier;
use Closure;
use Illuminate\Http\Request;

class ApplyTenantSettings
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = app(TenantManager::class)->tenant()
            ?? $request->attributes->get('tenant');

        if ($tenant) {
            TenantSettingsApplier::apply($tenant);
        }

        return $next($request);
    }
}
