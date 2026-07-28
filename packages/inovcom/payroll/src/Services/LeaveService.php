<?php

namespace InovCom\Payroll\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Models\LeaveBalance;
use InovCom\Payroll\Models\LeaveRequest;
use InovCom\Payroll\Models\LeaveType;

class LeaveService
{
    public function hasTables(): bool
    {
        return Schema::connection('tenant')->hasTable('leave_types')
            && Schema::connection('tenant')->hasTable('leave_requests');
    }

    public function seedDefaultTypes(): void
    {
        if (!Schema::connection('tenant')->hasTable('leave_types')) {
            return;
        }

        $defaults = [
            ['code' => 'annual', 'name' => 'Congé annuel', 'is_paid' => true, 'default_days_per_year' => 18],
            ['code' => 'sick', 'name' => 'Congé maladie', 'is_paid' => true, 'default_days_per_year' => null],
            ['code' => 'unpaid', 'name' => 'Congé sans solde', 'is_paid' => false, 'default_days_per_year' => null],
            ['code' => 'maternity', 'name' => 'Congé maternité', 'is_paid' => true, 'default_days_per_year' => null],
        ];

        foreach ($defaults as $row) {
            LeaveType::query()->firstOrCreate(
                ['code' => $row['code']],
                array_merge($row, ['requires_approval' => true, 'is_active' => true])
            );
        }
    }

    /**
     * @return Collection<int, LeaveType>
     */
    public function activeTypes(): Collection
    {
        if (!$this->hasTables()) {
            return collect();
        }

        return LeaveType::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function ensureBalance(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        $allocated = $type->default_days_per_year ?? ($type->code === 'annual' ? ($employee->annual_leave_days ?? 18) : 0);

        return LeaveBalance::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'year' => $year,
            ],
            [
                'allocated' => $allocated,
                'used' => 0,
                'remaining' => $allocated,
            ]
        );
    }

    /**
     * @return array{success: bool, message: string, request: ?LeaveRequest}
     */
    public function createRequest(
        Employee $employee,
        int $leaveTypeId,
        Carbon $start,
        Carbon $end,
        ?string $reason,
        ?int $requestedBy
    ): array {
        if (!$this->hasTables()) {
            return ['success' => false, 'message' => 'Module congés non installé.', 'request' => null];
        }

        $type = LeaveType::find($leaveTypeId);
        if (!$type || !$type->is_active) {
            return ['success' => false, 'message' => 'Type de congé invalide.', 'request' => null];
        }

        if ($end->lt($start)) {
            return ['success' => false, 'message' => 'La date de fin doit être après la date de début.', 'request' => null];
        }

        $days = $this->countLeaveDays($start, $end);

        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'status' => LeaveRequest::STATUS_PENDING,
            'reason' => $reason,
            'requested_by' => $requestedBy,
        ]);

        return ['success' => true, 'message' => 'Demande de congé enregistrée.', 'request' => $request];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function approve(LeaveRequest $request, ?int $approvedBy, ?string $notes = null): array
    {
        if (!$request->isPending()) {
            return ['success' => false, 'message' => 'Cette demande ne peut plus être approuvée.'];
        }

        $request->update([
            'status' => LeaveRequest::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'manager_notes' => $notes,
        ]);

        $this->refreshBalanceForRequest($request);

        return ['success' => true, 'message' => 'Congé approuvé.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function reject(LeaveRequest $request, ?int $approvedBy, ?string $notes = null): array
    {
        if (!$request->isPending()) {
            return ['success' => false, 'message' => 'Cette demande ne peut plus être refusée.'];
        }

        $request->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'manager_notes' => $notes,
        ]);

        return ['success' => true, 'message' => 'Demande refusée.'];
    }

    public function unpaidLeaveDaysInPeriod(Employee $employee, Carbon $from, Carbon $to): float
    {
        if (!$this->hasTables()) {
            return 0;
        }

        $requests = LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get();

        $days = 0.0;
        foreach ($requests as $req) {
            if (!$req->leaveType || $req->leaveType->is_paid) {
                continue;
            }
            $overlapStart = Carbon::parse($req->start_date)->max($from);
            $overlapEnd = Carbon::parse($req->end_date)->min($to);
            if ($overlapEnd->gte($overlapStart)) {
                $days += $this->countLeaveDays($overlapStart, $overlapEnd);
            }
        }

        return $days;
    }

    private function refreshBalanceForRequest(LeaveRequest $request): void
    {
        if (!Schema::connection('tenant')->hasTable('leave_balances')) {
            return;
        }

        $request->load('leaveType');
        $year = (int) Carbon::parse($request->start_date)->year;
        $balance = $this->ensureBalance($request->employee, $request->leaveType, $year);

        $used = LeaveRequest::query()
            ->where('employee_id', $request->employee_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->sum('days');

        $remaining = max(0, (float) $balance->allocated - (float) $used);
        $balance->update(['used' => $used, 'remaining' => $remaining]);
    }

    public function countLeaveDays(Carbon $start, Carbon $end): float
    {
        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if (!$date->isSunday()) {
                $days++;
            }
        }

        return (float) $days;
    }
}
