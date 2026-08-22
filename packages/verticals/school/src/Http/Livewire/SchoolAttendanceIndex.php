<?php

namespace School\Http\Livewire;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolAttendanceRecord;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Models\SchoolTimetableSlot;
use School\Support\SchoolTimetable;

class SchoolAttendanceIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;

    public string $yearId = '';

    public string $classId = '';

    public string $attendanceDate = '';

    public string $slotId = '';

    /** @var array<string, string> studentId => status */
    public array $marks = [];

    /** @var array<string, string> studentId => remark */
    public array $remarks = [];

    public function mount(): void
    {
        if (! $this->canSchool('school_attendance.view')) {
            abort(403, 'Permission refusée.');
        }

        $this->yearId = (string) (AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id')
            ?? '');
        $this->classId = (string) (request()->query('class') ?: SchoolClass::query()->where('is_active', true)->orderBy('name')->value('id') ?: '');
        $this->attendanceDate = (string) (request()->query('date') ?: now()->toDateString());
        $this->slotId = (string) request()->query('slot', '');
        $this->syncSessionAndMarks();
    }

    public function updatedYearId(): void
    {
        $this->slotId = '';
        $this->syncSessionAndMarks();
    }

    public function updatedClassId(): void
    {
        $this->slotId = '';
        $this->syncSessionAndMarks();
    }

    public function updatedAttendanceDate(): void
    {
        $this->slotId = '';
        $this->syncSessionAndMarks();
    }

    public function selectSession(string $slotId): void
    {
        $this->slotId = $slotId;
        $this->loadMarks();
    }

    public function shiftDate(int $days): void
    {
        $this->attendanceDate = Carbon::parse($this->attendanceDate ?: now())->addDays($days)->toDateString();
        $this->slotId = '';
        $this->syncSessionAndMarks();
    }

    public function goToday(): void
    {
        $this->attendanceDate = now()->toDateString();
        $this->slotId = '';
        $this->syncSessionAndMarks();
    }

    public function markAll(string $status): void
    {
        if (! in_array($status, array_keys(SchoolAttendanceRecord::statuses()), true)) {
            return;
        }
        foreach (array_keys($this->marks) as $id) {
            $this->marks[$id] = $status;
        }
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_attendance.manage')) {
            return;
        }

        if ($this->yearId === '' || $this->classId === '' || $this->attendanceDate === '') {
            notify()->error('Choisissez l’année, la classe et la date.');

            return;
        }

        if (! $this->tableReady()) {
            notify()->error('Le module présences n’est pas encore migré. Exécutez tenant:migrate.');

            return;
        }

        $slot = $this->selectedSlot();
        $userId = Auth::guard('tenant')->id();
        $saved = 0;

        foreach ($this->marks as $studentId => $status) {
            if (! in_array($status, array_keys(SchoolAttendanceRecord::statuses()), true)) {
                continue;
            }

            $payload = [
                'academic_year_id' => (int) $this->yearId,
                'class_id' => (int) $this->classId,
                'status' => $status,
                'remark' => filled($this->remarks[$studentId] ?? null) ? $this->remarks[$studentId] : null,
                'recorded_by' => $userId,
                'course_id' => $slot?->course_id,
                'timetable_slot_id' => $slot?->id,
            ];

            $query = SchoolAttendanceRecord::query()
                ->where('student_id', (int) $studentId)
                ->whereDate('attendance_date', $this->attendanceDate);

            if ($slot) {
                $query->where('timetable_slot_id', $slot->id);
            } else {
                $query->whereNull('timetable_slot_id')->whereNull('course_id');
            }

            $record = $query->first();
            if ($record) {
                $record->update($payload);
            } else {
                SchoolAttendanceRecord::query()->create($payload + [
                    'student_id' => (int) $studentId,
                    'attendance_date' => $this->attendanceDate,
                ]);
            }
            $saved++;
        }

        $label = $slot?->course?->subject?->name ?? 'appel général';
        notify()->success($saved > 0 ? "Appel enregistré — {$label} ({$saved} élève(s))." : 'Aucun élève à enregistrer.');
    }

    protected function syncSessionAndMarks(): void
    {
        $sessions = $this->sessionsForDate();
        if ($this->slotId !== '' && $sessions->contains(fn ($s) => (string) $s->id === $this->slotId)) {
            $this->loadMarks();

            return;
        }

        $this->slotId = $sessions->isNotEmpty() ? (string) $sessions->first()->id : '';
        $this->loadMarks();
    }

    protected function loadMarks(): void
    {
        $this->marks = [];
        $this->remarks = [];

        foreach ($this->roster() as $enrollment) {
            $id = (string) $enrollment->student_id;
            $this->marks[$id] = SchoolAttendanceRecord::STATUS_PRESENT;
            $this->remarks[$id] = '';
        }

        if (! $this->tableReady() || $this->classId === '' || $this->attendanceDate === '') {
            return;
        }

        $query = SchoolAttendanceRecord::query()
            ->where('class_id', (int) $this->classId)
            ->whereDate('attendance_date', $this->attendanceDate);

        if ($this->slotId !== '') {
            $query->where('timetable_slot_id', (int) $this->slotId);
        } else {
            $query->whereNull('course_id');
        }

        foreach ($query->get() as $row) {
            $this->marks[(string) $row->student_id] = $row->status;
            $this->remarks[(string) $row->student_id] = (string) ($row->remark ?? '');
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolTimetableSlot>
     */
    protected function sessionsForDate()
    {
        if (! $this->timetableReady() || $this->yearId === '' || $this->classId === '' || $this->attendanceDate === '') {
            return collect();
        }

        $weekday = SchoolTimetable::weekdayFromDate($this->attendanceDate);
        if ($weekday < 1 || $weekday > 6) {
            return collect();
        }

        return SchoolTimetableSlot::query()
            ->with(['course.subject', 'course.teacher'])
            ->where('weekday', $weekday)
            ->whereHas('course', fn ($q) => $q
                ->where('academic_year_id', (int) $this->yearId)
                ->where('class_id', (int) $this->classId)
                ->where('is_active', true))
            ->orderBy('start_time')
            ->get();
    }

    protected function selectedSlot(): ?SchoolTimetableSlot
    {
        if ($this->slotId === '') {
            return null;
        }

        return $this->sessionsForDate()->firstWhere('id', (int) $this->slotId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolEnrollment>
     */
    protected function roster()
    {
        if ($this->yearId === '' || $this->classId === '') {
            return collect();
        }

        return SchoolEnrollment::query()
            ->with('student')
            ->where('academic_year_id', (int) $this->yearId)
            ->where('class_id', (int) $this->classId)
            ->where('status', 'enrolled')
            ->whereHas('student', fn ($q) => $q->where('is_active', true))
            ->get()
            ->sortBy(fn ($e) => mb_strtolower($e->student?->last_name.' '.$e->student?->first_name))
            ->values();
    }

    protected function tableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_attendance_records');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function timetableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_timetable_slots');
        } catch (\Throwable) {
            return false;
        }
    }

    public function printUrl(): string
    {
        return route('tenant.school.attendance.print', array_filter([
            'tenant' => $this->tenantCode(),
            'year' => $this->yearId,
            'class' => $this->classId,
            'date' => $this->attendanceDate,
            'slot' => $this->slotId,
        ], fn ($v) => $v !== '' && $v !== null));
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $roster = $this->roster();
        $sessions = $this->sessionsForDate();
        $selectedSlot = $this->selectedSlot();
        $weekday = $this->attendanceDate !== '' ? SchoolTimetable::weekdayFromDate($this->attendanceDate) : 0;
        $counts = [
            'present' => collect($this->marks)->filter(fn ($s) => $s === 'present')->count(),
            'absent' => collect($this->marks)->filter(fn ($s) => $s === 'absent')->count(),
            'late' => collect($this->marks)->filter(fn ($s) => $s === 'late')->count(),
            'excused' => collect($this->marks)->filter(fn ($s) => $s === 'excused')->count(),
            'total' => $roster->count(),
        ];

        $sessionTitle = $selectedSlot
            ? $selectedSlot->sessionLabel()
            : ($sessions->isEmpty() ? 'Appel général de la classe' : 'Choisissez une séance');

        return view('school::livewire.school.attendance.index', [
            'years' => $years,
            'classes' => $classes,
            'roster' => $roster,
            'sessions' => $sessions,
            'selectedSlot' => $selectedSlot,
            'sessionTitle' => $sessionTitle,
            'weekdayLabel' => SchoolTimetable::weekdayLabel($weekday),
            'isSunday' => $weekday === 7,
            'counts' => $counts,
            'statuses' => SchoolAttendanceRecord::statuses(),
            'canManage' => $this->canSchool('school_attendance.manage'),
            'tenantCode' => $this->tenantCode(),
            'printUrl' => $this->printUrl(),
            'timetableUrl' => Route::has('tenant.school.timetable.index')
                ? route('tenant.school.timetable.index', array_filter(['tenant' => $this->tenantCode(), 'class' => $this->classId]))
                : null,
        ])->layout('layouts.app', [
            'title' => 'École — Présences',
            'subtitle' => 'Appel par cours, selon l’emploi du temps du jour.',
        ]);
    }
}
