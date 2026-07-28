<?php

namespace InovCom\Invoicing\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Services\DeliveryNotesService;
use Livewire\Component;

class DeliveryNoteShow extends Component
{
    public int $deliveryNoteId;

    public string $customer_purchase_order = '';

    public bool $show_prices = false;

    public bool $show_discounts = false;

    public function mount(DeliveryNote $deliveryNote): void
    {
        if (!$this->canView()) {
            abort(403);
        }
        $this->deliveryNoteId = $deliveryNote->id;
        $this->syncPrintFieldsFromNote($deliveryNote);
    }

    public function updatedShowPrices(bool $value): void
    {
        if (!$value) {
            $this->show_discounts = false;
        }
    }

    public function savePrintOptions(): void
    {
        if (!$this->canEdit()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $note = DeliveryNote::findOrFail($this->deliveryNoteId);
            app(DeliveryNotesService::class)->updatePrintOptions($note, $this->printOptionsPayload());
            session()->flash('success', 'Options d\'impression enregistrées.');
            $this->syncPrintFieldsFromNote($note->fresh(['quotation', 'invoice']));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function canEdit(): bool
    {
        return $this->canCreate() && DeliveryNote::find($this->deliveryNoteId)?->isDraft();
    }

    public function confirmDelivery(): void
    {
        if (!$this->canConfirm()) {
            session()->flash('error', 'Permission refusée : validation de livraison non autorisée.');
            return;
        }

        try {
            $note = DeliveryNote::findOrFail($this->deliveryNoteId);
            app(DeliveryNotesService::class)->confirmDelivery($note);
            session()->flash('success', 'Livraison validée. Le stock a été mis à jour.');
            $this->redirect(route('tenant.invoicing.deliveries.show', [
                'deliveryNote' => $note->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelDraft(): void
    {
        if (!$this->canCreate()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $note = DeliveryNote::findOrFail($this->deliveryNoteId);
            app(DeliveryNotesService::class)->cancelDraft($note);
            session()->flash('success', 'Brouillon de livraison annulé.');

            if ($note->quotation_id && !$note->invoice_id) {
                $target = route('tenant.quotations.edit', [
                    'quotation' => $note->quotation_id,
                    'tenant' => $this->tenantCode(),
                ]);
            } else {
                $target = route('tenant.invoicing.edit', [
                    'invoice' => $note->invoice_id,
                    'tenant' => $this->tenantCode(),
                ]);
            }

            $this->redirect($target, navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $deliveryNote = DeliveryNote::with([
            'invoice.client',
            'quotation.client',
            'client',
            'lines.invoiceLine',
            'lines.quotationLine',
            'creator',
            'confirmer',
        ])->findOrFail($this->deliveryNoteId);

        $printData = null;
        if ($this->show_prices) {
            $printData = \InovCom\Invoicing\Support\DeliveryNotePrintData::build(
                $deliveryNote,
                trim($this->customer_purchase_order) !== '' ? $this->customer_purchase_order : null
            );
        }

        return view('inovcom-invoicing::livewire.invoices.delivery-show')
            ->layout('layouts.app', [
                'title' => 'BL ' . $deliveryNote->delivery_number,
                'subtitle' => $deliveryNote->invoice?->invoice_number ?? $deliveryNote->quotation?->number,
            ])
            ->with([
                'deliveryNote' => $deliveryNote,
                'printData' => $printData,
                'tenantCode' => $this->tenantCode(),
                'canConfirm' => $this->canConfirm(),
                'canCreate' => $this->canCreate(),
                'canInvoice' => $this->can('invoicing.create'),
                'canEdit' => $this->canCreate() && $deliveryNote->isDraft(),
                'printUrl' => $this->buildPrintUrl($deliveryNote),
            ]);
    }

    private function syncPrintFieldsFromNote(DeliveryNote $note): void
    {
        $note->loadMissing(['quotation', 'invoice']);
        $this->customer_purchase_order = (string) ($note->customer_purchase_order ?? '');
        if (trim($this->customer_purchase_order) === '') {
            $this->customer_purchase_order = (string) ($note->quotation?->customer_purchase_order ?? $note->invoice?->customer_reference ?? '');
        }
        $this->show_prices = (bool) ($note->show_prices ?? false);
        $this->show_discounts = (bool) ($note->show_discounts ?? false);
    }

    /**
     * @return array{customer_purchase_order: ?string, show_prices: bool, show_discounts: bool}
     */
    private function printOptionsPayload(): array
    {
        $po = trim($this->customer_purchase_order);

        return [
            'customer_purchase_order' => $po !== '' ? $po : null,
            'show_prices' => $this->show_prices,
            'show_discounts' => $this->show_discounts,
        ];
    }

    private function buildPrintUrl(DeliveryNote $note): string
    {
        $query = array_merge(
            ['tenant' => $this->tenantCode()],
            \InovCom\Invoicing\Support\DeliveryNotePrintSettings::printRouteQuery($note)
        );

        if ($this->show_prices) {
            $query['show_prices'] = 1;
            if ($this->show_discounts) {
                $query['show_discounts'] = 1;
            }
        }

        $po = trim($this->customer_purchase_order);
        if ($po !== '') {
            $query['purchase_order'] = $po;
        }

        return route('tenant.invoicing.deliveries.print', ['deliveryNote' => $note->id]) . '?' . http_build_query($query);
    }

    private function canView(): bool
    {
        return $this->can('invoicing.delivery.view')
            || $this->can('invoicing.delivery.create')
            || $this->can('invoicing.delivery.confirm');
    }

    private function canConfirm(): bool
    {
        return $this->can('invoicing.delivery.confirm');
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
}
