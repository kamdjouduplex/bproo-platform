<?php

namespace InovCom\Sales\Http\Livewire;

use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleReturn;
use InovCom\Sales\Services\SaleReturnsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SaleReturnForm extends Component
{
    public int $saleId;

    /** @var array<int, array<string, mixed>> */
    public array $lineRows = [];

    public string $reason = '';
    public string $notes = '';

    public function mount(Sale $sale): void
    {
        if (!$this->canReturn()) {
            abort(403);
        }

        $this->saleId = $sale->id;
        $service = app(SaleReturnsService::class);

        $sale->load('lines');
        foreach ($sale->lines as $line) {
            $returnable = $service->returnableQuantity($line);
            if ($returnable <= 0) {
                continue;
            }
            $this->lineRows[] = [
                'sale_line_id' => $line->id,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'sold_qty' => (float) $line->quantity,
                'returnable_qty' => $returnable,
                'unit_name' => $line->unit_name,
                'quantity' => '0',
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
            ];
        }
    }

    public function selectFullReturn(): void
    {
        foreach ($this->lineRows as $i => $row) {
            $this->lineRows[$i]['quantity'] = (string) $row['returnable_qty'];
        }
    }

    public function confirmReturn(): void
    {
        if (!$this->canReturn()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $inputs = [];
        foreach ($this->lineRows as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $inputs[] = [
                'sale_line_id' => (int) $row['sale_line_id'],
                'quantity' => $qty,
            ];
        }

        if ($inputs === []) {
            session()->flash('error', 'Indiquez la quantité à retourner pour au moins une ligne.');
            return;
        }

        $sale = Sale::with(['lines', 'payments', 'client'])->findOrFail($this->saleId);

        try {
            $saleReturn = app(SaleReturnsService::class)->createReturn(
                $sale,
                $inputs,
                trim($this->reason) !== '' ? trim($this->reason) : null,
                trim($this->notes) !== '' ? trim($this->notes) : null
            );

            session()->flash('success', 'Retour enregistré : ' . $saleReturn->return_number);
            $this->redirect(route('tenant.sales.returns.show', [
                'saleReturn' => $saleReturn->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function getEstimatedRefundProperty(): float
    {
        $sale = Sale::find($this->saleId);
        if (!$sale) {
            return 0.0;
        }

        $subtotal = 0.0;
        foreach ($this->lineRows as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $sold = (float) ($row['sold_qty'] ?? 0);
            if ($qty <= 0 || $sold <= 0) {
                continue;
            }
            $subtotal += round(((float) $row['line_total']) * ($qty / $sold), 2);
        }

        $saleSubtotal = (float) $sale->subtotal;
        $discount = 0.0;
        if ($saleSubtotal > 0 && (float) $sale->discount_amount > 0) {
            $discount = round(((float) $sale->discount_amount) * ($subtotal / $saleSubtotal), 2);
        }

        return max(0, round($subtotal - $discount, 2));
    }

    public function render()
    {
        $sale = Sale::with(['lines', 'payments', 'client', 'confirmedReturns.lines'])
            ->findOrFail($this->saleId);

        return view('inovcom-sales::livewire.sales.return-form')
            ->layout('layouts.app', [
                'title' => 'Retour produit',
                'subtitle' => $sale->sale_number,
            ])
            ->with([
                'sale' => $sale,
                'tenantCode' => $this->tenantCode(),
                'estimatedRefund' => $this->estimatedRefund,
            ]);
    }

    private function canReturn(): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission')
            && ($user->hasPermission('sales.return') || $user->hasPermission('sales.update'));
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
