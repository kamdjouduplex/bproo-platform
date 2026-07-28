<?php

namespace InovCom\Returns\Http\Livewire;

use InovCom\Returns\Concerns\AuthorizesReturnActions;
use InovCom\Returns\Enums\RefundStatus;
use InovCom\Returns\Models\Refund;
use InovCom\Returns\Services\RefundService;
use Livewire\Component;
use Livewire\WithPagination;

class RefundsIndex extends Component
{
    use WithPagination;
    use AuthorizesReturnActions;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 20;

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function markPaid(int $id): void
    {
        if (! $this->can('refunds.validate')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        try {
            $refund = Refund::findOrFail($id);
            app(RefundService::class)->markPaid($refund, $this->tenantUserId());
            session()->flash('success', 'Remboursement marqué comme payé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $refunds = Refund::query()
            ->with('client')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('refund_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('refund_date')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('inovcom-returns::livewire.refunds.index')
            ->layout('layouts.app', [
                'title' => 'Remboursements',
                'subtitle' => 'Remboursements clients',
            ])
            ->with([
                'refunds' => $refunds,
                'statuses' => RefundStatus::options(),
                'canValidate' => $this->can('refunds.validate'),
            ]);
    }
}
