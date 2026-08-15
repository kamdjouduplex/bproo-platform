<?php

namespace InovCom\Batches\Http\Livewire;

use InovCom\Batches\BatchesModule;
use InovCom\Batches\Models\Batch;
use InovCom\Kernel\Contracts\BatchesApi;
use Livewire\Component;
use Livewire\WithPagination;

class BatchesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** all | d30 | d90 | d180 | expired */
    public string $filter = 'all';

    public function mount(): void
    {
        BatchesModule::syncPermissions();

        $requested = request()->query('filter');
        if (is_string($requested) && in_array($requested, ['all', 'expired', 'd30', 'd90', 'd180', 'near_expiry'], true)) {
            $this->filter = $requested;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function writeOffExpired(int $batchId): void
    {
        if (! $this->canWriteOff()) {
            session()->flash('error', 'Vous n’avez pas le droit de sortir les lots périmés.');

            return;
        }

        if (! app()->bound(BatchesApi::class)) {
            session()->flash('error', 'Module lots indisponible.');

            return;
        }

        try {
            $result = app(BatchesApi::class)->writeOffExpiredBatch($batchId);
            $qty = fmt_num((float) $result['quantity']);
            session()->flash(
                'success',
                "Lot {$result['batch_number']} sorti du stock ({$qty}). Stock article mis à jour."
                    .(! empty($result['loss_record_id']) ? ' Perte enregistrée.' : '')
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->resetPage();
    }

    public function writeOffAllExpired(): void
    {
        if (! $this->canWriteOff()) {
            session()->flash('error', 'Vous n’avez pas le droit de sortir les lots périmés.');

            return;
        }

        if (! app()->bound(BatchesApi::class)) {
            session()->flash('error', 'Module lots indisponible.');

            return;
        }

        $api = app(BatchesApi::class);
        $ids = Batch::query()
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            session()->flash('error', 'Aucun lot périmé avec stock à sortir.');

            return;
        }

        $ok = 0;
        $errors = [];
        foreach ($ids as $id) {
            try {
                $api->writeOffExpiredBatch((int) $id);
                $ok++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($ok > 0) {
            session()->flash(
                'success',
                $ok === 1
                    ? '1 lot périmé sorti du stock.'
                    : "{$ok} lots périmés sortis du stock."
            );
        }
        if ($errors !== []) {
            session()->flash('error', $errors[0].(count($errors) > 1 ? ' (+'.(count($errors) - 1).' autre(s))' : ''));
        }

        $this->filter = 'expired';
        $this->resetPage();
    }

    public function canWriteOff(): bool
    {
        $user = auth('tenant')->user();
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission('batches.write_off') || $user->hasPermission('batches.create');
        }

        return false;
    }

    public function canEditExpiry(): bool
    {
        $user = auth('tenant')->user();
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission('batches.create') || $user->hasPermission('batches.write_off');
        }

        return false;
    }

    public function render()
    {
        $today = now()->toDateString();

        $query = Batch::query()->with('item')
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $q->where(function ($outer) use ($term) {
                    $outer->whereHas('item', function ($q2) use ($term) {
                        $q2->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$term]);
                    })->orWhereRaw('LOWER(batch_number) LIKE ?', [$term]);
                });
            })
            ->when($this->filter === 'd30', function ($q) use ($today) {
                $q->where('quantity', '>', 0)
                    ->whereDate('expiry_date', '>=', $today)
                    ->whereDate('expiry_date', '<=', now()->addDays(30)->toDateString());
            })
            ->when($this->filter === 'd90', function ($q) use ($today) {
                $q->where('quantity', '>', 0)
                    ->whereDate('expiry_date', '>=', $today)
                    ->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString());
            })
            ->when($this->filter === 'd180', function ($q) use ($today) {
                $q->where('quantity', '>', 0)
                    ->whereDate('expiry_date', '>=', $today)
                    ->whereDate('expiry_date', '<=', now()->addDays(180)->toDateString());
            })
            ->when($this->filter === 'near_expiry', function ($q) use ($today) {
                $q->where('quantity', '>', 0)
                    ->whereDate('expiry_date', '>=', $today)
                    ->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString());
            })
            ->when($this->filter === 'expired', function ($q) use ($today) {
                $q->where('quantity', '>', 0)->whereDate('expiry_date', '<', $today);
            })
            ->orderBy('expiry_date')->orderBy('batch_number');

        $batches = $query->paginate(20);

        $stats = [
            'expired' => Batch::query()->where('quantity', '>', 0)->whereDate('expiry_date', '<', $today)->count(),
            'd30' => Batch::query()->where('quantity', '>', 0)
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', now()->addDays(30)->toDateString())->count(),
            'd90' => Batch::query()->where('quantity', '>', 0)
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString())->count(),
            'd180' => Batch::query()->where('quantity', '>', 0)
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', now()->addDays(180)->toDateString())->count(),
        ];

        return view('inovcom-batches::livewire.batches.index')
            ->layout('layouts.app', [
                'title' => 'Lots / Péremption',
                'subtitle' => 'Pharmacie',
            ])
            ->with([
                'batches' => $batches,
                'stats' => $stats,
                'canWriteOff' => $this->canWriteOff(),
                'canEditExpiry' => $this->canEditExpiry(),
            ]);
    }
}
