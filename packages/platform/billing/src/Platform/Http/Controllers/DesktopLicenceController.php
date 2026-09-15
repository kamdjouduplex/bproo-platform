<?php

namespace Bproo\Platform\Billing\Http\Controllers;

use App\Services\DesktopLicenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class DesktopLicenceController extends Controller
{
    public function activate(Request $request, DesktopLicenceService $licences): JsonResponse
    {
        try {
            $result = $licences->activate([
                'activation_code' => (string) $request->input('activation_code', ''),
                'fingerprint' => (string) $request->input('fingerprint', ''),
                'product_key' => $request->input('product_key'),
                'app_version' => $request->input('app_version'),
                'os' => $request->input('os'),
                'ip' => $request->ip(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'token' => $result['token'],
            'licence' => $result['licence'],
        ]);
    }

    public function heartbeat(Request $request, DesktopLicenceService $licences): JsonResponse
    {
        try {
            $result = $licences->heartbeat([
                'install_uuid' => (string) $request->input('install_uuid', ''),
                'token' => (string) $request->input('token', ''),
                'fingerprint' => (string) $request->input('fingerprint', ''),
                'app_version' => $request->input('app_version'),
                'os' => $request->input('os'),
                'ip' => $request->ip(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'token' => $result['token'],
            'licence' => $result['licence'],
        ]);
    }
}
