<?php

namespace Pressing\Http\Livewire\Workflow;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\Agence;
use Pressing\Models\OrderStageHistory;
use Pressing\Models\PressingOrder;
use Pressing\Models\WorkflowStage;
use Pressing\Services\PressingNotificationDispatcher;
use Pressing\Services\PressingSortingService;
use Pressing\Support\PressingAgenceContext;
use Pressing\Support\PressingProfile;
use Pressing\Support\PressingWorkflow;

class KanbanBoard extends Component
{
    use AuthorizesPressingActions;

    public string $search = '';

    public ?int $agenceFilter = null;

    public ?int $focusedOrderId = null;

    public bool $canViewAllAgences = false;

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_workflow.view');
        $this->canViewAllAgences = PressingAgenceContext::canViewAllAgences();

        if (! $this->canViewAllAgences) {
            $this->agenceFilter = PressingAgenceContext::userAgenceId();
        }
    }

    public function updatedAgenceFilter($value): void
    {
        if (! $this->canViewAllAgences) {
            $this->agenceFilter = PressingAgenceContext::userAgenceId();

            return;
        }

        $this->agenceFilter = $value === '' || $value === null ? null : (int) $value;
        $this->focusedOrderId = null;
    }

    public function updatedSearch(): void
    {
        $this->focusedOrderId = null;
    }

    public function focusOrder(int $orderId): void
    {
        $this->focusedOrderId = $orderId;
    }

    public function clearFocus(): void
    {
        $this->focusedOrderId = null;
    }

    public function reassignOrder(int $orderId, $userId): void
    {
        abort_unless($this->canReassignOrders(), 403, 'Action non autorisée.');

        $order = PressingOrder::with(['client', 'agence'])->findOrFail($orderId);

        if (! $this->canAccessOrderAgence((int) $order->agence_id)) {
            session()->flash('error', __('Cette commande n’appartient pas à votre agence.'));

            return;
        }

        $userId = $userId === '' || $userId === null ? null : (int) $userId;
        if (! $userId) {
            session()->flash('error', __('Choisissez un employé production.'));

            return;
        }

        $allowed = PressingProfile::productionEmployees((int) $order->agence_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! in_array($userId, $allowed, true)) {
            session()->flash('error', __('Employé production invalide pour cette agence.'));

            return;
        }

        $order->update(['assigned_user_id' => $userId]);
        $order = $order->fresh(['assignee', 'client']);

        app(PressingNotificationDispatcher::class)->dispatch(
            'assigned_production',
            $order,
            [
                'message' => __('La commande :number (:client) vous a été assignée en production.', [
                    'number' => $order->number,
                    'client' => $order->client?->full_name ?? '—',
                ]),
                'user_ids' => [$userId],
            ]
        );

        session()->flash('success', __('Commande :number assignée à :name.', [
            'number' => $order->number,
            'name' => $order->assignee?->name ?? '—',
        ]));
    }

    public function moveOrder(int $orderId, int $stageId): void
    {
        $this->authorizePressingAction('pressing_workflow.move');

        $order = PressingOrder::with(['currentStage', 'client', 'agence', 'receptionist', 'assignee'])->findOrFail($orderId);
        $stage = WorkflowStage::findOrFail($stageId);

        if (! PressingWorkflow::isProductionStage($stage)) {
            session()->flash('error', 'Cette étape n’apparaît plus sur le Kanban. Utilisez Fin de production ou Livraisons.');

            return;
        }

        if (! $this->canAccessOrderAgence((int) $order->agence_id)) {
            session()->flash('error', 'Cette commande n’appartient pas à votre agence.');

            return;
        }

        if ((int) $order->current_stage_id === (int) $stage->id) {
            return;
        }

        $sortingError = app(PressingSortingService::class)->validateMoveToStage($order, $stage);
        if ($sortingError) {
            session()->flash('error', $sortingError);

            return;
        }

        $userId = Auth::guard('tenant')->id();
        $enteringFin = PressingWorkflow::isFinProductionStage($stage);

        $order->update([
            'current_stage_id' => $stage->id,
            'status' => 'open',
            // Preserve production assignee on stage moves (reassign explicitly if needed)
            'assigned_user_id' => $enteringFin
                ? ($order->assigned_user_id ?: $order->receptionist_id ?: $userId)
                : ($order->assigned_user_id ?: $userId),
        ]);

        OrderStageHistory::create([
            'order_id' => $order->id,
            'stage_id' => $stage->id,
            'stage_name' => $stage->name,
            'user_id' => $userId,
            'moved_at' => now(),
        ]);

        $order = $order->fresh(['client', 'agence', 'receptionist', 'assignee']);

        if ($enteringFin) {
            $notifyIds = collect([$order->receptionist_id, $order->assigned_user_id])
                ->filter()
                ->unique()
                ->values()
                ->all();

            app(PressingNotificationDispatcher::class)->dispatch(
                'fin_production',
                $order,
                [
                    'message' => "La commande {$order->number} ({$order->client?->full_name}) est en Fin de production. Contrôle qualité et emballage à faire.",
                    'user_ids' => $notifyIds,
                ]
            );
        }

        $this->focusedOrderId = $order->id;
        session()->flash(
            'success',
            $enteringFin
                ? "{$order->number} → Fin de production (réceptionniste notifiée)"
                : "Commande {$order->number} → {$stage->name}"
        );
    }

    private function canAccessOrderAgence(int $agenceId): bool
    {
        if ($this->canViewAllAgences) {
            return true;
        }

        return $agenceId === (int) PressingAgenceContext::userAgenceId();
    }

    private function applyAgenceScope(Builder $query): Builder
    {
        if ($this->canViewAllAgences) {
            return $query->when($this->agenceFilter, fn ($q) => $q->where('agence_id', $this->agenceFilter));
        }

        $agenceId = PressingAgenceContext::userAgenceId();

        return $agenceId
            ? $query->where('agence_id', $agenceId)
            : $query->whereRaw('1 = 0');
    }

    private function pipelineQuery(): Builder
    {
        $query = $this->applyAgenceScope(
            PressingOrder::query()
                ->with(['client', 'agence', 'assignee', 'receptionist', 'currentStage', 'constitutionLines.articleType'])
                ->whereIn('status', ['open', 'ready'])
                ->whereNotNull('current_stage_id')
        );

        if (! $this->canViewFullPipeline()) {
            $userId = Auth::guard('tenant')->id();
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_user_id', $userId)
                    ->orWhere('receptionist_id', $userId);
            });
        }

        return $query;
    }

    private function canViewFullPipeline(): bool
    {
        if ($this->canViewAllAgences) {
            return true;
        }

        $user = Auth::guard('tenant')->user();
        if ($user && method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return $this->can('pressing_orders.view_all');
    }

    private function canReassignOrders(): bool
    {
        if ($this->canViewFullPipeline()) {
            return true;
        }

        return $this->can('pressing_orders.sort') || $this->can('pressing_orders.create');
    }

    /** Orders assigned to the current user (or received by them), for powerful search. */
    private function myAssignedQuery(): Builder
    {
        $userId = Auth::guard('tenant')->id();

        return $this->applyAgenceScope(
            PressingOrder::query()
                ->with(['client', 'agence', 'currentStage'])
                ->whereIn('status', ['open', 'ready'])
                ->where(function ($q) use ($userId) {
                    $q->where('assigned_user_id', $userId)
                        ->orWhere('receptionist_id', $userId);
                })
        );
    }

    private function searchMyOrders(string $term): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        $like = '%' . $term . '%';

        return $this->myAssignedQuery()
            ->where(function ($q) use ($like, $term) {
                $q->where('number', 'like', $like)
                    ->orWhere('qr_token', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('client', function ($client) use ($like) {
                        $client->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('whatsapp', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('code', 'like', $like)
                            ->orWhereRaw("CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) like ?", [$like]);
                    });

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            })
            ->orderByDesc('received_at')
            ->limit(20)
            ->get();
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_workflow.view');

        $stages = PressingWorkflow::kanbanStages();

        $pipelineOrders = $this->pipelineQuery()
            ->latest('received_at')
            ->get();

        // Only show orders that are currently on a kanban production stage
        $kanbanIds = $stages->pluck('id');
        $pipelineOrders = $pipelineOrders->filter(
            fn (PressingOrder $o) => $kanbanIds->contains((int) $o->current_stage_id)
        )->values();

        $ordersByStage = $pipelineOrders->groupBy('current_stage_id');

        $myAssigned = $this->myAssignedQuery()
            ->orderByDesc('received_at')
            ->limit(12)
            ->get();

        $searchResults = strlen(trim($this->search)) >= 1
            ? $this->searchMyOrders($this->search)
            : collect();

        $lockedAgence = $this->canViewAllAgences
            ? null
            : PressingAgenceContext::userAgence();

        $finStage = PressingWorkflow::finProductionStage();
        $myTriCount = 0; // reserved for tri via OrdersTri/constitution; not a kanban column
        $finCount = $finStage
            ? ($ordersByStage[$finStage->id] ?? collect())->count()
            : 0;

        return view('pressing::livewire.workflow.kanban', [
            'stages' => $stages,
            'ordersByStage' => $ordersByStage,
            'pipelineCount' => $pipelineOrders->count(),
            'triStageId' => null,
            'finStageId' => $finStage?->id,
            'myTriCount' => $myTriCount,
            'finCount' => $finCount,
            'viewFullPipeline' => $this->canViewFullPipeline(),
            'agences' => $this->canViewAllAgences
                ? Agence::where('is_active', true)->orderBy('name')->get()
                : collect(),
            'lockedAgence' => $lockedAgence,
            'canMove' => $this->can('pressing_workflow.move'),
            'canSort' => $this->can('pressing_orders.sort') || $this->can('pressing_orders.create'),
            'canReassign' => $this->canReassignOrders(),
            'productionEmployees' => PressingProfile::productionEmployees(
                $this->canViewAllAgences
                    ? $this->agenceFilter
                    : PressingAgenceContext::userAgenceId()
            ),
            'myAssigned' => $myAssigned,
            'searchResults' => $searchResults,
        ])->layout('layouts.app', [
            'title' => 'Production',
            'subtitle' => 'Mise en Production → Lavage → Séchage → Repassage → Fin de production',
        ]);
    }
}
