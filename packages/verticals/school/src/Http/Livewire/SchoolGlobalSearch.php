<?php

namespace School\Http\Livewire;

use Livewire\Component;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Models\SchoolStudent;

class SchoolGlobalSearch extends Component
{
    use ResolvesTenantCode;

    public string $q = '';

    public function selectStudent(int $id): void
    {
        $this->redirect(route('tenant.school.students.show', [
            'tenant' => $this->tenantCode(),
            'id' => $id,
        ]));
    }

    public function render()
    {
        $term = trim($this->q);
        $results = collect();

        if (mb_strlen($term) >= 2) {
            $like = '%'.mb_strtolower($term).'%';
            $students = SchoolStudent::query()
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
                ->limit(12)
                ->get();

            $classIds = SchoolClass::query()
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->pluck('id');

            if ($classIds->isNotEmpty()) {
                $fromClass = SchoolEnrollment::query()
                    ->with('student')
                    ->whereIn('class_id', $classIds)
                    ->whereHas('student')
                    ->orderByDesc('id')
                    ->limit(12)
                    ->get()
                    ->pluck('student')
                    ->filter();
                $students = $students->concat($fromClass)->unique('id')->take(12)->values();
            }

            $results = $students;
        }

        return view('school::livewire.school.global-search', [
            'results' => $results,
            'tenantCode' => $this->tenantCode(),
        ]);
    }
}
