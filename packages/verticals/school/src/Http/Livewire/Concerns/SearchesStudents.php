<?php

namespace School\Http\Livewire\Concerns;

use School\Models\SchoolStudent;

trait SearchesStudents
{
    public string $studentSearch = '';

    public ?int $studentId = null;

    public string $selectedStudentLabel = '';

    public function updatedStudentSearch(): void
    {
        // Livewire will re-render; results computed in render helpers.
    }

    public function selectStudent(int $id): void
    {
        $student = SchoolStudent::query()->find($id);
        if (! $student) {
            return;
        }

        $this->studentId = (int) $student->id;
        $this->selectedStudentLabel = trim($student->student_code.' — '.$student->first_name.' '.$student->last_name);
        $this->studentSearch = '';
        $this->resetErrorBag('studentId');
    }

    public function clearStudent(): void
    {
        $this->studentId = null;
        $this->selectedStudentLabel = '';
        $this->studentSearch = '';
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolStudent>
     */
    protected function studentSearchResults(int $limit = 12)
    {
        $term = trim($this->studentSearch);
        if ($term === '' || mb_strlen($term) < 1) {
            return collect();
        }

        $like = '%'.mb_strtolower($term).'%';

        return SchoolStudent::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(student_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(nisu, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) LIKE ?", [$like])
                    ->orWhereRaw('LOWER(COALESCE(parent_full_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(parent_phone, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(parent_email, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('last_name')
            ->limit($limit)
            ->get();
    }
}
