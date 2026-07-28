<?php

namespace Pressing\Http\Livewire\Orders;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\ArticleType;
use Pressing\Models\PressingOrder;
use Pressing\Models\PressingOrderConstitutionLine;
use Pressing\Services\PressingNotificationDispatcher;
use Pressing\Services\PressingSortingService;
use Pressing\Support\PressingConstitution;
use Pressing\Support\PressingProfile;
use Pressing\Support\PressingWorkflow;

class OrdersTri extends Component
{
    use AuthorizesPressingActions;

    public PressingOrder $order;

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public ?int $activeLineIndex = null;

    /** @var list<string> */
    public array $quick_colors = [];

    /** @var list<string> */
    public array $quick_patterns = [];

    public string $quick_color_custom = '';

    public string $quick_pattern_custom = '';

    /** Production employee who will own the order after Tri. */
    public ?int $production_user_id = null;

    public function mount(PressingOrder $pressingOrder): void
    {
        $this->authorizePressingAction('pressing_orders.view');
        $this->assertCanAccessOrder($pressingOrder);

        $sorting = app(PressingSortingService::class);
        $this->order = $pressingOrder->load(['client', 'agence', 'items.articleType', 'currentStage', 'assignee', 'receptionist']);

        $sorting->seedFromReception($this->order);
        $sorting->markInProgress($this->order->fresh());

        $triStage = PressingWorkflow::stageByName(PressingWorkflow::STAGE_TRI);
        if ($triStage && ! $this->order->isSortingCompleted()) {
            $this->order->update([
                'current_stage_id' => $triStage->id,
                'assigned_user_id' => $this->order->assigned_user_id ?: Auth::guard('tenant')->id(),
            ]);
            $this->order->refresh();
        }

        $productionIds = PressingProfile::productionEmployees((int) $this->order->agence_id)
            ->pluck('id')
            ->all();
        if ($this->order->assigned_user_id && in_array((int) $this->order->assigned_user_id, $productionIds, true)) {
            $this->production_user_id = (int) $this->order->assigned_user_id;
        } elseif (count($productionIds) === 1) {
            $this->production_user_id = (int) $productionIds[0];
        }

        $this->loadLines();

        if ($this->lines === []) {
            $this->lines = [$this->blankLine()];
        }

        $this->selectFirstIncomplete();
    }

    public function selectLine(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $this->activeLineIndex = $index;
        $this->quick_colors = PressingOrderConstitutionLine::splitList($this->lines[$index]['color'] ?? '');
        $this->quick_patterns = PressingOrderConstitutionLine::splitList($this->lines[$index]['pattern'] ?? '');
        $this->quick_color_custom = '';
        $this->quick_pattern_custom = '';
        $this->resetErrorBag();
    }

    public function toggleColor(string $color): void
    {
        $this->quick_colors = $this->toggleListValue($this->quick_colors, $color);
        $this->applyDetailToActiveLine();
    }

    public function togglePattern(string $pattern): void
    {
        $this->quick_patterns = $this->toggleListValue($this->quick_patterns, $pattern);
        $this->applyDetailToActiveLine();
    }

    public function removeColor(string $color): void
    {
        $this->quick_colors = $this->withoutListValue($this->quick_colors, $color);
        $this->applyDetailToActiveLine();
    }

    public function removePattern(string $pattern): void
    {
        $this->quick_patterns = $this->withoutListValue($this->quick_patterns, $pattern);
        $this->applyDetailToActiveLine();
    }

    public function addCustomColor(): void
    {
        $this->quick_colors = $this->mergeCustomIntoList($this->quick_colors, $this->quick_color_custom);
        $this->quick_color_custom = '';
        $this->applyDetailToActiveLine();
    }

    public function addCustomPattern(): void
    {
        $this->quick_patterns = $this->mergeCustomIntoList($this->quick_patterns, $this->quick_pattern_custom);
        $this->quick_pattern_custom = '';
        $this->applyDetailToActiveLine();
    }

    public function confirmActiveLine(): void
    {
        $this->authorizeTriAction();

        if ($this->activeLineIndex === null || ! isset($this->lines[$this->activeLineIndex])) {
            return;
        }

        // Absorb any pending free-text before validating
        if (trim($this->quick_color_custom) !== '') {
            $this->addCustomColor();
        }
        if (trim($this->quick_pattern_custom) !== '') {
            $this->addCustomPattern();
        }

        $this->applyDetailToActiveLine();

        $index = $this->activeLineIndex;
        if (! app(PressingSortingService::class)->isLineValid($this->lines[$index])) {
            $this->addError('quick_colors', 'Indiquez au moins une couleur ou un descriptif pour valider cette ligne.');

            return;
        }

        $this->resetErrorBag();
        $this->selectNextIncomplete($index);
    }

    public function addBlankLine(): void
    {
        $this->authorizeTriAction();
        $this->lines[] = $this->blankLine();
        $this->selectLine(count($this->lines) - 1);
    }

    public function removeLine(int $index): void
    {
        $this->authorizeTriAction();

        if (count($this->lines) <= 1) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->activeLineIndex === null) {
            $this->selectFirstIncomplete();

            return;
        }

        if ($this->activeLineIndex === $index) {
            $this->selectFirstIncomplete();

            return;
        }

        if ($this->activeLineIndex > $index) {
            $this->activeLineIndex--;
        }
    }

    public function incrementQty(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }
        $this->lines[$index]['quantity'] = min(999, (int) ($this->lines[$index]['quantity'] ?? 1) + 1);
    }

    public function decrementQty(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }
        $this->lines[$index]['quantity'] = max(1, (int) ($this->lines[$index]['quantity'] ?? 1) - 1);
    }

    public function updatedLines($value, $key): void
    {
        if (! str_contains((string) $key, 'article_type_id')) {
            return;
        }

        [$index] = explode('.', (string) $key);
        $typeId = (int) ($this->lines[(int) $index]['article_type_id'] ?? 0);
        if ($typeId) {
            $this->lines[(int) $index]['type_name'] = ArticleType::find($typeId)?->name ?? '';
        }
    }

    public function completeSorting(): void
    {
        $this->authorizeTriAction();
        $this->assertCanAccessOrder($this->order);

        $this->validate([
            'production_user_id' => ['required', 'integer'],
        ], [
            'production_user_id.required' => __('Choisissez l’employé production qui prendra la commande.'),
        ]);

        $allowed = PressingProfile::productionEmployees((int) $this->order->agence_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! in_array((int) $this->production_user_id, $allowed, true)) {
            $this->addError('production_user_id', __('Employé production invalide pour cette agence.'));

            return;
        }

        try {
            $order = app(PressingSortingService::class)->completeSorting(
                $this->order,
                $this->lines,
                (int) $this->production_user_id
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        app(PressingNotificationDispatcher::class)->dispatch(
            'assigned_production',
            $order,
            [
                'message' => __('La commande :number (:client) vous a été assignée en production.', [
                    'number' => $order->number,
                    'client' => $order->client?->full_name ?? '—',
                ]),
                'user_ids' => [(int) $this->production_user_id],
            ]
        );

        session()->flash('success', __('Commande constituée — assignée à :name.', [
            'name' => $order->assignee?->name ?? __('production'),
        ]));

        $this->redirect(route('tenant.pressing_workflow.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function getPreviewProperty(): string
    {
        return PressingConstitution::summary(
            collect($this->lines)->filter(fn ($l) => app(PressingSortingService::class)->isLineValid($l))
        );
    }

    public function getProgressProperty(): array
    {
        $sorting = app(PressingSortingService::class);
        $total = count($this->lines);
        $done = $sorting->validLineCount($this->lines);
        $pieces = $sorting->totalQuantityFromLines(array_filter($this->lines, fn ($l) => $sorting->isLineValid($l)));

        return ['done' => $done, 'total' => $total, 'pieces' => $pieces, 'remaining' => max(0, $total - $done)];
    }

    public function getActiveLineProperty(): ?array
    {
        if ($this->activeLineIndex === null || ! isset($this->lines[$this->activeLineIndex])) {
            return null;
        }

        return $this->lines[$this->activeLineIndex];
    }

    private function applyDetailToActiveLine(): void
    {
        if ($this->activeLineIndex === null || ! isset($this->lines[$this->activeLineIndex])) {
            return;
        }

        $this->lines[$this->activeLineIndex]['color'] = PressingOrderConstitutionLine::joinList($this->quick_colors);
        $this->lines[$this->activeLineIndex]['pattern'] = PressingOrderConstitutionLine::joinList($this->quick_patterns);
    }

    /** @param list<string> $list */
    private function toggleListValue(array $list, string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return $list;
        }

        $key = mb_strtolower($value);
        $exists = false;
        $out = [];
        foreach ($list as $item) {
            if (mb_strtolower(trim((string) $item)) === $key) {
                $exists = true;
                continue;
            }
            $out[] = $item;
        }

        if (! $exists) {
            $out[] = mb_strtolower($value);
        }

        return array_values($out);
    }

    /** @param list<string> $list */
    private function withoutListValue(array $list, string $value): array
    {
        $key = mb_strtolower(trim($value));

        return array_values(array_filter(
            $list,
            fn ($item) => mb_strtolower(trim((string) $item)) !== $key
        ));
    }

    /** @param list<string> $list */
    private function mergeCustomIntoList(array $list, string $custom): array
    {
        foreach (PressingOrderConstitutionLine::splitList($custom) as $item) {
            $key = mb_strtolower($item);
            $found = false;
            foreach ($list as $existing) {
                if (mb_strtolower(trim((string) $existing)) === $key) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $list[] = mb_strtolower($item);
            }
        }

        return array_values($list);
    }

    private function selectFirstIncomplete(): void
    {
        $sorting = app(PressingSortingService::class);
        foreach ($this->lines as $index => $line) {
            if (! $sorting->isLineValid($line)) {
                $this->selectLine($index);

                return;
            }
        }

        $this->activeLineIndex = $this->lines !== [] ? count($this->lines) - 1 : null;
        if ($this->activeLineIndex !== null) {
            $this->quick_colors = PressingOrderConstitutionLine::splitList($this->lines[$this->activeLineIndex]['color'] ?? '');
            $this->quick_patterns = PressingOrderConstitutionLine::splitList($this->lines[$this->activeLineIndex]['pattern'] ?? '');
        }
    }

    private function selectNextIncomplete(int $afterIndex): void
    {
        $sorting = app(PressingSortingService::class);
        $count = count($this->lines);

        for ($i = $afterIndex + 1; $i < $count; $i++) {
            if (! $sorting->isLineValid($this->lines[$i])) {
                $this->selectLine($i);

                return;
            }
        }

        for ($i = 0; $i <= $afterIndex; $i++) {
            if (! $sorting->isLineValid($this->lines[$i])) {
                $this->selectLine($i);

                return;
            }
        }

        $this->selectLine($afterIndex);
    }

    private function loadLines(): void
    {
        $this->lines = app(PressingSortingService::class)->linesToArray($this->order->fresh());
    }

    private function blankLine(): array
    {
        $type = ArticleType::where('is_active', true)->orderBy('sort_order')->first();

        return [
            'id' => null,
            'article_type_id' => $type?->id,
            'type_name' => $type?->name ?? '',
            'color' => '',
            'pattern' => '',
            'quantity' => 1,
            'notes' => '',
        ];
    }

    private function assertCanAccessOrder(PressingOrder $order): void
    {
        if ($this->canViewAllOrders()) {
            return;
        }

        $userId = (int) Auth::guard('tenant')->id();
        $assigned = (int) $order->assigned_user_id;
        $receptionist = (int) $order->receptionist_id;

        abort_unless(
            $userId === $assigned || $userId === $receptionist,
            403,
            'Cette commande n’est pas assignée à votre compte.'
        );
    }

    private function canViewAllOrders(): bool
    {
        $user = Auth::guard('tenant')->user();
        if ($user && method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return $this->can('pressing_orders.view_all');
    }

    private function canTri(): bool
    {
        return $this->can('pressing_orders.sort') || $this->can('pressing_orders.create');
    }

    private function authorizeTriAction(): void
    {
        abort_unless($this->canTri(), 403, 'Action non autorisée.');
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    public function render()
    {
        return view('pressing::livewire.orders.tri', [
            'articleTypes' => ArticleType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'colorPresets' => PressingOrderConstitutionLine::COLOR_PRESETS,
            'patternPresets' => PressingOrderConstitutionLine::PATTERN_PRESETS,
            'canComplete' => ! $this->order->isSortingCompleted(),
            'canSort' => $this->canTri(),
            'productionEmployees' => PressingProfile::productionEmployees((int) $this->order->agence_id),
        ])->layout('layouts.app', [
            'title' => 'Constitution — '.$this->order->number,
            'subtitle' => 'Tri par la réceptionniste',
        ]);
    }
}
