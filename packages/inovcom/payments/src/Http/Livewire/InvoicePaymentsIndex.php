<?php

namespace InovCom\InvoicePayments\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Services\InvoicePaymentsService;
use InovCom\Invoicing\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoicePaymentsIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $invoiceStatusFilter = 'unpaid';
    public int $perPage = 20;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedInvoiceStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->search);

        $invoices = Invoice::query()
            ->with(['client'])
            ->when($term !== '', function ($q) use ($term) {
                $like = '%' . $term . '%';
                $q->where(function ($q2) use ($like) {
                    $q2->where('invoice_number', 'like', $like)
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like));
                });
            })
            ->tap(fn ($q) => $this->applyPaymentStatusFilter($q))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $recentPayments = InvoicePayment::with(['invoice.client', 'creator'])
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('inovcom-invoice-payments::livewire.index')
            ->layout('layouts.app', [
                'title' => 'Paiements factures',
                'subtitle' => 'Suivi des encaissements',
            ])
            ->with([
                'invoices' => $invoices,
                'recentPayments' => $recentPayments,
                'totalOutstanding' => app(InvoicePaymentsService::class)->getOutstandingTotal(),
                'canReceive' => $this->can('invoice_payments.receive'),
                'canManageWithholdings' => $this->can('invoice_payments.manage_withholdings'),
            ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     */
    private function applyPaymentStatusFilter($query): void
    {
        match ($this->invoiceStatusFilter) {
            'paid' => $query->where('status', 'paid'),
            'all' => $query->whereNotIn('status', ['draft', 'cancelled']),
            'unpaid' => $query->whereIn('status', ['issued', 'partial']),
            default => $query->whereIn('status', ['issued', 'partial']),
        };
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
