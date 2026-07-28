<?php

namespace InovCom\Losses\Http\Livewire;

use InovCom\Losses\Models\LossRecord;
use InovCom\Losses\Models\LossReason;
use InovCom\Losses\Services\LossesService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class LossesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $reasonFilter = null;
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->reasonFilter = null;
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function confirmLoss(int $recordId): void
    {
        if (!$this->can('losses.confirm')) {
            session()->flash('error', 'Permission refusée: vous ne pouvez pas valider une perte.');
            return;
        }

        try {
            $service = app(LossesService::class);
            $service->confirmLoss($recordId);
            session()->flash('success', 'Perte confirmée. Stock mis à jour.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function delete(int $recordId): void
    {
        if (!$this->can('losses.delete')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $record = LossRecord::findOrFail($recordId);

        if (!$record->isDraft()) {
            session()->flash('error', 'Seules les pertes en brouillon peuvent être supprimées.');
            return;
        }

        $record->delete();
        $this->resetPage();
    }

    public function render()
    {
        $records = LossRecord::query()
            ->with(['item.unit', 'reason', 'creator', 'confirmer'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('reference', 'like', '%' . $this->search . '%')
                        ->orWhereHas('item', fn ($q3) => $q3->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('sku', 'like', '%' . $this->search . '%'));
                });
            }, function ($q) {
                $q->when($this->statusFilter !== 'all', fn ($q2) => $q2->where('status', $this->statusFilter))
                    ->when($this->reasonFilter, fn ($q2) => $q2->where('loss_reason_id', $this->reasonFilter))
                    ->when($this->dateFrom, fn ($q2) => $q2->where('loss_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q2) => $q2->where('loss_date', '<=', $this->dateTo));
            })
            ->orderBy('loss_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $reasons = LossReason::where('is_active', true)->orderBy('name')->get();

        $totalValue = LossRecord::query()
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->reasonFilter, fn ($q) => $q->where('loss_reason_id', $this->reasonFilter))
            ->when($this->dateFrom, fn ($q) => $q->where('loss_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('loss_date', '<=', $this->dateTo))
            ->where('status', 'confirmed')
            ->sum('value');

        return view('inovcom-losses::livewire.losses.index')
            ->layout('layouts.app', [
                'title' => 'Pertes',
                'subtitle' => 'Gestion des pertes',
            ])
            ->with([
                'records' => $records,
                'reasons' => $reasons,
                'totalValue' => $totalValue,
                'canConfirmLoss' => $this->can('losses.confirm'),
                'canDeleteLoss' => $this->can('losses.delete'),
            ]);
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
