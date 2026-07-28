<?php

namespace InovCom\Suivi\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Carbon\Carbon;
use InovCom\Clients\Models\Client;
use InovCom\Suivi\Models\SiteReport;
use InovCom\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SiteReportForm extends Component
{
    use AuthorizesWithTenant;

    public ?SiteReport $report = null;
    public bool $isEdit = false;

    // Form fields
    public string $project_id        = '';
    public string $client_id         = '';
    public string $assigned_to       = '';
    public string $report_date       = '';
    public string $weather            = 'sunny';
    public int    $workers_count     = 0;
    public int    $progress_percent  = 0;
    public string $work_done         = '';
    public string $issues            = '';
    public string $next_steps        = '';
    public string $status            = 'draft';
    public bool   $pv_signed         = false;
    public string $pv_client_name    = '';
    public string $notes             = '';

    protected function rules(): array
    {
        return [
            'project_id'       => 'required|integer',
            'client_id'        => 'nullable|integer',
            'assigned_to'      => 'nullable|integer',
            'report_date'      => 'required|date',
            'weather'          => 'required|in:sunny,cloudy,rainy,windy,other',
            'workers_count'    => 'required|integer|min:0',
            'progress_percent' => 'required|integer|min:0|max:100',
            'work_done'        => 'nullable|string',
            'issues'           => 'nullable|string',
            'next_steps'       => 'nullable|string',
            'status'           => 'required|in:draft,submitted,validated',
            'pv_signed'        => 'boolean',
            'pv_client_name'   => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
        ];
    }

    public function mount(?SiteReport $site_report = null): void
    {
        if ($site_report && $site_report->exists) {
            $this->tenantAuthorize('suivi.edit');
            $this->isEdit           = true;
            $this->report           = $site_report;
            $this->project_id       = (string) $site_report->project_id;
            $this->client_id        = (string) ($site_report->client_id ?? '');
            $this->assigned_to      = (string) ($site_report->assigned_to ?? '');
            $this->report_date      = $site_report->report_date->format('Y-m-d');
            $this->weather          = $site_report->weather;
            $this->workers_count    = $site_report->workers_count;
            $this->progress_percent = $site_report->progress_percent;
            $this->work_done        = $site_report->work_done ?? '';
            $this->issues           = $site_report->issues ?? '';
            $this->next_steps       = $site_report->next_steps ?? '';
            $this->status           = $site_report->status;
            $this->pv_signed        = (bool) $site_report->pv_signed;
            $this->pv_client_name   = $site_report->pv_client_name ?? '';
            $this->notes            = $site_report->notes ?? '';
        } else {
            $this->tenantAuthorize('suivi.create');
            $this->report_date = now()->format('Y-m-d');
            $this->assigned_to = (string) (auth('tenant')->id() ?? '');
        }
    }

    // When project changes, auto-fill client_id and pre-fill progress from current project value
    public function updatedProjectId(string $value): void
    {
        if (!$value) {
            return;
        }
        $project = DB::connection('tenant')->table('projects')->find($value, ['client_id', 'progress_percent']);
        if ($project) {
            $this->client_id        = (string) ($project->client_id ?? '');
            $this->progress_percent = (int) $project->progress_percent;
        }
    }

    // Toggle PV section
    public function updatedPvSigned(): void {}

    public function submit(): void
    {
        $this->status = 'submitted';
        $this->save();
    }

    public function validateReport(): void
    {
        $this->tenantAuthorize('suivi.validate');
        $this->status = 'validated';
        $this->save();
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $data = [
            'project_id'       => $this->project_id ?: null,
            'client_id'        => $this->client_id ?: null,
            'assigned_to'      => $this->assigned_to ?: null,
            'report_date'      => $this->report_date,
            'weather'          => $this->weather,
            'workers_count'    => $this->workers_count,
            'progress_percent' => $this->progress_percent,
            'work_done'        => $this->work_done ?: null,
            'issues'           => $this->issues ?: null,
            'next_steps'       => $this->next_steps ?: null,
            'status'           => $this->status,
            'pv_signed'        => $this->pv_signed,
            'pv_client_name'   => $this->pv_signed ? ($this->pv_client_name ?: null) : null,
            'pv_signed_at'     => $this->pv_signed && !$this->isEdit ? now() : ($this->report?->pv_signed_at),
            'notes'            => $this->notes ?: null,
        ];

        if ($this->isEdit) {
            $this->tenantAuthorize('suivi.edit');
            if ($this->report->pv_signed_at) {
                unset($data['pv_signed_at']);
            }
            $this->report->update($data);
            notify()->success(__('Rapport mis à jour.'));
        } else {
            $this->tenantAuthorize('suivi.create');
            $max = SiteReport::on('tenant')
                ->where('code', 'like', 'RPT%')
                ->pluck('code')
                ->map(fn(string $c) => (int) substr($c, 3))
                ->filter(fn(int $n) => $n > 0)
                ->max();
            $data['code'] = 'RPT' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);

            SiteReport::on('tenant')->create($data);
            notify()->success(__('Rapport créé.'));
        }

        // Always sync the report's progress_percent to the project
        if ($this->project_id && class_exists(\InovCom\Projets\Models\Project::class)) {
            $project = \InovCom\Projets\Models\Project::on('tenant')->find((int) $this->project_id);
            if ($project) {
                $project->progress_percent = $this->progress_percent;
                $project->saveQuietly();
            }
        }

        $tenantCode = request()->query('tenant') ?? session('tenant_code');
        $this->redirect(route('tenant.suivi.board', ['tenant' => $tenantCode]));
    }

    public function cancel(): void
    {
        $tenantCode = request()->query('tenant') ?? session('tenant_code');
        $this->redirect(route('tenant.suivi.board', ['tenant' => $tenantCode]));
    }

    public function render()
    {
        $projects = DB::connection('tenant')->table('projects')
            ->whereNotIn('status', ['closed'])
            ->orderBy('title')
            ->get(['id', 'code', 'title', 'client_id', 'progress_percent']);

        $clients = Client::on('tenant')->active()->ordered()->get(['id', 'name']);
        $users   = User::on('tenant')->orderBy('name')->get(['id', 'name']);

        $reportId = $this->isEdit ? $this->report->id : null;

        return view('inovcom-suivi::livewire.reports.form', [
            'projects' => $projects,
            'clients'  => $clients,
            'users'    => $users,
            'reportId' => $reportId,
        ])->layout('layouts.app', [
            'title'    => $this->isEdit ? __('Modifier le rapport') : __('Nouveau rapport de chantier'),
            'subtitle' => $this->isEdit
                ? ($this->report->code ?? '')
                : __('Rapport journalier d\'avancement de chantier.'),
        ]);
    }
}
