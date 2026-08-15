<?php

namespace InovCom\Expenses\Http\Livewire;

use InovCom\Expenses\ExpensesModule;
use InovCom\Expenses\Models\Expense;
use InovCom\Expenses\Models\ExpenseCategory;
use InovCom\Expenses\Services\ExpensesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class ExpensesForm extends Component
{
    public ?int $expenseId = null;

    // Expense fields
    public ?int $expense_category_id = null;
    public bool $useNewCategory = false;
    public string $newCategoryName = '';
    public string $amount = '0';
    public string $expense_date = '';
    public ?string $description = null;
    public string $payment_method = 'cash';
    public string $status = 'pending';
    public ?string $statusWarning = null;
    public ?string $expenseReference = null;
    public ?string $expenseCreator = null;

    public function mount(?Expense $expense = null): void
    {
        ExpensesModule::syncDefaultCategories();

        if (!$expense) {
            $this->expense_date = now()->format('Y-m-d');
            $this->status = 'pending';
            return;
        }

        $expense->loadMissing('creator');

        $this->expenseId = $expense->id;
        $this->expenseReference = $expense->reference;
        $this->expenseCreator = $expense->creator?->name;
        $this->expense_category_id = $expense->expense_category_id;
        $this->amount = (string) $expense->amount;
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->description = $expense->description;
        $this->payment_method = $expense->payment_method;
        $this->status = $expense->status;
    }

    public function getStatusLockedProperty(): bool
    {
        return $this->expenseId !== null
            && in_array($this->status, ['approved', 'rejected', 'paid'], true);
    }

    public function updatedStatus(): void
    {
        $this->statusWarning = null;
    }

    public function enableNewCategory(): void
    {
        $this->useNewCategory = true;
        $this->expense_category_id = null;
        $this->newCategoryName = '';
    }

    public function cancelNewCategory(): void
    {
        $this->useNewCategory = false;
        $this->newCategoryName = '';
    }

    public function save(): void
    {
        $this->statusWarning = null;

        $requiredPermission = $this->expenseId ? 'expenses.update' : 'expenses.create';
        if (!$this->can($requiredPermission)) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $allowedStatuses = $this->statusLocked
            ? [$this->status]
            : ($this->expenseId ? ['draft', 'pending'] : ['draft', 'pending']);

        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'payment_method' => 'required|in:cash,check,bank_transfer,mobile_money,other',
            'status' => 'required|in:' . implode(',', $allowedStatuses),
        ];

        if ($this->useNewCategory) {
            $rules['newCategoryName'] = 'required|string|min:2|max:100';
        } else {
            $rules['expense_category_id'] = 'required|exists:tenant.expense_categories,id';
        }

        $data = $this->validate($rules, [], [
            'expense_category_id' => 'catégorie',
            'newCategoryName' => 'nouvelle catégorie',
            'amount' => 'montant',
            'expense_date' => 'date',
            'description' => 'description',
            'payment_method' => 'méthode de paiement',
            'status' => 'statut',
        ]);

        try {
            $data['expense_category_id'] = $this->useNewCategory
                ? $this->findOrCreateCategoryId(trim($data['newCategoryName'] ?? $this->newCategoryName))
                : (int) $data['expense_category_id'];
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        unset($data['newCategoryName']);

        $expensesService = app(ExpensesService::class);

        try {
            if ($this->expenseId) {
                $expense = $expensesService->updateExpense($this->expenseId, $data);
                session()->flash('success', 'Dépense mise à jour avec succès.');
            } else {
                $expense = $expensesService->createExpense($data);
                session()->flash('success', 'Dépense créée avec succès.');
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->redirect(route('tenant.expenses.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function render()
    {
        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('inovcom-expenses::livewire.expenses.form')
            ->layout('layouts.app', [
                'title' => $this->expenseId ? 'Modifier dépense' : 'Nouvelle dépense',
                'subtitle' => 'Gestion des dépenses',
            ])
            ->with([
                'categories' => $categories,
                'canManageCategories' => $this->canManageCategories(),
                'statusLocked' => $this->statusLocked,
                'expenseReference' => $this->expenseReference,
                'expenseCreator' => $this->expenseCreator,
            ]);
    }

    private function findOrCreateCategoryId(string $name): int
    {
        if (!$this->canManageCategories()) {
            throw new \InvalidArgumentException('Permission refusée pour créer une catégorie.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Indiquez le nom de la catégorie.');
        }

        $existing = ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            if (!$existing->is_active) {
                $existing->is_active = true;
                $existing->save();
            }

            return (int) $existing->id;
        }

        $category = ExpenseCategory::create([
            'code' => $this->generateUniqueCategoryCode($name),
            'name' => $name,
            'description' => null,
            'is_active' => true,
        ]);

        return (int) $category->id;
    }

    private function generateUniqueCategoryCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        if ($base === '') {
            $base = 'CAT';
        }
        $base = substr($base, 0, 28);
        $code = $base;
        $suffix = 1;

        while (ExpenseCategory::where('code', $code)->exists()) {
            $code = $base . '_' . $suffix;
            $suffix++;
        }

        return $code;
    }

    private function canManageCategories(): bool
    {
        if ($this->can('categories.manage')) {
            return true;
        }

        return $this->expenseId
            ? $this->can('expenses.update')
            : $this->can('expenses.create');
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
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
