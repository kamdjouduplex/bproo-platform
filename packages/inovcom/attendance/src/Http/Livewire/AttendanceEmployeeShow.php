<?php

namespace InovCom\Attendance\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use InovCom\Attendance\Services\AttendanceService;
use InovCom\Users\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceEmployeeShow extends Component
{
    public ?int $employeeId = null;

    public ?int $userId = null;

    #[Url(as: 'month', except: '')]
    public string $month = '';

    public function mount(?int $employeeId = null, ?int $userId = null): void
    {
        $user = Auth::guard('tenant')->user();
        $service = app(AttendanceService::class);
        $canViewAll = $this->can('attendance.view_all');

        if (! $canViewAll && ! $this->can('attendance.view')) {
            abort(403);
        }

        $employeeId = $employeeId ?? (request()->route('employeeId') ? (int) request()->route('employeeId') : null);
        $userId = $userId ?? (request()->route('userId') ? (int) request()->route('userId') : null);

        if (! $employeeId && ! $userId) {
            abort(404);
        }

        $myEmployee = $user ? $service->resolveEmployeeForUser($user) : null;

        if (! $canViewAll) {
            $allowed = false;
            if ($employeeId && $myEmployee && (int) $myEmployee->id === $employeeId) {
                $allowed = true;
            }
            if ($userId && $user && (int) $user->id === $userId) {
                $allowed = true;
            }
            if (! $allowed) {
                abort(403);
            }
        }

        if ($employeeId) {
            $employees = $service->activeEmployees();
            if (! $employees->contains(fn ($e) => (int) $e->id === $employeeId)) {
                abort(404, 'Employé introuvable.');
            }
            $this->employeeId = $employeeId;
            $this->userId = null;
        } else {
            $target = User::query()->where('is_active', true)->find($userId);
            if (! $target) {
                abort(404, 'Utilisateur introuvable.');
            }
            $linked = $service->resolveEmployeeForUser($target);
            if ($linked) {
                $this->redirect(route('tenant.attendance.show', array_filter([
                    'tenant' => $this->tenantCode(),
                    'employeeId' => $linked->id,
                    'month' => request()->query('month', now()->format('Y-m')),
                ])));

                return;
            }
            $this->userId = (int) $userId;
            $this->employeeId = null;
        }

        $this->month = (string) request()->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    public function updatedMonth(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    public function exportExcel(): ?StreamedResponse
    {
        if (! $this->canExport()) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        $payload = $this->exportPayload();
        $filename = 'fiche-presence-'.$payload['slug'].'-'.$this->month.'.xls';
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $title = 'Fiche de présence — '.$payload['displayName'].' — '.$payload['monthLabel'];

        return response()->streamDownload(function () use ($payload, $title, $escape) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h3>'.$escape($title).'</h3>';
            echo '<p>'.$escape($payload['periodLabel']).' · Performance : '
                .$escape(number_format((float) $payload['report']['performance_percent'], 1, ',', ' ').'%')
                .' ('.$escape($payload['report']['performance_label']).')'
                .' · Ouvrés : '.(int) $payload['report']['expected_days']
                .' · Présences : '.(int) $payload['report']['present_days']
                .' · Complets : '.(int) ($payload['report']['complete_days'] ?? 0)
                .' · Absences : '.(int) $payload['report']['absent_days']
                .'</p>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach (['Date', 'Jour', 'Arrivée', 'Départ', 'Statut'] as $header) {
                echo '<th>'.$escape($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($payload['rows'] as $row) {
                echo '<tr>';
                echo '<td>'.$escape($row['label']).'</td>';
                echo '<td>'.$escape($row['weekday']).'</td>';
                echo '<td>'.$escape($row['arrival']).'</td>';
                echo '<td>'.$escape($row['departure']).'</td>';
                echo '<td>'.$escape($row['status']).'</td>';
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf()
    {
        if (! $this->canExport()) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $payload = $this->exportPayload();
            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'fiche-presence-'.$payload['slug'].'-'.$this->month.'.pdf';

            $pdf = Pdf::loadView('inovcom-attendance::pdf.presence-sheet', [
                'rows' => $payload['rows'],
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => 'Fiche de présence',
                'displayName' => $payload['displayName'],
                'employeeMeta' => $payload['employeeMeta'],
                'monthLabel' => $payload['monthLabel'],
                'periodLabel' => $payload['periodLabel'],
                'performancePercent' => $payload['report']['performance_percent'],
                'performanceLabel' => $payload['report']['performance_label'],
                'expectedDays' => $payload['report']['expected_days'],
                'presentDays' => $payload['report']['present_days'],
                'completeDays' => $payload['report']['complete_days'] ?? 0,
                'absentDays' => $payload['report']['absent_days'],
                'generatedAt' => now(),
            ])->setPaper('a4', 'portrait');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $fontMetrics = $dompdf->getFontMetrics();
            $font = $fontMetrics->getFont('DejaVu Sans');
            if ($font) {
                $size = 8;
                $width = $fontMetrics->getTextWidth('00/00', $font, $size);
                $x = ($canvas->get_width() - $width) / 2;
                $y = $canvas->get_height() - 18;
                $canvas->page_text($x, $y, '{PAGE_NUM}/{PAGE_COUNT}', $font, $size, [0.06, 0.46, 0.43]);
            }

            $output = $dompdf->output();

            return response()->streamDownload(function () use ($output) {
                echo $output;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Export PDF impossible. Réessayez.');

            return null;
        }
    }

    public function render()
    {
        $service = app(AttendanceService::class);
        [$from, $to] = $service->monthBounds($this->month);
        $monthOptions = $service->monthOptions(18);
        $monthLabel = $monthOptions[$this->month] ?? $from->locale('fr')->translatedFormat('F Y');

        $report = $service->presenceReport($this->employeeId, $this->userId, $from, $to);
        $displayName = $service->displayName($report['employee'], $report['user']);

        return view('inovcom-attendance::livewire.show')
            ->layout('layouts.app', [
                'title' => $displayName,
                'subtitle' => 'Historique de présence — '.$monthLabel,
            ])
            ->with([
                'report' => $report,
                'displayName' => $displayName,
                'monthOptions' => $monthOptions,
                'monthLabel' => $monthLabel,
                'dateFrom' => $from,
                'dateTo' => $to,
                'tenantCode' => $this->tenantCode(),
                'canViewAll' => $this->can('attendance.view_all'),
            ]);
    }

    private function exportPayload(): array
    {
        $service = app(AttendanceService::class);
        [$from, $to] = $service->monthBounds($this->month);
        $monthOptions = $service->monthOptions(18);
        $monthLabel = $monthOptions[$this->month] ?? $from->locale('fr')->translatedFormat('F Y');
        $report = $service->presenceReport($this->employeeId, $this->userId, $from, $to);
        $displayName = $service->displayName($report['employee'], $report['user']);
        $employee = $report['employee'] ?? null;

        $metaParts = [];
        if (! empty($employee?->employee_number)) {
            $metaParts[] = 'N° '.$employee->employee_number;
        }
        if (! empty($employee?->position)) {
            $metaParts[] = (string) $employee->position;
        }

        $rows = collect($report['days'] ?? [])->map(function (array $day) {
            $present = (bool) ($day['present'] ?? false);
            $complete = (bool) ($day['complete'] ?? false);

            if (! $present) {
                $status = 'Absent';
                $statusClass = 'absent';
            } elseif ($complete) {
                $status = 'Complet';
                $statusClass = 'present';
            } else {
                $status = 'Arrivée seule';
                $statusClass = 'partial';
            }

            return [
                'label' => (string) ($day['label'] ?? '—'),
                'weekday' => (string) ($day['weekday'] ?? '—'),
                'arrival' => (string) ($day['arrival'] ?? '—'),
                'departure' => (string) ($day['departure'] ?? '—'),
                'status' => $status,
                'status_class' => $statusClass,
            ];
        })->all();

        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($displayName)) ?: 'employe';
        $slug = trim($slug, '-') ?: 'employe';

        return [
            'report' => $report,
            'displayName' => $displayName,
            'employeeMeta' => implode(' · ', $metaParts),
            'monthLabel' => $monthLabel,
            'periodLabel' => $from->format('d/m/Y').' — '.$to->format('d/m/Y'),
            'rows' => $rows,
            'slug' => $slug,
        ];
    }

    private function canExport(): bool
    {
        return $this->can('attendance.view_all') || $this->can('attendance.view');
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
