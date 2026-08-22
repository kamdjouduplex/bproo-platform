<?php

namespace School\Http\Livewire;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolAttendanceRecord;
use School\Models\SchoolEnrollment;
use School\Models\SchoolExam;
use School\Models\SchoolPayment;
use School\Models\SchoolStudent;
use School\Support\SchoolReportBuilder;
use School\Support\StudentProfileCompletion;

class SchoolPilotageIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;

    public string $yearId = '';

    public function mount(): void
    {
        if (! $this->canSchool('school_pilotage.view')) {
            abort(403, 'Permission refusée.');
        }

        $this->yearId = (string) (AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id')
            ?? '');
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $yearId = $this->yearId !== '' ? (int) $this->yearId : null;
        $activeYear = $yearId
            ? $years->firstWhere('id', $yearId)
            : $years->firstWhere('is_active', true);

        $effectifs = (new SchoolReportBuilder('effectifs', $yearId))->build();
        $collection = (new SchoolReportBuilder('collection', $yearId))->build();

        $pendingPayments = 0;
        $openExams = 0;
        $incompleteProfiles = 0;
        $attendanceRate = null;
        $absencesMonth = 0;

        try {
            $pendingPayments = (int) SchoolPayment::query()->where('status', 'pending')->count();
        } catch (\Throwable) {
        }

        try {
            if (Schema::connection('tenant')->hasTable('school_exams')) {
                $openExams = (int) SchoolExam::query()
                    ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
                    ->where('status', 'open')
                    ->count();
            }
        } catch (\Throwable) {
        }

        try {
            $studentIds = SchoolEnrollment::query()
                ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
                ->where('status', 'enrolled')
                ->pluck('student_id');
            if ($studentIds->isNotEmpty()) {
                foreach (SchoolStudent::query()->whereIn('id', $studentIds)->get() as $student) {
                    if (StudentProfileCompletion::for($student)['percent'] < 100) {
                        $incompleteProfiles++;
                    }
                }
            }
        } catch (\Throwable) {
        }

        try {
            if (Schema::connection('tenant')->hasTable('school_attendance_records')) {
                $from = now()->startOfMonth()->toDateString();
                $to = now()->toDateString();
                $counts = SchoolAttendanceRecord::query()
                    ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
                    ->whereDate('attendance_date', '>=', $from)
                    ->whereDate('attendance_date', '<=', $to)
                    ->selectRaw('status, COUNT(*) as c')
                    ->groupBy('status')
                    ->pluck('c', 'status');
                $present = (int) ($counts['present'] ?? 0);
                $late = (int) ($counts['late'] ?? 0);
                $absent = (int) ($counts['absent'] ?? 0);
                $excused = (int) ($counts['excused'] ?? 0);
                $total = $present + $late + $absent + $excused;
                $absencesMonth = $absent;
                if ($total > 0) {
                    $attendanceRate = (int) round((($present + $late) / $total) * 100);
                }
            }
        } catch (\Throwable) {
        }

        $kpis = [
            ['label' => 'Effectif inscrit', 'value' => $effectifs['kpis'][2]['value'] ?? '0', 'hint' => $activeYear?->name ?? 'Toutes années', 'tone' => 'ok'],
            ['label' => 'Déjà perçu', 'value' => $collection['kpis'][1]['value'] ?? '0', 'hint' => 'Encaissements validés', 'tone' => 'ok'],
            ['label' => 'Reste à recouvrer', 'value' => $collection['kpis'][2]['value'] ?? '0', 'hint' => 'Soldes ouverts', 'tone' => 'mid'],
            ['label' => 'Assiduité (mois)', 'value' => $attendanceRate !== null ? $attendanceRate.' %' : '—', 'hint' => $absencesMonth.' absence(s)', 'tone' => $attendanceRate === null ? 'muted' : ($attendanceRate >= 90 ? 'ok' : 'mid')],
            ['label' => 'Profils incomplets', 'value' => (string) $incompleteProfiles, 'hint' => 'Fiches à compléter', 'tone' => $incompleteProfiles > 0 ? 'mid' : 'ok'],
            ['label' => 'Paiements en attente', 'value' => (string) $pendingPayments, 'hint' => 'À valider', 'tone' => $pendingPayments > 0 ? 'bad' : 'ok'],
        ];

        $tenantCode = $this->tenantCode();
        $alerts = [];
        if (! $activeYear) {
            $alerts[] = ['tone' => 'danger', 'title' => 'Aucune année académique active', 'hint' => 'Activez une année pour piloter inscriptions et finances.', 'route' => Route::has('tenant.school.years.index') ? route('tenant.school.years.index', ['tenant' => $tenantCode]) : null, 'action' => 'Gérer les années'];
        }
        if ($pendingPayments > 0 && Route::has('tenant.school.payments.index')) {
            $alerts[] = ['tone' => 'warning', 'title' => $pendingPayments.' paiement(s) en attente', 'hint' => 'Contrôle banque / mobile money avant d’actualiser les soldes.', 'route' => route('tenant.school.payments.index', ['tenant' => $tenantCode]), 'action' => 'Voir les paiements'];
        }
        if ($incompleteProfiles > 0 && Route::has('tenant.school.students.index')) {
            $alerts[] = ['tone' => 'info', 'title' => $incompleteProfiles.' fiche(s) élève incomplète(s)', 'hint' => 'Photo, adresse ou contact d’urgence souvent manquants.', 'route' => route('tenant.school.students.index', ['tenant' => $tenantCode]), 'action' => 'Ouvrir les élèves'];
        }
        if ($openExams > 0 && Route::has('tenant.school.exams.index')) {
            $alerts[] = ['tone' => 'info', 'title' => $openExams.' examen(s) ouvert(s)', 'hint' => 'Saisie des notes encore en cours.', 'route' => route('tenant.school.exams.index', ['tenant' => $tenantCode]), 'action' => 'Examens'];
        }

        $shortcuts = [];
        foreach ([
            ['label' => 'Rapports', 'route' => 'tenant.school.reports.index', 'hint' => 'Listes et PDF'],
            ['label' => 'Soldes scolarité', 'route' => 'tenant.school.tuition.index', 'hint' => 'Débiteurs'],
            ['label' => 'Présences', 'route' => 'tenant.school.attendance.index', 'hint' => 'Appel par cours'],
            ['label' => 'Emploi du temps', 'route' => 'tenant.school.timetable.index', 'hint' => 'Grille semaine'],
            ['label' => 'Inscriptions', 'route' => 'tenant.school.enrollments.index', 'hint' => 'Effectifs'],
        ] as $item) {
            if (Route::has($item['route'])) {
                $shortcuts[] = $item + ['url' => route($item['route'], ['tenant' => $tenantCode])];
            }
        }

        return view('school::livewire.school.pilotage.index', [
            'years' => $years,
            'activeYear' => $activeYear,
            'kpis' => $kpis,
            'alerts' => $alerts,
            'effectifs' => $effectifs,
            'collection' => $collection,
            'shortcuts' => $shortcuts,
            'tenantCode' => $tenantCode,
        ])->layout('layouts.app', [
            'title' => 'Pilotage',
            'subtitle' => $activeYear?->name
                ? 'Cockpit de direction — '.$activeYear->name
                : 'Cockpit de direction',
        ]);
    }
}
