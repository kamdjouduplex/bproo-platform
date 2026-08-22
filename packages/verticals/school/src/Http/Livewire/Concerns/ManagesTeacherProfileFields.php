<?php

namespace School\Http\Livewire\Concerns;

use Illuminate\Validation\Rule;
use School\Models\SchoolOption;
use School\Models\SchoolSubject;
use School\Models\SchoolTeacher;
use School\Support\SchoolOptionCatalog;
use School\Support\TeacherCodeGenerator;

trait ManagesTeacherProfileFields
{
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

    public ?string $remunerationAmount = null;

    /** @var list<int|string> */
    public array $subjectIds = [];

    public bool $isActive = true;

    public bool $createAccess = true;

    public string $accessPassword = '';

    public bool $lockOnSave = false;

    public ?string $photoPath = null;

    protected function resetTeacherProfileFields(): void
    {
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
        $this->remunerationAmount = null;
        $this->subjectIds = [];
        $this->isActive = true;
        $this->createAccess = true;
        $this->accessPassword = '';
        $this->lockOnSave = false;
        $this->photoPath = null;
    }

    protected function fillTeacherProfileFields(SchoolTeacher $teacher): void
    {
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
        $this->remunerationAmount = $teacher->remuneration_amount !== null ? (string) $teacher->remuneration_amount : null;
        $this->subjectIds = $teacher->subjects()->pluck('school_subjects.id')->map(fn ($id) => (string) $id)->all();
        $this->isActive = (bool) $teacher->is_active;
        $this->createAccess = ! $teacher->user_id;
        $this->accessPassword = '';
        $this->lockOnSave = false;
        $this->photoPath = $teacher->photo_path;
    }

    /**
     * @return array<string, mixed>
     */
    protected function teacherProfileRules(bool $locking, ?int $ignoreId = null): array
    {
        $genderValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER)->pluck('value')->all();
        $levelValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_EDUCATION_LEVEL)->pluck('value')->all();
        $diplomaValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_DIPLOMA_KIND)->pluck('value')->all();
        $sectionValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_TEACHING_SECTION)->pluck('value')->all();

        $req = $locking ? 'required' : 'nullable';

        $rules = [
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
            'remunerationAmount' => [$req, 'numeric', 'min:0'],
            'subjectIds' => [$locking ? 'required' : 'nullable', 'array'],
            'subjectIds.*' => ['integer', Rule::exists(SchoolSubject::class, 'id')],
            'isActive' => ['boolean'],
            'accessPassword' => ['nullable', 'string', 'min:8'],
        ];

        return $rules;
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
            'remuneration_amount' => filled($this->remunerationAmount) ? $this->remunerationAmount : null,
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
    protected function teacherFormCatalogs(): array
    {
        return [
            'genders' => SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER),
            'educationLevels' => SchoolOption::forGroup(SchoolOptionCatalog::GROUP_EDUCATION_LEVEL),
            'diplomaKinds' => SchoolOption::forGroup(SchoolOptionCatalog::GROUP_DIPLOMA_KIND),
            'teachingSections' => SchoolOption::forGroup(SchoolOptionCatalog::GROUP_TEACHING_SECTION),
            'subjects' => SchoolSubject::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
