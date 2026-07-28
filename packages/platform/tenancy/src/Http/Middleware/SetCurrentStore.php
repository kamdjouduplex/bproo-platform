<?php

namespace App\Http\Middleware;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use InovCom\Users\Models\User;

class SetCurrentStore
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant || !$tenant->multi_store_enabled) {
            $request->attributes->set('store_id', null);
            return $next($request);
        }

        // Pressing tenants use multi-agences instead of multi-stores.
        if (($tenant->type ?? null) === 'pressing') {
            $request->attributes->set('store_id', null);
            if ($request->hasSession()) {
                $request->session()->forget('tenant_store_id');
            }

            return $next($request);
        }

        $context = app(StoreContextService::class);
        $stores = $context->activeStores($tenant);
        if ($stores->isEmpty()) {
            $request->attributes->set('store_id', null);
            return $next($request);
        }

        $requestedStoreId = $request->query('store');
        $sessionStoreId = $request->session()->get('tenant_store_id');
        $defaultStoreId = $context->defaultStoreId($tenant);
        /** @var User|null $user */
        $user = auth('tenant')->user();
        $canViewAllStores = $user && method_exists($user, 'canViewAllStores') && $user->canViewAllStores();

        if ($canViewAllStores) {
            if ($requestedStoreId === 'all') {
                $request->session()->put('tenant_store_id', null);
                $request->attributes->set('store_id', null);
                return $next($request);
            }

            $candidate = $requestedStoreId ?: ($sessionStoreId ?: $defaultStoreId);
            $storeId = $stores->contains(fn ($s) => (int) $s->id === (int) $candidate)
                ? (int) $candidate
                : ($defaultStoreId ? (int) $defaultStoreId : (int) $stores->first()->id);

            $request->session()->put('tenant_store_id', $storeId);
            $request->attributes->set('store_id', $storeId);
            return $next($request);
        }

        $assignedStoreId = $user?->assigned_store_id ? (int) $user->assigned_store_id : null;
        if (!$assignedStoreId || !$stores->contains(fn ($s) => (int) $s->id === $assignedStoreId)) {
            $assignedStoreId = $defaultStoreId ? (int) $defaultStoreId : (int) $stores->first()->id;
        }

        $request->session()->put('tenant_store_id', $assignedStoreId);
        $request->attributes->set('store_id', $assignedStoreId);

        return $next($request);
    }
}
