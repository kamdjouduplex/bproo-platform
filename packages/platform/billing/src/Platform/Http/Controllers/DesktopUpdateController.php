<?php

namespace Bproo\Platform\Billing\Http\Controllers;

use App\Models\DesktopRelease;
use App\Services\DesktopUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DesktopUpdateController extends Controller
{
    public function check(Request $request, DesktopUpdateService $updates): JsonResponse
    {
        try {
            $result = $updates->check([
                'install_uuid' => (string) $request->input('install_uuid', ''),
                'token' => (string) $request->input('token', ''),
                'fingerprint' => (string) $request->input('fingerprint', ''),
                'current_version' => (string) $request->input('current_version', ''),
                'channel' => $request->input('channel'),
                'product_key' => $request->input('product_key'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        if (! ($result['update_available'] ?? false)) {
            return response()->json([
                'ok' => true,
                'update_available' => false,
            ]);
        }

        return response()->json([
            'ok' => true,
            'update_available' => true,
            'manifest' => $result['manifest'],
            'signed_manifest' => $result['signed_manifest'],
        ]);
    }

    public function download(Request $request, string $uuid, DesktopUpdateService $updates): StreamedResponse|\Illuminate\Http\RedirectResponse|JsonResponse
    {
        try {
            $resolved = $updates->resolveDownload($uuid, [
                'install_uuid' => (string) $request->input('install_uuid', $request->query('install_uuid', '')),
                'token' => (string) $request->input('token', $request->query('token', '')),
                'fingerprint' => (string) $request->input('fingerprint', $request->query('fingerprint', '')),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        /** @var DesktopRelease $release */
        $release = $resolved['release'];

        if ($resolved['mode'] === 'redirect') {
            return redirect()->away((string) $resolved['url']);
        }

        $disk = (string) $resolved['disk'];
        $path = (string) $resolved['path'];
        $filename = basename($path);
        $absolute = Storage::disk($disk)->path($path);

        if (! is_file($absolute) || ! is_readable($absolute)) {
            return response()->json([
                'ok' => false,
                'errors' => ['package' => ['Fichier package introuvable sur le serveur.']],
            ], 404);
        }

        // Large zips (~100MB+) must stream with Content-Length. Avoid buffering the
        // whole file (breaks PHP built-in server / artisan serve mid-transfer).
        @set_time_limit(0);
        ignore_user_abort(true);

        return response()->file($absolute, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Package-Sha256' => (string) $release->package_sha256,
            'X-Package-Version' => (string) $release->version,
            'Cache-Control' => 'no-store',
            'Connection' => 'close',
        ]);
    }
}
