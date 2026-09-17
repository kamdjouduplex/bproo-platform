<?php

namespace Bproo\Platform\Billing\Http\Controllers;

use App\Services\DesktopSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class DesktopSyncController extends Controller
{
    public function pushOut(Request $request, DesktopSyncService $sync): JsonResponse
    {
        try {
            $result = $sync->pushOut([
                'install_uuid' => (string) $request->input('install_uuid', ''),
                'token' => (string) $request->input('token', ''),
                'fingerprint' => (string) $request->input('fingerprint', ''),
                'batch_uuid' => $request->input('batch_uuid'),
                'app_version' => $request->input('app_version'),
                'events' => $request->input('events', []),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'batch_uuid' => $result['batch']->uuid,
            'status' => $result['batch']->status,
            'accepted' => $result['accepted'],
            'duplicates' => $result['duplicates'],
            'rejected' => $result['rejected'],
            'accepted_event_ids' => $result['accepted_event_ids'],
            'duplicate_event_ids' => $result['duplicate_event_ids'],
            'idempotent_batch' => $result['idempotent_batch'] ?? false,
        ]);
    }

    public function status(Request $request, DesktopSyncService $sync): JsonResponse
    {
        try {
            $status = $sync->status([
                'install_uuid' => (string) $request->input('install_uuid', ''),
                'token' => (string) $request->input('token', ''),
                'fingerprint' => (string) $request->input('fingerprint', ''),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(array_merge([
            'ok' => true,
        ], $status));
    }

    public function pullIn(Request $request, DesktopSyncService $sync): JsonResponse
    {
        try {
            $result = $sync->pullIn([
                'install_uuid' => (string) $request->input('install_uuid', ''),
                'token' => (string) $request->input('token', ''),
                'fingerprint' => (string) $request->input('fingerprint', ''),
                'after_id' => $request->input('after_id'),
                'limit' => $request->input('limit'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'count' => $result['count'],
            'next_after_id' => $result['next_after_id'],
            'events' => $result['events'],
        ]);
    }
}
