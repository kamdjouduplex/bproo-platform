<?php

namespace School\Http\Livewire;

use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use School\Models\AcademicYear;
use School\Models\SchoolEnrollment;
use School\Models\SchoolExam;
use School\Models\SchoolPayment;
use School\Models\SchoolStudent;
use School\Models\SchoolTeacher;
use School\Support\SchoolBootstrap;

class SchoolHub extends Component
{
    public function render()
    {
        SchoolBootstrap::ensure();

        $tenant = App::bound('tenant') ? App::make('tenant') : null;
        $registry = App::bound(ModuleRegistry::class) ? App::make(ModuleRegistry::class) : null;
        $tenantCode = request()->query('tenant')
            ?? request()->attributes->get('tenant')?->code
            ?? session('tenant_code');

        $user = Auth::guard('tenant')->user();
        $userName = $user?->name ?? '';

        $sections = [
            'school_scolarite' => [
                'label' => 'Scolarité',
                'items' => [
                    ['module' => 'school_teachers', 'label' => 'Enseignants', 'route' => 'tenant.school.teachers.index', 'hint' => 'Profils enseignants'],
                    ['module' => 'school_students', 'label' => 'Élèves', 'route' => 'tenant.school.students.index', 'hint' => 'Profils + historique'],
                    ['module' => 'school_students', 'label' => 'Parents', 'route' => 'tenant.school.parents.index', 'hint' => 'Tuteurs et enfants'],
                    ['module' => 'school_documents', 'label' => 'Pièces', 'route' => 'tenant.school.documents.index', 'hint' => 'Acte de naissance et autres documents'],
                    ['module' => 'school_enrollments', 'label' => 'Inscriptions', 'route' => 'tenant.school.enrollments.index', 'hint' => 'Inscription par année'],
                    ['module' => 'school_timetable', 'label' => 'Cours', 'route' => 'tenant.school.courses.index', 'hint' => 'Matière + enseignant par classe'],
                    ['module' => 'school_timetable', 'label' => 'Emploi du temps', 'route' => 'tenant.school.timetable.index', 'hint' => 'Grille semaine par classe / enseignant'],
                    ['module' => 'school_timetable', 'label' => 'Salles', 'route' => 'tenant.school.rooms.index', 'hint' => 'Locaux (distincts des classes)'],
                    ['module' => 'school_attendance', 'label' => 'Présences', 'route' => 'tenant.school.attendance.index', 'hint' => 'Appel par cours'],
                    ['module' => 'school_id_cards', 'label' => 'Cartes ID', 'route' => 'tenant.school.id_cards.index', 'hint' => 'QR / impression'],
                ],
            ],
            'school_finances' => [
                'label' => 'Finances scolaires',
                'items' => [
                    ['module' => 'school_fees', 'label' => 'Structures de frais', 'route' => 'tenant.school.fees.index', 'hint' => 'Barèmes par année / classe'],
                    ['module' => 'school_payments', 'label' => 'Paiements', 'route' => 'tenant.school.payments.index', 'hint' => 'Banque & paiements à l’école'],
                    ['module' => 'school_payments', 'label' => 'Soldes scolarité', 'route' => 'tenant.school.tuition.index', 'hint' => 'À jour / partiel / impayé'],
                    ['module' => 'school_reports', 'label' => 'Rapports', 'route' => 'tenant.school.reports.index', 'hint' => 'Listes, finances, présences, notes'],
                ],
            ],
            'school_examens' => [
                'label' => 'Examens & résultats',
                'items' => [
                    ['module' => 'school_exams', 'label' => 'Examens', 'route' => 'tenant.school.exams.index', 'hint' => 'Épreuves et notes'],
                    ['module' => 'school_grading', 'label' => 'Notation', 'route' => 'tenant.school.grading.systems.index', 'hint' => 'Barèmes et règles'],
                    ['module' => 'school_publications', 'label' => 'Publication', 'route' => 'tenant.school.publications.index', 'hint' => 'Publication conditionnée'],
                    ['module' => 'school_report_cards', 'label' => 'Bulletins', 'route' => 'tenant.school.report_cards.index', 'hint' => 'Bulletins et relevés'],
                ],
            ],
            'school_referentiel' => [
                'label' => 'Référentiel',
                'items' => [
                    ['module' => 'school_years', 'label' => 'Années académiques', 'route' => 'tenant.school.years.index', 'hint' => 'Créer / clôturer'],
                    ['module' => 'school_classes', 'label' => 'Classes', 'route' => 'tenant.school.classes.index', 'hint' => 'Niveaux et classes'],
                    ['module' => 'school_subjects', 'label' => 'Matières', 'route' => 'tenant.school.subjects.index', 'hint' => 'Référentiel matières'],
                ],
            ],
            'school_admin' => [
                'label' => 'Administration',
                'items' => [
                    ['module' => 'school_pilotage', 'label' => 'Pilotage', 'route' => 'tenant.school.pilotage.index', 'hint' => 'Cockpit direction'],
                    ['module' => 'school_notifications', 'label' => 'Notifications', 'route' => 'tenant.school.notifications.index', 'hint' => 'SMS / Email'],
                    ['module' => 'school_settings', 'label' => 'Paramétrage', 'route' => 'tenant.school.options.index', 'hint' => 'Listes configurables'],
                    ['module' => 'school_settings', 'label' => 'Matricules', 'route' => 'tenant.school.id_settings.index', 'hint' => 'Modèle d’identifiants'],
                    ['module' => 'school_settings', 'label' => 'Langues', 'route' => 'tenant.school.languages.index', 'hint' => 'FR / EN / ES / PT / AR'],
                    ['module' => 'school_settings', 'label' => 'Audit', 'route' => 'tenant.school.audit.index', 'hint' => 'Journal des actions'],
                ],
            ],
        ];

        $groupedLinks = [];
        foreach ($sections as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }
                if ($registry && $tenant && ! $registry->isEnabled($item['module'], $tenant)) {
                    continue;
                }
                $items[] = $item;
            }
            if ($items !== []) {
                $groupedLinks[] = [
                    'label' => $section['label'],
                    'items' => $items,
                ];
            }
        }

        $activeYear = $this->tableReady('academic_years')
            ? AcademicYear::query()->where('is_active', true)->orderByDesc('id')->first()
            : null;

        $yearId = $activeYear?->id;

        $stats = [
            'students' => $this->safeCount(SchoolStudent::class, 'school_students', fn ($q) => $q->where('is_active', true)),
            'teachers' => $this->safeCount(SchoolTeacher::class, 'school_teachers', fn ($q) => $q->where('is_active', true)),
            'enrollments' => $this->safeCount(
                SchoolEnrollment::class,
                'school_enrollments',
                fn ($q) => $q->when($yearId, fn ($qq) => $qq->where('academic_year_id', $yearId))->where('status', 'enrolled')
            ),
            'pending_payments' => $this->safeCount(
                SchoolPayment::class,
                'school_payments',
                fn ($q) => $q->where('status', 'pending')
            ),
            'open_exams' => $this->safeCount(
                SchoolExam::class,
                'school_exams',
                fn ($q) => $q->when($yearId, fn ($qq) => $qq->where('academic_year_id', $yearId))->where('status', 'open')
            ),
            'payments_today' => $this->safeSum(
                SchoolPayment::class,
                'school_payments',
                'amount',
                fn ($q) => $q->where('status', 'verified')->whereDate('paid_at', today())
            ),
        ];

        $alerts = [];
        if ($stats['pending_payments'] > 0 && Route::has('tenant.school.payments.index')) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => $stats['pending_payments'].' paiement(s) banque en attente',
                'hint' => 'À vérifier avant de mettre à jour les soldes.',
                'route' => 'tenant.school.payments.index',
                'action' => 'Voir les paiements',
            ];
        }
        if ($stats['open_exams'] > 0 && Route::has('tenant.school.exams.index')) {
            $alerts[] = [
                'tone' => 'info',
                'title' => $stats['open_exams'].' examen(s) ouvert(s)',
                'hint' => 'Saisie des notes en cours.',
                'route' => 'tenant.school.exams.index',
                'action' => 'Ouvrir les examens',
            ];
        }
        if (! $activeYear && Route::has('tenant.school.years.index')) {
            $alerts[] = [
                'tone' => 'danger',
                'title' => 'Aucune année académique active',
                'hint' => 'Activez une année pour travailler sur les inscriptions et examens.',
                'route' => 'tenant.school.years.index',
                'action' => 'Gérer les années',
            ];
        }

        $quickActions = [];
        foreach ([
            ['module' => 'school_students', 'label' => 'Élèves', 'route' => 'tenant.school.students.index', 'style' => 'primary'],
            ['module' => 'school_enrollments', 'label' => 'Inscriptions', 'route' => 'tenant.school.enrollments.index', 'style' => 'secondary'],
            ['module' => 'school_attendance', 'label' => 'Présences', 'route' => 'tenant.school.attendance.index', 'style' => 'secondary'],
            ['module' => 'school_timetable', 'label' => 'Emploi du temps', 'route' => 'tenant.school.timetable.index', 'style' => 'secondary'],
            ['module' => 'school_payments', 'label' => 'Paiements', 'route' => 'tenant.school.payments.index', 'style' => 'secondary'],
            ['module' => 'school_exams', 'label' => 'Examens', 'route' => 'tenant.school.exams.index', 'style' => 'secondary'],
            ['module' => 'school_report_cards', 'label' => 'Bulletins', 'route' => 'tenant.school.report_cards.index', 'style' => 'secondary'],
        ] as $action) {
            if (! Route::has($action['route'])) {
                continue;
            }
            if ($registry && $tenant && ! $registry->isEnabled($action['module'], $tenant)) {
                continue;
            }
            $quickActions[] = $action;
        }

        return view('school::livewire.hub', [
            'groupedLinks' => $groupedLinks,
            'stats' => $stats,
            'alerts' => $alerts,
            'quickActions' => $quickActions,
            'activeYear' => $activeYear,
            'userName' => $userName,
            'tenantCode' => $tenantCode,
            'tenantName' => $tenant?->name ?? 'École',
        ])->layout('layouts.app', [
            'title' => 'Tableau de bord',
            'subtitle' => $activeYear?->name
                ? 'Année active — '.$activeYear->name
                : 'Vue d’ensemble scolaire',
        ]);
    }

    protected function tableReady(string $table): bool
    {
        try {
            return Schema::connection('tenant')->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  class-string  $model
     * @param  callable(\Illuminate\Database\Eloquent\Builder): mixed  $configure
     */
    protected function safeCount(string $model, string $table, callable $configure): int
    {
        if (! $this->tableReady($table)) {
            return 0;
        }

        try {
            $query = $model::query();
            $configure($query);

            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  class-string  $model
     * @param  callable(\Illuminate\Database\Eloquent\Builder): mixed  $configure
     */
    protected function safeSum(string $model, string $table, string $column, callable $configure): float
    {
        if (! $this->tableReady($table)) {
            return 0.0;
        }

        try {
            $query = $model::query();
            $configure($query);

            return (float) $query->sum($column);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
