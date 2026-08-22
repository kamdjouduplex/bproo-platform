<?php

namespace School\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use School\Models\SchoolAttendanceRecord;
use School\Models\SchoolEnrollment;
use School\Models\SchoolExamMark;
use School\Models\SchoolFeeStructure;
use School\Models\SchoolOption;
use School\Models\SchoolPayment;
use School\Models\SchoolStudent;
use School\Models\SchoolTeacher;

final class SchoolReportBuilder
{
    public function __construct(
        public readonly string $type,
        public readonly ?int $yearId = null,
        public readonly ?int $classId = null,
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
        public readonly ?int $teacherId = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'enrollments' => 'Liste des inscrits',
            'students' => 'Fichier élèves',
            'parents' => 'Répertoire parents',
            'effectifs' => 'Effectifs par classe',
            'teachers' => 'Enseignants',
            'debtors' => 'Débiteurs',
            'collection' => 'Recouvrement par classe',
            'payments' => 'Paiements',
            'fees' => 'Structures de frais',
            'attendance' => 'Présences (détail)',
            'absentees' => 'Absences et retards',
            'exams' => 'Notes d’examens',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'Scolarité' => ['enrollments', 'students', 'parents', 'effectifs', 'teachers'],
            'Finances' => ['debtors', 'collection', 'payments', 'fees'],
            'Présences' => ['attendance', 'absentees'],
            'Examens' => ['exams'],
        ];
    }

    public static function usesYear(string $type): bool
    {
        return true;
    }

    public static function usesClass(string $type): bool
    {
        return in_array($type, [
            'enrollments', 'students', 'effectifs', 'debtors', 'collection',
            'payments', 'fees', 'attendance', 'absentees', 'exams',
        ], true);
    }

    public static function usesDates(string $type): bool
    {
        return in_array($type, ['payments', 'attendance', 'absentees'], true);
    }

    public static function usesTeacher(string $type): bool
    {
        return $type === 'exams';
    }

    /**
     * @return array{title:string, summary:string, headers:list<string>, rows:list<list<string>>, kpis:list<array{label:string,value:string}>, totals:?list<string>}
     */
    public function build(): array
    {
        $type = array_key_exists($this->type, self::types()) ? $this->type : 'enrollments';

        return match ($type) {
            'students' => $this->students(),
            'parents' => $this->parents(),
            'effectifs' => $this->effectifs(),
            'teachers' => $this->teachers(),
            'debtors' => $this->debtors(),
            'collection' => $this->collection(),
            'payments' => $this->payments(),
            'fees' => $this->fees(),
            'attendance' => $this->attendance(),
            'absentees' => $this->absentees(),
            'exams' => $this->exams(),
            default => $this->enrollments(),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<array{label:string,value:string}>  $kpis
     * @param  list<string>|null  $totals
     * @return array{title:string, summary:string, headers:list<string>, rows:list<list<string>>, kpis:list<array{label:string,value:string}>, totals:?list<string>}
     */
    protected function pack(string $title, string $summary, array $headers, array $rows, array $kpis = [], ?array $totals = null): array
    {
        return [
            'title' => $title,
            'summary' => $summary,
            'headers' => $headers,
            'rows' => $rows,
            'kpis' => $kpis,
            'totals' => $totals,
        ];
    }

    protected function money(float|int $n): string
    {
        return number_format((float) $n, 0, ',', ' ');
    }

    /**
     * @return array<string, string>
     */
    protected function optionLabels(string $group): array
    {
        try {
            return SchoolOption::forGroup($group)
                ->mapWithKeys(fn ($opt) => [(string) $opt->value => (string) $opt->label])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function genderLabel(?string $value): string
    {
        if (! filled($value)) {
            return '—';
        }
        $labels = $this->optionLabels(SchoolOptionCatalog::GROUP_GENDER);

        return $labels[$value] ?? $value;
    }

    protected function enrollmentStatusLabel(?string $value): string
    {
        if (! filled($value)) {
            return '—';
        }
        $labels = $this->optionLabels(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS);

        return $labels[$value] ?? $value;
    }

    protected function enrollments()
    {
        $items = SchoolEnrollment::query()
            ->with(['student', 'academicYear', 'schoolClass'])
            ->when($this->yearId, fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->classId, fn ($q) => $q->where('class_id', $this->classId))
            ->orderBy('class_id')
            ->orderBy('id')
            ->get();

        $rows = $items->map(fn ($e) => [
            (string) ($e->academicYear?->name ?? '—'),
            (string) $e->student?->student_code,
            (string) $e->student?->full_name,
            $this->genderLabel($e->student?->gender),
            (string) ($e->schoolClass?->name ?? '—'),
            (string) ($e->section ?: '—'),
            (string) ($e->student?->parent_full_name ?: '—'),
            (string) ($e->student?->parent_phone ?: '—'),
            $this->enrollmentStatusLabel($e->status),
        ])->all();

        return $this->pack(
            'Liste des inscrits',
            count($rows).' inscription(s)',
            ['Année', 'Matricule', 'Élève', 'Genre', 'Classe', 'Section', 'Parent', 'Téléphone', 'Statut'],
            $rows,
            [
                ['label' => 'Inscrits', 'value' => (string) $items->where('status', 'enrolled')->count()],
                ['label' => 'Total lignes', 'value' => (string) count($rows)],
            ]
        );
    }

    protected function students()
    {
        $enrollQuery = SchoolEnrollment::query()->with(['schoolClass', 'academicYear']);
        if ($this->yearId) {
            $enrollQuery->where('academic_year_id', $this->yearId);
        }
        if ($this->classId) {
            $enrollQuery->where('class_id', $this->classId);
        }
        $enrollments = $enrollQuery->orderByDesc('id')->get()->unique('student_id')->keyBy('student_id');

        $query = SchoolStudent::query()->orderBy('last_name')->orderBy('first_name');
        if ($this->yearId || $this->classId) {
            $query->whereIn('id', $enrollments->keys()->all() ?: [0]);
        }
        $students = $query->get();

        $rows = $students->map(function ($s) use ($enrollments) {
            $e = $enrollments->get($s->id);

            return [
                (string) ($s->nisu ?: '—'),
                (string) $s->student_code,
                (string) $s->full_name,
                $this->genderLabel($s->gender),
                (string) ($s->birth_date?->format('d/m/Y') ?: '—'),
                (string) ($e?->schoolClass?->name ?? '—'),
                (string) ($s->address ?: '—'),
                (string) ($s->parent_full_name ?: '—'),
                (string) ($s->parent_phone ?: '—'),
                (string) ($s->emergency_contact_phone ?: '—'),
                $s->is_active ? 'Actif' : 'Inactif',
            ];
        })->all();

        $active = $students->where('is_active', true)->count();

        return $this->pack(
            'Fichier élèves',
            count($rows).' profil(s)',
            ['NISU', 'Matricule', 'Élève', 'Genre', 'Naissance', 'Classe', 'Adresse', 'Parent', 'Tél. parent', 'Urgence', 'Statut'],
            $rows,
            [
                ['label' => 'Élèves', 'value' => (string) count($rows)],
                ['label' => 'Actifs', 'value' => (string) $active],
                ['label' => 'Inactifs', 'value' => (string) (count($rows) - $active)],
            ]
        );
    }

    protected function parents()
    {
        $students = SchoolStudent::query()
            ->where(function ($q) {
                $q->whereNotNull('parent_full_name')->where('parent_full_name', '!=', '')
                    ->orWhere(function ($qq) {
                        $qq->whereNotNull('parent_phone')->where('parent_phone', '!=', '');
                    });
            })
            ->when($this->yearId || $this->classId, function ($q) {
                $q->whereHas('enrollments', function ($eq) {
                    $eq->when($this->yearId, fn ($y) => $y->where('academic_year_id', $this->yearId))
                        ->when($this->classId, fn ($c) => $c->where('class_id', $this->classId));
                });
            })
            ->orderBy('parent_full_name')
            ->get();

        $groups = $students->groupBy(function (SchoolStudent $s) {
            $phone = trim((string) $s->parent_phone);
            $name = mb_strtolower(trim((string) $s->parent_full_name));
            if ($phone !== '') {
                return 'tel:'.$phone;
            }

            return $name !== '' ? 'name:'.$name : 'id:'.$s->id;
        });

        $rows = $groups->map(function ($children) {
            $first = $children->first();
            $kids = $children->map(fn ($c) => trim($c->student_code.' '.$c->full_name))->implode(' · ');

            return [
                (string) ($first->parent_full_name ?: '—'),
                (string) ($first->parent_phone ?: '—'),
                (string) ($children->pluck('parent_email')->filter()->first() ?: '—'),
                (string) $children->count(),
                $kids,
            ];
        })->values()->all();

        return $this->pack(
            'Répertoire parents',
            count($rows).' responsable(s)',
            ['Parent / tuteur', 'Téléphone', 'Email', 'Enfants', 'Élèves'],
            $rows,
            [
                ['label' => 'Parents', 'value' => (string) count($rows)],
                ['label' => 'Élèves concernés', 'value' => (string) $students->count()],
            ]
        );
    }

    protected function effectifs()
    {
        $items = SchoolEnrollment::query()
            ->with(['student', 'schoolClass'])
            ->when($this->yearId, fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->classId, fn ($q) => $q->where('class_id', $this->classId))
            ->where('status', 'enrolled')
            ->get();

        $grouped = $items->groupBy(fn ($e) => ($e->class_id ?: 0).'|'.($e->section ?: ''));
        $rows = [];
        $totalM = 0;
        $totalF = 0;
        $totalO = 0;
        foreach ($grouped as $group) {
            $first = $group->first();
            $m = $group->filter(fn ($e) => $e->student?->gender === 'M')->count();
            $f = $group->filter(fn ($e) => $e->student?->gender === 'F')->count();
            $o = $group->count() - $m - $f;
            $totalM += $m;
            $totalF += $f;
            $totalO += $o;
            $rows[] = [
                (string) ($first->schoolClass?->name ?? '—'),
                (string) ($first->section ?: ($first->schoolClass?->section ?? '—')),
                (string) $m,
                (string) $f,
                (string) $o,
                (string) $group->count(),
            ];
        }
        usort($rows, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return $this->pack(
            'Effectifs par classe',
            $items->count().' élève(s) inscrits',
            ['Classe', 'Section', 'Garçons', 'Filles', 'Autres', 'Total'],
            $rows,
            [
                ['label' => 'Garçons', 'value' => (string) $totalM],
                ['label' => 'Filles', 'value' => (string) $totalF],
                ['label' => 'Effectif', 'value' => (string) $items->count()],
            ],
            ['', 'Total', (string) $totalM, (string) $totalF, (string) $totalO, (string) $items->count()]
        );
    }

    protected function teachers()
    {
        $teachers = SchoolTeacher::query()->orderBy('full_name')->get();
        $examCounts = collect();
        try {
            if (Schema::connection('tenant')->hasTable('school_exams')) {
                $examCounts = \School\Models\SchoolExam::query()
                    ->when($this->yearId, fn ($q) => $q->where('academic_year_id', $this->yearId))
                    ->selectRaw('teacher_id, COUNT(*) as c')
                    ->groupBy('teacher_id')
                    ->pluck('c', 'teacher_id');
            }
        } catch (\Throwable) {
            $examCounts = collect();
        }

        $rows = $teachers->map(fn ($t) => [
            (string) $t->id,
            (string) $t->full_name,
            (string) ($t->phone ?: '—'),
            (string) ($t->email ?: '—'),
            (string) ($t->address ?: '—'),
            $t->is_active ? 'Actif' : 'Inactif',
            (string) ($examCounts[$t->id] ?? 0),
        ])->all();

        return $this->pack(
            'Enseignants',
            count($rows).' enseignant(s)',
            ['N°', 'Nom', 'Téléphone', 'Email', 'Adresse', 'Statut', 'Examens'],
            $rows,
            [
                ['label' => 'Enseignants', 'value' => (string) count($rows)],
                ['label' => 'Actifs', 'value' => (string) $teachers->where('is_active', true)->count()],
            ]
        );
    }

    protected function debtors()
    {
        $ledger = app(StudentLedgerService::class);
        $enrollments = SchoolEnrollment::query()
            ->with(['student', 'schoolClass'])
            ->when($this->yearId, fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->classId, fn ($q) => $q->where('class_id', $this->classId))
            ->where('status', 'enrolled')
            ->get();

        $rows = [];
        $dueTotal = 0.0;
        $chargedTotal = 0.0;
        $paidTotal = 0.0;
        foreach ($enrollments as $e) {
            if (! $e->academic_year_id || ! $e->student) {
                continue;
            }
            $snap = $ledger->tuitionSnapshot((int) $e->student_id, (int) $e->academic_year_id);
            if ($snap['due'] <= 0.009) {
                continue;
            }
            $dueTotal += $snap['due'];
            $chargedTotal += $snap['charged'];
            $paidTotal += $snap['paid'];
            $rows[] = [
                (string) $e->student->student_code,
                (string) $e->student->full_name,
                (string) ($e->schoolClass?->name ?? '—'),
                (string) ($e->student->parent_full_name ?: '—'),
                (string) ($e->student->parent_phone ?: '—'),
                $this->money($snap['charged']),
                $this->money($snap['paid']),
                $this->money($snap['due']),
            ];
        }

        return $this->pack(
            'Débiteurs — soldes scolarité',
            count($rows).' élève(s) · reste '.$this->money($dueTotal),
            ['Matricule', 'Élève', 'Classe', 'Parent', 'Téléphone', 'Frais', 'Payé', 'Reste'],
            $rows,
            [
                ['label' => 'Débiteurs', 'value' => (string) count($rows)],
                ['label' => 'Déjà perçu', 'value' => $this->money($paidTotal)],
                ['label' => 'Reste à recouvrer', 'value' => $this->money($dueTotal)],
            ],
            ['', '', '', '', 'Total', $this->money($chargedTotal), $this->money($paidTotal), $this->money($dueTotal)]
        );
    }

    protected function collection()
    {
        $ledger = app(StudentLedgerService::class);
        $enrollments = SchoolEnrollment::query()
            ->with(['student', 'schoolClass'])
            ->when($this->yearId, fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->classId, fn ($q) => $q->where('class_id', $this->classId))
            ->where('status', 'enrolled')
            ->get();

        $byClass = [];
        foreach ($enrollments as $e) {
            if (! $e->academic_year_id || ! $e->student) {
                continue;
            }
            $snap = $ledger->tuitionSnapshot((int) $e->student_id, (int) $e->academic_year_id);
            $key = (string) ($e->class_id ?: 0);
            if (! isset($byClass[$key])) {
                $byClass[$key] = [
                    'name' => $e->schoolClass?->name ?? '—',
                    'headcount' => 0,
                    'charged' => 0.0,
                    'paid' => 0.0,
                    'due' => 0.0,
                    'debtors' => 0,
                ];
            }
            $byClass[$key]['headcount']++;
            $byClass[$key]['charged'] += $snap['charged'];
            $byClass[$key]['paid'] += $snap['paid'];
            $byClass[$key]['due'] += $snap['due'];
            if ($snap['due'] > 0.009) {
                $byClass[$key]['debtors']++;
            }
        }

        $rows = [];
        $sumCharged = 0.0;
        $sumPaid = 0.0;
        $sumDue = 0.0;
        $sumHead = 0;
        foreach ($byClass as $row) {
            $sumCharged += $row['charged'];
            $sumPaid += $row['paid'];
            $sumDue += $row['due'];
            $sumHead += $row['headcount'];
            $rate = $row['charged'] > 0 ? (int) round(($row['paid'] / $row['charged']) * 100) : 0;
            $rows[] = [
                (string) $row['name'],
                (string) $row['headcount'],
                (string) $row['debtors'],
                $this->money($row['charged']),
                $this->money($row['paid']),
                $this->money($row['due']),
                $rate.' %',
            ];
        }
        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);

        return $this->pack(
            'Recouvrement par classe',
            $sumHead.' élève(s) · perçu '.$this->money($sumPaid).' · reste '.$this->money($sumDue),
            ['Classe', 'Effectif', 'Débiteurs', 'Imputé', 'Perçu', 'Reste', 'Taux'],
            $rows,
            [
                ['label' => 'Imputé', 'value' => $this->money($sumCharged)],
                ['label' => 'Déjà perçu', 'value' => $this->money($sumPaid)],
                ['label' => 'Reste à recouvrer', 'value' => $this->money($sumDue)],
            ],
            ['Total', (string) $sumHead, '', $this->money($sumCharged), $this->money($sumPaid), $this->money($sumDue), '']
        );
    }

    protected function payments()
    {
        $items = SchoolPayment::query()
            ->with(['student', 'academicYear'])
            ->when($this->yearId, fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->classId, function ($q) {
                $q->whereHas('student.enrollments', function ($eq) {
                    $eq->when($this->yearId, fn ($y) => $y->where('academic_year_id', $this->yearId))
                        ->where('class_id', $this->classId);
                });
            })
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from->toDateString()))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to->toDateString()))
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        $verified = (float) $items->where('status', 'verified')->sum('amount');
        $pending = (float) $items->where('status', 'pending')->sum('amount');

        $rows = $items->map(fn ($p) => [
            (string) ($p->paid_at?->format('d/m/Y') ?: $p->created_at?->format('d/m/Y')),
            (string) $p->student?->student_code,
            (string) $p->student?->full_name,
            $p->typeLabel(),
            $this->money((float) $p->amount).' '.$p->currency_code,
            $p->statusLabel(),
            (string) ($p->reference ?: '—'),
        ])->all();

        return $this->pack(
            'Paiements',
            count($rows).' paiement(s) · validés '.$this->money($verified),
            ['Date', 'Matricule', 'Élève', 'Type', 'Montant', 'Statut', 'Référence'],
            $rows,
            [
                ['label' => 'Paiements', 'value' => (string) count($rows)],
                ['label' => 'Validés', 'value' => $this->money($verified)],
                ['label' => 'En attente', 'value' => $this->money($pending)],
            ]
        );
    }

    protected function fees()
    {
        $items = SchoolFeeStructure::query()
            ->with(['academicYear', 'schoolClass'])
            ->when($this->yearId, fn ($q) => $q->where(function ($inner) {
                $inner->whereNull('academic_year_id')->orWhere('academic_year_id', $this->yearId);
            }))
            ->when($this->classId, fn ($q) => $q->where(function ($inner) {
                $inner->whereNull('class_id')->orWhere('class_id', $this->classId);
            }))
            ->orderBy('name')
            ->get();

        $rows = $items->map(fn ($f) => [
            (string) $f->name,
            (string) ($f->academicYear?->name ?? 'Toutes années'),
            (string) ($f->schoolClass?->name ?? 'Toutes classes'),
            $this->money((float) $f->amount).' '.($f->currency_code ?: ''),
            $f->is_active ? 'Actif' : 'Inactif',
            (string) ($f->description ?: '—'),
        ])->all();

        return $this->pack(
            'Structures de frais',
            count($rows).' barème(s)',
            ['Libellé', 'Année', 'Classe', 'Montant', 'Statut', 'Description'],
            $rows,
            [
                ['label' => 'Barèmes', 'value' => (string) count($rows)],
                ['label' => 'Actifs', 'value' => (string) $items->where('is_active', true)->count()],
            ]
        );
    }

    protected function attendanceEmpty(): array
    {
        return $this->pack(
            'Présences',
            'Aucune donnée',
            ['Date', 'Classe', 'Cours', 'Matricule', 'Élève', 'Statut', 'Remarque'],
            []
        );
    }

    protected function attendanceTableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_attendance_records');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function attendance()
    {
        if (! $this->attendanceTableReady()) {
            return $this->attendanceEmpty();
        }

        $items = SchoolAttendanceRecord::query()
            ->with(['student', 'schoolClass', 'course.subject'])
            ->when($this->yearId, fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->classId, fn ($q) => $q->where('class_id', $this->classId))
            ->when($this->from, fn ($q) => $q->whereDate('attendance_date', '>=', $this->from->toDateString()))
            ->when($this->to, fn ($q) => $q->whereDate('attendance_date', '<=', $this->to->toDateString()))
            ->orderByDesc('attendance_date')
            ->limit(1000)
            ->get();

        $rows = $items->map(fn ($r) => [
            (string) $r->attendance_date?->format('d/m/Y'),
            (string) ($r->schoolClass?->name ?? '—'),
            (string) ($r->course?->subject?->name ?? 'Appel général'),
            (string) $r->student?->student_code,
            (string) $r->student?->full_name,
            (string) (SchoolAttendanceRecord::statuses()[$r->status] ?? $r->status),
            (string) ($r->remark ?: '—'),
        ])->all();

        return $this->pack(
            'Présences (détail)',
            count($rows).' ligne(s) (1 000 max.)',
            ['Date', 'Classe', 'Cours', 'Matricule', 'Élève', 'Statut', 'Remarque'],
            $rows,
            [
                ['label' => 'Présents', 'value' => (string) $items->where('status', 'present')->count()],
                ['label' => 'Absents', 'value' => (string) $items->where('status', 'absent')->count()],
                ['label' => 'Retards', 'value' => (string) $items->where('status', 'late')->count()],
            ]
        );
    }

    protected function absentees()
    {
        if (! $this->attendanceTableReady()) {
            return $this->pack(
                'Absences et retards',
                'Aucune donnée',
                ['Matricule', 'Élève', 'Classe', 'Absences', 'Retards', 'Excusés', 'Présents'],
                []
            );
        }

        $items = SchoolAttendanceRecord::query()
            ->with(['student', 'schoolClass'])
            ->when($this->yearId, fn ($q) => $q->where('academic_year_id', $this->yearId))
            ->when($this->classId, fn ($q) => $q->where('class_id', $this->classId))
            ->when($this->from, fn ($q) => $q->whereDate('attendance_date', '>=', $this->from->toDateString()))
            ->when($this->to, fn ($q) => $q->whereDate('attendance_date', '<=', $this->to->toDateString()))
            ->get();

        $grouped = $items->groupBy('student_id');
        $rows = [];
        foreach ($grouped as $group) {
            $absent = $group->where('status', 'absent')->count();
            $late = $group->where('status', 'late')->count();
            if ($absent === 0 && $late === 0) {
                continue;
            }
            $first = $group->first();
            $rows[] = [
                (string) $first->student?->student_code,
                (string) $first->student?->full_name,
                (string) ($first->schoolClass?->name ?? '—'),
                (string) $absent,
                (string) $late,
                (string) $group->where('status', 'excused')->count(),
                (string) $group->where('status', 'present')->count(),
            ];
        }
        usort($rows, fn ($a, $b) => ((int) $b[3] <=> (int) $a[3]) ?: ((int) $b[4] <=> (int) $a[4]));

        return $this->pack(
            'Absences et retards',
            count($rows).' élève(s) concernés',
            ['Matricule', 'Élève', 'Classe', 'Absences', 'Retards', 'Excusés', 'Présents'],
            $rows,
            [
                ['label' => 'Élèves concernés', 'value' => (string) count($rows)],
                ['label' => 'Absences', 'value' => (string) $items->where('status', 'absent')->count()],
                ['label' => 'Retards', 'value' => (string) $items->where('status', 'late')->count()],
            ]
        );
    }

    protected function exams()
    {
        $empty = $this->pack(
            'Notes d’examens',
            'Aucune donnée',
            ['Date', 'Examen', 'Matière', 'Classe', 'Enseignant', 'Matricule', 'Élève', 'Note', 'Validé'],
            []
        );

        try {
            if (! Schema::connection('tenant')->hasTable('school_exam_marks')) {
                return $empty;
            }
        } catch (\Throwable) {
            return $empty;
        }

        $items = SchoolExamMark::query()
            ->with(['exam.subject', 'exam.schoolClass', 'exam.teacher', 'student'])
            ->whereHas('exam', function ($q) {
                $q->when($this->yearId, fn ($y) => $y->where('academic_year_id', $this->yearId))
                    ->when($this->classId, fn ($c) => $c->where('class_id', $this->classId))
                    ->when($this->teacherId, fn ($t) => $t->where('teacher_id', $this->teacherId));
            })
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        $scored = $items->where('is_absent', false)->filter(fn ($m) => $m->score !== null);
        $avg = $scored->count() > 0 ? $scored->avg('score') : null;

        $rows = $items->map(function ($m) {
            $note = $m->is_absent
                ? 'Absent'
                : rtrim(rtrim(number_format((float) $m->score, 2, ',', ' '), '0'), ',');
            if (! $m->is_absent && $m->exam?->max_score) {
                $note .= ' / '.rtrim(rtrim(number_format((float) $m->exam->max_score, 2, ',', ' '), '0'), ',');
            }

            return [
                (string) ($m->exam?->exam_date?->format('d/m/Y') ?: '—'),
                (string) ($m->exam?->title ?? '—'),
                (string) ($m->exam?->subject?->name ?? '—'),
                (string) ($m->exam?->schoolClass?->name ?? '—'),
                (string) ($m->exam?->teacher?->full_name ?? '—'),
                (string) $m->student?->student_code,
                (string) $m->student?->full_name,
                $note,
                (string) ($m->validated_at?->format('d/m/Y') ?: '—'),
            ];
        })->all();

        return $this->pack(
            'Notes d’examens',
            count($rows).' note(s) (1 000 max.)',
            ['Date', 'Examen', 'Matière', 'Classe', 'Enseignant', 'Matricule', 'Élève', 'Note', 'Validé'],
            $rows,
            [
                ['label' => 'Notes', 'value' => (string) count($rows)],
                ['label' => 'Absents', 'value' => (string) $items->where('is_absent', true)->count()],
                ['label' => 'Moyenne', 'value' => $avg !== null ? number_format((float) $avg, 2, ',', ' ') : '—'],
            ]
        );
    }
}
