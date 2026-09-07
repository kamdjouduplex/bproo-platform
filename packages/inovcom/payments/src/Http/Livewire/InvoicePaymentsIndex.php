<?php

namespace InovCom\InvoicePayments\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Support\CollectionPeriodRecap;
use InovCom\Invoicing\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoicePaymentsIndex extends Component
{
    use WithPagination;

    public string $tab = 'unpaid';
    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['unpaid', 'collected'], true) ? $tab : 'unpaid';
        $this->search = '';
        $this->resetPage('unpaidPage');
        $this->resetPage('collectedPage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('unpaidPage');
        $this->resetPage('collectedPage');
    }

    public function updatedDateFrom(): void
    {
        $this->resetPages();
    }

    public function updatedDateTo(): void
    {
        $this->resetPages();
    }

    public function updatedPerPage(): void
    {
        $this->resetPages();
    }

    public function setPeriod(string $period): void
    {
        $now = now();
        switch ($period) {
            case 'day':
                $this->dateFrom = $now->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'week':
                $this->dateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'year':
                $this->dateFrom = $now->copy()->startOfYear()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfYear()->format('Y-m-d');
                break;
        }
        $this->resetPages();
    }

    public function clearPeriod(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPages();
    }

    private function resetPages(): void
    {
        $this->resetPage('unpaidPage');
        $this->resetPage('collectedPage');
    }

    public function render()
    {
        $unpaidInvoices = $this->tab === 'unpaid'
            ? $this->unpaidInvoicesQuery()->paginate($this->perPage, pageName: 'unpaidPage')
            : null;

        $payments = $this->tab === 'collected'
            ? $this->paymentsQuery()->paginate($this->perPage, pageName: 'collectedPage')
            : null;

        return view('inovcom-invoice-payments::livewire.index')
            ->layout('layouts.app', [
                'title' => 'Encaissements',
                'subtitle' => 'Factures à encaisser et déjà encaissées',
            ])
            ->with([
                'unpaidCount' => $this->unpaidInvoicesQuery(false)->count(),
                'collectedCount' => $this->paymentsQuery(false)->count(),
                'unpaidInvoices' => $unpaidInvoices,
                'payments' => $payments,
                'fiscalRecap' => $this->tab === 'collected' ? CollectionPeriodRecap::forNow() : null,
                'canReceive' => $this->can('invoice_payments.receive'),
                'canManageWithholdings' => $this->can('invoice_payments.manage_withholdings'),
            ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Invoice>
     */
    private function unpaidInvoicesQuery(bool $withSearch = true)
    {
        $term = $withSearch ? trim($this->search) : '';
        $like = '%'.$term.'%';
        $today = now()->toDateString();

        return Invoice::query()
            ->with(['client'])
            ->whereIn('status', ['issued', 'partial'])
            ->where('balance', '>', 0.01)
            ->when($term !== '', function ($q) use ($like) {
                $q->where(function ($q2) use ($like) {
                    $q2->where('invoice_number', 'like', $like)
                        ->orWhere('quotation_reference', 'like', $like)
                        ->orWhere('customer_reference', 'like', $like)
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery
                            ->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like));
                });
            })
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('invoice_date', '<=', $this->dateTo))
            ->orderByRaw(
                'case when due_date is not null and due_date < ? then 0 else 1 end',
                [$today]
            )
            ->orderByRaw('due_date asc nulls last')
            ->orderBy('invoice_date');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<InvoicePayment>
     */
    private function paymentsQuery(bool $withSearch = true)
    {
        $term = $withSearch ? trim($this->search) : '';
        $like = '%'.$term.'%';

        return InvoicePayment::query()
            ->with(array_merge(
                ['invoice.client', 'invoice.quotation'],
                InvoicePayment::optionalWithholdingsRelation(),
            ))
            ->active()
            ->when($term !== '', function ($q) use ($like) {
                $q->where(function ($q2) use ($like) {
                    $q2->where('reference', 'like', $like)
                        ->orWhereHas('invoice', function ($invoiceQuery) use ($like) {
                            $invoiceQuery->where('invoice_number', 'like', $like)
                                ->orWhere('quotation_reference', 'like', $like)
                                ->orWhere('customer_reference', 'like', $like)
                                ->orWhere('delivery_note_number', 'like', $like)
                                ->orWhereHas('client', fn ($clientQuery) => $clientQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('code', 'like', $like))
                                ->orWhereHas('quotation', fn ($quotationQuery) => $quotationQuery
                                    ->where('number', 'like', $like)
                                    ->orWhere('customer_purchase_order', 'like', $like));
                        });
                });
            })
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
