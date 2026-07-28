<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

class SetTenantConnection
{
    public function handle(Request $request, Closure $next)
    {
        $code = $this->resolveTenantCode($request);

        if (!$code) {
            abort(404, 'Tenant not specified.');
        }

        $manager = app(TenantManager::class);
        $tenant = $manager->resolveByCode($code);

        if (!$tenant) {
            abort(404, 'Tenant not found.');
        }

        $manager->setTenant($tenant);
        if ($request->hasSession()) {
            $request->session()->put('tenant_code', $tenant->code);
        }
        $request->attributes->set('tenant', $tenant);

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

        $host = $request->getHost();
        if (str_contains($host, '.')) {
            $subdomain = explode('.', $host)[0];
            if (!in_array($subdomain, ['www', 'app', 'admin'], true)) {
                return $subdomain;
            }
        }

        return null;
    }
}
