<?php

namespace InovCom\Expenses\Services;

use App\Services\StoreContextService;
use InovCom\Expenses\Models\Approval;
use InovCom\Expenses\Models\Expense;
use InovCom\Expenses\Models\ExpenseCategory;
use Illuminate\Support\Collection;

/**
 * Expenses service for managing expenses and approvals
 */
class ExpensesService
{
    /**
     * Create a new expense
     */
    public function createExpense(array $data, ?int $userId = null): Expense
    {
        $data['reference'] = $this->generateReference();
        $data['created_by'] = $userId ?? auth('tenant')->id();
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('expenses', 'store_id')) {
            $data['store_id'] = app(StoreContextService::class)->currentStoreId();
        }
        
        // If status is pending, create approval request
        if (($data['status'] ?? 'draft') === 'pending') {
            $expense = Expense::create($data);
            $this->createApprovalRequest($expense, $userId);
        } else {
            $expense = Expense::create($data);
        }

        return $expense;
    }

    /**
     * Update an expense
     */
    public function updateExpense(int $expenseId, array $data): Expense
    {
        $expense = Expense::findOrFail($expenseId);

        // If changing to pending, create approval request
        if (isset($data['status']) && $data['status'] === 'pending' && !$expense->isPending()) {
            $expense->update($data);
            $this->createApprovalRequest($expense);
        } else {
            $expense->update($data);
        }

        return $expense->fresh();
    }

    /**
     * Approve an expense
     */
    public function approveExpense(int $expenseId, ?string $comments = null, ?int $userId = null): Expense
    {
        $expense = Expense::findOrFail($expenseId);
        
        if (!$expense->canBeApproved()) {
            throw new \Exception('Cette dépense ne peut pas être approuvée.');
        }

        $expense->status = 'approved';
        $expense->approved_by = $userId ?? auth('tenant')->id();
        $expense->approved_at = now();
        $expense->save();

        // Update approval record
        if ($expense->approval) {
            $expense->approval->status = 'approved';
            $expense->approval->approved_by = $userId ?? auth('tenant')->id();
            $expense->approval->approved_at = now();
            $expense->approval->comments = $comments;
            $expense->approval->save();
        }

        return $expense->fresh();
    }

    /**
     * Reject an expense
     */
    public function rejectExpense(int $expenseId, string $reason, ?int $userId = null): Expense
    {
        $expense = Expense::findOrFail($expenseId);
        
        if (!$expense->canBeRejected()) {
            throw new \Exception('Cette dépense ne peut pas être rejetée.');
        }

        $expense->status = 'rejected';
        $expense->approved_by = $userId ?? auth('tenant')->id();
        $expense->approved_at = now();
        $expense->rejection_reason = $reason;
        $expense->save();

        // Update approval record
        if ($expense->approval) {
            $expense->approval->status = 'rejected';
            $expense->approval->approved_by = $userId ?? auth('tenant')->id();
            $expense->approval->approved_at = now();
            $expense->approval->rejection_reason = $reason;
            $expense->approval->save();
        }

        return $expense->fresh();
    }

    /**
     * Mark expense as paid
     */
    public function markAsPaid(int $expenseId): Expense
    {
        $expense = Expense::findOrFail($expenseId);
        
        if (!$expense->canBePaid()) {
            throw new \Exception('Seules les dépenses approuvées peuvent être marquées comme payées.');
        }

        $expense->status = 'paid';
        $expense->save();

        // Auto-capture caisse : seules les dépenses réglées en espèces sortent du tiroir.
        if (($expense->payment_method ?? 'cash') === 'cash') {
            \App\Support\CashLedger::recordOut(
                \App\Support\CashLedger::EXPENSE_CASH_OUT,
                (float) $expense->amount,
                'Paiement dépense ' . $expense->reference,
                'expense',
                Expense::class,
                (int) $expense->id,
                $expense->reference,
                ['category_id' => $expense->expense_category_id],
            );
        }

        return $expense->fresh();
    }

    /**
     * Create approval request
     */
    public function createApprovalRequest(Expense $expense, ?int $userId = null): Approval
    {
        // Delete existing approval if any
        $expense->approval?->delete();

        return Approval::create([
            'approvable_type' => Expense::class,
            'approvable_id' => $expense->id,
            'status' => 'pending',
            'requested_by' => $userId ?? auth('tenant')->id(),
            'approval_level' => 1,
        ]);
    }

    /**
     * Get expenses by category
     */
    public function getExpensesByCategory(int $categoryId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = Expense::where('expense_category_id', $categoryId)
            ->where('status', '!=', 'rejected');

        if ($startDate) {
            $query->where('expense_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('expense_date', '<=', $endDate);
        }

        return $query->orderBy('expense_date', 'desc')->get();
    }

    /**
     * Get total expenses for a period
     */
    public function getTotalExpenses(?string $startDate = null, ?string $endDate = null, ?string $status = null): float
    {
        $query = Expense::query();

        if ($startDate) {
            $query->where('expense_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('expense_date', '<=', $endDate);
        }

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', 'rejected');
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get expenses by status
     */
    public function getExpensesByStatus(string $status): Collection
    {
        return Expense::where('status', $status)
            ->with(['category', 'creator', 'approver'])
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    /**
     * Get pending approvals
     */
    public function getPendingApprovals(): Collection
    {
        return Approval::where('status', 'pending')
            ->where('approvable_type', Expense::class)
            ->with(['approvable', 'requester'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Generate expense reference
     */
    private function generateReference(): string
    {
        $year = now()->year;
        $lastExpense = Expense::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastExpense && is_string($lastExpense->reference)) {
            // Strict expected format: EXP-YYYY-000001 (exactly 6 digits sequence).
            // This avoids propagating legacy malformed refs like EXP-2026-20262026000003.
            if (preg_match('/^EXP-\d{4}-(\d{6})$/', $lastExpense->reference, $matches) === 1) {
                $nextNumber = ((int) $matches[1]) + 1;
            } else {
                // Fallback to yearly count if format was corrupted
                $nextNumber = Expense::whereYear('created_at', $year)->count() + 1;
            }
        }

        return 'EXP-' . $year . '-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
