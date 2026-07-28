<?php

namespace InovCom\Returns\Http\Livewire;

use InovCom\Invoicing\Models\Invoice;
use InovCom\Returns\Concerns\AuthorizesReturnActions;
use InovCom\Returns\Models\ReturnReason;
use InovCom\Returns\Services\ReturnService;
use Livewire\Component;

class ReturnCreate extends Component
{
    use AuthorizesReturnActions;

    public ?int $invoiceId = null;
    public string $invoiceSearch = '';

    /** @var array<int, array{quantity: string, reason_id: ?int, returnable: float, name: string}> */
    public array $lines = [];

    public ?int $reasonId = null;
    public string $notes = '';
    public string $returnDate = '';

    public function mount(?int $invoice = null): void
    {
        $this->returnDate = now()->toDateString();

        if ($invoice) {
            $this->selectInvoice($invoice);
        }
    }

    public function selectInvoice(int $invoiceId): void
    {
        $invoice = Invoice::on('tenant')->with('lines')->find($invoiceId);
        if (! $invoice) {
            session()->flash('error', 'Facture introuvable.');

            return;
        }

        $this->invoiceId = $invoiceId;
        $this->lines = [];

        foreach (app(ReturnService::class)->returnableForInvoice($invoice) as $lineId => $info) {
            $this->lines[$lineId] = [
                'quantity' => '',
                'reason_id' => null,
                'returnable' => $info['returnable'],
                'name' => $info['line']->item_name,
            ];
        }
    }

    public function clearInvoice(): void
    {
        $this->invoiceId = null;
        $this->lines = [];
    }

    public function save()
    {
        if (! $this->can('returns.create')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        if (! $this->invoiceId) {
            session()->flash('error', 'Sélectionnez une facture.');

            return null;
        }

        $payload = [];
        foreach ($this->lines as $lineId => $row) {
            $qty = (float) ($row['quantity'] ?: 0);
            if ($qty > 0) {
                $payload[$lineId] = ['quantity' => $qty, 'reason_id' => $row['reason_id'] ?: null];
            }
        }

        try {
            $return = app(ReturnService::class)->createFromInvoice(
                $this->invoiceId,
                $payload,
                [
                    'reason_id' => $this->reasonId,
                    'notes' => $this->notes ?: null,
                    'return_date' => $this->returnDate ?: null,
                ],
                $this->tenantUserId(),
            );

            session()->flash('success', 'Retour ' . $return->return_number . ' créé.');

            return redirect()->route('tenant.returns.show', [
                $return->id,
                'tenant' => request()->query('tenant') ?? session('tenant_code'),
            ]);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return null;
        }
    }

    public function render()
    {
        $invoice = $this->invoiceId
            ? Invoice::on('tenant')->with('lines', 'client')->find($this->invoiceId)
            : null;

        return view('inovcom-returns::livewire.returns.create')
            ->layout('layouts.app', [
                'title' => 'Nouveau retour',
                'subtitle' => 'Saisir une demande de retour',
            ])
            ->with([
                'invoice' => $invoice,
                'invoices' => $this->invoiceOptions(),
                'reasons' => ReturnReason::query()->where('is_active', true)->orderBy('sort_order')->get(),
            ]);
    }

    private function invoiceOptions()
    {
        if ($this->invoiceId) {
            return collect();
        }

        return Invoice::on('tenant')
            ->with('client')
            ->whereIn('status', ['issued', 'partial', 'paid'])
            ->when($this->invoiceSearch !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('invoice_number', 'like', '%' . $this->invoiceSearch . '%')
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', '%' . $this->invoiceSearch . '%'));
                });
            })
            ->orderByDesc('invoice_date')
            ->limit(25)
            ->get();
    }
}
