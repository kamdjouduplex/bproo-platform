<?php

namespace InovCom\Reporting\Http\Livewire;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InovCom\Reporting\Exports\ReportingExcelExporter;
use InovCom\Reporting\Services\ReportRunner;
use InovCom\Reporting\Support\ReportCatalog;
use Livewire\Component;
use Livewire\WithPagination;

class ReportingIndex extends Component
{
    use WithPagination;

    public string $module = 'invoicing';

    public string $report = 'journal_factures';

    public string $period = 'monthly';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $storeId = '';

    public string $clientId = '';

    public string $clientSearch = '';

    public string $status = '';

    public string $userId = '';

    public string $categoryId = '';

    public string $itemId = '';

    public string $itemSearch = '';

    public string $amountMin = '';

    public string $amountMax = '';

    public string $sort = 'date';

    public string $dir = 'desc';

    public int $perPage = 25;

    public bool $moreFilters = false;

    public string $saveName = '';

    public string $loadedReportId = '';

    /** @var array<int, string> */
    public array $hiddenColumns = [];

    /** @var array<string, mixed> */
    public array $applied = [];

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->syncModuleDefaults();
        $this->applied = $this->filterPayload();
    }

    public function updatedModule(): void
    {
        $this->report = ReportCatalog::firstReport($this->module);
        $this->status = '';
    }

    public function updatedPeriod(): void
    {
        $this->syncDatesFromPeriod();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->applied = $this->filterPayload();
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->period = 'monthly';
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->storeId = '';
        $this->clientId = '';
        $this->clientSearch = '';
        $this->status = '';
        $this->userId = '';
        $this->categoryId = '';
        $this->itemId = '';
        $this->itemSearch = '';
        $this->amountMin = '';
        $this->amountMax = '';
        $this->sort = 'date';
        $this->dir = 'desc';
        $this->loadedReportId = '';
        $this->saveName = '';
        $this->applied = $this->filterPayload();
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->dir = 'desc';
        }
        $this->applied['sort'] = $this->sort;
        $this->applied['dir'] = $this->dir;
        $this->resetPage();
    }

    public function toggleColumn(string $key): void
    {
        if (in_array($key, $this->hiddenColumns, true)) {
            $this->hiddenColumns = array_values(array_filter($this->hiddenColumns, fn ($k) => $k !== $key));
        } else {
            $this->hiddenColumns[] = $key;
        }
    }

    public function selectClient(int $id, string $name): void
    {
        $this->clientId = (string) $id;
        $this->clientSearch = $name;
    }

    public function clearClient(): void
    {
        $this->clientId = '';
        $this->clientSearch = '';
    }

    public function selectItem(int $id, string $name): void
    {
        $this->itemId = (string) $id;
        $this->itemSearch = $name;
    }

    public function clearItem(): void
    {
        $this->itemId = '';
        $this->itemSearch = '';
    }

    public function saveCurrentReport(): void
    {
        $tenant = app(TenantManager::class)->tenant();
        if (! $tenant) {
            return;
        }

        $name = trim($this->saveName);
        if ($name === '') {
            $name = $this->currentReportLabel().' — '.$this->periodLabel();
        }

        $saved = $this->savedReports();
        $id = $this->loadedReportId !== '' ? $this->loadedReportId : (string) Str::uuid();
        $entry = [
            'id' => $id,
            'name' => mb_substr($name, 0, 80),
            'filters' => $this->filterPayload(),
            'hidden' => $this->hiddenColumns,
        ];

        $found = false;
        foreach ($saved as $i => $row) {
            if (($row['id'] ?? '') === $id) {
                $saved[$i] = $entry;
                $found = true;
                break;
            }
        }
        if (! $found) {
            $saved[] = $entry;
        }
        $saved = array_slice($saved, -20);

        $tenant->setSetting('reporting.saved_reports', $saved);
        $this->loadedReportId = $id;
        $this->saveName = '';
        session()->flash('success', 'Rapport enregistré.');
    }

    public function updatedLoadedReportId(string $id): void
    {
        if ($id !== '') {
            $this->loadSavedReport($id);
        }
    }

    public function loadSavedReport(string $id): void
    {
        foreach ($this->savedReports() as $row) {
            if (($row['id'] ?? '') !== $id) {
                continue;
            }
            $filters = $row['filters'] ?? [];
            $this->module = (string) ($filters['module'] ?? $this->module);
            $this->report = (string) ($filters['report'] ?? $this->report);
            $this->period = (string) ($filters['period'] ?? 'custom');
            $this->dateFrom = (string) ($filters['from'] ?? $this->dateFrom);
            $this->dateTo = (string) ($filters['to'] ?? $this->dateTo);
            $this->storeId = (string) ($filters['store_id'] ?? '');
            $this->clientId = (string) ($filters['client_id'] ?? '');
            $this->status = (string) ($filters['status'] ?? '');
            $this->userId = (string) ($filters['user_id'] ?? '');
            $this->categoryId = (string) ($filters['category_id'] ?? '');
            $this->itemId = (string) ($filters['item_id'] ?? '');
            $this->amountMin = (string) ($filters['amount_min'] ?? '');
            $this->amountMax = (string) ($filters['amount_max'] ?? '');
            $this->sort = (string) ($filters['sort'] ?? 'date');
            $this->dir = (string) ($filters['dir'] ?? 'desc');
            $this->hiddenColumns = $row['hidden'] ?? [];
            $this->loadedReportId = $id;
            $this->saveName = (string) ($row['name'] ?? '');
            $this->applied = $this->filterPayload();
            $this->resetPage();

            return;
        }
    }

    public function deleteSavedReport(string $id): void
    {
        $tenant = app(TenantManager::class)->tenant();
        if (! $tenant) {
            return;
        }

        $saved = array_values(array_filter(
            $this->savedReports(),
            fn ($row) => ($row['id'] ?? '') !== $id
        ));
        $tenant->setSetting('reporting.saved_reports', $saved);
        if ($this->loadedReportId === $id) {
            $this->loadedReportId = '';
            $this->saveName = '';
        }
    }

    public function exportExcel()
    {
        $user = auth('tenant')->user();
        if ($user && method_exists($user, 'hasPermission')
            && ! $user->hasPermission('reporting.export')
            && ! $user->hasPermission('reporting.view')) {
            session()->flash('error', 'Permission d\'export insuffisante.');

            return null;
        }

        $result = app(ReportRunner::class)->run(
            $this->applied !== [] ? $this->applied : $this->filterPayload(),
            1,
            ReportRunner::EXPORT_MAX,
            true
        );
        [$headers, $rows] = $this->flatExport($result);
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', $this->report) ?: 'rapport';

        return ReportingExcelExporter::download(
            'reporting-'.$slug.'-'.now()->format('Y-m-d').'.xls',
            $headers,
            $rows,
            ($result['title'] ?? 'Rapport').' — '.$this->periodLabel()
        );
    }

    public function exportPdfUrl(): string
    {
        return $this->exportUrl('tenant.reporting.explorer.pdf');
    }

    public function printUrl(): string
    {
        return $this->exportUrl('tenant.reporting.explorer.print');
    }

    public function render()
    {
        $runner = app(ReportRunner::class);
        $available = $runner->availableModules();
        $modules = $this->visibleModules($available);

        if (! isset($modules[$this->module])) {
            $this->module = (string) array_key_first($modules);
            $this->report = ReportCatalog::firstReport($this->module);
        }

        $tenant = app(TenantManager::class)->tenant();
        $result = $runner->run(
            $this->applied !== [] ? $this->applied : $this->filterPayload(),
            $this->getPage(),
            $this->perPage
        );
        $currency = $tenant ? (string) $tenant->getSetting('currency', 'XOF') : 'XOF';
        $tenantCode = $tenant?->code ?? request()->query('tenant') ?? session('tenant_code');
        $catalog = $modules[$this->module]['reports'][$this->report] ?? ['filters' => [], 'statuses' => []];

        return view('inovcom-reporting::livewire.reporting.index', [
            'result' => $result,
            'modules' => $modules,
            'catalog' => $catalog,
            'currency' => $currency,
            'tenantCode' => $tenantCode,
            'periodLabel' => $this->periodLabel(),
            'savedReports' => $this->savedReports(),
            'stores' => $this->storeOptions($tenant),
            'users' => $this->userOptions(),
            'categories' => $this->categoryOptions(),
            'clientSuggestions' => $this->searchClients(),
            'itemSuggestions' => $this->searchItems(),
            'canExport' => $this->canExport(),
        ])->layout('layouts.app', [
            'title' => 'Rapports et analyses',
            'subtitle' => 'Générez, filtrez et exportez vos données selon vos besoins.',
            'hidePageHeader' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filterPayload(): array
    {
        return [
            'module' => $this->module,
            'report' => $this->report,
            'period' => $this->period,
            'from' => $this->dateFrom ?: now()->startOfMonth()->format('Y-m-d'),
            'to' => $this->dateTo ?: now()->format('Y-m-d'),
            'store_id' => $this->storeId !== '' ? (int) $this->storeId : null,
            'client_id' => $this->clientId !== '' ? (int) $this->clientId : null,
            'status' => $this->status,
            'user_id' => $this->userId !== '' ? (int) $this->userId : null,
            'category_id' => $this->categoryId !== '' ? (int) $this->categoryId : null,
            'item_id' => $this->itemId !== '' ? (int) $this->itemId : null,
            'amount_min' => $this->amountMin !== '' ? $this->amountMin : null,
            'amount_max' => $this->amountMax !== '' ? $this->amountMax : null,
            'sort' => $this->sort,
            'dir' => $this->dir,
        ];
    }

    private function syncModuleDefaults(): void
    {
        $available = app(ReportRunner::class)->availableModules();
        $modules = $this->visibleModules($available);
        if (! isset($modules[$this->module])) {
            $this->module = (string) (array_key_first($modules) ?: 'invoicing');
        }
        if (! isset($modules[$this->module]['reports'][$this->report])) {
            $this->report = ReportCatalog::firstReport($this->module);
        }
    }

    private function syncDatesFromPeriod(): void
    {
        $now = now();
        if ($this->period === 'today' || $this->period === 'daily') {
            $this->dateFrom = $now->format('Y-m-d');
            $this->dateTo = $now->format('Y-m-d');
        } elseif ($this->period === 'weekly') {
            $this->dateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
            $this->dateTo = $now->copy()->endOfWeek()->format('Y-m-d');
        } elseif ($this->period === 'monthly') {
            $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
            $this->dateTo = $now->copy()->endOfMonth()->format('Y-m-d');
        } elseif ($this->period === 'yearly') {
            $this->dateFrom = $now->copy()->startOfYear()->format('Y-m-d');
            $this->dateTo = $now->copy()->endOfYear()->format('Y-m-d');
        }
    }

    /**
     * @param  array<string, bool>  $available
     * @return array<string, array<string, mixed>>
     */
    private function visibleModules(array $available): array
    {
        $out = [];
        foreach (ReportCatalog::modules() as $key => $meta) {
            if (! empty($available[$key])) {
                $out[$key] = $meta;
            }
        }

        if (! empty($available['invoicing']) && isset($out['sales']['reports'])) {
            foreach (['top_produits', 'top_produits_marge', 'top_produits_qty', 'ca_categorie'] as $dup) {
                unset($out['sales']['reports'][$dup]);
            }
        }

        return $out !== [] ? $out : ReportCatalog::modules();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function savedReports(): array
    {
        $tenant = app(TenantManager::class)->tenant();
        $raw = $tenant?->getSetting('reporting.saved_reports', []);
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, fn ($row) => is_array($row) && ! empty($row['id'])));
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function storeOptions($tenant): array
    {
        if (! $tenant) {
            return [];
        }

        return app(StoreContextService::class)->activeStores($tenant)
            ->map(fn ($s) => ['id' => (int) $s->id, 'name' => (string) $s->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function userOptions(): array
    {
        if (! Schema::connection('tenant')->hasTable('users')) {
            return [];
        }

        return DB::connection('tenant')
            ->table('users')
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        $table = $this->module === 'expenses' ? 'expense_categories' : 'categories';
        if (! Schema::connection('tenant')->hasTable($table)) {
            return [];
        }

        return DB::connection('tenant')
            ->table($table)
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function searchClients(): array
    {
        if ($this->clientId !== '' && Schema::connection('tenant')->hasTable('clients')) {
            $name = DB::connection('tenant')->table('clients')->where('id', $this->clientId)->value('name');
            if ($name) {
                return [['id' => (int) $this->clientId, 'name' => (string) $name]];
            }
        }

        $q = trim($this->clientSearch);
        if ($q === '' || mb_strlen($q) < 2 || ! Schema::connection('tenant')->hasTable('clients')) {
            return [];
        }

        $like = '%'.$q.'%';

        return DB::connection('tenant')
            ->table('clients')
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)->orWhere('code', 'like', $like);
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function searchItems(): array
    {
        if ($this->itemId !== '' && Schema::connection('tenant')->hasTable('items')) {
            $name = DB::connection('tenant')->table('items')->where('id', $this->itemId)->value('name');
            if ($name) {
                return [['id' => (int) $this->itemId, 'name' => (string) $name]];
            }
        }

        $q = trim($this->itemSearch);
        if ($q === '' || mb_strlen($q) < 2 || ! Schema::connection('tenant')->hasTable('items')) {
            return [];
        }

        $like = '%'.$q.'%';

        return DB::connection('tenant')
            ->table('items')
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)->orWhere('sku', 'like', $like);
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name'])
            ->map(fn ($i) => ['id' => (int) $i->id, 'name' => (string) $i->name])
            ->all();
    }

    private function periodLabel(): string
    {
        $from = Carbon::parse($this->dateFrom ?: now());
        $to = Carbon::parse($this->dateTo ?: now());
        if ($from->toDateString() === $to->toDateString()) {
            return $from->translatedFormat('d F Y');
        }

        return $from->format('d/m/Y').' – '.$to->format('d/m/Y');
    }

    private function currentReportLabel(): string
    {
        return ReportCatalog::report($this->module, $this->report)['label'] ?? 'Rapport';
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function flatExport(array $result): array
    {
        $headers = [];
        $keys = [];
        foreach ($result['headers'] ?? [] as $col) {
            $headers[] = $col['label'];
            $keys[] = $col['key'];
        }
        $rows = [];
        foreach ($result['rows'] ?? [] as $row) {
            $line = [];
            foreach ($keys as $key) {
                $line[] = $row[$key.'_label'] ?? $row[$key] ?? '';
            }
            $rows[] = $line;
        }
        if (! empty($result['totals'])) {
            $totalLine = [];
            foreach ($keys as $i => $key) {
                $totalLine[] = $i === 0 ? 'Total' : ($result['totals'][$key] ?? '');
            }
            $rows[] = $totalLine;
        }

        return [$headers, $rows];
    }

    private function exportUrl(string $routeName): string
    {
        if (! Route::has($routeName)) {
            return '#';
        }

        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;

        $filters = $this->applied !== [] ? $this->applied : $this->filterPayload();

        return route($routeName, array_filter([
            'tenant' => $tenantCode,
            'module' => $filters['module'] ?? $this->module,
            'report_type' => $filters['report'] ?? $this->report,
            'date_from' => $filters['from'] ?? $this->dateFrom,
            'date_to' => $filters['to'] ?? $this->dateTo,
            'store_id' => $filters['store_id'] ?? $this->storeId,
            'client_id' => $filters['client_id'] ?? $this->clientId,
            'status' => $filters['status'] ?? $this->status,
            'user_id' => $filters['user_id'] ?? $this->userId,
            'category_id' => $filters['category_id'] ?? $this->categoryId,
            'item_id' => $filters['item_id'] ?? $this->itemId,
            'amount_min' => $filters['amount_min'] ?? $this->amountMin,
            'amount_max' => $filters['amount_max'] ?? $this->amountMax,
            'sort' => $filters['sort'] ?? $this->sort,
            'dir' => $filters['dir'] ?? $this->dir,
        ], fn ($v) => $v !== '' && $v !== null));
    }

    private function canExport(): bool
    {
        $user = auth('tenant')->user();
        if (! $user || ! method_exists($user, 'hasPermission')) {
            return true;
        }

        return $user->hasPermission('reporting.export') || $user->hasPermission('reporting.view');
    }
}
