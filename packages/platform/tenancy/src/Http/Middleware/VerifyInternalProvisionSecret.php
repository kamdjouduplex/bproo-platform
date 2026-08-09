<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalProvisionSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('tenant_types.provision_secret', '');
        if ($secret === '') {
            abort(503, 'TENANT_PROVISION_SECRET is not configured.');
        }

        $token = (string) $request->bearerToken();
        if ($token === '' || ! hash_equals($secret, $token)) {
            abort(401, 'Invalid provision token.');
        }

        return $next($request);
    }
}
