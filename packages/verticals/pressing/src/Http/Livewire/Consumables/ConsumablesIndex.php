<?php

namespace Pressing\Http\Livewire\Consumables;

use Illuminate\Support\Facades\Auth;
use InovCom\Users\Models\User;
use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\PressingConsumableIssue;
use Pressing\Models\PressingOrder;
use Pressing\Services\PressingConsumablesService;

class ConsumablesIndex extends Component
{
    use AuthorizesPressingActions;

    public string $viewMode = 'liste'; // liste | nouveau

    public string $listFilter = 'atelier'; // atelier | livraison | all

    public ?int $taken_by = null;

    public string $purpose = 'lavage';

    public string $work_context = '';

    public string $pieces_processed = '';

    public ?int $order_id = null;

    public string $notes = '';

    /** @var array<int, array{item_id:?int, quantity:string}> */
    public array $issueLines = [];

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_consumables.view');
        app(PressingConsumablesService::class)->seedCatalog();
        $this->taken_by = Auth::guard('tenant')->id();
        $this->resetIssueLines();
    }

    public function showList(): void
    {
        $this->viewMode = 'liste';
    }

    public function showForm(): void
    {
        abort_unless($this->can('pressing_consumables.consume') || $this->can('stock.adjust'), 403);
        $this->resetIssueForm();
        $this->viewMode = 'nouveau';
    }

    public function addIssueLine(): void
    {
        $this->issueLines[] = ['item_id' => null, 'quantity' => '1'];
    }

    public function removeIssueLine(int $index): void
    {
        unset($this->issueLines[$index]);
        $this->issueLines = array_values($this->issueLines);
        if ($this->issueLines === []) {
            $this->resetIssueLines();
        }
    }

    public function submitIssue(): void
    {
        abort_unless($this->can('pressing_consumables.consume') || $this->can('stock.adjust'), 403);

        $this->validate([
            'taken_by' => ['required', 'integer'],
            'purpose' => ['required', 'in:'.implode(',', array_keys(PressingConsumableIssue::PURPOSES))],
            'work_context' => ['nullable', 'string', 'max:120'],
            'pieces_processed' => ['nullable', 'integer', 'min:0'],
            'order_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
            'issueLines' => ['required', 'array', 'min:1'],
            'issueLines.*.item_id' => ['required', 'integer'],
            'issueLines.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        try {
            $issue = app(PressingConsumablesService::class)->issueAtelier([
                'taken_by' => $this->taken_by,
                'purpose' => $this->purpose,
                'work_context' => $this->work_context ?: null,
                'pieces_processed' => $this->pieces_processed !== '' ? (int) $this->pieces_processed : null,
                'order_id' => $this->order_id,
                'notes' => $this->notes ?: null,
            ], $this->issueLines);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', __('Bon de sortie :number enregistré.', ['number' => $issue->number]));
        $this->resetIssueForm();
        $this->listFilter = 'atelier';
        $this->viewMode = 'liste';
    }

    private function resetIssueLines(): void
    {
        $first = collect(app(PressingConsumablesService::class)->dashboardRows(PressingConsumablesService::USAGE_ATELIER))->first();
        $this->issueLines = [[
            'item_id' => $first['id'] ?? null,
            'quantity' => '1',
        ]];
    }

    private function resetIssueForm(): void
    {
        $this->purpose = 'lavage';
        $this->work_context = '';
        $this->pieces_processed = '';
        $this->order_id = null;
        $this->notes = '';
        $this->taken_by = Auth::guard('tenant')->id();
        $this->resetIssueLines();
        $this->resetValidation();
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_consumables.view');

        $service = app(PressingConsumablesService::class);
        $tenantCode = request()->query('tenant') ?? session('tenant_code');

        $issues = $service->recentIssues(40);
        if ($this->listFilter === 'atelier') {
            $issues = $issues->where('type', PressingConsumableIssue::TYPE_ATELIER)->values();
        } elseif ($this->listFilter === 'livraison') {
            $issues = $issues->where('type', PressingConsumableIssue::TYPE_LIVRAISON)->values();
        }

        return view('pressing::livewire.consumables.index', [
            'atelierItems' => $service->dashboardRows(PressingConsumablesService::USAGE_ATELIER),
            'issues' => $issues,
            'employees' => User::query()->orderBy('name')->limit(100)->get(['id', 'name']),
            'openOrders' => PressingOrder::query()
                ->whereIn('status', ['open', 'ready'])
                ->latest('received_at')
                ->limit(40)
                ->get(['id', 'number']),
            'purposes' => collect(PressingConsumableIssue::PURPOSES)
                ->except('livraison')
                ->all(),
            'canConsume' => $this->can('pressing_consumables.consume') || $this->can('stock.adjust'),
            'tenantCode' => $tenantCode,
            'hasStockRoute' => \Illuminate\Support\Facades\Route::has('tenant.stock.index'),
        ])->layout('layouts.app', [
            'title' => 'Bons de sortie',
            'subtitle' => 'Consommables atelier',
        ]);
    }
}
