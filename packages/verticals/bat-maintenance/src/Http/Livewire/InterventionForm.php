<?php

namespace InovCom\Maintenance\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Maintenance\Models\Intervention;
use InovCom\Maintenance\Models\MaintenanceOrder;
use InovCom\Users\Models\User;
use Livewire\Component;

class InterventionForm extends Component
{
    use AuthorizesWithTenant;

    // ── Identity ──────────────────────────────────────────────────────
    public ?int   $interventionId    = null;
    public ?int   $maintenanceOrderId = null;

    // ── Core fields ───────────────────────────────────────────────────
    public int     $technician_id    = 0;
    public string  $status           = 'scheduled';
    public ?string $scheduled_at     = null;
    public ?string $started_at       = null;
    public ?string $completed_at     = null;
    public string  $duration_minutes = '';
    public string  $work_done        = '';
    public string  $findings         = '';
    public string  $next_action      = '';
    public string  $client_signature = '';
    public ?string $signed_at        = null;

    // ── Materials (managed as collection of rows) ─────────────────────
    public array   $materials        = [];  // [{item_id, description, qty, unit_price}]

    // ── Read-only order context ───────────────────────────────────────
    public ?string $orderCode        = null;
    public ?string $orderTitle       = null;

    public function mount(?MaintenanceOrder $maintenance_order = null, ?Intervention $intervention = null): void
    {
        $this->tenantAuthorize('maintenance.view');

        $this->scheduled_at = now()->format('Y-m-d\TH:i');

        if ($maintenance_order && $maintenance_order->exists) {
            $this->maintenanceOrderId = $maintenance_order->id;
            $this->orderCode          = $maintenance_order->code;
            $this->orderTitle         = $maintenance_order->title;
        }

        if ($intervention && $intervention->exists) {
            $i = $intervention;
            $this->interventionId     = $i->id;
            $this->maintenanceOrderId = $i->maintenance_order_id;
            $this->orderCode          = $i->order->code ?? null;
            $this->orderTitle         = $i->order->title ?? null;
            $this->technician_id      = $i->technician_id;
            $this->status             = $i->status;
            $this->scheduled_at       = $i->scheduled_at?->format('Y-m-d\TH:i');
            $this->started_at         = $i->started_at?->format('Y-m-d\TH:i');
            $this->completed_at       = $i->completed_at?->format('Y-m-d\TH:i');
            $this->duration_minutes   = (string) ($i->duration_minutes ?? '');
            $this->work_done          = $i->work_done ?? '';
            $this->findings           = $i->findings ?? '';
            $this->next_action        = $i->next_action ?? '';
            $this->client_signature   = $i->client_signature ?? '';
            $this->signed_at          = $i->signed_at?->format('Y-m-d\TH:i');
            $this->materials          = $i->materials_used ?? [];
        }
    }

    // ── Materials management ──────────────────────────────────────────
    public function addMaterialRow(): void
    {
        $this->materials[] = ['description' => '', 'qty' => 1, 'unit_price' => 0];
    }

    public function removeMaterialRow(int $index): void
    {
        unset($this->materials[$index]);
        $this->materials = array_values($this->materials);
    }

    // ── Workflow actions ──────────────────────────────────────────────
    public function startIntervention(): void
    {
        $this->tenantAuthorize('maintenance.dispatch');
        $intervention = Intervention::on('tenant')->findOrFail($this->interventionId);
        try {
            $intervention->transitionTo('in_progress', auth('tenant')->id());
            $this->status     = 'in_progress';
            $this->started_at = now()->format('Y-m-d\TH:i');
            notify()->success(__('Intervention démarrée.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function completeIntervention(): void
    {
        $this->tenantAuthorize('maintenance.dispatch');
        $intervention = Intervention::on('tenant')->findOrFail($this->interventionId);
        try {
            $intervention->transitionTo('done', auth('tenant')->id());
            $this->status       = 'done';
            $this->completed_at = now()->format('Y-m-d\TH:i');
            notify()->success(__('Intervention terminée.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function rules(): array
    {
        return [
            'technician_id'    => ['required', 'integer', 'min:1'],
            'status'           => ['required', 'in:scheduled,in_progress,done'],
            'scheduled_at'     => ['required', 'date'],
            'started_at'       => ['nullable', 'date'],
            'completed_at'     => ['nullable', 'date', 'after_or_equal:started_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'work_done'        => ['nullable', 'string'],
            'findings'         => ['nullable', 'string'],
            'next_action'      => ['nullable', 'string'],
            'client_signature' => ['nullable', 'string'],
            'signed_at'        => ['nullable', 'date'],
            'materials'        => ['nullable', 'array'],
            'materials.*.description' => ['nullable', 'string', 'max:255'],
            'materials.*.qty'         => ['nullable', 'numeric', 'min:0'],
            'materials.*.unit_price'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function save(): void
    {
        $this->tenantAuthorize($this->interventionId ? 'maintenance.edit' : 'maintenance.create');
        $this->validate();

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        // Auto-compute duration if both timestamps present and not set manually
        $duration = $this->duration_minutes !== ''
            ? (int) $this->duration_minutes
            : null;

        if (!$duration && $this->started_at && $this->completed_at) {
            $duration = (int) \Carbon\Carbon::parse($this->started_at)
                ->diffInMinutes(\Carbon\Carbon::parse($this->completed_at));
        }

        $data = [
            'maintenance_order_id' => $this->maintenanceOrderId,
            'technician_id'        => $this->technician_id,
            'scheduled_at'         => $this->scheduled_at,
            'started_at'           => $this->started_at ?: null,
            'completed_at'         => $this->completed_at ?: null,
            'duration_minutes'     => $duration,
            'work_done'            => $this->work_done ?: null,
            'findings'             => $this->findings ?: null,
            'next_action'          => $this->next_action ?: null,
            'client_signature'     => $this->client_signature ?: null,
            'signed_at'            => $this->signed_at ?: null,
            'materials_used'       => $this->materials ?: null,
        ];

        if ($this->interventionId) {
            $intervention = Intervention::on('tenant')->findOrFail($this->interventionId);
            if ($intervention->status !== $this->status) {
                try {
                    $intervention->transitionTo($this->status, auth('tenant')->id());
                } catch (InvalidWorkflowTransitionException $e) {
                    $this->addError('status', $e->getMessage());
                    return;
                }
            }
            $intervention->update($data);
            notify()->success(__('Intervention mise à jour.'));
            $this->redirect(
                route('tenant.maintenance.interventions.edit', ['tenant' => $tenantCode, 'intervention' => $this->interventionId]),
                navigate: true
            );
        } else {
            $intervention = Intervention::create(array_merge($data, ['status' => $this->status]));
            notify()->success(__('Intervention planifiée.'));
            $this->redirect(
                route('tenant.maintenance.interventions.edit', ['tenant' => $tenantCode, 'intervention' => $intervention->id]),
                navigate: true
            );
        }
    }

    public function render()
    {
        $users = User::on('tenant')->orderBy('name')->get(['id', 'name']);

        $allowedTransitions = [];
        if ($this->interventionId) {
            $intervention = Intervention::on('tenant')->find($this->interventionId);
            $allowedTransitions = $intervention?->allowedTransitions()[$this->status] ?? [];
        }

        $materialsCost = collect($this->materials)->sum(fn ($m) => ($m['qty'] ?? 0) * ($m['unit_price'] ?? 0));

        return view('inovcom-maintenance::livewire.interventions.form', [
            'users'              => $users,
            'allowedTransitions' => $allowedTransitions,
            'materialsCost'      => $materialsCost,
        ])->layout('layouts.app', [
            'title'    => $this->interventionId ? __('Modifier l\'intervention') : __('Planifier une intervention'),
            'subtitle' => $this->orderCode ? "OM #{$this->orderCode}" : '',
        ]);
    }
}
