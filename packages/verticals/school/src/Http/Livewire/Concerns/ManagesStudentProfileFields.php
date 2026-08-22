<?php

namespace School\Http\Livewire\Concerns;

use Illuminate\Validation\Rule;
use School\Models\SchoolOption;
use School\Models\SchoolStudent;
use School\Support\SchoolOptionCatalog;
use School\Support\StudentCodeGenerator;

trait ManagesStudentProfileFields
{
    public string $studentCode = '';

    public ?string $nisu = null;

    public string $firstName = '';

    public string $lastName = '';

    public ?string $gender = null;

    public ?string $birthDate = null;

    public ?string $birthPlace = null;

    public ?string $address = null;

    public ?string $parentFullName = null;

    public ?string $parentPhone = null;

    public ?string $parentEmail = null;

    public ?string $parentRelationship = null;

    public ?string $emergencyContactName = null;

    public ?string $emergencyContactPhone = null;

    public ?string $previousSchool = null;

    public ?string $photoPath = null;

    public ?string $notes = null;

    public bool $isActive = true;

    public function updatedNisu(?string $value): void
    {
        $this->nisu = filled($value) ? trim($value) : null;
    }

    protected function resetStudentProfileFields(): void
    {
        $this->studentCode = StudentCodeGenerator::next();
        $this->nisu = null;
        $this->firstName = '';
        $this->lastName = '';
        $this->gender = null;
        $this->birthDate = null;
        $this->birthPlace = null;
        $this->address = null;
        $this->parentFullName = null;
        $this->parentPhone = null;
        $this->parentEmail = null;
        $this->parentRelationship = null;
        $this->emergencyContactName = null;
        $this->emergencyContactPhone = null;
        $this->previousSchool = null;
        $this->photoPath = null;
        $this->notes = null;
        $this->isActive = true;
    }

    protected function fillStudentProfileFields(SchoolStudent $student): void
    {
        $this->studentCode = (string) $student->student_code;
        $this->nisu = $student->nisu;
        $this->firstName = (string) $student->first_name;
        $this->lastName = (string) $student->last_name;
        $this->gender = $student->gender;
        $this->birthDate = $student->birth_date?->format('Y-m-d');
        $this->birthPlace = $student->birth_place;
        $this->address = $student->address;
        $this->parentFullName = $student->parent_full_name;
        $this->parentPhone = $student->parent_phone;
        $this->parentEmail = $student->parent_email;
        $this->parentRelationship = $student->parent_relationship;
        $this->emergencyContactName = $student->emergency_contact_name;
        $this->emergencyContactPhone = $student->emergency_contact_phone;
        $this->previousSchool = $student->previous_school;
        $this->photoPath = $student->photo_path;
        $this->notes = $student->notes;
        $this->isActive = (bool) $student->is_active;
    }

    protected function studentProfileRules(?int $ignoreId = null): array
    {
        $genderValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER)->pluck('value')->all();
        $relValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_PARENT_RELATIONSHIP)->pluck('value')->all();
        $this->nisu = filled($this->nisu) ? trim((string) $this->nisu) : null;

        $rules = [
            'studentCode' => [
                'required',
                'string',
                'max:120',
                Rule::unique(SchoolStudent::class, 'student_code')->ignore($ignoreId),
            ],
            'nisu' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique(SchoolStudent::class, 'nisu')->ignore($ignoreId),
            ],
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'max:40', Rule::in(array_merge([''], $genderValues))],
            'birthDate' => ['nullable', 'date'],
            'birthPlace' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'parentFullName' => ['nullable', 'string', 'max:255'],
            'parentPhone' => ['nullable', 'string', 'max:80'],
            'parentEmail' => ['nullable', 'email', 'max:255'],
            'parentRelationship' => ['nullable', 'string', 'max:40', Rule::in(array_merge([''], $relValues))],
            'emergencyContactName' => ['nullable', 'string', 'max:255'],
            'emergencyContactPhone' => ['nullable', 'string', 'max:80'],
            'previousSchool' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ];

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    protected function studentProfilePayload(?string $photoPath): array
    {
        $nullable = static fn (?string $value): ?string => filled($value) ? $value : null;

        return [
            'student_code' => $this->studentCode,
            'nisu' => $nullable($this->nisu),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'gender' => $nullable($this->gender),
            'birth_date' => $this->birthDate,
            'birth_place' => $nullable($this->birthPlace),
            'address' => $nullable($this->address),
            'parent_full_name' => $nullable($this->parentFullName),
            'parent_phone' => $nullable($this->parentPhone),
            'parent_email' => $nullable($this->parentEmail),
            'parent_relationship' => $nullable($this->parentRelationship),
            'emergency_contact_name' => $nullable($this->emergencyContactName),
            'emergency_contact_phone' => $nullable($this->emergencyContactPhone),
            'previous_school' => $nullable($this->previousSchool),
            'photo_path' => $photoPath,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
        ];
    }
}
