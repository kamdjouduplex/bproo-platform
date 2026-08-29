<?php

namespace InovCom\Invoicing\Http\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\Clients\Models\Client;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Services\DeliveryNotesService;
use InovCom\Invoicing\Services\InvoicingService;
use Livewire\Component;
use Livewire\WithPagination;

class InvoicesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $declarationFilter = 'all';
    public string $clientFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;
    public bool $showAdvancedFilters = false;

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'statusFilter', 'declarationFilter', 'clientFilter', 'dateFrom', 'dateTo', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function setStatusFilter(string $value): void
    {
        $this->statusFilter = $this->statusFilter === $value && $value !== 'all' ? 'all' : $value;
        $this->resetPage();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = !$this->showAdvancedFilters;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->declarationFilter = 'all';
        $this->clientFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function clearPeriod(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
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
        $invoices = $this->invoicesQuery()
            ->orderByDesc('invoice_date')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $deliveryByInvoice = [];
        if (Schema::connection('tenant')->hasTable('delivery_notes')) {
            $deliveryService = app(DeliveryNotesService::class);
            foreach ($invoices as $inv) {
                $deliveryByInvoice[$inv->id] = $deliveryService->invoiceDeliveryProgress($inv);
            }
        }

        return view('inovcom-invoicing::livewire.invoices.index')
            ->layout('layouts.app', [
                'title' => 'Factures',
                'subtitle' => 'Suivi, encaissement et relance',
            ])
            ->with([
                'invoices' => $invoices,
                'deliveryByInvoice' => $deliveryByInvoice,
                'clients' => Client::where('is_active', true)->orderBy('name')->get(),
                'kpis' => $this->kpis(),
                'activeFiltersCount' => $this->activeFiltersCount(),
                'canCreate' => $this->can('invoicing.create'),
                'canInvoice' => $this->can('invoicing.create'),
                'canUpdate' => $this->can('invoicing.update'),
                'canIssue' => $this->can('invoicing.issue'),
                'canCancel' => $this->can('invoicing.cancel'),
                'canPay' => $this->moduleEnabled('invoice_payments') && $this->can('invoice_payments.receive'),
                'canCollection' => $this->can('invoicing.collection.view'),
            ]);
    }

    private function invoicesQuery(): Builder
    {
        $with = ['client', 'quotation', 'lines'];
        if (Schema::connection('tenant')->hasTable('invoice_schedules')) {
            $with[] = 'schedules';
        }

        $query = Invoice::query()
            ->with($with)
            ->when($this->search !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('invoice_number', 'like', $term)
                        ->orWhere('customer_reference', 'like', $term)
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term));
                });
            })
            ->when($this->declarationFilter !== 'all', fn ($q) => $q->where('declaration_type', $this->declarationFilter))
            ->when($this->clientFilter !== '', fn ($q) => $q->where('client_id', (int) $this->clientFilter))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('invoice_date', '<=', $this->dateTo));

        $this->applyStatusBucket($query);

        return $query;
    }

    private function applyStatusBucket(Builder $query): void
    {
        match ($this->statusFilter) {
            'collect' => $query->whereIn('status', ['issued', 'partial'])->where('balance', '>', 0.01),
            'overdue' => $this->constrainOverdue($query),
            'paid' => $query->where('status', 'paid'),
            'draft' => $query->where('status', 'draft'),
            'cancelled' => $query->where('status', 'cancelled'),
            'issued' => $query->where('status', 'issued'),
            'partial' => $query->where('status', 'partial'),
            default => null,
        };
    }

    private function constrainOverdue(Builder $query): void
    {
        $today = now()->toDateString();

        $query->whereIn('status', ['issued', 'partial'])
            ->where('balance', '>', 0.01)
            ->where(function (Builder $inner) use ($today) {
                $inner->whereDate('due_date', '<', $today)
                    ->orWhere(function (Builder $q2) use ($today) {
                        $q2->whereNull('due_date')->whereDate('invoice_date', '<', $today);
                    });

                if (Schema::connection('tenant')->hasTable('invoice_schedules')) {
                    $inner->orWhereHas('schedules', function (Builder $s) use ($today) {
                        $s->where('status', '!=', 'paid')
                            ->whereDate('due_date', '<', $today)
                            ->whereRaw('amount_due - amount_paid > 0.01');
                    });
                }
            });
    }

    /**
     * @return array{outstanding_amount: float, outstanding_count: int, overdue_amount: float, overdue_count: int, draft_count: int}
     */
    private function kpis(): array
    {
        $collectible = Invoice::query()
            ->whereIn('status', ['issued', 'partial'])
            ->where('balance', '>', 0.01);

        $overdue = Invoice::query();
        $this->constrainOverdue($overdue);

        return [
            'outstanding_amount' => round((float) (clone $collectible)->sum('balance'), 2),
            'outstanding_count' => (clone $collectible)->count(),
            'overdue_amount' => round((float) (clone $overdue)->sum('balance'), 2),
            'overdue_count' => (clone $overdue)->count(),
            'draft_count' => Invoice::query()->where('status', 'draft')->count(),
        ];
    }

    private function activeFiltersCount(): int
    {
        $count = 0;
        if ($this->declarationFilter !== 'all') {
            $count++;
        }
        if ($this->clientFilter !== '') {
            $count++;
        }
        if ($this->dateFrom !== '') {
            $count++;
        }
        if ($this->dateTo !== '') {
            $count++;
        }

        return $count;
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
