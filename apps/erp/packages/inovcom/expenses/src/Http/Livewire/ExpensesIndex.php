<?php

namespace InovCom\Expenses\Http\Livewire;

use InovCom\Expenses\Models\Expense;
use InovCom\Expenses\Models\ExpenseCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all'; // all, draft, pending, approved, rejected, paid
    public ?int $categoryFilter = null;
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;

    public function mount(): void
    {
        $status = request()->query('status');
        if (is_string($status) && $status !== '') {
            $this->statusFilter = $status;
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->categoryFilter = null;
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function approve(int $expenseId): void
    {
        if (!$this->can('expenses.approve')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $expensesService = app(\InovCom\Expenses\Services\ExpensesService::class);
            $expensesService->approveExpense($expenseId);
            session()->flash('success', 'Dépense approuvée avec succès.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reject(int $expenseId): void
    {
        if (!$this->can('expenses.approve')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $expensesService = app(\InovCom\Expenses\Services\ExpensesService::class);
            $expensesService->rejectExpense($expenseId, 'Rejetée depuis la liste des dépenses.');
            session()->flash('success', 'Dépense rejetée.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markAsPaid(int $expenseId): void
    {
        if (!$this->can('expenses.approve')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $expensesService = app(\InovCom\Expenses\Services\ExpensesService::class);
            $expensesService->markAsPaid($expenseId);
            session()->flash('success', 'Dépense marquée comme payée.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function delete(int $expenseId): void
    {
        if (!$this->can('expenses.delete')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $expense = Expense::findOrFail($expenseId);
        
        if (!$expense->isDraft() && !$expense->isRejected()) {
            session()->flash('error', 'Seules les dépenses en brouillon ou rejetées peuvent être supprimées.');
            return;
        }

        $expense->delete();
        $this->resetPage();
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

    public function render()
    {
        $expenses = Expense::query()
            ->with(['category', 'creator', 'approver'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('reference', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            }, function ($query) {
                $query->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
                    ->when($this->categoryFilter, fn ($q) => $q->where('expense_category_id', $this->categoryFilter))
                    ->when($this->dateFrom, fn ($q) => $q->where('expense_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q) => $q->where('expense_date', '<=', $this->dateTo));
            })
            ->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Calculate totals
        $totalAmount = Expense::query()
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->categoryFilter, function ($query) {
                $query->where('expense_category_id', $this->categoryFilter);
            })
            ->when($this->dateFrom, function ($query) {
                $query->where('expense_date', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->where('expense_date', '<=', $this->dateTo);
            })
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        return view('inovcom-expenses::livewire.expenses.index')
            ->layout('layouts.app', [
                'title' => 'Dépenses',
                'subtitle' => 'Gestion des dépenses',
            ])
            ->with([
                'expenses' => $expenses,
                'categories' => $categories,
                'totalAmount' => $totalAmount,
            ]);
    }
}
