<?php

namespace InovCom\Payroll\Support;

use Illuminate\Validation\Rule;
use InovCom\Payroll\Models\Employee;

class EmployeeRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?int $employeeId = null): array
    {
        return [
            'employee_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique(Employee::class, 'employee_number')->ignore($employeeId),
            ],
            'first_name' => 'required|string|max:128',
            'last_name' => 'required|string|max:128',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'position' => 'nullable|string|max:128',
            'department' => 'nullable|string|max:128',
            'contract_type' => 'nullable|string|in:cdi,cdd,stage,freelance,other',
            'cnps_number' => 'nullable|string|max:64',
            'bank_name' => 'nullable|string|max:128',
            'bank_account' => 'nullable|string|max:64',
            'gender' => 'nullable|string|in:M,F,other',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'annual_leave_days' => 'nullable|integer|min:0|max:365',
            'base_salary' => 'required|numeric|min:0',
            'hire_date' => 'nullable|date',
            'is_active' => 'boolean',
            'user_id' => 'nullable|exists:tenant.users,id',
            'notes' => 'nullable|string|max:2000',
            'salary_change_reason' => 'nullable|string|max:255',
        ];
    }

    public static function contractTypeLabel(?string $type): string
    {
        return match ($type) {
            'cdi' => 'CDI',
            'cdd' => 'CDD',
            'stage' => 'Stage',
            'freelance' => 'Freelance',
            'other' => 'Autre',
            default => '—',
        };
    }
}
