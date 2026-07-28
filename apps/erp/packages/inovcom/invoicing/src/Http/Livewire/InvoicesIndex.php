<?php

namespace InovCom\Invoicing\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Clients\Models\Client;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Services\InvoicingService;
use Livewire\Component;
use Livewire\WithPagination;

class InvoicesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $declarationFilter = 'all';
    public ?int $clientFilter = null;
    public int $perPage = 20;

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->declarationFilter = 'all';
        $this->clientFilter = null;
        $this->resetPage();
    }

    public function issue(int $invoiceId): void
    {
        if (!$this->can('invoicing.issue')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $invoice = Invoice::findOrFail($invoiceId);
            app(InvoicingService::class)->issue($invoice);
            session()->flash('success', 'Facture émise.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancel(int $invoiceId): void
    {
        if (!$this->can('invoicing.cancel')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $invoice = Invoice::findOrFail($invoiceId);
            app(InvoicingService::class)->cancel($invoice);
            session()->flash('success', 'Facture annulée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function deleteDraft(int $invoiceId): void
    {
        if (!$this->can('invoicing.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $invoice = Invoice::findOrFail($invoiceId);
            app(InvoicingService::class)->deleteDraft($invoice);
            session()->flash('success', 'Brouillon supprimé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with(['client', 'quotation'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('invoice_number', 'like', $term)
                        ->orWhere('customer_reference', 'like', $term)
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->declarationFilter !== 'all', fn ($q) => $q->where('declaration_type', $this->declarationFilter))
            ->when($this->clientFilter, fn ($q) => $q->where('client_id', $this->clientFilter))
            ->orderByDesc('invoice_date')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        return view('inovcom-invoicing::livewire.invoices.index')
            ->layout('layouts.app', [
                'title' => 'Factures',
                'subtitle' => 'Facturation avec et sans déclaration',
            ])
            ->with([
                'invoices' => $invoices,
                'clients' => Client::where('is_active', true)->orderBy('name')->get(),
                'canCreate' => $this->can('invoicing.create'),
                'canInvoice' => $this->can('invoicing.create'),
                'canUpdate' => $this->can('invoicing.update'),
                'canIssue' => $this->can('invoicing.issue'),
                'canCancel' => $this->can('invoicing.cancel'),
                'canPay' => $this->moduleEnabled('invoice_payments') && $this->can('invoice_payments.receive'),
                'canCollection' => $this->can('invoicing.collection.view'),
            ]);
    }

    private function moduleEnabled(string $key): bool
    {
        return app(\App\Services\ModuleRegistry::class)->isEnabled($key);
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
}
