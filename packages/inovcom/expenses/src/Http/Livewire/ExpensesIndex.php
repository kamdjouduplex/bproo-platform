<?php

namespace InovCom\Expenses\Http\Livewire;

use InovCom\Expenses\Models\Expense;
use InovCom\Expenses\Models\ExpenseCategory;
use InovCom\Expenses\Services\ExpensesService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $categoryFilter = '';

    public string $paymentMethodFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 20;

    public bool $showAdvancedFilters = false;

    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        $status = request()->query('status');
        if (is_string($status) && $status !== '') {
            $this->statusFilter = $status;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPaymentMethodFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = ! $this->showAdvancedFilters;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->categoryFilter = '';
        $this->paymentMethodFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->showAdvancedFilters = false;
        $this->resetPage();
    }

    public function openReject(int $expenseId): void
    {
        if (! $this->can('expenses.approve')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        $this->rejectingId = $expenseId;
        $this->rejectionReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectionReason = '';
    }

    public function confirmReject(): void
    {
        if (! $this->can('expenses.approve') || ! $this->rejectingId) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        $this->validate([
            'rejectionReason' => 'required|string|min:3|max:500',
        ], [], [
            'rejectionReason' => 'motif du rejet',
        ]);

        try {
            app(ExpensesService::class)->rejectExpense($this->rejectingId, trim($this->rejectionReason));
            session()->flash('success', 'Dépense rejetée.');
            $this->cancelReject();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function approve(int $expenseId): void
    {
        if (! $this->can('expenses.approve')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        try {
            app(ExpensesService::class)->approveExpense($expenseId);
            session()->flash('success', 'Dépense approuvée avec succès.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markAsPaid(int $expenseId): void
    {
        if (! $this->can('expenses.approve')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        try {
            app(ExpensesService::class)->markAsPaid($expenseId);
            session()->flash('success', 'Dépense marquée comme payée.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function delete(int $expenseId): void
    {
        if (! $this->can('expenses.delete')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        $expense = Expense::findOrFail($expenseId);

        if (! $expense->isDraft() && ! $expense->isRejected()) {
            session()->flash('error', 'Seules les dépenses en brouillon ou rejetées peuvent être supprimées.');

            return;
        }

        $expense->delete();
        $this->resetPage();
        session()->flash('success', 'Dépense supprimée.');
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

    private function baseQuery(): Builder
    {
        return Expense::query()
            ->when($this->search !== '', function ($query) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('reference', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('expense_category_id', (int) $this->categoryFilter))
            ->when($this->paymentMethodFilter !== '', fn ($q) => $q->where('payment_method', $this->paymentMethodFilter))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('expense_date', '<=', $this->dateTo));
    }

    public function getActiveFiltersCountProperty(): int
    {
        $count = 0;
        if ($this->categoryFilter !== '') {
            $count++;
        }
        if ($this->paymentMethodFilter !== '') {
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

    public function render()
    {
        $expenses = $this->baseQuery()
            ->with(['category', 'creator', 'approver'])
            ->orderByDesc('expense_date')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get();

        $totalAmount = (clone $this->baseQuery())
            ->when($this->statusFilter === 'all', fn ($q) => $q->where('status', '!=', 'rejected'))
            ->sum('amount');

        $statusCounts = Expense::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pendingCount = (int) ($statusCounts['pending'] ?? 0);
        $draftCount = (int) ($statusCounts['draft'] ?? 0);

        return view('inovcom-expenses::livewire.expenses.index')
            ->layout('layouts.app', [
                'title' => 'Dépenses',
                'subtitle' => 'Suivi, approbation et paiement',
            ])
            ->with([
                'expenses' => $expenses,
                'categories' => $categories,
                'totalAmount' => (float) $totalAmount,
                'statusCounts' => $statusCounts,
                'pendingCount' => $pendingCount,
                'draftCount' => $draftCount,
                'canCreate' => $this->can('expenses.create'),
                'canApprove' => $this->can('expenses.approve'),
                'canDelete' => $this->can('expenses.delete'),
                'activeFiltersCount' => $this->activeFiltersCount,
            ]);
    }
}
