<?php

namespace InovCom\Losses\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InovCom\Losses\Models\LossRecord;
use InovCom\Losses\Models\LossReason;
use InovCom\Losses\Services\LossesService;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LossesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $reasonFilter = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 20;

    public bool $showAdvancedFilters = false;

    public function mount(): void
    {
        if ($this->reasonFilter || $this->dateFrom !== '' || $this->dateTo !== '') {
            $this->showAdvancedFilters = true;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedReasonFilter($value): void
    {
        $this->reasonFilter = ($value === '' || $value === null) ? null : (int) $value;
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = ! $this->showAdvancedFilters;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
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
            case 'clear':
                $this->dateFrom = '';
                $this->dateTo = '';
                break;
        }
        $this->showAdvancedFilters = true;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->reasonFilter = null;
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->showAdvancedFilters = false;
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function confirmLoss(int $recordId): void
    {
        if (! $this->can('losses.confirm')) {
            session()->flash('error', 'Permission refusée: vous ne pouvez pas valider une perte.');

            return;
        }

        try {
            app(LossesService::class)->confirmLoss($recordId);
            session()->flash('success', 'Perte confirmée. Stock mis à jour.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function delete(int $recordId): void
    {
        if (! $this->can('losses.delete')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        $record = LossRecord::findOrFail($recordId);

        if (! $record->isDraft()) {
            session()->flash('error', 'Seules les pertes en brouillon peuvent être supprimées.');

            return;
        }

        $record->delete();
        $this->resetPage();
    }

    public function exportExcel(): ?StreamedResponse
    {
        if (! $this->can('losses.view')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $headers = [
            'Référence',
            'Date',
            'Article',
            'Réf. article',
            'Raison',
            'Quantité',
            'Unité',
            'Valeur',
            'Statut',
            'Créé par',
        ];
        $title = 'Pertes — '.$this->filterLabel();
        $filename = 'pertes-'.now()->format('Ymd_His').'.xls';
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        return response()->streamDownload(function () use ($headers, $title, $escape) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h3>'.$escape($title).'</h3>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>'.$escape($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            $exported = 0;
            $this->baseQuery()
                ->with(['item.unit', 'reason', 'creator'])
                ->orderBy('id')
                ->chunkById(300, function ($chunk) use (&$exported, $escape) {
                    foreach ($chunk as $record) {
                        if ($exported >= 5000) {
                            return false;
                        }

                        $row = $this->mapLossRow($record);
                        echo '<tr>';
                        echo '<td>'.$escape($row['reference']).'</td>';
                        echo '<td>'.$escape($row['loss_date']).'</td>';
                        echo '<td>'.$escape($row['item_name']).'</td>';
                        echo '<td>'.$escape($row['item_sku']).'</td>';
                        echo '<td>'.$escape($row['reason_name']).'</td>';
                        echo '<td>'.$escape(fmt_num($row['quantity'])).'</td>';
                        echo '<td>'.$escape($row['unit']).'</td>';
                        echo '<td>'.$escape(fmt_money($row['value'])).'</td>';
                        echo '<td>'.$escape($row['status_label']).'</td>';
                        echo '<td>'.$escape($row['creator_name']).'</td>';
                        echo '</tr>';
                        $exported++;
                    }

                    return $exported < 5000;
                }, 'loss_records.id', 'id');

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf()
    {
        if (! $this->can('losses.view')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $records = $this->baseQuery()
                ->with(['item.unit', 'reason', 'creator'])
                ->orderBy('loss_date')
                ->orderBy('id')
                ->limit(5000)
                ->get();

            $rows = $records->map(fn (LossRecord $record) => $this->mapLossRow($record))->all();
            $totalValue = collect($rows)->where('status', 'confirmed')->sum('value');

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'pertes-'.now()->format('Ymd_His').'.pdf';

            $pdf = Pdf::loadView('inovcom-losses::pdf.losses-list', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => 'Pertes',
                'filterLabel' => $this->filterLabel(),
                'totalValue' => $totalValue,
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

    public function render()
    {
        $records = $this->baseQuery()
            ->with(['item.unit', 'reason', 'creator', 'confirmer'])
            ->orderBy('loss_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $reasons = LossReason::where('is_active', true)->orderBy('name')->get();

        $totalValue = (float) $this->baseQuery()
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
                'canCreate' => $this->can('losses.create'),
                'canExport' => $this->can('losses.view'),
                'activeFiltersCount' => $this->activeFiltersCount(),
            ]);
    }

    private function activeFiltersCount(): int
    {
        $count = 0;
        if ($this->reasonFilter) {
            $count++;
        }
        if ($this->dateFrom !== '' || $this->dateTo !== '') {
            $count++;
        }

        return $count;
    }

    private function baseQuery(): Builder
    {
        return LossRecord::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('reference', 'like', $term)
                        ->orWhereHas('item', fn ($q3) => $q3->where('name', 'like', $term)
                            ->orWhere('sku', 'like', $term));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->reasonFilter, fn ($q) => $q->where('loss_reason_id', $this->reasonFilter))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('loss_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('loss_date', '<=', $this->dateTo));
    }

    /**
     * @return array{
     *     reference: string,
     *     loss_date: string,
     *     item_name: string,
     *     item_sku: string,
     *     reason_name: string,
     *     quantity: float,
     *     unit: string,
     *     value: float,
     *     status: string,
     *     status_label: string,
     *     creator_name: string
     * }
     */
    private function mapLossRow(LossRecord $record): array
    {
        return [
            'reference' => (string) $record->reference,
            'loss_date' => $record->loss_date?->format('d/m/Y') ?? '—',
            'item_name' => (string) ($record->item?->name ?? '—'),
            'item_sku' => (string) ($record->item?->sku ?? ''),
            'reason_name' => (string) ($record->reason?->name ?? '—'),
            'quantity' => (float) $record->quantity,
            'unit' => (string) ($record->item?->unit?->abbreviation ?? $record->item?->unit?->name ?? 'pc'),
            'value' => (float) $record->value,
            'status' => (string) $record->status,
            'status_label' => $record->status === 'confirmed' ? 'Confirmé' : 'Brouillon',
            'creator_name' => (string) ($record->creator?->name ?? '—'),
        ];
    }

    private function filterLabel(): string
    {
        $parts = [];
        if ($this->search !== '') {
            $parts[] = 'Recherche : '.$this->search;
        }
        if ($this->statusFilter !== 'all') {
            $parts[] = 'Statut : '.($this->statusFilter === 'confirmed' ? 'Confirmé' : 'Brouillon');
        }
        if ($this->reasonFilter) {
            $reason = LossReason::find($this->reasonFilter);
            $parts[] = 'Raison : '.($reason?->name ?? '#'.$this->reasonFilter);
        }
        if ($this->dateFrom !== '' || $this->dateTo !== '') {
            $parts[] = 'Période : '.($this->dateFrom ?: '…').' → '.($this->dateTo ?: '…');
        }

        return $parts === [] ? 'Tous les enregistrements' : implode(' · ', $parts);
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
