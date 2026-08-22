<?php

namespace InovCom\Expenses\Http\Livewire;

use InovCom\Expenses\ExpensesModule;
use InovCom\Expenses\Models\Expense;
use InovCom\Expenses\Models\ExpenseCategory;
use InovCom\Expenses\Services\ExpensesService;
use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
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
        ExpensesModule::syncDefaultCategories();

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

    public function exportPdf()
    {
        if (! $this->can('expenses.view')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $expenses = $this->baseQuery()
                ->with(['category', 'creator'])
                ->orderByDesc('expense_date')
                ->orderByDesc('created_at')
                ->limit(5000)
                ->get();

            $methodLabels = [
                'cash' => 'Espèces',
                'check' => 'Chèque',
                'bank_transfer' => 'Virement',
                'mobile_money' => 'Mobile Money',
                'other' => 'Autre',
            ];

            $statusLabels = [
                'draft' => 'Brouillon',
                'pending' => 'En attente',
                'approved' => 'Approuvé',
                'rejected' => 'Rejeté',
                'paid' => 'Payé',
            ];

            $rows = $expenses->map(function (Expense $expense) use ($methodLabels, $statusLabels) {
                return [
                    'reference' => (string) $expense->reference,
                    'expense_date' => $expense->expense_date?->format('d/m/Y') ?? '—',
                    'category' => (string) ($expense->category?->name ?? '—'),
                    'description' => (string) ($expense->description ?: '—'),
                    'amount' => (float) $expense->amount,
                    'payment_method' => $methodLabels[$expense->payment_method] ?? (string) $expense->payment_method,
                    'status' => $statusLabels[$expense->status] ?? (string) $expense->status,
                    'creator' => (string) ($expense->creator?->name ?? '—'),
                ];
            })->all();

            $totalAmount = (float) collect($rows)->sum('amount');
            if ($this->statusFilter === 'all') {
                $totalAmount = (float) (clone $this->baseQuery())
                    ->where('status', '!=', 'rejected')
                    ->sum('amount');
            }

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'depenses-' . now()->format('Ymd_His') . '.pdf';

            $pdf = Pdf::loadView('inovcom-expenses::pdf.expenses-list', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => 'Dépenses',
                'filterLabel' => $this->filterLabel(),
                'totalAmount' => $totalAmount,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $fontMetrics = $dompdf->getFontMetrics();
            $font = $fontMetrics->getFont('DejaVu Sans');
            if ($font) {
                $size = 8;
                $width = $fontMetrics->getTextWidth('00/00', $font, $size);
                $x = ($canvas->get_width() - $width) / 2;
                $y = $canvas->get_height() - 18;
                $canvas->page_text($x, $y, '{PAGE_NUM}/{PAGE_COUNT}', $font, $size, [0.06, 0.46, 0.43]);
            }

            $output = $dompdf->output();

            return response()->streamDownload(function () use ($output) {
                echo $output;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Export PDF impossible. Affinez les filtres puis réessayez.');

            return null;
        }
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
                'canExport' => $this->can('expenses.view'),
                'activeFiltersCount' => $this->activeFiltersCount,
            ]);
    }

    private function filterLabel(): string
    {
        $parts = [];
        if ($this->search !== '') {
            $parts[] = 'Recherche : '.$this->search;
        }
        if ($this->statusFilter !== 'all') {
            $parts[] = 'Statut : '.$this->statusFilter;
        }
        if ($this->categoryFilter !== '') {
            $category = ExpenseCategory::find((int) $this->categoryFilter);
            $parts[] = 'Catégorie : '.($category?->name ?? '#'.$this->categoryFilter);
        }
        if ($this->paymentMethodFilter !== '') {
            $parts[] = 'Paiement : '.$this->paymentMethodFilter;
        }
        if ($this->dateFrom !== '' || $this->dateTo !== '') {
            $parts[] = 'Période : '.($this->dateFrom ?: '…').' → '.($this->dateTo ?: '…');
        }

        return $parts === [] ? 'Tous les enregistrements' : implode(' · ', $parts);
    }
}
