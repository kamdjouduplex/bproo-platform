<?php

namespace App\Http\Controllers\Tenant;

use App\Services\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function showLogin()
    {
        return view('tenant.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Check plan expiry before even attempting credentials — avoids
        // creating a session that the middleware would immediately destroy.
        $tenant = app(TenantManager::class)->tenant();
        if ($tenant && $tenant->isExpired()) {
            throw ValidationException::withMessages([
                'email' => 'Votre abonnement a expiré. Veuillez contacter votre administrateur pour renouveler votre plan.',
            ]);
        }

        if (!Auth::guard('tenant')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants invalides.',
            ]);
        }

        // Reject inactive users immediately after credential check
        if (!Auth::guard('tenant')->user()->is_active) {
            Auth::guard('tenant')->logout();
            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé. Contactez votre administrateur.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('tenant.dashboard', ['tenant' => $request->query('tenant')]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login', ['tenant' => $request->query('tenant')]);
    }
}
