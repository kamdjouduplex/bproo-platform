<?php

namespace InovCom\Attendance\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Attendance\Models\AttendancePunch;
use InovCom\Users\Models\User;

class AttendanceService
{
    public function hasTable(): bool
    {
        return Schema::connection('tenant')->hasTable('attendance_punches');
    }

    public function hasPunchTypeColumn(): bool
    {
        return $this->hasTable()
            && Schema::connection('tenant')->hasColumn('attendance_punches', 'punch_type');
    }

    public function resolveEmployeeForUser(User $user): ?object
    {
        if (!Schema::connection('tenant')->hasTable('employees')) {
            return null;
        }

        $employeeClass = \InovCom\Payroll\Models\Employee::class;

        $employee = $employeeClass::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($employee) {
            return $employee;
        }

        if (!empty($user->email)) {
            return $employeeClass::query()
                ->where('is_active', true)
                ->where('email', $user->email)
                ->first();
        }

        return null;
    }

    /**
     * Jours ouvrés : lundi–samedi (dimanche exclu).
     */
    public function isWorkday(Carbon $date): bool
    {
        return !$date->isSunday();
    }

    public function punchTypeLabel(?string $type): string
    {
        return AttendancePunch::TYPE_LABELS[$type ?? AttendancePunch::TYPE_IN] ?? 'Pointage';
    }

    private function dateKey(Carbon|\DateTimeInterface|string $date): string
    {
        return Carbon::parse($date)->toDateString();
    }

    /**
     * @return Collection<int, AttendancePunch>
     */
    public function punchesForUserOnDate(User $user, Carbon|string|null $date = null): Collection
    {
        if (!$this->hasTable()) {
            return collect();
        }

        $date = $date ? Carbon::parse($date) : today();

        return AttendancePunch::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', $date)
            ->orderBy('punched_at')
            ->get();
    }

    /**
     * @return array{
     *   open_session: bool,
     *   can_punch_in: bool,
     *   can_punch_out: bool,
     *   next_action: 'in'|'out',
     *   is_present: bool,
     *   arrival_time: ?string,
     *   departure_time: ?string,
     *   last_punch: ?AttendancePunch,
     *   punches_today: Collection
     * }
     */
    public function todayStatus(User $user): array
    {
        $punches = $this->punchesForUserOnDate($user);
        $last = $punches->last();
        $openSession = $last && $this->resolvePunchType($last) === AttendancePunch::TYPE_IN;

        $firstIn = $punches->first(fn (AttendancePunch $p) => $this->resolvePunchType($p) === AttendancePunch::TYPE_IN);
        $lastOut = $punches->filter(fn (AttendancePunch $p) => $this->resolvePunchType($p) === AttendancePunch::TYPE_OUT)->last();

        return [
            'open_session' => $openSession,
            'can_punch_in' => ! $openSession,
            'can_punch_out' => $openSession,
            'next_action' => $openSession ? AttendancePunch::TYPE_OUT : AttendancePunch::TYPE_IN,
            'is_present' => $openSession,
            'arrival_time' => $firstIn?->punched_at?->format('H:i'),
            'departure_time' => $lastOut?->punched_at?->format('H:i'),
            'last_punch' => $last,
            'punches_today' => $punches,
        ];
    }

    /**
     * @return array{success: bool, message: string, punch: ?AttendancePunch}
     */
    public function punchIn(User $user, ?string $notes = null): array
    {
        return $this->recordPunch($user, AttendancePunch::TYPE_IN, $notes);
    }

    /**
     * @return array{success: bool, message: string, punch: ?AttendancePunch}
     */
    public function punchOut(User $user, ?string $notes = null): array
    {
        return $this->recordPunch($user, AttendancePunch::TYPE_OUT, $notes);
    }

    /**
     * @return array{success: bool, message: string, punch: ?AttendancePunch}
     */
    public function punch(User $user, ?string $notes = null): array
    {
        $status = $this->todayStatus($user);

        return $status['can_punch_out']
            ? $this->punchOut($user, $notes)
            : $this->punchIn($user, $notes);
    }

    /**
     * @return array{success: bool, message: string, punch: ?AttendancePunch}
     */
    private function recordPunch(User $user, string $type, ?string $notes = null): array
    {
        if (!$this->hasTable()) {
            return ['success' => false, 'message' => 'Module présence non installé (migration manquante).', 'punch' => null];
        }

        $status = $this->todayStatus($user);

        if ($type === AttendancePunch::TYPE_IN && !$status['can_punch_in']) {
            return [
                'success' => false,
                'message' => 'Arrivée déjà pointée. Enregistrez d\'abord votre départ.',
                'punch' => null,
            ];
        }

        if ($type === AttendancePunch::TYPE_OUT && !$status['can_punch_out']) {
            return [
                'success' => false,
                'message' => 'Pointez d\'abord votre arrivée avant le départ.',
                'punch' => null,
            ];
        }

        $now = now();
        $employee = $this->resolveEmployeeForUser($user);

        if ($employee && empty($employee->user_id)) {
            $employee->update(['user_id' => $user->id]);
        }

        $data = [
            'user_id' => $user->id,
            'employee_id' => $employee?->id,
            'attendance_date' => $this->dateKey($now),
            'punched_at' => $now,
            'source' => 'manual',
            'notes' => $notes,
        ];

        if ($this->hasPunchTypeColumn()) {
            $data['punch_type'] = $type;
        }

        $punch = AttendancePunch::create($data);

        $label = $this->punchTypeLabel($type);

        return [
            'success' => true,
            'message' => $label . ' enregistrée à ' . $now->format('H:i') . '.',
            'punch' => $punch,
        ];
    }

    public function isPresentToday(User $user): bool
    {
        if (!$this->hasTable()) {
            return false;
        }

        return $this->punchesForUserOnDate($user)->isNotEmpty();
    }

    public function lastPunchToday(User $user): ?AttendancePunch
    {
        return $this->todayStatus($user)['last_punch'];
    }

    /**
     * @return Collection<int, object>
     */
    public function activeEmployees(): Collection
    {
        if (!Schema::connection('tenant')->hasTable('employees')) {
            return collect();
        }

        return \InovCom\Payroll\Models\Employee::query()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return array{
     *   employee: object|null,
     *   user: User|null,
     *   from: Carbon,
     *   to: Carbon,
     *   expected_days: int,
     *   present_days: int,
     *   absent_days: int,
     *   complete_days: int,
     *   performance_percent: float,
     *   performance_level: string,
     *   performance_label: string,
     *   days: array<int, array{
     *     date: string,
     *     label: string,
     *     weekday: string,
     *     present: bool,
     *     complete: bool,
     *     arrival: ?string,
     *     departure: ?string,
     *     punches: array<int, array{type: string, type_label: string, time: string}>
     *   }>,
     *   punches: Collection
     * }
     */
    public function presenceReport(?int $employeeId, ?int $userId, Carbon $from, Carbon $to): array
    {
        $employee = null;
        $user = null;

        if ($employeeId && Schema::connection('tenant')->hasTable('employees')) {
            $employee = \InovCom\Payroll\Models\Employee::find($employeeId);
            if ($employee?->user_id) {
                $user = User::find($employee->user_id);
            }
        }

        if (!$user && $userId) {
            $user = User::find($userId);
            if (!$employee) {
                $employee = $this->resolveEmployeeForUser($user);
            }
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $today = Carbon::today();

        if ($employee?->id && $user) {
            if (empty($employee->user_id)) {
                $employee->update(['user_id' => $user->id]);
            }

            AttendancePunch::query()
                ->where('user_id', $user->id)
                ->whereNull('employee_id')
                ->whereBetween('attendance_date', [$this->dateKey($from), $this->dateKey($to)])
                ->update(['employee_id' => $employee->id]);
        }

        $punchQuery = AttendancePunch::query()
            ->whereBetween('attendance_date', [$this->dateKey($from), $this->dateKey($to)])
            ->orderBy('punched_at');

        if ($employee?->id) {
            $punchQuery->where(function ($q) use ($employee, $user) {
                $q->where('employee_id', $employee->id);
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            });
        } elseif ($user) {
            $punchQuery->where('user_id', $user->id);
        } else {
            $punchQuery->whereRaw('0 = 1');
        }

        $punches = $punchQuery->get();

        $presentDateKeys = $punches
            ->map(fn (AttendancePunch $p) => $this->dateKey($p->attendance_date))
            ->unique()
            ->values();

        $expectedDays = 0;
        $presentDays = 0;
        $completeDays = 0;
        $days = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            if ($date->gt($today)) {
                continue;
            }

            if (!$this->isWorkday($date)) {
                continue;
            }

            $expectedDays++;
            $dateKey = $this->dateKey($date);
            $dayPunches = $punches->filter(fn (AttendancePunch $p) => $this->dateKey($p->attendance_date) === $dateKey);
            $daySummary = $this->summarizeDayPunches($dayPunches);
            $present = $presentDateKeys->contains($dateKey);

            if ($present) {
                $presentDays++;
            }
            if ($daySummary['complete']) {
                $completeDays++;
            }

            $days[] = array_merge([
                'date' => $dateKey,
                'label' => $date->format('d/m/Y'),
                'weekday' => $date->locale('fr')->isoFormat('ddd'),
                'present' => $present,
            ], $daySummary);
        }

        $absentDays = max(0, $expectedDays - $presentDays);
        $performancePercent = $expectedDays > 0
            ? round($presentDays / $expectedDays * 100, 1)
            : 0.0;

        [$level, $label] = $this->performanceMeta($performancePercent);

        return [
            'employee' => $employee,
            'user' => $user,
            'from' => $from,
            'to' => $to,
            'expected_days' => $expectedDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'complete_days' => $completeDays,
            'performance_percent' => $performancePercent,
            'performance_level' => $level,
            'performance_label' => $label,
            'days' => $days,
            'punches' => $punches,
        ];
    }

    /**
     * Rapport de présence pour tous les employés actifs (vue manager).
     *
     * @return array{
     *   from: Carbon,
     *   to: Carbon,
     *   employees: array<int, array{
     *     employee: object,
     *     display_name: string,
     *     report: array
     *   }>
     * }
     */
    public function teamPresenceReport(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $employees = $this->activeEmployees()->map(function ($employee) use ($from, $to) {
            $report = $this->presenceReport($employee->id, null, $from, $to);

            return [
                'employee' => $employee,
                'display_name' => $this->displayName($employee, $report['user']),
                'report' => $report,
            ];
        })->values()->all();

        return [
            'from' => $from,
            'to' => $to,
            'employees' => $employees,
        ];
    }

    /**
     * @param  Collection<int, AttendancePunch>  $dayPunches
     * @return array{
     *   arrival: ?string,
     *   departure: ?string,
     *   complete: bool,
     *   punches: array<int, array{type: string, type_label: string, time: string}>
     * }
     */
    private function summarizeDayPunches(Collection $dayPunches): array
    {
        $sorted = $dayPunches->sortBy('punched_at')->values();

        $punchRows = $sorted->map(function (AttendancePunch $p) {
            $type = $this->resolvePunchType($p);

            return [
                'type' => $type,
                'type_label' => $this->punchTypeLabel($type),
                'time' => $p->punched_at->format('H:i'),
            ];
        })->all();

        $firstIn = $sorted->first(fn (AttendancePunch $p) => $this->resolvePunchType($p) === AttendancePunch::TYPE_IN);
        $lastOut = $sorted->filter(fn (AttendancePunch $p) => $this->resolvePunchType($p) === AttendancePunch::TYPE_OUT)->last();

        if (!$firstIn && $sorted->isNotEmpty() && !$this->hasPunchTypeColumn()) {
            $firstIn = $sorted->first();
            $lastOut = $sorted->count() > 1 ? $sorted->last() : null;
        }

        $arrival = $firstIn?->punched_at?->format('H:i');
        $departure = $lastOut?->punched_at?->format('H:i');

        return [
            'arrival' => $arrival,
            'departure' => $departure,
            'complete' => $arrival !== null && $departure !== null,
            'punches' => $punchRows,
        ];
    }

    private function resolvePunchType(AttendancePunch $punch): string
    {
        if ($this->hasPunchTypeColumn() && !empty($punch->punch_type)) {
            return $punch->punch_type === AttendancePunch::TYPE_OUT
                ? AttendancePunch::TYPE_OUT
                : AttendancePunch::TYPE_IN;
        }

        return AttendancePunch::TYPE_IN;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function performanceMeta(float $percent): array
    {
        if ($percent >= 95) {
            return ['excellent', 'Excellent'];
        }
        if ($percent >= 80) {
            return ['good', 'Bon'];
        }
        if ($percent >= 60) {
            return ['warning', 'À surveiller'];
        }

        return ['poor', 'Insuffisant'];
    }

    public function displayName(?object $employee, ?User $user): string
    {
        if ($employee && method_exists($employee, 'getFullNameAttribute')) {
            return $employee->full_name;
        }
        if ($employee) {
            return trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
        }

        return $user?->name ?? '—';
    }
}
