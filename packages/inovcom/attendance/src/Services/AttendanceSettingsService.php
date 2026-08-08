<?php

namespace InovCom\Attendance\Services;

use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use InovCom\Users\Models\User;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Tenant attendance settings + corporate network (Wi‑Fi) gate.
 *
 * Browsers cannot read Wi‑Fi SSID. Security is enforced by allowing only
 * configured public/LAN IP ranges of the company network, while the Wi‑Fi
 * name is shown to users as the network they must join.
 */
class AttendanceSettingsService
{
    public const SETTING_WIFI_NAME = 'attendance_wifi_name';

    public const SETTING_ALLOWED_CIDRS = 'attendance_allowed_cidrs';

    public const SETTING_KIOSK_CODE = 'attendance_kiosk_code';

    public const SETTING_KIOSK_ENABLED = 'attendance_kiosk_enabled';

    public function tenant(): ?object
    {
        return app(TenantManager::class)->tenant();
    }

    public function wifiName(?object $tenant = null): string
    {
        $tenant ??= $this->tenant();

        return trim((string) ($tenant?->getSetting(self::SETTING_WIFI_NAME, '') ?? ''));
    }

    /**
     * @return list<string>
     */
    public function allowedCidrs(?object $tenant = null): array
    {
        $tenant ??= $this->tenant();
        $raw = (string) ($tenant?->getSetting(self::SETTING_ALLOWED_CIDRS, '') ?? '');

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $cidrs = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $cidrs[] = $part;
            }
        }

        return array_values(array_unique($cidrs));
    }

    public function kioskCode(?object $tenant = null): string
    {
        $tenant ??= $this->tenant();

        return (string) ($tenant?->getSetting(self::SETTING_KIOSK_CODE, '') ?? '');
    }

    public function kioskEnabled(?object $tenant = null): bool
    {
        $tenant ??= $this->tenant();
        $raw = $tenant?->getSetting(self::SETTING_KIOSK_ENABLED, false);

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    public function networkRestrictionConfigured(?object $tenant = null): bool
    {
        return $this->allowedCidrs($tenant) !== [];
    }

    /**
     * @return array{allowed: bool, client_ip: string, message: ?string, wifi_name: string}
     */
    public function assertOnCompanyNetwork(?Request $request = null, ?object $tenant = null): array
    {
        $request ??= request();
        $tenant ??= $this->tenant();
        $clientIp = (string) ($request->ip() ?? '');
        $wifi = $this->wifiName($tenant) ?: 'Wi‑Fi de l’entreprise';
        $cidrs = $this->allowedCidrs($tenant);

        if ($cidrs === []) {
            return [
                'allowed' => true,
                'client_ip' => $clientIp,
                'message' => null,
                'wifi_name' => $wifi,
            ];
        }

        $allowed = $clientIp !== '' && IpUtils::checkIp($clientIp, $cidrs);

        if ($allowed) {
            return [
                'allowed' => true,
                'client_ip' => $clientIp,
                'message' => null,
                'wifi_name' => $wifi,
            ];
        }

        return [
            'allowed' => false,
            'client_ip' => $clientIp,
            'message' => 'Vous devez être connecté au réseau Wi‑Fi « '.$wifi
                .' » (réseau de l’entreprise) pour pointer votre présence ou votre départ. '
                .'Déconnectez-vous des données mobiles / autre Wi‑Fi, rejoignez « '.$wifi.' », puis réessayez.',
            'wifi_name' => $wifi,
        ];
    }

    public function verifyKioskCode(string $code, ?object $tenant = null): bool
    {
        $expected = $this->kioskCode($tenant);
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, trim($code));
    }

    /**
     * Resolve active employee from phone (kiosk).
     */
    public function findEmployeeByPhone(string $phone): ?object
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return null;
        }

        if (! Schema::connection('tenant')->hasTable('employees')) {
            return null;
        }

        $employees = \InovCom\Payroll\Models\Employee::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        return $employees->first(function ($emp) use ($normalized) {
            $empDigits = $this->normalizePhone((string) $emp->phone);
            if ($empDigits === '' || $normalized === '') {
                return false;
            }
            if ($empDigits === $normalized) {
                return true;
            }
            $len = min(9, strlen($empDigits), strlen($normalized));
            if ($len < 8) {
                return false;
            }

            return substr($empDigits, -$len) === substr($normalized, -$len);
        });
    }

    /**
     * Resolve active user from employee phone (kiosk).
     *
     * @return array{user: ?User, employee: ?object, error: ?string}
     */
    public function resolveKioskIdentity(string $phone): array
    {
        $employee = $this->findEmployeeByPhone($phone);
        if (! $employee) {
            return [
                'user' => null,
                'employee' => null,
                'error' => 'Aucun employé actif trouvé avec ce numéro. Vérifiez le téléphone dans Utilisateurs / Mon compte.',
            ];
        }

        $user = null;
        if (! empty($employee->user_id)) {
            $user = User::query()->where('id', $employee->user_id)->where('is_active', true)->first();
        }

        if (! $user && ! empty($employee->email)) {
            $user = User::query()->where('email', $employee->email)->where('is_active', true)->first();
        }

        if (! $user) {
            return [
                'user' => null,
                'employee' => $employee,
                'error' => 'Employé trouvé ('.$employee->full_name.'), mais aucun compte utilisateur n’est lié. '
                    .'Associez le compte dans Utilisateurs, puis réessayez.',
            ];
        }

        return [
            'user' => $user,
            'employee' => $employee,
            'error' => null,
        ];
    }

    /**
     * @deprecated Prefer resolveKioskIdentity()
     */
    public function findUserByPhone(string $phone): ?User
    {
        return $this->resolveKioskIdentity($phone)['user'];
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Drop leading country zeros noise but keep last 8–12 digits for match flexibility
        return ltrim($digits, '0') !== '' ? $digits : '';
    }

    /**
     * @param  array{wifi_name?: string, allowed_cidrs?: string, kiosk_code?: string, kiosk_enabled?: bool}  $data
     */
    public function save(object $tenant, array $data): void
    {
        if (array_key_exists('wifi_name', $data)) {
            $tenant->setSetting(self::SETTING_WIFI_NAME, trim((string) $data['wifi_name']));
        }
        if (array_key_exists('allowed_cidrs', $data)) {
            $tenant->setSetting(self::SETTING_ALLOWED_CIDRS, trim((string) $data['allowed_cidrs']));
        }
        if (array_key_exists('kiosk_code', $data)) {
            $tenant->setSetting(self::SETTING_KIOSK_CODE, trim((string) $data['kiosk_code']));
        }
        if (array_key_exists('kiosk_enabled', $data)) {
            $tenant->setSetting(self::SETTING_KIOSK_ENABLED, $data['kiosk_enabled'] ? '1' : '0');
        }
    }
}
