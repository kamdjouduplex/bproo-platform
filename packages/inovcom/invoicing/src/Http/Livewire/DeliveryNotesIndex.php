<?php

namespace InovCom\Invoicing\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class DeliveryNotesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public int $perPage = 20;

    public function mount(): void
    {
        $status = request()->query('status');
        if (is_string($status) && $status !== '') {
            $this->status = $status;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        if (!$this->canView()) {
            abort(403);
        }

        $query = DeliveryNote::query()
            ->with(['invoice.client', 'invoice', 'quotation.client', 'quotation', 'client'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('delivery_number', 'like', $term)
                        ->orWhereHas('invoice', fn ($iq) => $iq->where('invoice_number', 'like', $term))
                        ->orWhereHas('quotation', fn ($qq) => $qq->where('number', 'like', $term));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('created_at');

        $notes = $query->paginate($this->perPage);
        $quotationIds = collect($notes->items())->pluck('quotation_id')->filter()->unique()->values();
        $invoicesByQuotation = $quotationIds->isEmpty()
            ? collect()
            : Invoice::query()
                ->whereIn('quotation_id', $quotationIds)
                ->whereNotIn('status', ['cancelled'])
                ->orderBy('id')
                ->get()
                ->unique('quotation_id')
                ->keyBy('quotation_id');

        return view('inovcom-invoicing::livewire.invoices.deliveries-index')
            ->layout('layouts.app', [
                'title' => 'Bons de livraison',
                'subtitle' => 'Livraisons facturation B2B',
            ])
            ->with([
                'notes' => $notes,
                'tenantCode' => $this->tenantCode(),
                'canInvoice' => $this->can('invoicing.create'),
                'invoicesByQuotation' => $invoicesByQuotation,
            ]);
    }

    private function canView(): bool
    {
        return $this->can('invoicing.delivery.view')
            || $this->can('invoicing.delivery.create')
            || $this->can('invoicing.delivery.confirm');
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
