<?php

namespace InovCom\Payroll\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use InovCom\Payroll\Models\Employee;
use InovCom\Users\Models\User;

/**
 * When Users + Payroll are both available: every system user gets an employee card.
 */
class UserEmployeeSyncService
{
    public function __construct(private EmployeeService $employees)
    {
    }

    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('employees')
            && Schema::connection('tenant')->hasTable('users');
    }

    /**
     * Create or update the employee linked to a user.
     *
     * @param  array<string, mixed>  $profile  Optional HR fields (position, department, base_salary, …)
     * @return array{employee: Employee, created: bool, plain_pin: ?string}
     */
    public function ensureForUser(User $user, ?string $phone = null, ?string $plainPin = null, array $profile = []): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('Table employés indisponible.');
        }

        $phone = $this->normalizePhone($phone ?? (string) ($user->phone ?? ''));
        $employee = Employee::query()->where('user_id', $user->id)->first();

        if (! $employee && $user->email) {
            $employee = Employee::query()
                ->whereNull('user_id')
                ->where('email', $user->email)
                ->first();
        }

        if (! $employee && $phone !== '') {
            $employee = Employee::query()
                ->whereNull('user_id')
                ->where('phone', $phone)
                ->first();
        }

        $created = false;
        $generatedPin = null;

        [$first, $last] = $this->splitName($user->name);
        $hr = $this->filterHrProfile($profile);

        if (! $employee) {
            $payload = array_merge([
                'employee_number' => $this->employees->nextEmployeeNumber(),
                'first_name' => $first,
                'last_name' => $last !== '' ? $last : $first,
                'email' => $user->email,
                'phone' => $phone !== '' ? $phone : null,
                'base_salary' => (float) ($hr['base_salary'] ?? 0),
                'hire_date' => $hr['hire_date'] ?? now()->toDateString(),
                'is_active' => (bool) $user->is_active,
                'user_id' => $user->id,
            ], $hr);

            if (Schema::connection('tenant')->hasColumn('employees', 'punch_pin')) {
                $generatedPin = $plainPin !== null && $plainPin !== ''
                    ? $this->normalizePin($plainPin)
                    : $this->generatePin();
                $payload['punch_pin'] = Hash::make($generatedPin);
            }

            $employee = $this->employees->create($payload);
            $created = true;
        } else {
            $updates = array_merge([
                'user_id' => $user->id,
                'first_name' => $first,
                'last_name' => $last !== '' ? $last : $first,
                'email' => $user->email ?: $employee->email,
                'is_active' => (bool) $user->is_active,
            ], $hr);

            if ($phone !== '') {
                $updates['phone'] = $phone;
            }

            if (Schema::connection('tenant')->hasColumn('employees', 'punch_pin')) {
                if ($plainPin !== null && $plainPin !== '') {
                    $generatedPin = $this->normalizePin($plainPin);
                    $updates['punch_pin'] = Hash::make($generatedPin);
                } elseif (empty($employee->punch_pin)) {
                    $generatedPin = $this->generatePin();
                    $updates['punch_pin'] = Hash::make($generatedPin);
                }
            }

            $reason = $profile['salary_change_reason'] ?? null;
            $employee = $this->employees->update($employee, $updates, null, is_string($reason) ? $reason : null);
        }

        if ($phone !== '' && Schema::connection('tenant')->hasColumn('users', 'phone')) {
            if ((string) $user->phone !== $phone) {
                $user->phone = $phone;
                $user->save();
            }
        }

        return [
            'employee' => $employee->fresh(),
            'created' => $created,
            'plain_pin' => $generatedPin,
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function filterHrProfile(array $profile): array
    {
        $allowed = [
            'position', 'department', 'contract_type', 'cnps_number',
            'bank_name', 'bank_account', 'gender', 'birth_date', 'address',
            'annual_leave_days', 'base_salary', 'hire_date', 'notes',
        ];

        $out = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $profile)) {
                continue;
            }
            $value = $profile[$key];
            if ($value === '' || $value === null) {
                if (in_array($key, ['base_salary', 'annual_leave_days'], true)) {
                    continue;
                }
                $out[$key] = null;
                continue;
            }
            if ($key === 'base_salary' || $key === 'annual_leave_days') {
                $out[$key] = $key === 'annual_leave_days' ? (int) $value : (float) $value;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Link all existing users that have no employee (run on payroll install).
     *
     * @return array{linked: int, pins: array<int, array{user: string, pin: string, matricule: string}>}
     */
    public function syncAllUsers(): array
    {
        if (! $this->isAvailable()) {
            return ['linked' => 0, 'pins' => []];
        }

        $linked = 0;
        $pins = [];

        User::query()->orderBy('id')->each(function (User $user) use (&$linked, &$pins) {
            $result = $this->ensureForUser($user, $user->phone ?? null, null);
            if ($result['created'] || $result['plain_pin']) {
                $linked++;
            }
            if ($result['plain_pin']) {
                $pins[] = [
                    'user' => $user->name,
                    'pin' => $result['plain_pin'],
                    'matricule' => $result['employee']->employee_number,
                ];
            }
        });

        return ['linked' => $linked, 'pins' => $pins];
    }

    public function verifyPunchPin(Employee $employee, string $code): bool
    {
        $pin = (string) ($employee->punch_pin ?? '');
        if ($pin === '') {
            return false;
        }

        $code = $this->normalizePin($code);

        // Support legacy plain storage during transition
        if (! str_starts_with($pin, '$2y$') && ! str_starts_with($pin, '$2a$') && ! str_starts_with($pin, '$argon')) {
            return hash_equals($pin, $code);
        }

        return Hash::check($code, $pin);
    }

    public function setPunchPin(Employee $employee, string $plainPin): void
    {
        if (! Schema::connection('tenant')->hasColumn('employees', 'punch_pin')) {
            throw new \RuntimeException('Colonne punch_pin absente — exécutez les migrations paie.');
        }

        $employee->update([
            'punch_pin' => Hash::make($this->normalizePin($plainPin)),
        ]);
    }

    public function generatePin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function normalizePin(string $pin): string
    {
        $digits = preg_replace('/\D+/', '', $pin) ?? '';

        return $digits;
    }

    public function normalizePhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['Employé', ''];
        }

        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
