<?php

namespace School\Http\Livewire;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolTeacher;
use School\Support\SchoolReportBuilder;

class SchoolReportsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;

    public string $reportType = 'enrollments';

    public string $yearId = '';

    public string $classId = '';

    public string $teacherId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        if (! $this->canSchool('school_reports.view')) {
            abort(403, 'Permission refusée.');
        }

        $this->yearId = (string) (AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id')
            ?? '');
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $type = (string) request()->query('type', '');
        if (array_key_exists($type, SchoolReportBuilder::types())) {
            $this->reportType = $type;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return SchoolReportBuilder::types();
    }

    public function printUrl(): string
    {
        return route('tenant.school.reports.print', array_filter([
            'tenant' => $this->tenantCode(),
            'type' => $this->reportType,
            'year' => $this->yearId,
            'class' => $this->classId,
            'teacher' => $this->teacherId,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ], fn ($v) => $v !== '' && $v !== null));
    }

    /**
     * @return array{title:string, summary:string, headers:list<string>, rows:list<list<string>>, kpis:list<array{label:string,value:string}>, totals:?list<string>}
     */
    public function dataset(): array
    {
        return $this->builder()->build();
    }

    public function exportCsv()
    {
        $report = $this->dataset();
        $name = 'rapport-'.Str::slug($this->reportType).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $report['headers'], ';');
            foreach ($report['rows'] as $row) {
                fputcsv($handle, $row, ';');
            }
            if (! empty($report['totals'])) {
                fputcsv($handle, $report['totals'], ';');
            }
            fclose($handle);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function builder(): SchoolReportBuilder
    {
        $yearId = $this->yearId !== '' ? (int) $this->yearId : null;
        $classId = $this->classId !== '' ? (int) $this->classId : null;
        $teacherId = $this->teacherId !== '' ? (int) $this->teacherId : null;
        $from = $this->dateFrom !== '' ? Carbon::parse($this->dateFrom)->startOfDay() : null;
        $to = $this->dateTo !== '' ? Carbon::parse($this->dateTo)->endOfDay() : null;

        return new SchoolReportBuilder(
            $this->reportType,
            $yearId,
            $classId,
            $from,
            $to,
            $teacherId,
        );
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $teachers = SchoolTeacher::query()->where('is_active', true)->orderBy('full_name')->get();
        $report = $this->dataset();

        return view('school::livewire.school.reports.index', [
            'years' => $years,
            'classes' => $classes,
            'teachers' => $teachers,
            'types' => SchoolReportBuilder::types(),
            'groups' => SchoolReportBuilder::groups(),
            'report' => $report,
            'printUrl' => $this->printUrl(),
            'tenantCode' => $this->tenantCode(),
            'usesYear' => SchoolReportBuilder::usesYear($this->reportType),
            'usesClass' => SchoolReportBuilder::usesClass($this->reportType),
            'usesDates' => SchoolReportBuilder::usesDates($this->reportType),
            'usesTeacher' => SchoolReportBuilder::usesTeacher($this->reportType),
        ])->layout('layouts.app', [
            'title' => 'École — Rapports',
            'subtitle' => 'Listes, finances, présences et notes — PDF et CSV.',
        ]);
    }
}
