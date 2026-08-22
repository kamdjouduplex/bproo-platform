<?php

namespace School\Http\Livewire;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ManagesStudentDocuments;
use School\Http\Livewire\Concerns\ManagesStudentProfileFields;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolAttendanceRecord;
use School\Models\SchoolExamMark;
use School\Models\SchoolOption;
use School\Models\SchoolStudent;
use School\Models\SchoolStudentLedgerEntry;
use School\Support\SchoolDocumentCatalog;
use School\Support\SchoolOptionCatalog;
use School\Support\StudentLedgerService;
use School\Support\StudentPhotoStorage;
use School\Support\StudentProfileCompletion;

class SchoolStudentsDetail extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ManagesStudentDocuments;
    use ManagesStudentProfileFields;
    use ResolvesTenantCode;
    use WithFileUploads;

    public int $studentId;

    public string $mode = 'show';

    /** @var mixed */
    public $photoFile = null;

    public bool $removePhoto = false;

    /** @var mixed */
    public $profilePhotoFile = null;

    public ?string $croppedPhotoData = null;

    public string $dossierTab = 'scolarite';

    public function mount(int $id): void
    {
        $this->studentId = $id;
        SchoolStudent::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
        if ($this->mode === 'manage' && ! $this->canSchool('school_students.manage')) {
            $this->mode = 'show';
        }
        $tab = (string) request()->query('tab', '');
        if ($tab === 'pieces' && ! $this->canSchool('school_documents.view')) {
            $tab = 'scolarite';
        }
        if (in_array($tab, ['scolarite', 'finances', 'notes', 'presences', 'pieces'], true)) {
            $this->dossierTab = $tab;
        }
    }

    public function saveCroppedProfilePhoto(string $dataUrl): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        if (! str_starts_with($dataUrl, 'data:image/') || strlen($dataUrl) > 6_000_000) {
            notify()->error('Image invalide ou trop volumineuse.');

            return;
        }

        try {
            $student = $this->entity();
            $path = StudentPhotoStorage::storeFromDataUri($dataUrl, $student);
            $student->update(['photo_path' => $path]);
            $this->profilePhotoFile = null;
            $this->croppedPhotoData = null;
            notify()->success('Photo de profil cadrée et enregistrée.');
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function setCroppedPhoto(string $dataUrl): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        if (! str_starts_with($dataUrl, 'data:image/')) {
            notify()->error('Image invalide.');

            return;
        }

        if (strlen($dataUrl) > 6_000_000) {
            notify()->error('Photo trop volumineuse après cadrage.');

            return;
        }

        $this->croppedPhotoData = $dataUrl;
        $this->photoFile = null;
        $this->removePhoto = false;
        notify()->success('Cadrage prêt — enregistrez le formulaire pour confirmer.');
    }

    public function clearProfilePhoto(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        $student = $this->entity();
        StudentPhotoStorage::delete($student->photo_path);
        $student->update(['photo_path' => null]);
        $this->profilePhotoFile = null;
        $this->croppedPhotoData = null;
        notify()->success('Photo de profil retirée.');
    }

    public function edit(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }
        $student = $this->entity();
        $this->fillStudentProfileFields($student);
        $this->photoFile = null;
        $this->removePhoto = false;
        $this->openEditForm($student->id);
    }

    public function activate(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }
        $this->entity()->update(['is_active' => true]);
        notify()->success('Élève activé.');
    }

    public function deactivate(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }
        $this->entity()->update(['is_active' => false]);
        notify()->success('Élève désactivé.');
    }

    protected function resetFormFields(): void
    {
        $this->resetStudentProfileFields();
        $this->photoFile = null;
        $this->removePhoto = false;
        $this->croppedPhotoData = null;
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        $this->validate(array_merge($this->studentProfileRules($this->studentId), [
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]));

        $student = $this->entity();
        $photoPath = $student->photo_path;
        if ($this->removePhoto) {
            StudentPhotoStorage::delete($photoPath);
            $photoPath = null;
            $this->croppedPhotoData = null;
        }
        if (filled($this->croppedPhotoData)) {
            $photoPath = StudentPhotoStorage::storeFromDataUri($this->croppedPhotoData, $student);
            $this->croppedPhotoData = null;
        } elseif ($this->photoFile) {
            $photoPath = StudentPhotoStorage::store($this->photoFile, $student);
        }

        $student->update($this->studentProfilePayload($photoPath));
        notify()->success('Élève mis à jour.');
        $this->cancel();
    }

    protected function entity(): SchoolStudent
    {
        return SchoolStudent::query()->findOrFail($this->studentId);
    }

    public function render()
    {
        $student = $this->entity();
        $isManage = $this->mode === 'manage';
        $canManage = $this->canSchool('school_students.manage');
        $genders = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER);
        $relationships = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_PARENT_RELATIONSHIP);
        $genderLabel = $genders->firstWhere('value', $student->gender)?->label ?? $student->gender;
        $relationshipLabel = $relationships->firstWhere('value', $student->parent_relationship)?->label ?? $student->parent_relationship;
        $completion = StudentProfileCompletion::for($student);
        $enrollments = $student->enrollments()->with(['academicYear', 'schoolClass'])->orderByDesc('id')->get();
        $payments = $student->payments()->with('academicYear')->orderByDesc('id')->limit(50)->get();
        $ledger = SchoolStudentLedgerEntry::query()
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->limit(80)
            ->get();
        $ledgerService = app(StudentLedgerService::class);
        $balance = $ledgerService->balance((int) $student->id);
        $examMarks = SchoolExamMark::query()
            ->with(['exam.subject'])
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->limit(80)
            ->get();
        $attendance = collect();
        try {
            if (Schema::connection('tenant')->hasTable('school_attendance_records')) {
                $attendance = SchoolAttendanceRecord::query()
                    ->with(['schoolClass', 'academicYear', 'course.subject'])
                    ->where('student_id', $student->id)
                    ->orderByDesc('attendance_date')
                    ->limit(40)
                    ->get();
            }
        } catch (\Throwable) {
            $attendance = collect();
        }
        $tenantCode = $this->tenantCode();
        $photoUrl = $student->photoUrl($tenantCode);
        $currentEnrollment = $enrollments->firstWhere('status', 'enrolled') ?? $enrollments->first();
        $tuition = ['charged' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'status' => 'none'];
        if ($currentEnrollment?->academic_year_id) {
            $tuition = $ledgerService->tuitionSnapshot((int) $student->id, (int) $currentEnrollment->academic_year_id);
        }
        $enrollmentStatusLabels = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS)
            ->mapWithKeys(fn ($opt) => [(string) $opt->value => (string) $opt->label])
            ->all();
        $attendanceStats = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'total' => 0,
            'rate' => null,
        ];
        try {
            if (Schema::connection('tenant')->hasTable('school_attendance_records')) {
                $counts = SchoolAttendanceRecord::query()
                    ->where('student_id', $student->id)
                    ->selectRaw('status, COUNT(*) as c')
                    ->groupBy('status')
                    ->pluck('c', 'status');
                foreach (['present', 'absent', 'late', 'excused'] as $status) {
                    $attendanceStats[$status] = (int) ($counts[$status] ?? 0);
                }
                $attendanceStats['total'] = array_sum(array_intersect_key($attendanceStats, array_flip(['present', 'absent', 'late', 'excused'])));
                if ($attendanceStats['total'] > 0) {
                    $attendanceStats['rate'] = (int) round((($attendanceStats['present'] + $attendanceStats['late']) / $attendanceStats['total']) * 100);
                }
            }
        } catch (\Throwable) {
            // table may not exist on older tenants
        }
        $latestIdCard = null;
        try {
            $latestIdCard = $student->idCards()->orderByDesc('id')->first();
        } catch (\Throwable) {
            $latestIdCard = null;
        }
        $initials = mb_strtoupper(mb_substr((string) $student->first_name, 0, 1).mb_substr((string) $student->last_name, 0, 1));
        $age = $student->birth_date?->age;
        $studentDocuments = $this->documentsForStudent((int) $student->id);
        $documentTypes = SchoolDocumentCatalog::types();
        $documentChecklist = SchoolDocumentCatalog::checklist($studentDocuments);
        $canViewDocuments = $this->canSchool('school_documents.view');
        $canManageDocuments = $this->canSchool('school_documents.manage');

        return view('school::livewire.school.students.detail', compact(
            'student', 'isManage', 'canManage', 'genders', 'relationships', 'genderLabel', 'relationshipLabel',
            'completion', 'enrollments', 'payments', 'examMarks', 'attendance', 'ledger', 'balance', 'photoUrl',
            'currentEnrollment', 'tuition', 'enrollmentStatusLabels', 'attendanceStats', 'latestIdCard', 'initials', 'age',
            'studentDocuments', 'documentTypes', 'documentChecklist', 'canViewDocuments', 'canManageDocuments'
        ) + [
            'tenantCode' => $tenantCode,
            'canViewPayments' => $this->canSchool('school_payments.view'),
            'canViewEnrollments' => $this->canSchool('school_enrollments.view'),
            'canViewAttendance' => $this->canSchool('school_attendance.view'),
            'canViewIdCards' => $this->canSchool('school_id_cards.view'),
            'hasEnrollmentPrint' => Route::has('tenant.school.enrollments.print'),
            'hasIdCardPrint' => Route::has('tenant.school.id_cards.print'),
            'hasDocumentsIndex' => Route::has('tenant.school.documents.index'),
        ])->layout('layouts.app', [
            'title' => $student->full_name,
            'subtitle' => trim('Dossier élève'.($student->student_code ? ' · '.$student->student_code : '')),
        ]);
    }
}
