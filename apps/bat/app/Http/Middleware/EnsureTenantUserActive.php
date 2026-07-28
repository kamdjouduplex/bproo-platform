<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logs out a tenant user whose account has been deactivated mid-session.
 *
 * Applied after auth:tenant on every authenticated tenant request so that
 * disabling a user in the back-office takes effect immediately on the
 * next request — not just at the next login attempt.
 */
class EnsureTenantUserActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('tenant')->user();

        if ($user && !$user->is_active) {
            Auth::guard('tenant')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('tenant.login', ['tenant' => $request->query('tenant') ?? session('tenant_code')])
                ->withErrors(['email' => 'Ce compte a été désactivé. Contactez votre administrateur.']);
        }

        return $next($request);
    }
}
