<?php

namespace InovCom\Maintenance\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Clients\Models\Client;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Maintenance\Models\MaintenanceContract;
use InovCom\Maintenance\Services\MaintenanceCycleService;
use InovCom\Maintenance\Support\MaintenanceCycle;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ContractForm extends Component
{
    use AuthorizesWithTenant;

    // ── Identity ──────────────────────────────────────────────────────
    public ?int    $contractId = null;
    public string  $code       = '';

    // ── Core fields ───────────────────────────────────────────────────
    public int     $client_id       = 0;
    public string  $title           = '';
    public string  $type            = 'full_service'; // preventive | corrective | full_service
    public string  $status          = 'active';
    public ?string $start_date      = null;
    public ?string $end_date        = null;
    public string  $price_per_month = '0';
    public string  $response_time   = '';    // hours
    public string  $resolution_time = '';   // hours
    public string  $billing_cycle   = 'monthly';
    public string  $intervention_frequency = 'monthly';
    public ?string $next_intervention_at   = null;
    public ?string $last_intervention_at   = null;
    public bool    $auto_generate_orders   = true;
    public string  $terms           = '';
    // Sites stored as JSON text to allow textarea editing
    public string  $sites_json      = '[]';

    public function mount(?MaintenanceContract $maintenance_contract = null): void
    {
        $this->tenantAuthorize('maintenance.view');

        if ($maintenance_contract && $maintenance_contract->exists) {
            $c = $maintenance_contract;
            $this->contractId      = $c->id;
            $this->code            = $c->code;
            $this->client_id       = $c->client_id;
            $this->title           = $c->title ?? '';
            $this->type            = $c->type;
            $this->status          = $c->status;
            $this->start_date      = $c->start_date?->format('Y-m-d');
            $this->end_date        = $c->end_date?->format('Y-m-d');
            $this->price_per_month = (string) ($c->price_per_month ?? 0);
            $this->response_time   = (string) ($c->response_time ?? '');
            $this->resolution_time = (string) ($c->resolution_time ?? '');
            $this->billing_cycle   = $c->billing_cycle;
            $this->intervention_frequency = $c->intervention_frequency ?? 'monthly';
            $this->next_intervention_at   = $c->next_intervention_at?->format('Y-m-d');
            $this->last_intervention_at   = $c->last_intervention_at?->format('Y-m-d');
            $this->auto_generate_orders   = (bool) ($c->auto_generate_orders ?? true);
            $this->terms           = $c->terms ?? '';
            $this->sites_json      = json_encode($c->sites ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    public function rules(): array
    {
        $rules = [
            'client_id'       => ['required', 'integer', 'min:1'],
            'title'           => ['nullable', 'string', 'max:255'],
            'type'            => ['required', 'in:preventive,corrective,full_service'],
            'status'          => ['required', 'in:active,suspended,expired'],
            'start_date'      => ['required', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'price_per_month' => ['nullable', 'numeric', 'min:0'],
            'response_time'   => ['nullable', 'integer', 'min:1'],
            'resolution_time' => ['nullable', 'integer', 'min:1'],
            'billing_cycle'   => ['required', 'in:monthly,quarterly,yearly'],
            'intervention_frequency' => ['required', 'in:monthly,bimonthly,quarterly,semi_annual,yearly'],
            'next_intervention_at'   => ['nullable', 'date'],
            'last_intervention_at'   => ['nullable', 'date'],
            'auto_generate_orders'   => ['boolean'],
            'terms'           => ['nullable', 'string'],
            'sites_json'      => ['nullable', 'string'],
        ];
        if ($this->contractId) {
            $rules['code'] = ['required', 'string', 'max:50',
                Rule::unique(MaintenanceContract::class, 'code')->ignore($this->contractId)];
        }
        return $rules;
    }

    // ── Workflow actions ──────────────────────────────────────────────
    public function suspendContract(): void
    {
        $this->tenantAuthorize('maintenance.edit');
        $contract = MaintenanceContract::on('tenant')->findOrFail($this->contractId);
        try {
            $contract->transitionTo('suspended', auth('tenant')->id());
            $this->status = 'suspended';
            notify()->success(__('Contrat suspendu.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function reactivateContract(): void
    {
        $this->tenantAuthorize('maintenance.edit');
        $contract = MaintenanceContract::on('tenant')->findOrFail($this->contractId);
        try {
            $contract->transitionTo('active', auth('tenant')->id());
            $this->status = 'active';
            notify()->success(__('Contrat réactivé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function expireContract(): void
    {
        $this->tenantAuthorize('maintenance.edit');
        $contract = MaintenanceContract::on('tenant')->findOrFail($this->contractId);
        try {
            $contract->transitionTo('expired', auth('tenant')->id());
            $this->status = 'expired';
            notify()->success(__('Contrat marqué expiré.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function planNextIntervention(): void
    {
        $this->tenantAuthorize('maintenance.dispatch');

        $contract = MaintenanceContract::on('tenant')->findOrFail($this->contractId);

        try {
            $order = app(MaintenanceCycleService::class)->planPreventiveOrder(
                $contract,
                auth('tenant')->id()
            );

            $contract->refresh();
            $this->next_intervention_at = $contract->next_intervention_at?->format('Y-m-d');
            $this->last_intervention_at = $contract->last_intervention_at?->format('Y-m-d');

            notify()->success(__('Ordre :code planifié.', ['code' => $order->code]));
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    // ── Save ──────────────────────────────────────────────────────────
    public function save(): void
    {
        $this->tenantAuthorize($this->contractId ? 'maintenance.edit' : 'maintenance.create');
        $this->validate();

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        $sites = [];
        if ($this->sites_json) {
            $decoded = json_decode($this->sites_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $sites = $decoded;
            }
        }

        $data = [
            'client_id'       => $this->client_id,
            'title'           => $this->title ?: null,
            'type'            => $this->type,
            'start_date'      => $this->start_date,
            'end_date'        => $this->end_date ?: null,
            'price_per_month' => $this->price_per_month ? (float) $this->price_per_month : null,
            'response_time'   => $this->response_time ? (int) $this->response_time : null,
            'resolution_time' => $this->resolution_time ? (int) $this->resolution_time : null,
            'billing_cycle'   => $this->billing_cycle,
            'intervention_frequency' => $this->intervention_frequency,
            'next_intervention_at'   => $this->next_intervention_at ?: null,
            'last_intervention_at'   => $this->last_intervention_at ?: null,
            'auto_generate_orders'   => $this->auto_generate_orders,
            'terms'           => $this->terms ?: null,
            'sites'           => $sites ?: null,
        ];

        if ($this->contractId) {
            $contract = MaintenanceContract::on('tenant')->findOrFail($this->contractId);
            if ($contract->status !== $this->status) {
                try {
                    $contract->transitionTo($this->status, auth('tenant')->id());
                } catch (InvalidWorkflowTransitionException $e) {
                    $this->addError('status', $e->getMessage());
                    return;
                }
            }
            $contract->update($data);
            notify()->success(__('Contrat mis à jour.'));
            $this->redirect(
                route('tenant.maintenance.contracts.edit', ['tenant' => $tenantCode, 'maintenance_contract' => $this->contractId]),
                navigate: true
            );
        } else {
            $code     = $this->generateNextCode();
            $data['next_intervention_at'] = $data['next_intervention_at'] ?? $this->start_date;
            $contract = MaintenanceContract::create(array_merge($data, [
                'code'   => $code,
                'status' => $this->status,
            ]));
            notify()->success(__('Contrat créé.'));
            $this->redirect(
                route('tenant.maintenance.contracts.edit', ['tenant' => $tenantCode, 'maintenance_contract' => $contract->id]),
                navigate: true
            );
        }
    }

    protected function generateNextCode(): string
    {
        $max = MaintenanceContract::where('code', 'like', 'CTR%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();
        return 'CTR' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        $clients = Client::on('tenant')->active()->ordered()->get(['id', 'name', 'code']);

        $allowedTransitions = [];
        $cycleSummary = null;
        if ($this->contractId) {
            $contract = MaintenanceContract::on('tenant')->find($this->contractId);
            $allowedTransitions = $contract?->allowedTransitions()[$this->status] ?? [];
            if ($contract) {
                $cycleSummary = app(MaintenanceCycleService::class)->summarize($contract);
            }
        }

        return view('inovcom-maintenance::livewire.contracts.form', [
            'clients'            => $clients,
            'allowedTransitions' => $allowedTransitions,
            'cycleSummary'       => $cycleSummary,
            'interventionFrequencies' => MaintenanceCycle::interventionFrequencies(),
            'billingCycles'      => MaintenanceCycle::billingCycles(),
        ])->layout('layouts.app', [
            'title'    => $this->contractId ? __('Modifier le contrat') : __('Nouveau contrat'),
            'subtitle' => $this->code ?: '',
        ]);
    }
}
