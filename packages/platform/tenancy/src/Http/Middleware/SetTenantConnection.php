<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use App\Support\TenantSettingsApplier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant DB connection for /app routes. Also enforces subscription:
 * without an active subscription, only login/logout/subscription page are allowed.
 * All tenant app routes MUST use middleware ['tenant', 'tenant.active'] so this
 * (and EnsureTenantActive) run and cannot be bypassed.
 */
class SetTenantConnection
{
    /** Session key: tenant code the current tenant guard session was authenticated for. */
    public const AUTH_TENANT_SESSION_KEY = 'tenant_auth_code';

    /** Routes that do not require an active subscription (login and subscription status page only). */
    private const ALLOWED_WITHOUT_ACTIVE_SUBSCRIPTION = [
        'tenant.login',
        'tenant.login.submit',
        'tenant.logout',
        'tenant.subscription',
    ];

    public function handle(Request $request, Closure $next)
    {
        $code = $this->resolveTenantCode($request);

        if (!$code) {
            abort(404, 'Tenant not specified.');
        }

        $manager = app(TenantManager::class);
        $tenant = $manager->findByCode($code);

        if (!$tenant) {
            abort(404, 'Tenant not found.');
        }

        if (!$tenant->is_active) {
            $request->session()->forget('tenant_code');
            return response()->view('errors.tenant-deactivated', [
                'tenantName' => $tenant->name,
                'tenantCode' => $tenant->code,
                'hasActiveSubscription' => $tenant->hasActiveSubscription(),
            ], 403);
        }

        $routeName = $request->route()?->getName();
        $allowedWithoutSubscription = in_array($routeName, self::ALLOWED_WITHOUT_ACTIVE_SUBSCRIPTION, true);

        if (!$allowedWithoutSubscription && !$tenant->hasActiveSubscription()) {
            $request->session()->forget('tenant_code');
            return redirect()->route('tenant.subscription', ['tenant' => $tenant->code]);
        }

        $manager->setTenant($tenant);

        if ($response = $this->enforceTenantAuthScope($request, $tenant->code)) {
            return $response;
        }

        if ($request->hasSession()) {
            $request->session()->put('tenant_code', $tenant->code);
        }
        $request->attributes->set('tenant', $tenant);

        // Réapplique après setTenant (couvre /app et sous-domaines).
        TenantSettingsApplier::apply($tenant);

        return $next($request);
    }

    private function resolveTenantCode(Request $request): ?string
    {
        if ($request->header('X-Tenant-Code')) {
            return $request->header('X-Tenant-Code');
        }

        if ($request->query('tenant')) {
            return $request->query('tenant');
        }

        if ($request->hasSession()) {
            $sessionCode = $request->session()->get('tenant_code');
            if (is_string($sessionCode) && $sessionCode !== '') {
                return $sessionCode;
            }
        }

        $host = $request->getHost();
        if (str_contains($host, '.')) {
            $subdomain = explode('.', $host)[0];
            if (!in_array($subdomain, ['www', 'app', 'admin'], true)) {
                return $subdomain;
            }
        }

        return null;
    }

    /**
     * Prevent cross-tenant session reuse: changing ?tenant= must re-authenticate.
     */
    private function enforceTenantAuthScope(Request $request, string $requestedCode): ?Response
    {
        $authTenantCode = $request->session()->get(self::AUTH_TENANT_SESSION_KEY);

        if (!is_string($authTenantCode) || $authTenantCode === '') {
            if (Auth::guard('tenant')->check()) {
                $this->clearTenantAuth($request);
            }

            return null;
        }

        if ($authTenantCode === $requestedCode) {
            return null;
        }

        $this->clearTenantAuth($request);

        $routeName = $request->route()?->getName();
        if (in_array($routeName, self::ALLOWED_WITHOUT_ACTIVE_SUBSCRIPTION, true)) {
            if ($request->hasSession()) {
                $request->session()->put('tenant_code', $requestedCode);
            }

            return null;
        }

        return redirect()
            ->route('tenant.login', ['tenant' => $requestedCode])
            ->with('warning', 'Session terminée. Connectez-vous pour accéder à ce vendeur.');
    }

    private function clearTenantAuth(Request $request): void
    {
        Auth::guard('tenant')->logout();
        $request->session()->forget(self::AUTH_TENANT_SESSION_KEY);
        $request->session()->forget('tenant_store_id');
    }
}
