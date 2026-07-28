<?php

namespace InovCom\Stock\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use InovCom\Items\Models\Item;
use InovCom\Stock\Exports\StockExcelExporter;
use InovCom\Stock\Services\StockMovementService;
use InovCom\Stock\Services\StockService;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockMovementsIndex extends Component
{
    use WithPagination;

    public ?int $itemId = null;

    public string $search = '';

    public string $direction = '';

    public string $referenceType = '';

    public ?int $referenceId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 25;

    protected $queryString = [
        'search' => ['except' => ''],
        'direction' => ['except' => ''],
        'referenceType' => ['except' => ''],
        'referenceId' => ['except' => null],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function mount(?int $itemId = null): void
    {
        if (! $this->canViewMovements()) {
            abort(403, 'Permission refusée.');
        }

        $this->itemId = $itemId;

        if (request()->has('reference_type')) {
            $this->referenceType = (string) request()->query('reference_type', '');
        }
        if (request()->has('reference_id')) {
            $this->referenceId = (int) request()->query('reference_id');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDirection(): void
    {
        $this->resetPage();
    }

    public function updatedReferenceType(): void
    {
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

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->direction = '';
        if (! $this->referenceIdFromRoute()) {
            $this->referenceType = '';
            $this->referenceId = null;
        }
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function exportExcel(): StreamedResponse
    {
        if (! $this->canViewMovements()) {
            abort(403, 'Permission refusée.');
        }

        $service = app(StockMovementService::class);
        $filters = $this->currentFilters();
        $item = $this->itemId ? Item::find($this->itemId) : null;
        $rows = $service->enrich(
            $service->queryMovements($filters)->limit(5000)->get()
        );

        $headers = ['Date', 'Sens', 'Cause', 'Qté', 'Stock avant', 'Stock après', 'Explication', 'Document', 'Utilisateur', 'Motif'];
        if (! $item) {
            array_splice($headers, 1, 0, ['Référence', 'Article']);
        }

        return StockExcelExporter::download(
            'mouvements-stock-' . now()->format('Ymd_His') . '.xls',
            $headers,
            StockExcelExporter::movementRows($rows, $item === null),
            $this->exportTitle($item)
        );
    }

    public function printPdf()
    {
        if (! $this->canViewMovements()) {
            abort(403, 'Permission refusée.');
        }

        $service = app(StockMovementService::class);
        $filters = $this->currentFilters();
        $item = $this->itemId ? Item::find($this->itemId) : null;
        $rows = $service->enrich(
            $service->queryMovements($filters)->limit(5000)->get()
        );
        $summary = $service->summarize($filters);

        if ($item) {
            try {
                $summary['current_available'] = app(StockService::class)->getAvailableQuantity((int) $item->id);
            } catch (\Throwable) {
                $summary['current_available'] = null;
            }
        }

        $options = StockMovementService::referenceTypeOptions();
        $pdf = Pdf::loadView('inovcom-stock::pdf.movements', [
            'rows' => $rows,
            'item' => $item,
            'summary' => $summary,
            'settings' => $this->documentSettings(),
            'generatedAt' => now(),
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'search' => trim($this->search),
            'directionLabel' => match ($this->direction) {
                'in' => 'Entrées physiques',
                'out' => 'Sorties physiques',
                'reserve' => 'Réservations / libérations',
                default => null,
            },
            'originLabel' => ($this->referenceType !== '' ? ($options[$this->referenceType] ?? $this->referenceType) : null),
            'title' => $this->exportTitle($item),
        ])->setPaper('a4', 'landscape');

        $filename = 'mouvements-stock-' . now()->format('Ymd_His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function render()
    {
        $service = app(StockMovementService::class);
        $item = $this->itemId ? Item::find($this->itemId) : null;
        $filters = $this->currentFilters();

        $paginator = $service->paginate($filters, $this->perPage);
        $rows = $service->enrich($paginator->getCollection());
        $paginator->setCollection($rows);

        $summary = $service->summarize($filters);
        if ($item) {
            try {
                $summary['current_available'] = app(StockService::class)->getAvailableQuantity((int) $item->id);
            } catch (\Throwable) {
                $summary['current_available'] = null;
            }
        }

        return view('inovcom-stock::livewire.stock.movements-index')
            ->layout('layouts.app', [
                'title' => $item ? 'Mouvements — ' . $item->name : 'Mouvements de stock',
                'subtitle' => $item
                    ? trim(($item->sku ? $item->sku . ' · ' : '') . 'Historique clair du stock')
                    : 'Comprendre d’où vient et où part le stock',
            ])
            ->with([
                'movements' => $paginator,
                'item' => $item,
                'summary' => $summary,
                'referenceTypeOptions' => StockMovementService::referenceTypeOptions(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentFilters(): array
    {
        $filters = [
            'search' => $this->search,
            'direction' => $this->direction,
            'item_id' => $this->itemId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];

        if ($this->referenceType !== '') {
            $filters['reference_type'] = $this->referenceType;
            if ($this->referenceId) {
                $filters['reference_id'] = $this->referenceId;
            }
        }

        return $filters;
    }

    private function exportTitle(?Item $item): string
    {
        if ($item) {
            return 'Mouvements — ' . trim(($item->sku ? $item->sku . ' — ' : '') . $item->name);
        }

        return 'Mouvements de stock';
    }

    private function documentSettings(): array
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        // DomPDF exige l’extension GD pour rendre les images — sinon on imprime le nom seul.
        if (! extension_loaded('gd')) {
            $settings['logo_embed_src'] = null;
            $settings['logo_absolute_path'] = null;
            $settings['logo_url'] = null;
        }

        return $settings;
    }

    private function referenceIdFromRoute(): bool
    {
        return request()->has('reference_type') || request()->has('reference_id');
    }

    private function canViewMovements(): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && (
            $user->hasPermission('stock.movements')
            || $user->hasPermission('stock.view')
        );
    }
}
