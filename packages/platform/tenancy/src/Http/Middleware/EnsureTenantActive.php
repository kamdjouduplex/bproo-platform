<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $allowedWhenInactiveOrNoSubscription = [
            'tenant.login',
            'tenant.login.submit',
            'tenant.logout',
            'tenant.subscription',
        ];

        if ($routeName && in_array($routeName, $allowedWhenInactiveOrNoSubscription, true)) {
            return $next($request);
        }

        if (!$tenant->is_active) {
            return redirect()->route('tenant.subscription', ['tenant' => $tenant->code]);
        }

        // Desktop: commercial gate is EnsureDesktopLicence (signed CC token), not local SQLite sub.
        $desktop = (bool) config('desktop.enabled', false)
            || filter_var(env('DESKTOP_RUNTIME', false), FILTER_VALIDATE_BOOLEAN);
        if (! $desktop && ! $tenant->hasActiveSubscription()) {
            return redirect()->route('tenant.subscription', ['tenant' => $tenant->code]);
        }

        return $next($request);
    }
}
