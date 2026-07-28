<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $code = $request->query('tenant')
            ?? $request->header('X-Tenant-Code')
            ?? ($request->hasSession() ? $request->session()->get('tenant_code') : null);

        if (!$code) {
            return $next($request);
        }

        if ($request->hasSession() && $request->query('tenant')) {
            $request->session()->put('tenant_code', $code);
        }

        $manager = app(TenantManager::class);
        $tenant = $manager->resolveByCode($code);

        if ($tenant) {
            $manager->setTenant($tenant);
            $request->attributes->set('tenant', $tenant);
            url()->defaults(['tenant' => $tenant->code]);
        } elseif ($request->hasSession()) {
            $request->session()->forget('tenant_code');
        }

        return $next($request);
    }
}
