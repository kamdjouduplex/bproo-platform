<?php

namespace InovCom\Achats\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Achats\Models\PurchaseOrder;
use InovCom\Achats\Models\PurchaseOrderLine;
use InovCom\Achats\Models\Supplier;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Projets\Models\Project;
use Livewire\Component;

class PurchaseOrderForm extends Component
{
    use AuthorizesWithTenant;

    public ?int    $purchaseOrderId = null;
    public string  $code            = '';
    public int     $supplier_id     = 0;
    public ?int    $project_id      = null;
    public string  $status          = 'draft';
    public ?string $ordered_at      = null;
    public ?string $received_at     = null;
    public string  $notes           = '';
    public array   $lines           = [];

    public function mount(?PurchaseOrder $purchase_order = null): void
    {
        $this->tenantAuthorize('achats.view');

        if ($purchase_order && $purchase_order->exists) {
            $this->purchaseOrderId = $purchase_order->id;
            $this->code            = $purchase_order->code;
            $this->supplier_id     = $purchase_order->supplier_id;
            $this->project_id      = $purchase_order->project_id;
            $this->status          = $purchase_order->status;
            $this->ordered_at      = $purchase_order->ordered_at?->format('Y-m-d');
            $this->received_at     = $purchase_order->received_at?->format('Y-m-d');
            $this->notes           = $purchase_order->notes ?? '';

            foreach ($purchase_order->lines as $line) {
                $this->lines[] = [
                    'description' => $line->description,
                    'quantity'    => (string) $line->quantity,
                    'unit_price'  => (string) $line->unit_price,
                    'amount'      => (string) $line->amount,
                ];
            }
        }

        if (empty($this->lines)) {
            $this->addLine();
        }

        if (!$this->purchaseOrderId && request()->filled('project')) {
            $this->project_id = (int) request()->query('project');
        }
    }

    protected function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'min:1'],
            'project_id'  => ['nullable', 'integer'],
            'ordered_at'  => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'notes'       => ['nullable', 'string'],
        ];
    }

    // ── Line management ───────────────────────────────────────────────────────

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'quantity' => '1', 'unit_price' => '0', 'amount' => '0'];
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
        if (empty($this->lines)) {
            $this->addLine();
        }
    }

    public function updatedLines($value, $key): void
    {
        if (preg_match('/^(\d+)\.(quantity|unit_price)$/', $key, $m)) {
            $idx = (int) $m[1];
            if (!isset($this->lines[$idx])) {
                return;
            }
            $qty   = (float) ($this->lines[$idx]['quantity']   ?? 0);
            $price = (float) ($this->lines[$idx]['unit_price'] ?? 0);
            $this->lines[$idx]['amount'] = (string) round($qty * $price, 2);
        }
    }

    // ── Workflow actions ──────────────────────────────────────────────────────

    public function submitForValidation(): void
    {
        $this->tenantAuthorize('achats.edit');
        $po = PurchaseOrder::on('tenant')->findOrFail($this->purchaseOrderId);
        try {
            $po->transitionTo('pending_validation', auth('tenant')->id());
            $this->status = 'pending_validation';
            notify()->success(__('Bon de commande soumis pour validation.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function approvePO(): void
    {
        $this->tenantAuthorize('achats.edit');
        $po = PurchaseOrder::on('tenant')->findOrFail($this->purchaseOrderId);
        try {
            $po->transitionTo('validated', auth('tenant')->id());
            $this->status = 'validated';
            notify()->success(__('Bon de commande validé.'));
            if ($po->project_id) {
                Project::on('tenant')->find($po->project_id)?->recalculateActualCost();
            }
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function markAsOrdered(): void
    {
        $this->tenantAuthorize('achats.edit');
        $po = PurchaseOrder::on('tenant')->findOrFail($this->purchaseOrderId);
        try {
            $po->transitionTo('ordered', auth('tenant')->id());
            $this->status = 'ordered';
            notify()->success(__('Bon de commande marqué comme commandé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function markAsPartiallyReceived(): void
    {
        $this->tenantAuthorize('achats.edit');
        $po = PurchaseOrder::on('tenant')->findOrFail($this->purchaseOrderId);
        try {
            $po->transitionTo('partially_received', auth('tenant')->id());
            $this->status = 'partially_received';
            notify()->success(__('Bon de commande partiellement reçu.'));
            if ($po->project_id) {
                Project::on('tenant')->find($po->project_id)?->recalculateActualCost();
            }
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function markAsReceived(): void
    {
        $this->tenantAuthorize('achats.edit');
        $po = PurchaseOrder::on('tenant')->findOrFail($this->purchaseOrderId);
        try {
            $po->transitionTo('received', auth('tenant')->id());
            $this->status = 'received';
            notify()->success(__('Bon de commande reçu.'));
            if ($po->project_id) {
                Project::on('tenant')->find($po->project_id)?->recalculateActualCost();
            }
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function backToDraft(): void
    {
        $this->tenantAuthorize('achats.edit');
        $po = PurchaseOrder::on('tenant')->findOrFail($this->purchaseOrderId);
        try {
            $po->transitionTo('draft', auth('tenant')->id());
            $this->status = 'draft';
            notify()->success(__('Bon de commande remis en brouillon.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function cancelPO(): void
    {
        $this->tenantAuthorize('achats.edit');
        $po = PurchaseOrder::on('tenant')->findOrFail($this->purchaseOrderId);
        try {
            $po->transitionTo('cancelled', auth('tenant')->id());
            $this->status = 'cancelled';
            notify()->success(__('Bon de commande annulé.'));
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    // ── Save (data only — status changes go through workflow buttons) ─────────

    public function save(): void
    {
        $this->tenantAuthorize($this->purchaseOrderId ? 'achats.edit' : 'achats.create');
        $this->validate();

        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(app(TenantManager::class)->tenant())->code;

        $totalHt    = 0;
        $validLines = [];
        foreach ($this->lines as $row) {
            $desc = trim($row['description'] ?? '');
            if ($desc === '') {
                continue;
            }
            $qty    = (float) ($row['quantity']   ?? 0);
            $price  = (float) ($row['unit_price'] ?? 0);
            $amount = round($qty * $price, 2);
            $totalHt    += $amount;
            $validLines[] = [
                'description' => $desc,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'amount'      => $amount,
            ];
        }

        $data = [
            'supplier_id' => $this->supplier_id,
            'project_id'  => $this->project_id ?: null,
            'ordered_at'  => $this->ordered_at  ?: null,
            'received_at' => $this->received_at ?: null,
            'total_ht'    => $totalHt,
            'notes'       => $this->notes ?: null,
        ];

        if ($this->purchaseOrderId) {
            $purchaseOrder = PurchaseOrder::on('tenant')->findOrFail($this->purchaseOrderId);
            $purchaseOrder->update($data);
            $purchaseOrder->lines()->delete();
        } else {
            $data['code']   = $this->generateNextPurchaseOrderCode();
            $data['status'] = 'draft';
            $purchaseOrder  = PurchaseOrder::on('tenant')->create($data);
        }

        foreach ($validLines as $pos => $row) {
            PurchaseOrderLine::on('tenant')->create([
                'purchase_order_id' => $purchaseOrder->id,
                'position'          => $pos,
                'description'       => $row['description'],
                'quantity'          => $row['quantity'],
                'unit_price'        => $row['unit_price'],
                'amount'            => $row['amount'],
            ]);
        }

        if ($purchaseOrder->project_id) {
            Project::on('tenant')->find($purchaseOrder->project_id)?->recalculateActualCost();
        }

        notify()->success($this->purchaseOrderId
            ? __('Bon de commande mis à jour.')
            : __('Bon de commande créé.'));

        $this->redirect(
            route('tenant.achats.edit', ['tenant' => $tenantCode, 'purchase_order' => $purchaseOrder->id]),
            navigate: true
        );
    }

    protected function generateNextPurchaseOrderCode(): string
    {
        $max = PurchaseOrder::on('tenant')
            ->where('code', 'like', 'BON%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return 'BON' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $suppliers = Supplier::on('tenant')->active()->ordered()->get(['id', 'name', 'code']);
        $projects  = Project::on('tenant')->ordered()->get(['id', 'code', 'title']);

        $allowedTransitions = [];
        $liveTotal          = collect($this->lines)->sum(fn ($l) => (float) ($l['amount'] ?? 0));

        if ($this->purchaseOrderId) {
            $po = PurchaseOrder::on('tenant')->find($this->purchaseOrderId);
            if ($po) {
                $allowedTransitions = $po->allowedTransitions()[$this->status] ?? [];
                $liveTotal          = (float) $po->total_ht;
            }
        }

        return view('inovcom-achats::livewire.purchase-orders.form', [
            'suppliers'          => $suppliers,
            'projects'           => $projects,
            'allowedTransitions' => $allowedTransitions,
            'liveTotal'          => $liveTotal,
        ])->layout('layouts.app', [
            'title'    => $this->purchaseOrderId
                ? __('Modifier le bon de commande')
                : __('Nouveau bon de commande'),
            'subtitle' => $this->code ?: '',
        ]);
    }
}
