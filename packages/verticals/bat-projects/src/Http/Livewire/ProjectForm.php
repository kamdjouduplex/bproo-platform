<?php

namespace InovCom\Projets\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Clients\Models\Client;
use InovCom\Devis\Models\Quote;
use InovCom\Kernel\Support\ServiceCatalog;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Projets\Models\Project;
use InovCom\Users\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProjectForm extends Component
{
    use AuthorizesWithTenant;

    // ── Identity ──────────────────────────────────────────────────────
    public ?int    $projectId   = null;
    public string  $code        = '';

    // ── Core fields ───────────────────────────────────────────────────
    public int     $quote_id    = 0;
    public int     $client_id   = 0;
    public string  $title       = '';
    public string  $status      = 'planned';
    public string  $project_type = 'construction';   // construction | maintenance | service
    public bool    $projectTypeLocked = false;
    public bool    $isPrestation = false;
    public string  $priority    = 'normal';           // low | normal | high | urgent
    public ?string $start_date  = null;
    public ?string $end_date    = null;
    public ?int    $assigned_to = null;
    public string  $notes       = '';
    public string  $contract_number = '';
    public string  $site_address    = '';

    // ── Financial ─────────────────────────────────────────────────────
    public string $budget = '0';

    // ── Progress (read-only display in edit mode) ─────────────────────
    public float $progress_percent = 0;
    public float $actual_cost      = 0;

    public function mount(?Project $project = null): void
    {
        $this->tenantAuthorize('projets.view');

        if ($project && $project->exists) {
            $this->projectId       = $project->id;
            $this->code            = $project->code;
            $this->quote_id        = $project->quote_id ?? 0;
            $this->client_id       = $project->client_id;
            $this->title           = $project->title;
            $this->status          = $project->status;
            $this->project_type    = $project->project_type ?? 'construction';
            $this->projectTypeLocked = true;
            $this->isPrestation    = ($project->project_type ?? '') === ServiceCatalog::EXEC_SERVICE;
            $this->priority        = $project->priority ?? 'normal';
            $this->start_date      = $project->start_date?->format('Y-m-d');
            $this->end_date        = $project->end_date?->format('Y-m-d');
            $this->assigned_to     = $project->assigned_to;
            $this->notes           = $project->notes ?? '';
            $this->contract_number = $project->contract_number ?? '';
            $this->site_address    = $project->site_address ?? '';
            $this->budget          = (string) ($project->budget ?? 0);
            $this->progress_percent = (float) ($project->progress_percent ?? 0);
            $this->actual_cost     = (float) ($project->actual_cost ?? 0);
        } elseif (request()->routeIs('tenant.prestations.create', 'tenant.prestations.index')) {
            $this->project_type = ServiceCatalog::EXEC_SERVICE;
            $this->projectTypeLocked = true;
            $this->isPrestation = true;
        } elseif (request()->routeIs('tenant.projets.create', 'tenant.projets.index', 'tenant.projets.edit')) {
            $this->project_type = ServiceCatalog::EXEC_CONSTRUCTION;
            $this->projectTypeLocked = true;
            $this->isPrestation = false;
        }

        if (request()->filled('quote')) {
            $this->quote_id = (int) request()->query('quote');
            $this->hydrateFromQuote($this->quote_id);
        }
    }

    protected function hydrateFromQuote(int $quoteId): void
    {
        if ($quoteId < 1) {
            return;
        }

        $quote = Quote::on('tenant')->with('offer')->find($quoteId);
        if (!$quote) {
            return;
        }

        $this->client_id = $quote->client_id;
        $this->title     = $quote->title;
        $this->budget    = (string) ($quote->total_ttc ?? 0);

        if (!$this->projectTypeLocked) {
            $this->project_type = ServiceCatalog::offerToExecutionType($quote->offer?->category);
            $this->isPrestation = $this->project_type === ServiceCatalog::EXEC_SERVICE;
        }
    }

    // ── Validation ────────────────────────────────────────────────────
    public function rules(): array
    {
        $rules = [
            'quote_id'        => ['nullable', 'integer'],
            'client_id'       => ['required', 'integer', 'min:1'],
            'title'           => ['required', 'string', 'max:255'],
            'status'          => ['required', 'in:planned,in_progress,on_hold,completed,closed'],
            'project_type'    => ['required', 'in:construction,maintenance,service,other'],
            'priority'        => ['required', 'in:low,normal,high,urgent'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'assigned_to'     => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string'],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'site_address'    => ['nullable', 'string', 'max:500'],
            'budget'          => ['nullable', 'numeric', 'min:0'],
        ];
        if ($this->projectId) {
            $rules['code'] = ['required', 'string', 'max:50',
                Rule::unique(Project::class, 'code')->ignore($this->projectId)];
        }
        return $rules;
    }

    // ── Auto-fill from Quote ──────────────────────────────────────────
    public function updatedQuoteId($value): void
    {
        $this->hydrateFromQuote((int) $value);
    }

    // ── Workflow actions ──────────────────────────────────────────────

    public function startProject(): void
    {
        $this->tenantAuthorize('projets.edit');
        $project = Project::on('tenant')->findOrFail($this->projectId);
        try {
            $project->transitionTo('in_progress', auth('tenant')->id());
            $this->status = 'in_progress';
            notify()->success(__('Projet démarré.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function holdProject(): void
    {
        $this->tenantAuthorize('projets.edit');
        $project = Project::on('tenant')->findOrFail($this->projectId);
        try {
            $project->transitionTo('on_hold', auth('tenant')->id());
            $this->status = 'on_hold';
            notify()->success(__('Projet mis en attente.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function completeProject(): void
    {
        $this->tenantAuthorize('projets.edit');
        $project = Project::on('tenant')->findOrFail($this->projectId);
        try {
            $project->transitionTo('completed', auth('tenant')->id());
            $this->status = 'completed';
            notify()->success(__('Projet marqué comme terminé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function closeProject(): void
    {
        $this->tenantAuthorize('projets.edit');
        $project = Project::on('tenant')->findOrFail($this->projectId);
        try {
            $project->transitionTo('closed', auth('tenant')->id());
            $this->status = 'closed';
            notify()->success(__('Projet clôturé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    // ── Save ──────────────────────────────────────────────────────────
    public function save(): void
    {
        $this->tenantAuthorize($this->projectId ? 'projets.edit' : 'projets.create');
        $this->validate();

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        $data = [
            'quote_id'        => $this->quote_id ?: null,
            'client_id'       => $this->client_id,
            'title'           => $this->title,
            'project_type'    => $this->project_type,
            'priority'        => $this->priority,
            'start_date'      => $this->start_date ?: null,
            'end_date'        => $this->end_date ?: null,
            'assigned_to'     => $this->assigned_to ?: null,
            'notes'           => $this->notes ?: null,
            'contract_number' => $this->contract_number ?: null,
            'site_address'    => $this->site_address ?: null,
            'budget'          => (float) $this->budget,
        ];

        if ($this->projectId) {
            $project = Project::on('tenant')->findOrFail($this->projectId);

            if ($project->status !== $this->status) {
                try {
                    $project->transitionTo($this->status, auth('tenant')->id());
                } catch (InvalidWorkflowTransitionException $e) {
                    $this->addError('status', $e->getMessage());
                    return;
                }
            }

            $project->update($data);
            notify()->success(__('Projet mis à jour.'));
            $this->redirect(
                route('tenant.projets.show', ['tenant' => $tenantCode, 'project' => $this->projectId]),
                navigate: true
            );
        } else {
            $code    = $this->generateNextProjectCode();
            $project = Project::create(array_merge($data, [
                'code'   => $code,
                'status' => $this->status,
            ]));
            notify()->success($this->isPrestation ? __('Prestation créée.') : __('Projet créé.'));
            $this->redirect(
                route('tenant.projets.show', ['tenant' => $tenantCode, 'project' => $project->id]),
                navigate: true
            );
        }
    }

    protected function generateNextProjectCode(): string
    {
        $prefix = $this->project_type === ServiceCatalog::EXEC_SERVICE ? 'PST' : 'PRJ';
        $max = Project::where('code', 'like', $prefix . '%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, strlen($prefix)))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();
        return $prefix . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ── Render ────────────────────────────────────────────────────────
    public function render()
    {
        $acceptedQuotes = Quote::on('tenant')->where('status', 'accepted')->ordered()->get(['id', 'code', 'title', 'client_id', 'total_ttc']);
        $clients        = Client::on('tenant')->active()->ordered()->get(['id', 'name', 'code']);
        $users          = User::on('tenant')->orderBy('name')->get(['id', 'name']);

        $allowedTransitions = [];
        $liveActualCost     = $this->actual_cost;
        $liveProgress       = $this->progress_percent;

        if ($this->projectId) {
            $project = Project::on('tenant')->find($this->projectId);
            if ($project) {
                $allowedTransitions = $project->allowedTransitions()[$this->status] ?? [];
                // Always pass fresh values so the display reflects what other modules wrote
                $liveActualCost = (float) $project->actual_cost;
                $liveProgress   = (float) $project->progress_percent;
            }
        }

        return view('inovcom-projets::livewire.projects.form', [
            'acceptedQuotes'     => $acceptedQuotes,
            'clients'            => $clients,
            'users'              => $users,
            'allowedTransitions' => $allowedTransitions,
            'liveActualCost'     => $liveActualCost,
            'liveProgress'       => $liveProgress,
            'projectTypeLocked'  => $this->projectTypeLocked,
            'isPrestation'       => $this->isPrestation,
            'projectTypeLabel'   => ServiceCatalog::executionLabel($this->project_type),
            'projectTypeBadge'   => ServiceCatalog::executionBadgeClass($this->project_type),
        ])->layout('layouts.app', [
            'title'    => $this->projectId
                ? ($this->isPrestation ? __('Modifier la prestation') : __('Modifier le projet'))
                : ($this->isPrestation ? __('Nouvelle prestation') : __('Nouveau projet')),
            'subtitle' => $this->code ?: '',
        ]);
    }
}
