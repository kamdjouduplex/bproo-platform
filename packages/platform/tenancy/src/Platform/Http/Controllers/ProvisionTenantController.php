<?php

namespace Bproo\Platform\Tenancy\Http\Controllers;

use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProvisionTenantController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'tenant_code' => ['required', 'string', 'max:64'],
            'admin_name' => ['nullable', 'string', 'max:255'],
            'admin_email' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = Tenant::query()->findOrFail($data['tenant_id']);

        if ($tenant->code !== $data['tenant_code']) {
            return response()->json(['message' => 'tenant_code mismatch'], 422);
        }

        $type = Tenant::normalizeType($tenant->getRawOriginal('type') ?? $tenant->type);
        $localKey = (string) config('tenant_types.local_app_key', '');
        $typeKey = (string) config("tenant_types.types.{$type}.app_key", $type);

        if ($localKey !== '' && $localKey !== $typeKey) {
            return response()->json([
                'message' => "This host (APP_PRODUCT_KEY={$localKey}) cannot provision type [{$type}].",
            ], 422);
        }

        // Return 202 quickly; with sync queue this still runs after the response
        // so Control Center does not hit PHP max_execution_time (30s).
        ProvisionTenantJob::dispatch(
            $tenant,
            (string) ($data['admin_name'] ?? ''),
            (string) ($data['admin_email'] ?? ''),
            (string) ($data['admin_password'] ?? '')
        )->afterResponse();

        return response()->json([
            'ok' => true,
            'tenant_id' => $tenant->id,
            'tenant_code' => $tenant->code,
            'status' => 'queued',
        ], 202);
    }
}
