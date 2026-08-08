<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Middleware\SetTenantConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ], [], [
            'login' => 'email ou téléphone',
        ]);

        $user = $this->resolveUserByLogin($data['login']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'Identifiants invalides.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Ce compte est désactivé.',
            ]);
        }

        Auth::guard('tenant')->login($user, $request->boolean('remember'));
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

    private function resolveUserByLogin(string $login): ?User
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return User::query()->where('email', $login)->first();
        }

        $digits = preg_replace('/\D+/', '', $login) ?? '';
        if ($digits === '') {
            return User::query()->where('email', $login)->first();
        }

        $user = null;
        if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
            $user = User::query()->where('phone', $digits)->first();
            if (! $user) {
                $user = User::query()
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->get()
                    ->first(function (User $candidate) use ($digits) {
                        $cand = preg_replace('/\D+/', '', (string) $candidate->phone) ?? '';
                        if ($cand === '') {
                            return false;
                        }
                        $len = min(9, strlen($cand), strlen($digits));

                        return $len >= 8 && substr($cand, -$len) === substr($digits, -$len);
                    });
            }
        }

        if ($user) {
            return $user;
        }

        // Fallback: employee phone → linked user
        if (Schema::connection('tenant')->hasTable('employees')) {
            $employees = \InovCom\Payroll\Models\Employee::query()
                ->whereNotNull('user_id')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get(['user_id', 'phone']);

            $match = $employees->first(function ($emp) use ($digits) {
                $cand = preg_replace('/\D+/', '', (string) $emp->phone) ?? '';
                if ($cand === '' || $digits === '') {
                    return false;
                }
                if ($cand === $digits) {
                    return true;
                }
                $len = min(9, strlen($cand), strlen($digits));

                return $len >= 8 && substr($cand, -$len) === substr($digits, -$len);
            });

            if ($match) {
                return User::query()->where('id', $match->user_id)->first();
            }
        }

        return User::query()->where('email', $login)->first();
    }
}
