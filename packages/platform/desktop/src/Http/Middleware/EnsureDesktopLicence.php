<?php

namespace Bproo\Platform\Desktop\Http\Middleware;

use Bproo\Platform\Desktop\Services\LicenceGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces signed desktop licence on /app when DESKTOP_RUNTIME=1.
 * Fail-closed: missing/invalid HMAC → blocked. Expired grace → read-only.
 */
class EnsureDesktopLicence
{
    private const ALLOWED_WITHOUT_LICENCE = [
        'tenant.login',
        'tenant.login.submit',
        'tenant.logout',
        'tenant.subscription',
        'desktop.licence',
    ];

    public function __construct(
        protected LicenceGuard $guard
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->desktopEnabled()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_WITHOUT_LICENCE, true)) {
            return $next($request);
        }

        $result = $this->guard->evaluate();

        if (! ($result['ok'] ?? false)) {
            return $this->blocked($request, (string) ($result['reason'] ?? 'invalid'));
        }

        $mode = (string) ($result['access_mode'] ?? 'read_only');
        $request->attributes->set('desktop_licence', $result);
        $request->attributes->set('desktop_access_mode', $mode);
        view()->share('desktopAccessMode', $mode);
        view()->share('desktopLicenceExpiresAt', $result['expires_at'] ?? null);

        if ($mode === 'read_only' && $this->isMutating($request)) {
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                return response()->json([
                    'message' => 'Licence offline expirée. Mode lecture seule — reconnectez-vous au Control Center (desktop:heartbeat).',
                    'access_mode' => 'read_only',
                ], 403);
            }

            return redirect()
                ->route('desktop.licence', array_filter(['tenant' => $request->query('tenant')]))
                ->with('warning', 'Licence offline expirée : application en lecture seule.');
        }

        return $next($request);
    }

    private function desktopEnabled(): bool
    {
        return (bool) config('desktop.enabled', false)
            || filter_var(env('DESKTOP_RUNTIME', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function isMutating(Request $request): bool
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        // Livewire update endpoint
        $path = trim($request->path(), '/');
        if (str_contains($path, 'livewire')) {
            return true;
        }

        return false;
    }

    private function blocked(Request $request, string $reason): Response
    {
        $messages = [
            'missing_activation' => 'Cette installation n’est pas activée. Lancez Activer la licence.',
            'invalid_signature' => 'Jeton licence invalide ou clé de signature incorrecte. Réactivez auprès du Control Center.',
            'install_mismatch' => 'Jeton licence ne correspond pas à cette installation.',
            'product_mismatch' => 'Produit non autorisé pour ce jeton.',
            'fingerprint_mismatch' => 'Cette licence est liée à une autre machine.',
            'missing_signing_key' => 'Clé DESKTOP_LICENCE_SIGNING_KEY manquante. Elle doit être identique à celle du Control Center.',
        ];
        $message = $messages[$reason] ?? 'Licence desktop refusée.';

        if ($request->expectsJson() || $request->header('X-Livewire')) {
            return response()->json(['message' => $message, 'reason' => $reason], 403);
        }

        return response()->view('desktop::licence-blocked', [
            'message' => $message,
            'reason' => $reason,
        ], 403);
    }
}
