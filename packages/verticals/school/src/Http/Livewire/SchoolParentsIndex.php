<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolStudent;

class SchoolParentsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        if (! $this->canSchool('school_students.view')) {
            abort(403, 'Permission refusée.');
        }

        $term = trim($this->search);
        $students = SchoolStudent::query()
            ->where(function ($q) {
                $q->whereNotNull('parent_full_name')
                    ->where('parent_full_name', '!=', '')
                    ->orWhere(function ($qq) {
                        $qq->whereNotNull('parent_phone')->where('parent_phone', '!=', '');
                    });
            })
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(COALESCE(parent_full_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_phone, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_email, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                });
            })
            ->orderBy('parent_full_name')
            ->orderBy('last_name')
            ->get();

        $groups = $students->groupBy(function (SchoolStudent $s) {
            $phone = trim((string) $s->parent_phone);
            $name = mb_strtolower(trim((string) $s->parent_full_name));
            if ($phone !== '') {
                return 'tel:'.$phone;
            }
            if ($name !== '') {
                return 'name:'.$name;
            }

            return 'unknown:'.$s->id;
        })->map(function ($children) {
            $first = $children->first();

            return [
                'name' => $first->parent_full_name ?: 'Parent non renseigné',
                'phone' => $first->parent_phone,
                'email' => $children->pluck('parent_email')->filter()->first(),
                'relationship' => $first->parent_relationship,
                'children' => $children->values(),
            ];
        })->values();

        $page = max(1, (int) $this->getPage());
        $perPage = 15;
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $groups->slice(($page - 1) * $perPage, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('school::livewire.school.parents.index', [
            'parents' => $paginator,
            'tenantCode' => $this->tenantCode(),
            'totalParents' => $groups->count(),
        ])->layout('layouts.app', [
            'title' => 'École — Parents / Tuteurs',
            'subtitle' => 'Répertoire des responsables liés aux profils élèves.',
        ]);
    }
}
