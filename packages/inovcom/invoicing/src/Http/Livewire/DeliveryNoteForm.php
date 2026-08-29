<?php

namespace InovCom\Invoicing\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Services\DeliveryNotesService;
use InovCom\Quotations\Models\Quotation;
use Livewire\Component;

class DeliveryNoteForm extends Component
{
    public ?int $invoiceId = null;
    public ?int $quotationId = null;
    public ?int $deliveryNoteId = null;

    /** invoice | quotation */
    public string $mode = 'invoice';

    /** @var array<int, array<string, mixed>> */
    public array $lineRows = [];

    public string $notes = '';

    public string $customer_purchase_order = '';

    public bool $show_prices = false;

    public bool $show_discounts = false;

    public function mount(?Invoice $invoice = null, ?DeliveryNote $deliveryNote = null): void
    {
        if (!$this->canCreate()) {
            abort(403);
        }

        $service = app(DeliveryNotesService::class);

        if ($deliveryNote) {
            if (!$deliveryNote->isDraft()) {
                abort(404);
            }
            $deliveryNote->load('lines');
            $this->deliveryNoteId = $deliveryNote->id;
            $this->notes = (string) ($deliveryNote->notes ?? '');
            $this->loadPrintOptionsFromNote($deliveryNote);

            if ($deliveryNote->quotation_id && !$deliveryNote->invoice_id) {
                $this->mode = 'quotation';
                $quotation = Quotation::with('lines')->findOrFail($deliveryNote->quotation_id);
                $this->quotationId = $quotation->id;
                $this->loadQuotationRows($service, $quotation, $deliveryNote);
                return;
            }

            $invoice = Invoice::with('lines')->findOrFail($deliveryNote->invoice_id);
            $this->mode = 'invoice';
            $this->invoiceId = $invoice->id;
            $this->loadInvoiceRows($service, $invoice, $deliveryNote);
            return;
        }

        $quotationId = request()->query('quotation_id');
        if ($quotationId) {
            $quotation = Quotation::with('lines')->findOrFail((int) $quotationId);
            if (!$quotation->isAccepted()) {
                abort(403);
            }
            $existingInvoice = Invoice::openForQuotation($quotation->id);
            if ($existingInvoice && in_array($existingInvoice->status, ['issued', 'partial', 'paid'], true)) {
                $this->redirect(route('tenant.invoicing.deliveries.create', [
                    'invoice' => $existingInvoice->id,
                    'tenant' => request()->query('tenant'),
                ]), navigate: true);

                return;
            }
            $this->mode = 'quotation';
            $this->quotationId = $quotation->id;
            $this->prefillPurchaseOrderFromQuotation($quotation);
            $this->loadQuotationRows($service, $quotation, null);
            return;
        }

        if (!$invoice) {
            abort(404);
        }

        if ($service->invoiceDeliveryProgress($invoice)['remaining'] <= 0.0001) {
            session()->flash('error', 'Cette facture est déjà entièrement livrée. Aucun nouveau bon de livraison n’est nécessaire.');
            $this->redirect(route('tenant.invoicing.edit', [
                $invoice->id,
                'tenant' => request()->query('tenant'),
            ]), navigate: true);

            return;
        }

        $this->mode = 'invoice';
        $this->invoiceId = $invoice->id;
        $this->prefillPurchaseOrderFromInvoice($invoice);
        $this->loadInvoiceRows($service, $invoice, null);
    }

    public function updatedShowPrices(bool $value): void
    {
        if (!$value) {
            $this->show_discounts = false;
        }
    }

    public function selectFullDelivery(): void
    {
        foreach ($this->lineRows as $i => $row) {
            $this->lineRows[$i]['quantity'] = (string) $row['deliverable_qty'];
        }
    }

    public function saveDraft(): void
    {
        if (!$this->canCreate()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $inputs = $this->collectLineInputs();
        if ($inputs === []) {
            session()->flash('error', 'Indiquez au moins une quantité à livrer.');
            return;
        }

        $service = app(DeliveryNotesService::class);
        $notes = trim($this->notes) !== '' ? trim($this->notes) : null;
        $options = $this->printOptionsForSave();

        try {
            if ($this->mode === 'quotation') {
                if ($this->deliveryNoteId) {
                    $note = DeliveryNote::findOrFail($this->deliveryNoteId);
                    $service->updateDraftFromQuotation($note, $inputs, $notes, $options);
                    session()->flash('success', 'Brouillon de livraison mis à jour.');
                } else {
                    $quotation = Quotation::with('lines')->findOrFail($this->quotationId);
                    $note = $service->createDraftFromQuotation($quotation, $inputs, $notes, null, $options);
                    session()->flash('success', 'Brouillon créé : ' . $note->delivery_number);
                }
            } else {
                $invoice = Invoice::with('lines')->findOrFail($this->invoiceId);
                if ($this->deliveryNoteId) {
                    $note = DeliveryNote::findOrFail($this->deliveryNoteId);
                    $service->updateDraft($note, $inputs, $notes, $options);
                    session()->flash('success', 'Brouillon de livraison mis à jour.');
                } else {
                    $note = $service->createDraft($invoice, $inputs, $notes, null, $options);
                    session()->flash('success', 'Brouillon créé : ' . $note->delivery_number);
                }
            }

            $this->redirect(route('tenant.invoicing.deliveries.show', [
                'deliveryNote' => $note->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $tenantCode = $this->tenantCode();

        if ($this->mode === 'quotation') {
            $quotation = Quotation::with('client')->findOrFail($this->quotationId);

            return view('inovcom-invoicing::livewire.invoices.delivery-form')
                ->layout('layouts.app', [
                    'title' => $this->deliveryNoteId ? 'Modifier livraison' : 'Nouvelle livraison',
                    'subtitle' => 'Devis ' . $quotation->number,
                ])
                ->with([
                    'sourceLabel' => 'Devis',
                    'sourceNumber' => $quotation->number,
                    'clientName' => $quotation->client?->name,
                    'backUrl' => route('tenant.quotations.edit', ['quotation' => $quotation->id, 'tenant' => $tenantCode]),
                    'tenantCode' => $tenantCode,
                ]);
        }

        $invoice = Invoice::with(['client', 'lines'])->findOrFail($this->invoiceId);

        return view('inovcom-invoicing::livewire.invoices.delivery-form')
            ->layout('layouts.app', [
                'title' => $this->deliveryNoteId ? 'Modifier livraison' : 'Nouvelle livraison',
                'subtitle' => 'Facture ' . $invoice->invoice_number,
            ])
            ->with([
                'sourceLabel' => 'Facture',
                'sourceNumber' => $invoice->invoice_number,
                'clientName' => $invoice->client?->name,
                'backUrl' => route('tenant.invoicing.edit', [$invoice->id, 'tenant' => $tenantCode]),
                'tenantCode' => $tenantCode,
            ]);
    }

    private function loadInvoiceRows(DeliveryNotesService $service, Invoice $invoice, ?DeliveryNote $note): void
    {
        $excludeId = $note?->id;
        $draftQtys = [];
        if ($note) {
            foreach ($note->lines as $line) {
                if ($line->invoice_line_id) {
                    $draftQtys[$line->invoice_line_id] = (float) $line->quantity;
                }
            }
        }

        $this->lineRows = [];
        foreach ($invoice->lines as $line) {
            $deliverable = $service->deliverableQuantity($line, true, $excludeId);
            if ($deliverable <= 0 && !isset($draftQtys[$line->id])) {
                continue;
            }

            $this->lineRows[] = [
                'line_id' => $line->id,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'invoiced_qty' => (float) $line->quantity,
                'already_delivered' => $service->deliveredQuantityForLine($line->id, false, $excludeId),
                'deliverable_qty' => $deliverable,
                'quantity' => isset($draftQtys[$line->id])
                    ? (string) $draftQtys[$line->id]
                    : (string) $deliverable,
            ];
        }
    }

    private function loadQuotationRows(DeliveryNotesService $service, Quotation $quotation, ?DeliveryNote $note): void
    {
        $excludeId = $note?->id;
        $draftQtys = [];
        if ($note) {
            foreach ($note->lines as $line) {
                if ($line->quotation_line_id) {
                    $draftQtys[$line->quotation_line_id] = (float) $line->quantity;
                }
            }
        }

        $this->lineRows = [];
        foreach ($quotation->lines as $line) {
            $deliverable = $service->deliverableQuantityForQuotationLine($line, true, $excludeId);
            if ($deliverable <= 0 && !isset($draftQtys[$line->id])) {
                continue;
            }

            $this->lineRows[] = [
                'line_id' => $line->id,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'invoiced_qty' => (float) $line->quantity,
                'already_delivered' => $service->deliveredQuantityForQuotationLine($line->id, false, $excludeId),
                'deliverable_qty' => $deliverable,
                'quantity' => isset($draftQtys[$line->id])
                    ? (string) $draftQtys[$line->id]
                    : (string) $deliverable,
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectLineInputs(): array
    {
        $key = $this->mode === 'quotation' ? 'quotation_line_id' : 'invoice_line_id';
        $inputs = [];
        foreach ($this->lineRows as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty > 0) {
                $inputs[] = [
                    $key => (int) $row['line_id'],
                    'quantity' => $qty,
                ];
            }
        }

        return $inputs;
    }

    private function canCreate(): bool
    {
        return $this->can('invoicing.delivery.create');
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
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

    private function loadPrintOptionsFromNote(DeliveryNote $note): void
    {
        $this->customer_purchase_order = (string) ($note->customer_purchase_order ?? '');
        $this->show_prices = (bool) ($note->show_prices ?? false);
        $this->show_discounts = (bool) ($note->show_discounts ?? false);
    }

    private function prefillPurchaseOrderFromQuotation(Quotation $quotation): void
    {
        if (trim($this->customer_purchase_order) !== '') {
            return;
        }
        $this->customer_purchase_order = (string) ($quotation->customer_purchase_order ?? '');
    }

    private function prefillPurchaseOrderFromInvoice(Invoice $invoice): void
    {
        if (trim($this->customer_purchase_order) !== '') {
            return;
        }
        $this->customer_purchase_order = (string) ($invoice->customer_reference ?? '');
    }

    /**
     * @return array{customer_purchase_order: ?string, show_prices: bool, show_discounts: bool}
     */
    private function printOptionsForSave(): array
    {
        $po = trim($this->customer_purchase_order);

        return [
            'customer_purchase_order' => $po !== '' ? $po : null,
            'show_prices' => $this->show_prices,
            'show_discounts' => $this->show_discounts,
        ];
    }
}
