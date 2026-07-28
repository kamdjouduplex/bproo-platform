<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Evicts an authenticated tenant user mid-session when their tenant's plan
 * has expired. Applied after auth:tenant on every authenticated tenant request
 * so that plan expiry takes effect on the very next request — not just at the
 * next login attempt.
 *
 * Login-time enforcement is handled separately in AuthController::login().
 */
class EnsureTenantPlanActive
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = app(TenantManager::class)->tenant();

        if ($tenant && $tenant->isExpired()) {
            Auth::guard('tenant')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('tenant.login', ['tenant' => $request->query('tenant') ?? session('tenant_code')])
                ->withErrors(['email' => 'Votre abonnement a expiré. Veuillez contacter votre administrateur pour renouveler votre plan.']);
        }

        return $next($request);
    }
}
