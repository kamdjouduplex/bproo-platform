<?php

namespace Pressing\Http\Livewire\Orders;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\Agence;
use Pressing\Models\ArticleType;
use Pressing\Models\OrderStageHistory;
use Pressing\Models\PressingClient;
use Pressing\Models\PressingOrder;
use Pressing\Models\PressingOrderItem;
use Pressing\Models\PressingPayment;
use Pressing\Services\PressingNotificationDispatcher;
use Pressing\Services\PressingSortingService;
use Pressing\Support\PressingAgenceContext;
use Pressing\Support\PressingBilling;
use Pressing\Support\PressingSettings;
use Pressing\Support\PressingWorkflow;

class OrdersCreate extends Component
{
    use AuthorizesPressingActions;

    public ?int $agence_id = null;

    public ?int $client_id = null;

    public string $client_search = '';

    public string $billing_mode = PressingBilling::MODE_MIXED;

    public string $total_weight_kg = '';

    public string $weight_unit_price = '0';

    public ?string $notes = null;

    public string $discount_amount = '0';

    public string $tax_amount = '0';

    public string $advance_amount = '0';

    /** Loyalty reward applied at reception (discount voucher). */
    public ?int $applied_reward_id = null;

    public string $applied_reward_label = '';

    /** Paiements hors avance de réception (inchangés à la modification). */
    public float $other_payments_total = 0;

    public array $items = [];

    public bool $canPickAgence = false;

    public ?int $editingOrderId = null;

    public string $editingOrderNumber = '';

    public bool $orderAlreadySorted = false;

    /** Reception wizard: identify client first, then build the order. */
    public string $receptionStep = 'client'; // client | order

    public bool $showQuickCreate = false;

    public string $qc_first_name = '';

    public string $qc_last_name = '';

    public string $qc_whatsapp = '';

    public string $qc_phone = '';

    public string $qc_email = '';

    public string $qc_address = '';

    public function mount(?PressingOrder $pressingOrder = null): void
    {
        $this->canPickAgence = PressingAgenceContext::canViewAllAgences();

        if ($pressingOrder) {
            $this->authorizePressingAction('pressing_orders.update');
            $this->assertOrderEditable($pressingOrder);
            $this->fillFromOrder($pressingOrder);
            $this->receptionStep = 'order';

            return;
        }

        $this->authorizePressingAction('pressing_orders.create');

        $this->agence_id = PressingAgenceContext::userAgenceId()
            ?? Agence::where('is_active', true)->value('id');

        $this->billing_mode = PressingSettings::billingDefaultMode();
        $this->weight_unit_price = (string) PressingSettings::globalWeightPrice($this->agence_id);
        $this->items = [$this->blankItem()];
        $this->receptionStep = 'client';
    }

    public function getIsEditingProperty(): bool
    {
        return $this->editingOrderId !== null;
    }

    private function assertOrderEditable(PressingOrder $order): void
    {
        if ($order->status === 'delivered') {
            abort(403, 'Impossible de modifier une commande déjà livrée.');
        }

        if (! PressingAgenceContext::canViewAllAgences()) {
            $userAgence = PressingAgenceContext::userAgenceId();
            if ($userAgence && (int) $order->agence_id !== (int) $userAgence) {
                abort(403, 'Cette commande n’appartient pas à votre agence.');
            }
        }
    }

    private function fillFromOrder(PressingOrder $order): void
    {
        $order->loadMissing(['client', 'items']);

        $this->editingOrderId = $order->id;
        $this->editingOrderNumber = $order->number;
        $this->orderAlreadySorted = $order->isSortingCompleted();
        $this->agence_id = $order->agence_id;
        $this->client_id = $order->client_id;
        $this->client_search = $order->client?->full_name ?? '';
        $this->billing_mode = $order->billing_mode ?: PressingBilling::MODE_MIXED;
        $this->total_weight_kg = $order->total_weight_kg !== null ? (string) $order->total_weight_kg : '';
        $this->weight_unit_price = (string) ($order->weight_unit_price ?? PressingSettings::globalWeightPrice($this->agence_id));
        $this->notes = $order->notes;
        $this->discount_amount = (string) ($order->discount_amount ?? 0);
        $this->tax_amount = (string) ($order->tax_amount ?? 0);

        $advance = $this->findReceptionAdvance($order);
        $this->advance_amount = (string) ($advance?->amount ?? 0);
        $this->other_payments_total = $this->sumOtherPayments($order, $advance?->id);

        $items = [];
        foreach ($order->items as $item) {
            $perKg = in_array($item->pricing_mode, [
                PressingBilling::ARTICLE_PER_KG,
                PressingBilling::MODE_WEIGHT_BY_TYPE,
            ], true) || ($order->billing_mode === PressingBilling::MODE_WEIGHT_BY_TYPE && $item->weight_kg);

            $items[] = [
                'article_type_id' => $item->article_type_id,
                'quantity' => max(1, (int) $item->quantity),
                'weight_kg' => $item->weight_kg !== null ? (string) $item->weight_kg : '',
                'price_per_kg' => (string) ($item->price_per_kg ?? 0),
                'unit_price' => (string) ($item->unit_price ?? 0),
                'pricing_mode' => $perKg
                    ? PressingBilling::ARTICLE_PER_KG
                    : PressingBilling::ARTICLE_FIXED,
            ];
        }

        $this->items = $items !== [] ? $items : [$this->blankItem()];
    }

    public function setBillingMode(string $mode): void
    {
        if (! array_key_exists($mode, PressingBilling::modes())) {
            return;
        }

        $this->billing_mode = $mode;

        if ($mode === PressingBilling::MODE_WEIGHT_GLOBAL) {
            $this->weight_unit_price = (string) PressingSettings::globalWeightPrice($this->agence_id);
        }

        $this->items = [$this->blankItem()];
    }

    public function selectClient(int $clientId, string $label = ''): void
    {
        $client = PressingClient::findOrFail($clientId);
        $this->client_id = $client->id;
        $this->client_search = $label !== '' ? $label : $client->full_name;
        $this->showQuickCreate = false;
        $this->clearReward();

        if (! $this->isEditing) {
            $this->receptionStep = 'order';
        }
    }

    public function clearClient(): void
    {
        $this->client_id = null;
        $this->client_search = '';
        $this->clearReward();
        if (! $this->isEditing) {
            $this->receptionStep = 'client';
            $this->showQuickCreate = false;
        }
    }

    public function backToClientSearch(): void
    {
        if ($this->isEditing) {
            return;
        }

        $this->client_id = null;
        $this->client_search = '';
        $this->clearReward();
        $this->showQuickCreate = false;
        $this->resetQuickCreate();
        $this->receptionStep = 'client';
    }

    public function openQuickCreate(): void
    {
        abort_unless($this->can('pressing_clients.create'), 403);

        $this->showQuickCreate = true;
        $this->resetQuickCreate();

        // Prefill from what the user typed in the search box
        $term = trim($this->client_search);
        if ($term === '') {
            return;
        }

        if (preg_match('/^\+?\d[\d\s\-]{5,}$/', $term)) {
            $this->qc_whatsapp = preg_replace('/\s+/', '', $term);
        } else {
            $parts = preg_split('/\s+/', $term, 2);
            $this->qc_first_name = $parts[0] ?? '';
            $this->qc_last_name = $parts[1] ?? '';
        }
    }

    public function cancelQuickCreate(): void
    {
        $this->showQuickCreate = false;
        $this->resetQuickCreate();
    }

    public function saveQuickClient(): void
    {
        $this->authorizePressingAction('pressing_clients.create');

        $data = $this->validate([
            'qc_first_name' => ['required', 'string', 'max:100'],
            'qc_last_name' => ['required', 'string', 'max:100'],
            'qc_whatsapp' => ['required', 'string', 'max:50'],
            'qc_phone' => ['nullable', 'string', 'max:50'],
            'qc_email' => ['nullable', 'email', 'max:150'],
            'qc_address' => ['nullable', 'string', 'max:255'],
            'agence_id' => ['required', 'integer', 'exists:tenant.agences,id'],
        ], [], [
            'qc_first_name' => __('prénom'),
            'qc_last_name' => __('nom'),
            'qc_whatsapp' => 'WhatsApp',
            'agence_id' => __('agence'),
        ]);

        $code = 'CL-'.str_pad((string) (PressingClient::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);

        $client = PressingClient::create([
            'code' => $code,
            'agence_id' => (int) $data['agence_id'],
            'first_name' => $data['qc_first_name'],
            'last_name' => $data['qc_last_name'],
            'whatsapp' => $data['qc_whatsapp'],
            'phone' => $data['qc_phone'] ?: $data['qc_whatsapp'],
            'email' => $data['qc_email'] ?: null,
            'address' => $data['qc_address'] ?: null,
            'is_active' => true,
        ]);

        $this->showQuickCreate = false;
        $this->resetQuickCreate();
        $this->selectClient($client->id, $client->full_name);

        session()->flash('success', __('Client créé — vous pouvez enregistrer la commande.'));
    }

    private function resetQuickCreate(): void
    {
        $this->qc_first_name = '';
        $this->qc_last_name = '';
        $this->qc_whatsapp = '';
        $this->qc_phone = '';
        $this->qc_email = '';
        $this->qc_address = '';
        $this->resetValidation([
            'qc_first_name', 'qc_last_name', 'qc_whatsapp', 'qc_phone', 'qc_email', 'qc_address',
        ]);
    }

    public function applyReward(int $rewardId): void
    {
        if (! $this->client_id) {
            return;
        }

        $reward = \Pressing\Models\PressingLoyaltyReward::where('client_id', $this->client_id)->find($rewardId);
        if (! $reward || ! $reward->isAvailable()) {
            session()->flash('error', __('Cette récompense n’est plus disponible.'));

            return;
        }

        $discount = $reward->discountFor($this->subtotal);
        if ($discount <= 0) {
            session()->flash('error', __('Ajoutez d’abord des articles pour appliquer la récompense.'));

            return;
        }

        $this->applied_reward_id = $reward->id;
        $this->applied_reward_label = $reward->code.' · '.$reward->label();
        $this->discount_amount = (string) $discount;
    }

    public function clearReward(): void
    {
        if ($this->applied_reward_id !== null) {
            $this->discount_amount = '0';
        }
        $this->applied_reward_id = null;
        $this->applied_reward_label = '';
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        if ($this->billing_mode !== PressingBilling::MODE_WEIGHT_GLOBAL && count($this->items) <= 1) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedAgenceId(): void
    {
        // Keep selected client — search is multi-agence; only refresh weight price.
        $this->weight_unit_price = (string) PressingSettings::globalWeightPrice($this->agence_id);
        $this->refreshItemPrices();
    }

    public function updatedItems($value, $key): void
    {
        if (! str_ends_with((string) $key, 'article_type_id')) {
            return;
        }

        [$index] = explode('.', (string) $key);
        $this->applyTypePricing((int) $index);
    }

    private function refreshItemPrices(): void
    {
        foreach (array_keys($this->items) as $index) {
            $this->applyTypePricing((int) $index);
        }
    }

    private function applyTypePricing(int $index): void
    {
        $typeId = (int) ($this->items[$index]['article_type_id'] ?? 0);
        if (! $typeId) {
            return;
        }

        $type = ArticleType::find($typeId);
        if (! $type) {
            return;
        }

        if (! PressingBilling::isTypeCompatibleWithOrderMode($type, $this->billing_mode, $this->agence_id)) {
            $this->items[$index]['article_type_id'] = null;
            session()->flash('error', 'Ce type n\'est pas compatible avec le mode « ' . PressingBilling::modeLabel($this->billing_mode) . ' ».');

            return;
        }

        $this->items[$index]['unit_price'] = (string) $type->priceForAgence($this->agence_id);
        $this->items[$index]['price_per_kg'] = (string) $type->pricePerKgForAgence($this->agence_id);

        if ($this->billing_mode === PressingBilling::MODE_MIXED) {
            $this->items[$index]['pricing_mode'] = PressingBilling::suggestLinePricingMode(
                $type,
                $this->agence_id,
                $this->items[$index]['pricing_mode'] ?? null
            );
        } elseif ($this->billing_mode === PressingBilling::MODE_WEIGHT_BY_TYPE) {
            $this->items[$index]['pricing_mode'] = PressingBilling::ARTICLE_PER_KG;
        } else {
            $this->items[$index]['pricing_mode'] = PressingBilling::ARTICLE_FIXED;
        }
    }

    public function setLinePricingMode(int $index, string $mode): void
    {
        if ($this->billing_mode !== PressingBilling::MODE_MIXED) {
            return;
        }

        if (! array_key_exists($mode, PressingBilling::articleModes())) {
            return;
        }

        $typeId = (int) ($this->items[$index]['article_type_id'] ?? 0);
        $type = $typeId ? ArticleType::find($typeId) : null;
        if (! $type) {
            $this->items[$index]['pricing_mode'] = $mode;

            return;
        }

        if ($mode === PressingBilling::ARTICLE_FIXED && ! PressingBilling::hasFixedPrice($type, $this->agence_id)) {
            session()->flash('error', 'Ce type n’a pas de prix fixe configuré.');

            return;
        }

        if ($mode === PressingBilling::ARTICLE_PER_KG && ! PressingBilling::hasPerKgPrice($type, $this->agence_id)) {
            session()->flash('error', 'Ce type n’a pas de prix/kg configuré.');

            return;
        }

        $this->items[$index]['pricing_mode'] = $mode;
        $this->items[$index]['unit_price'] = (string) $type->priceForAgence($this->agence_id);
        $this->items[$index]['price_per_kg'] = (string) $type->pricePerKgForAgence($this->agence_id);
    }

    public function getSubtotalProperty(): float
    {
        return PressingBilling::orderSubtotal(
            $this->billing_mode,
            $this->items,
            (float) $this->total_weight_kg,
            (float) $this->weight_unit_price
        );
    }

    public function getComputedTaxProperty(): float
    {
        if (! PressingSettings::taxEnabled()) {
            return (float) $this->tax_amount;
        }

        if ((float) $this->tax_amount > 0) {
            return (float) $this->tax_amount;
        }

        return round(max(0, $this->subtotal - (float) $this->discount_amount) * (PressingSettings::taxRate() / 100), 2);
    }

    public function getGrandTotalProperty(): float
    {
        return max(0, round($this->subtotal - (float) $this->discount_amount + $this->computedTax, 2));
    }

    public function getItemsCountProperty(): int
    {
        if ($this->billing_mode === PressingBilling::MODE_WEIGHT_GLOBAL) {
            return count(array_filter($this->items, fn ($i) => ! empty($i['article_type_id'])));
        }

        $count = 0;
        foreach ($this->items as $item) {
            if (PressingBilling::isLinePerKg($this->billing_mode, $item)) {
                $count += max(1, (int) ($item['quantity'] ?? 1));
            } else {
                $count += (int) ($item['quantity'] ?? 0);
            }
        }

        return $count;
    }

    public function getTotalWeightProperty(): float
    {
        if ($this->billing_mode === PressingBilling::MODE_WEIGHT_GLOBAL) {
            return (float) $this->total_weight_kg;
        }

        $total = 0.0;
        foreach ($this->items as $item) {
            if (PressingBilling::isLinePerKg($this->billing_mode, $item)) {
                $total += (float) ($item['weight_kg'] ?? 0);
            }
        }

        return $total;
    }

    public function save(): void
    {
        if ($this->isEditing) {
            $this->authorizePressingAction('pressing_orders.update');
            $this->updateOrder();

            return;
        }

        $this->authorizePressingAction('pressing_orders.create');

        if (! $this->client_id || $this->receptionStep !== 'order') {
            $this->receptionStep = 'client';
            session()->flash('error', __('Sélectionnez d’abord un client.'));

            return;
        }

        $this->createOrder();
    }

    private function validateReceptionForm(): void
    {
        $rules = [
            'agence_id' => ['required', 'integer', 'exists:tenant.agences,id'],
            'client_id' => ['required', 'integer', 'exists:tenant.pressing_clients,id'],
            'billing_mode' => ['required', 'in:' . implode(',', array_keys(PressingBilling::modes()))],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_amount' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($this->billing_mode === PressingBilling::MODE_WEIGHT_GLOBAL) {
            $rules['total_weight_kg'] = ['required', 'numeric', 'min:0.001'];
            $rules['weight_unit_price'] = ['required', 'numeric', 'min:0'];
        } elseif ($this->billing_mode === PressingBilling::MODE_WEIGHT_BY_TYPE) {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*.article_type_id'] = ['required', 'integer', 'exists:tenant.article_types,id'];
            $rules['items.*.weight_kg'] = ['required', 'numeric', 'min:0.001'];
            $rules['items.*.price_per_kg'] = ['required', 'numeric', 'min:0'];
        } elseif ($this->billing_mode === PressingBilling::MODE_MIXED) {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*.article_type_id'] = ['required', 'integer', 'exists:tenant.article_types,id'];
            $rules['items.*.pricing_mode'] = ['required', 'in:'.implode(',', array_keys(PressingBilling::articleModes()))];
        } else {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*.article_type_id'] = ['required', 'integer', 'exists:tenant.article_types,id'];
            $rules['items.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['items.*.unit_price'] = ['required', 'numeric', 'min:0'];
        }

        $this->validate($rules);

        if ($this->billing_mode === PressingBilling::MODE_MIXED) {
            $this->validateMixedItemLines();
        }
    }

    private function billingWeightPayload(): array
    {
        return [
            'billing_mode' => $this->billing_mode,
            'total_weight_kg' => $this->billing_mode === PressingBilling::MODE_WEIGHT_GLOBAL
                ? (float) $this->total_weight_kg
                : (in_array($this->billing_mode, [
                    PressingBilling::MODE_WEIGHT_BY_TYPE,
                    PressingBilling::MODE_MIXED,
                ], true) ? $this->totalWeight : null),
            'weight_unit_price' => $this->billing_mode === PressingBilling::MODE_WEIGHT_GLOBAL
                ? (float) $this->weight_unit_price
                : null,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => $this->computedTax,
            'notes' => $this->notes,
        ];
    }

    private function createOrder(): void
    {
        $this->validateReceptionForm();

        $order = DB::connection('tenant')->transaction(function () {
            $number = 'CMD-' . now()->format('Ymd') . '-' . str_pad((string) (PressingOrder::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);
            $triStage = PressingWorkflow::stageByName(PressingWorkflow::STAGE_TRI);
            $userId = Auth::guard('tenant')->id();

            $order = PressingOrder::create(array_merge([
                'number' => $number,
                'agence_id' => $this->agence_id,
                'client_id' => $this->client_id,
                'receptionist_id' => $userId,
                'assigned_user_id' => $userId,
                'received_at' => now(),
                'due_at' => now()->addHours(PressingSettings::defaultDelayHours()),
                'current_stage_id' => $triStage?->id,
                'status' => 'open',
                'sorting_status' => PressingSortingService::STATUS_IN_PROGRESS,
            ], $this->billingWeightPayload()));

            $this->persistItems($order);

            $order->recalculateTotals();

            $advance = (float) $this->advance_amount;
            if ($advance > 0) {
                $methods = array_keys(PressingSettings::paymentMethodsMap());
                PressingPayment::create([
                    'order_id' => $order->id,
                    'agence_id' => $order->agence_id,
                    'method' => $methods[0] ?? 'cash',
                    'amount' => min($advance, (float) $order->fresh()->total),
                    'reference' => 'Avance à la réception',
                    'received_by' => Auth::guard('tenant')->id(),
                    'paid_at' => now(),
                    'notes' => 'Avance',
                ]);
                $order->recalculateTotals();
            }

            if ($triStage) {
                OrderStageHistory::create([
                    'order_id' => $order->id,
                    'stage_id' => $triStage->id,
                    'stage_name' => $triStage->name,
                    'user_id' => $userId,
                    'moved_at' => now(),
                    'note' => 'Réception enregistrée — constitution à faire',
                ]);
            }

            return $order->fresh();
        });

        $this->consumeAppliedReward($order);

        app(\Pressing\Services\PressingLoyaltyService::class)->syncOrderPoints($order->fresh());

        app(PressingNotificationDispatcher::class)->dispatch('order_created', $order->fresh(['client', 'agence', 'receptionist']));

        session()->flash('success', 'Réception ' . $order->number . ' enregistrée et assignée à vous. Constituez le contenu du lot.');

        $this->redirect(route('tenant.pressing_orders.tri', [
            'tenant' => $this->tenantCode(),
            'pressingOrder' => $order->id,
        ]), navigate: true);
    }

    private function updateOrder(): void
    {
        $order = PressingOrder::findOrFail($this->editingOrderId);
        $this->assertOrderEditable($order);
        $this->validateReceptionForm();

        DB::connection('tenant')->transaction(function () use ($order) {
            $order->update(array_merge([
                'agence_id' => $this->agence_id,
                'client_id' => $this->client_id,
            ], $this->billingWeightPayload()));

            $order->items()->delete();
            $this->persistItems($order->fresh());
            $order->recalculateTotals();
            $this->syncReceptionAdvance($order->fresh());
            $order->recalculateTotals();
        });

        session()->flash('success', 'Réception ' . $order->number . ' mise à jour.');

        $this->redirect(route('tenant.pressing_orders.index', [
            'tenant' => $this->tenantCode(),
        ]), navigate: true);
    }

    private function findReceptionAdvance(PressingOrder $order): ?PressingPayment
    {
        return $order->payments()
            ->where(function ($q) {
                $q->where('notes', 'Avance')
                    ->orWhere('reference', 'Avance à la réception');
            })
            ->orderBy('id')
            ->first();
    }

    private function sumOtherPayments(PressingOrder $order, ?int $advanceId): float
    {
        return (float) $order->payments()
            ->when($advanceId, fn ($q) => $q->where('id', '!=', $advanceId))
            ->when(! $advanceId, function ($q) {
                $q->where(function ($inner) {
                    $inner->where(function ($n) {
                        $n->whereNull('notes')->orWhere('notes', '!=', 'Avance');
                    })->where(function ($r) {
                        $r->whereNull('reference')->orWhere('reference', '!=', 'Avance à la réception');
                    });
                });
            })
            ->sum('amount');
    }

    private function syncReceptionAdvance(PressingOrder $order): void
    {
        $desired = max(0, round((float) $this->advance_amount, 2));
        $other = $this->sumOtherPayments($order, $this->findReceptionAdvance($order)?->id);
        $maxAllowed = max(0, round((float) $order->total - $other, 2));
        $desired = min($desired, $maxAllowed);

        $payment = $this->findReceptionAdvance($order);

        if ($desired <= 0) {
            $payment?->delete();

            return;
        }

        if ($payment) {
            $payment->update([
                'amount' => $desired,
                'agence_id' => $order->agence_id,
            ]);

            return;
        }

        $methods = array_keys(PressingSettings::paymentMethodsMap());
        PressingPayment::create([
            'order_id' => $order->id,
            'agence_id' => $order->agence_id,
            'method' => $methods[0] ?? 'cash',
            'amount' => $desired,
            'reference' => 'Avance à la réception',
            'received_by' => Auth::guard('tenant')->id(),
            'paid_at' => now(),
            'notes' => 'Avance',
        ]);
    }

    private function persistItems(PressingOrder $order): void
    {
        if ($this->billing_mode === PressingBilling::MODE_WEIGHT_GLOBAL) {
            foreach ($this->items as $item) {
                if (empty($item['article_type_id'])) {
                    continue;
                }

                PressingOrderItem::create([
                    'order_id' => $order->id,
                    'article_type_id' => $item['article_type_id'],
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'weight_kg' => null,
                    'price_per_kg' => null,
                    'pricing_mode' => $this->billing_mode,
                    'unit_price' => 0,
                    'line_total' => 0,
                ]);
            }

            return;
        }

        foreach ($this->items as $item) {
            $perKg = PressingBilling::isLinePerKg($this->billing_mode, $item);
            $lineTotal = PressingBilling::lineTotal($this->billing_mode, $item);

            PressingOrderItem::create([
                'order_id' => $order->id,
                'article_type_id' => $item['article_type_id'],
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'weight_kg' => $perKg ? (float) $item['weight_kg'] : null,
                'price_per_kg' => $perKg ? (float) $item['price_per_kg'] : null,
                'pricing_mode' => PressingBilling::storedItemPricingMode($this->billing_mode, $item),
                'unit_price' => $perKg ? 0 : (float) ($item['unit_price'] ?? 0),
                'line_total' => $lineTotal,
            ]);
        }
    }

    private function validateMixedItemLines(): void
    {
        $messages = [];

        foreach ($this->items as $index => $item) {
            if (PressingBilling::isLinePerKg($this->billing_mode, $item)) {
                if ((float) ($item['weight_kg'] ?? 0) <= 0) {
                    $messages["items.{$index}.weight_kg"] = 'Indiquez le poids (kg).';
                }
                if ((float) ($item['price_per_kg'] ?? 0) < 0) {
                    $messages["items.{$index}.price_per_kg"] = 'Prix/kg invalide.';
                }
            } else {
                if ((int) ($item['quantity'] ?? 0) < 1) {
                    $messages["items.{$index}.quantity"] = 'Quantité minimale : 1.';
                }
                if ((float) ($item['unit_price'] ?? 0) < 0) {
                    $messages["items.{$index}.unit_price"] = 'Prix unitaire invalide.';
                }
            }
        }

        if ($messages !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($messages);
        }
    }

    private function blankItem(): array
    {
        return [
            'article_type_id' => null,
            'quantity' => 1,
            'weight_kg' => '',
            'price_per_kg' => '0',
            'unit_price' => '0',
            'pricing_mode' => PressingBilling::ARTICLE_FIXED,
        ];
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    private function consumeAppliedReward(PressingOrder $order): void
    {
        if (! $this->applied_reward_id) {
            return;
        }

        $reward = \Pressing\Models\PressingLoyaltyReward::where('client_id', $order->client_id)
            ->find($this->applied_reward_id);

        if ($reward && $reward->isAvailable()) {
            app(\Pressing\Services\PressingLoyaltyService::class)
                ->redeemReward($reward, $order, (float) $order->discount_amount);
        }

        $this->applied_reward_id = null;
        $this->applied_reward_label = '';
    }

    public function render()
    {
        $agences = $this->canPickAgence
            ? Agence::where('is_active', true)->orderBy('name')->get()
            : collect(PressingAgenceContext::userAgence())->filter();

        $clientsQuery = PressingClient::query()
            ->with('agence')
            ->where('is_active', true);

        // Multi-agence search: a client registered at Bonanjo can deposit at Akwa.
        if (trim($this->client_search) !== '') {
            $term = '%'.mb_strtolower(trim($this->client_search)).'%';
            $clientsQuery->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(whatsapp) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(phone,\'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) LIKE ?", [$term])
                    ->orWhereRaw("LOWER(CONCAT(COALESCE(last_name,''),' ',COALESCE(first_name,''))) LIKE ?", [$term]);
            });
        }

        $clients = trim($this->client_search) === ''
            ? collect()
            : $clientsQuery
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(20)
                ->get();

        $allTypes = ArticleType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $compatibleTypes = $allTypes->filter(
            fn (ArticleType $type) => PressingBilling::isTypeCompatibleWithOrderMode($type, $this->billing_mode, $this->agence_id)
        );

        $typeCounts = [
            'fixed' => $allTypes->filter(fn (ArticleType $t) => $t->priceForAgence($this->agence_id) > 0)->count(),
            'per_kg' => $allTypes->filter(fn (ArticleType $t) => $t->pricePerKgForAgence($this->agence_id) > 0)->count(),
            'mixed' => $allTypes->filter(
                fn (ArticleType $t) => PressingBilling::isTypeCompatibleWithOrderMode($t, PressingBilling::MODE_MIXED, $this->agence_id)
            )->count(),
        ];

        $loyaltyService = app(\Pressing\Services\PressingLoyaltyService::class);
        $selectedClient = $this->client_id ? PressingClient::with('agence')->find($this->client_id) : null;
        $availableRewards = ($loyaltyService->active() && $selectedClient && ! $this->isEditing)
            ? $loyaltyService->availableRewards($selectedClient)
            : collect();

        return view('pressing::livewire.orders.create', [
            'agences' => $agences,
            'clients' => $clients,
            'selectedClient' => $selectedClient,
            'availableRewards' => $availableRewards,
            'appliedRewardId' => $this->applied_reward_id,
            'appliedRewardLabel' => $this->applied_reward_label,
            'canCreateClient' => $this->can('pressing_clients.create'),
            'articleTypes' => $compatibleTypes,
            'allArticleTypes' => $allTypes,
            'typeCounts' => $typeCounts,
            'compatibleCount' => $compatibleTypes->count(),
            'lockedAgence' => $this->canPickAgence ? null : PressingAgenceContext::userAgence(),
            'taxEnabled' => PressingSettings::taxEnabled(),
            'taxRate' => PressingSettings::taxRate(),
            'defaultDelayHours' => PressingSettings::defaultDelayHours(),
            'billingModes' => PressingBilling::modes(),
            'isEditing' => $this->isEditing,
            'editingOrderNumber' => $this->editingOrderNumber,
            'orderAlreadySorted' => $this->orderAlreadySorted,
        ])->layout('layouts.app', [
            'title' => $this->isEditing ? __('Modifier réception') : __('Nouvelle réception'),
            'subtitle' => $this->isEditing
                ? ($this->editingOrderNumber.' — '.__('corriger client, articles ou tarifs'))
                : ($this->receptionStep === 'client'
                    ? __('Étape 1 — Identifier le client')
                    : __('Étape 2 — Enregistrer la commande')),
        ]);
    }
}
