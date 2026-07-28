<?php

namespace Pressing\Http\Livewire\FinProduction;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\OrderStageHistory;
use Pressing\Models\PressingDelivery;
use Pressing\Models\PressingOrder;
use Pressing\Services\PressingNotificationDispatcher;
use Pressing\Support\PressingAgenceContext;
use Pressing\Support\PressingWorkflow;

class FinProductionIndex extends Component
{
    use AuthorizesPressingActions;

    public string $search = '';

    public string $tab = 'a_controler'; // a_controler | pretes

    public ?int $activeOrderId = null;

    public string $qc_notes = '';

    public string $delivery_type = 'agence';

    public ?string $delivery_address = null;

    public ?string $delivery_notes = null;

    public function mount(): void
    {
        abort_unless($this->canAccessFinProduction(), 403, 'Action non autorisée.');
    }

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['a_controler', 'pretes'], true) ? $tab : 'a_controler';
        $this->activeOrderId = null;
    }

    public function openOrder(int $orderId): void
    {
        abort_unless($this->canAccessFinProduction(), 403, 'Action non autorisée.');
        $this->activeOrderId = $orderId;
        $this->qc_notes = '';
        $this->delivery_type = 'agence';
        $this->delivery_address = null;
        $this->delivery_notes = null;

        $order = PressingOrder::with('client')->find($orderId);
        if ($order?->client?->address) {
            $this->delivery_address = $order->client->address;
        }
    }

    public function closePanel(): void
    {
        $this->activeOrderId = null;
    }

    public function rejectQc(int $orderId): void
    {
        abort_unless($this->canProcessFinProduction(), 403, 'Action non autorisée.');

        $order = $this->findScopedOrder($orderId);
        $mise = PressingWorkflow::productionEntryStage();
        if (! $mise) {
            session()->flash('error', 'Étape Mise en Production introuvable.');

            return;
        }

        $order->update([
            'current_stage_id' => $mise->id,
            'status' => 'open',
            'assigned_user_id' => Auth::guard('tenant')->id(),
        ]);

        OrderStageHistory::create([
            'order_id' => $order->id,
            'stage_id' => $mise->id,
            'stage_name' => $mise->name,
            'user_id' => Auth::guard('tenant')->id(),
            'moved_at' => now(),
            'note' => 'CQ non conforme — retour production' . ($this->qc_notes ? ' : '.$this->qc_notes : ''),
        ]);

        session()->flash('success', "{$order->number} renvoyée en Mise en Production.");
        $this->activeOrderId = null;
        $this->tab = 'a_controler';
    }

    public function validateAndPackage(int $orderId): void
    {
        abort_unless($this->canProcessFinProduction(), 403, 'Action non autorisée.');

        $data = $this->validate([
            'delivery_type' => ['required', 'in:agence,domicile'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'qc_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['delivery_type'] === 'domicile' && trim((string) ($data['delivery_address'] ?? '')) === '') {
            $this->addError('delivery_address', 'Adresse requise pour une livraison à domicile.');

            return;
        }

        $order = $this->findScopedOrder($orderId);

        DB::connection('tenant')->transaction(function () use ($order, $data) {
            $pret = PressingWorkflow::stageByName(PressingWorkflow::STAGE_PRET);
            $userId = Auth::guard('tenant')->id();

            $order->update([
                'status' => 'ready',
                'current_stage_id' => $pret?->id ?? $order->current_stage_id,
                'assigned_user_id' => $order->assigned_user_id ?: $userId,
            ]);

            OrderStageHistory::create([
                'order_id' => $order->id,
                'stage_id' => $pret?->id,
                'stage_name' => $pret?->name ?? 'Prêt',
                'user_id' => $userId,
                'moved_at' => now(),
                'note' => 'CQ OK + emballé' . (! empty($data['qc_notes']) ? ' — '.$data['qc_notes'] : ''),
            ]);

            // Always create delivery so the flow is unambiguous
            $existingOpen = PressingDelivery::query()
                ->where('order_id', $order->id)
                ->whereIn('status', ['pending', 'in_transit'])
                ->exists();

            if (! $existingOpen) {
                PressingDelivery::create([
                    'order_id' => $order->id,
                    'agence_id' => $order->agence_id,
                    'type' => $data['delivery_type'],
                    'status' => 'pending',
                    'address' => $data['delivery_type'] === 'domicile'
                        ? ($data['delivery_address'] ?: $order->client?->address)
                        : null,
                    'scheduled_at' => now(),
                    'notes' => $data['delivery_notes'],
                    'created_by' => $userId,
                    'driver_user_id' => $data['delivery_type'] === 'agence' ? $userId : null,
                ]);
            }

            app(PressingNotificationDispatcher::class)->dispatch(
                'order_ready',
                $order->fresh(['client', 'agence', 'receptionist', 'assignee'])
            );
        });

        $label = $data['delivery_type'] === 'agence' ? 'retrait en agence' : 'livraison domicile';
        session()->flash('success', "{$order->number} emballée et prête ({$label}).");
        $this->activeOrderId = null;
        $this->tab = 'pretes';
    }

    private function canAccessFinProduction(): bool
    {
        return $this->can('pressing_fin_production.view')
            || $this->can('pressing_fin_production.process')
            || $this->can('pressing_orders.create');
    }

    private function canProcessFinProduction(): bool
    {
        return $this->can('pressing_fin_production.process')
            || $this->can('pressing_orders.create');
    }

    private function findScopedOrder(int $orderId): PressingOrder
    {
        $query = PressingOrder::query()->with(['client', 'agence', 'constitutionLines.articleType']);

        if (! PressingAgenceContext::canViewAllAgences()) {
            $agenceId = PressingAgenceContext::userAgenceId();
            $query->where('agence_id', $agenceId ?: 0);
        }

        return $query->findOrFail($orderId);
    }

    private function baseQuery()
    {
        $query = PressingOrder::query()
            ->with(['client', 'agence', 'assignee', 'constitutionLines.articleType', 'currentStage'])
            ->whereIn('status', ['open', 'ready']);

        if (! PressingAgenceContext::canViewAllAgences()) {
            $agenceId = PressingAgenceContext::userAgenceId();
            $query->where('agence_id', $agenceId ?: 0);
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('number', 'like', $term)
                    ->orWhereHas('client', function ($c) use ($term) {
                        $c->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('whatsapp', 'like', $term);
                    });
            });
        }

        return $query;
    }

    public function render()
    {
        abort_unless($this->canAccessFinProduction(), 403, 'Action non autorisée.');

        $fin = PressingWorkflow::finProductionStage();
        $pret = PressingWorkflow::stageByName(PressingWorkflow::STAGE_PRET);

        $aControler = $fin
            ? (clone $this->baseQuery())
                ->where('status', 'open')
                ->where('current_stage_id', $fin->id)
                ->latest('updated_at')
                ->get()
            : collect();

        $pretes = (clone $this->baseQuery())
            ->where('status', 'ready')
            ->latest('updated_at')
            ->get();

        $active = $this->activeOrderId
            ? PressingOrder::with(['client', 'agence', 'constitutionLines.articleType', 'items.articleType'])
                ->find($this->activeOrderId)
            : null;

        return view('pressing::livewire.fin-production.index', [
            'aControler' => $aControler,
            'pretes' => $pretes,
            'active' => $active,
            'canProcess' => $this->canProcessFinProduction(),
            'tenantCode' => request()->query('tenant') ?? session('tenant_code'),
        ])->layout('layouts.app', [
            'title' => 'Fin de production',
            'subtitle' => 'Contrôle qualité · Emballage · Mise en livraison',
        ]);
    }
}
