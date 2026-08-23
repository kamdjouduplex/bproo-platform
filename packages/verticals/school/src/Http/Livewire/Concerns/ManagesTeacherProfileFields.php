<?php

namespace School\Http\Livewire\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use InovCom\Users\Models\User;
use School\Models\SchoolOption;
use School\Models\SchoolSubject;
use School\Models\SchoolTeacher;
use School\Support\SchoolOptionCatalog;
use School\Support\TeacherCodeGenerator;

trait ManagesTeacherProfileFields
{
    public string $userId = '';

    public string $teacherCode = '';

    public string $firstName = '';

    public string $lastName = '';

    public ?string $gender = null;

    public string $phone = '';

    public ?string $email = null;

    public ?string $address = null;

    public ?string $educationLevel = null;

    public ?string $diplomaKind = null;

    public ?string $diplomaLabel = null;

    public ?string $studiesInProgress = null;

    public ?string $teachingSection = null;

    public ?string $scheduleNote = null;

    /** @var list<int|string> */
    public array $subjectIds = [];

    public bool $isActive = true;

    public bool $lockOnSave = false;

    public ?string $photoPath = null;

    public function updatedUserId(?string $value): void
    {
        $id = (int) $value;
        if ($id < 1) {
            return;
        }
        $this->applyUserPrefill($id);
    }

    protected function resetTeacherProfileFields(): void
    {
        $this->userId = '';
        $this->teacherCode = TeacherCodeGenerator::next();
        $this->firstName = '';
        $this->lastName = '';
        $this->gender = null;
        $this->phone = '';
        $this->email = null;
        $this->address = null;
        $this->educationLevel = null;
        $this->diplomaKind = null;
        $this->diplomaLabel = null;
        $this->studiesInProgress = null;
        $this->teachingSection = null;
        $this->scheduleNote = null;
        $this->subjectIds = [];
        $this->isActive = true;
        $this->lockOnSave = false;
        $this->photoPath = null;
    }

    protected function fillTeacherProfileFields(SchoolTeacher $teacher): void
    {
        $this->userId = $teacher->user_id ? (string) $teacher->user_id : '';
        $this->teacherCode = (string) ($teacher->teacher_code ?: TeacherCodeGenerator::next());
        $this->firstName = (string) ($teacher->first_name ?: '');
        $this->lastName = (string) ($teacher->last_name ?: '');
        $this->gender = $teacher->gender;
        $this->phone = (string) ($teacher->phone ?: '');
        $this->email = $teacher->email;
        $this->address = $teacher->address;
        $this->educationLevel = $teacher->education_level;
        $this->diplomaKind = $teacher->diploma_kind;
        $this->diplomaLabel = $teacher->diploma_label;
        $this->studiesInProgress = $teacher->studies_in_progress;
        $this->teachingSection = $teacher->teaching_section;
        $this->scheduleNote = $teacher->schedule_note;
        $this->subjectIds = $teacher->subjects()->pluck('school_subjects.id')->map(fn ($id) => (string) $id)->all();
        $this->isActive = (bool) $teacher->is_active;
        $this->lockOnSave = false;
        $this->photoPath = $teacher->photo_path;
    }

    protected function applyUserPrefill(int $userId): void
    {
        $user = User::on('tenant')->find($userId);
        if (! $user) {
            return;
        }

        $name = trim((string) $user->name);
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $this->firstName = $parts[0] ?? '';
        $this->lastName = $parts[1] ?? '';
        if ($this->lastName === '' && $this->firstName !== '') {
            $this->lastName = $this->firstName;
        }

        $phone = (string) ($user->phone ?? '');
        if ($phone !== '') {
            $this->phone = $phone;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));
        $this->email = ($email !== '' && ! str_ends_with($email, '@compte.local')) ? $email : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function teacherProfileRules(bool $locking, ?int $ignoreId = null): array
    {
        $includeCurrent = function (array $values, ?string $current): array {
            if ($current && ! in_array($current, $values, true)) {
                $values[] = $current;
            }

            return $values;
        };

        $genderValues = $includeCurrent(
            SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER)->pluck('value')->all(),
            $this->gender
        );
        $levelValues = $includeCurrent(
            SchoolOption::forGroup(SchoolOptionCatalog::GROUP_EDUCATION_LEVEL)->pluck('value')->all(),
            $this->educationLevel
        );
        $diplomaValues = $includeCurrent(
            SchoolOption::forGroup(SchoolOptionCatalog::GROUP_DIPLOMA_KIND)->pluck('value')->all(),
            $this->diplomaKind
        );
        $sectionValues = $includeCurrent(
            SchoolOption::forGroup(SchoolOptionCatalog::GROUP_TEACHING_SECTION)->pluck('value')->all(),
            $this->teachingSection
        );

        $req = $locking ? 'required' : 'nullable';

        return [
            'userId' => [
                $ignoreId ? 'nullable' : 'required',
                'integer',
                Rule::exists(User::class, 'id'),
                Rule::unique(SchoolTeacher::class, 'user_id')->ignore($ignoreId),
            ],
            'teacherCode' => ['required', 'string', 'max:40', Rule::unique(SchoolTeacher::class, 'teacher_code')->ignore($ignoreId)],
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => [$req, 'string', Rule::in($genderValues ?: ['M', 'F'])],
            'address' => [$req, 'string', 'max:500'],
            'educationLevel' => [$req, 'string', Rule::in($levelValues ?: ['other'])],
            'diplomaKind' => [$req, 'string', Rule::in($diplomaValues ?: ['diploma', 'licence', 'other'])],
            'diplomaLabel' => [
                Rule::requiredIf(fn () => $locking && $this->diplomaKind === 'other'),
                'nullable',
                'string',
                'max:255',
            ],
            'studiesInProgress' => ['nullable', 'string', 'max:255'],
            'teachingSection' => [$req, 'string', Rule::in($sectionValues ?: ['lycee'])],
            'scheduleNote' => [$req, 'string', 'max:255'],
            'subjectIds' => [$locking ? 'required' : 'nullable', 'array'],
            'subjectIds.*' => ['integer', Rule::exists(SchoolSubject::class, 'id')],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function teacherProfilePayload(?string $photoPath, bool $locking, ?int $validatedBy = null): array
    {
        $first = trim($this->firstName);
        $last = trim($this->lastName);
        $nullable = fn (?string $v) => filled($v) ? trim($v) : null;

        $payload = [
            'user_id' => filled($this->userId) ? (int) $this->userId : null,
            'teacher_code' => trim($this->teacherCode),
            'first_name' => $first,
            'last_name' => $last,
            'full_name' => trim($first.' '.$last),
            'gender' => $nullable($this->gender),
            'phone' => trim($this->phone),
            'email' => $nullable($this->email),
            'address' => $nullable($this->address),
            'education_level' => $nullable($this->educationLevel),
            'diploma_kind' => $nullable($this->diplomaKind),
            'diploma_label' => $nullable($this->diplomaLabel),
            'studies_in_progress' => $nullable($this->studiesInProgress),
            'teaching_section' => $nullable($this->teachingSection),
            'schedule_note' => $nullable($this->scheduleNote),
            'is_active' => $this->isActive,
        ];

        if ($photoPath !== null) {
            $payload['photo_path'] = $photoPath === '' ? null : $photoPath;
        }

        if ($locking) {
            $payload['profile_status'] = SchoolTeacher::STATUS_VALIDATED;
            $payload['validated_at'] = now();
            $payload['validated_by'] = $validatedBy;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function teacherFormCatalogs(?int $keepUserId = null): array
    {
        return [
            'genders' => SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER),
            'educationLevels' => SchoolOption::forGroup(SchoolOptionCatalog::GROUP_EDUCATION_LEVEL),
            'diplomaKinds' => SchoolOption::forGroup(SchoolOptionCatalog::GROUP_DIPLOMA_KIND),
            'teachingSections' => SchoolOption::forGroup(SchoolOptionCatalog::GROUP_TEACHING_SECTION),
            'subjects' => SchoolSubject::query()->where('is_active', true)->orderBy('name')->get(),
            'availableUsers' => $this->availableUsers($keepUserId),
        ];
    }

    protected function availableUsers(?int $keepUserId = null)
    {
        $taken = SchoolTeacher::query()
            ->whereNotNull('user_id')
            ->when($keepUserId, fn ($q) => $q->where('user_id', '!=', $keepUserId))
            ->pluck('user_id');

        $query = User::on('tenant')->orderBy('name');
        try {
            if (Schema::connection('tenant')->hasColumn('users', 'is_active')) {
                $query->where('is_active', true);
            }
        } catch (\Throwable) {
        }

        return $query
            ->when($taken->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $taken))
            ->get();
    }
}
