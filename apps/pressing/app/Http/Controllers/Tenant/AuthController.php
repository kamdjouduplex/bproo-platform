<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Middleware\SetTenantConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InovCom\Users\Models\User;

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

        if (!Auth::guard('tenant')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants invalides.',
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('tenant')->user();
        if (!$user->is_active) {
            Auth::guard('tenant')->logout();
            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé.',
            ]);
        }

        $request->session()->regenerate();

        $tenantCode = $request->query('tenant')
            ?? $request->session()->get('tenant_code')
            ?? optional($request->attributes->get('tenant'))->code;

        if (is_string($tenantCode) && $tenantCode !== '') {
            $request->session()->put(SetTenantConnection::AUTH_TENANT_SESSION_KEY, $tenantCode);
            $request->session()->put('tenant_code', $tenantCode);
        }

        return redirect()->route('tenant.dashboard', ['tenant' => $tenantCode]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login', ['tenant' => $request->query('tenant')]);
    }
}
