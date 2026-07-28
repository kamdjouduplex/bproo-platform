<?php

namespace InovCom\Maintenance\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Clients\Models\Client;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Maintenance\Models\MaintenanceContract;
use InovCom\Maintenance\Models\MaintenanceOrder;
use InovCom\Users\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

class OrderForm extends Component
{
    use AuthorizesWithTenant;

    // ── Identity ──────────────────────────────────────────────────────
    public ?int    $orderId = null;
    public string  $code    = '';

    // ── Core fields ───────────────────────────────────────────────────
    public int     $contract_id  = 0;
    public int     $client_id    = 0;
    public string  $title        = '';
    public string  $description  = '';
    public string  $type         = 'corrective'; // corrective | preventive | emergency
    public string  $priority     = 'normal';     // low | normal | high | critical
    public string  $status       = 'open';
    public string  $site_address = '';
    public string  $reported_by  = '';
    public ?string $reported_at  = null;
    public ?string $due_at       = null;
    public ?int    $assigned_to  = null;

    // ── Computed display ──────────────────────────────────────────────
    public bool    $slaBreached  = false;
    public ?float  $slaHours     = null;

    public function mount(?MaintenanceOrder $maintenance_order = null): void
    {
        $this->tenantAuthorize('maintenance.view');

        $this->reported_at = now()->format('Y-m-d\TH:i');

        if ($maintenance_order && $maintenance_order->exists) {
            $o = $maintenance_order;
            $this->orderId      = $o->id;
            $this->code         = $o->code;
            $this->contract_id  = $o->contract_id ?? 0;
            $this->client_id    = $o->client_id;
            $this->title        = $o->title;
            $this->description  = $o->description ?? '';
            $this->type         = $o->type;
            $this->priority     = $o->priority;
            $this->status       = $o->status;
            $this->site_address = $o->site_address ?? '';
            $this->reported_by  = $o->reported_by ?? '';
            $this->reported_at  = $o->reported_at?->format('Y-m-d\TH:i');
            $this->due_at       = $o->due_at?->format('Y-m-d\TH:i');
            $this->assigned_to  = $o->assigned_to;
            $this->slaBreached  = $o->isSlaBreached();
            $this->slaHours     = $o->slaHoursRemaining();
        }
    }

    // ── Auto-fill SLA from contract ───────────────────────────────────
    public function updatedContractId($value): void
    {
        $contractId = (int) $value;
        if ($contractId < 1) {
            return;
        }
        $contract = MaintenanceContract::on('tenant')->find($contractId);
        if ($contract) {
            $this->client_id = $contract->client_id;
            if ($contract->response_time && $this->reported_at) {
                $reportedAt = \Carbon\Carbon::parse($this->reported_at);
                $this->due_at = $reportedAt->addHours($contract->response_time)->format('Y-m-d\TH:i');
            }
            if ($contract->sites) {
                $firstSite = collect($contract->sites)->first();
                if ($firstSite) {
                    $this->site_address = $firstSite['address'] ?? '';
                }
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'contract_id'  => ['nullable', 'integer'],
            'client_id'    => ['required', 'integer', 'min:1'],
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'type'         => ['required', 'in:corrective,preventive,emergency'],
            'priority'     => ['required', 'in:low,normal,high,critical'],
            'status'       => ['required', 'in:open,assigned,in_progress,done,closed,cancelled'],
            'site_address' => ['nullable', 'string'],
            'reported_by'  => ['nullable', 'string', 'max:255'],
            'reported_at'  => ['required', 'date'],
            'due_at'       => ['nullable', 'date', 'after:reported_at'],
            'assigned_to'  => ['nullable', 'integer'],
        ];
        if ($this->orderId) {
            $rules['code'] = ['required', 'string', 'max:50',
                Rule::unique(MaintenanceOrder::class, 'code')->ignore($this->orderId)];
        }
        return $rules;
    }

    // ── Workflow actions ──────────────────────────────────────────────
    public function assignOrder(): void
    {
        $this->tenantAuthorize('maintenance.dispatch');
        $order = MaintenanceOrder::on('tenant')->findOrFail($this->orderId);
        try {
            $order->transitionTo('assigned', auth('tenant')->id());
            $this->status = 'assigned';
            notify()->success(__('Ordre assigné.'));

            // Notify the assigned technician
            if ($order->assigned_to) {
                $technician = User::on('tenant')->find($order->assigned_to);
                if ($technician) {
                    $technician->notify(new \App\Notifications\MaintenanceOrderAssigned($order));
                }
            }
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function startOrder(): void
    {
        $this->tenantAuthorize('maintenance.dispatch');
        $order = MaintenanceOrder::on('tenant')->findOrFail($this->orderId);
        try {
            $order->transitionTo('in_progress', auth('tenant')->id());
            $this->status = 'in_progress';
            notify()->success(__('Intervention démarrée.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function closeOrder(): void
    {
        $this->tenantAuthorize('maintenance.edit');
        $order = MaintenanceOrder::on('tenant')->findOrFail($this->orderId);
        try {
            $order->transitionTo('closed', auth('tenant')->id());
            $this->status = 'closed';
            notify()->success(__('Ordre clôturé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function cancelOrder(): void
    {
        $this->tenantAuthorize('maintenance.edit');
        $order = MaintenanceOrder::on('tenant')->findOrFail($this->orderId);
        try {
            $order->transitionTo('cancelled', auth('tenant')->id());
            $this->status = 'cancelled';
            notify()->success(__('Ordre annulé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    // ── Save ──────────────────────────────────────────────────────────
    public function save(): void
    {
        $this->tenantAuthorize($this->orderId ? 'maintenance.edit' : 'maintenance.create');
        $this->validate();

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        $data = [
            'contract_id'  => $this->contract_id ?: null,
            'client_id'    => $this->client_id,
            'title'        => $this->title,
            'description'  => $this->description ?: null,
            'type'         => $this->type,
            'priority'     => $this->priority,
            'site_address' => $this->site_address ?: null,
            'reported_by'  => $this->reported_by ?: null,
            'reported_at'  => $this->reported_at,
            'due_at'       => $this->due_at ?: null,
            'assigned_to'  => $this->assigned_to ?: null,
        ];

        if ($this->orderId) {
            $order = MaintenanceOrder::on('tenant')->findOrFail($this->orderId);
            if ($order->status !== $this->status) {
                try {
                    $order->transitionTo($this->status, auth('tenant')->id());
                } catch (InvalidWorkflowTransitionException $e) {
                    $this->addError('status', $e->getMessage());
                    return;
                }
            }
            $order->update($data);
            notify()->success(__('Ordre mis à jour.'));
            $this->redirect(
                route('tenant.maintenance.orders.edit', ['tenant' => $tenantCode, 'maintenance_order' => $this->orderId]),
                navigate: true
            );
        } else {
            $code  = $this->generateNextCode();
            $order = MaintenanceOrder::create(array_merge($data, [
                'code'   => $code,
                'status' => $this->status,
            ]));
            notify()->success(__('Ordre créé.'));
            $this->redirect(
                route('tenant.maintenance.orders.edit', ['tenant' => $tenantCode, 'maintenance_order' => $order->id]),
                navigate: true
            );
        }
    }

    protected function generateNextCode(): string
    {
        $max = MaintenanceOrder::where('code', 'like', 'OM%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 2))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();
        return 'OM' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        $clients   = Client::on('tenant')->active()->ordered()->get(['id', 'name', 'code']);
        $contracts = MaintenanceContract::on('tenant')->active()->ordered()->get(['id', 'code', 'title', 'client_id']);
        $users     = User::on('tenant')->orderBy('name')->get(['id', 'name']);

        $allowedTransitions = [];
        if ($this->orderId) {
            $order = MaintenanceOrder::on('tenant')->find($this->orderId);
            $allowedTransitions = $order?->allowedTransitions()[$this->status] ?? [];
        }

        return view('inovcom-maintenance::livewire.orders.form', [
            'clients'            => $clients,
            'contracts'          => $contracts,
            'users'              => $users,
            'allowedTransitions' => $allowedTransitions,
        ])->layout('layouts.app', [
            'title'    => $this->orderId ? __('Modifier l\'ordre') : __('Nouvel ordre de maintenance'),
            'subtitle' => $this->code ?: '',
        ]);
    }
}
