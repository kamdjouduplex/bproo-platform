<?php

namespace Pressing\Http\Livewire\Deliveries;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InovCom\Users\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\PressingDelivery;
use Pressing\Models\PressingOrder;
use Pressing\Models\PressingPayment;
use Pressing\Services\PressingConsumablesService;
use Pressing\Services\PressingLoyaltyService;
use Pressing\Services\PressingNotificationDispatcher;
use Pressing\Services\PressingSettlementService;
use Pressing\Support\PressingAgenceContext;
use Pressing\Support\PressingSettings;
use Pressing\Support\PressingWorkflow;

class DeliveriesIndex extends Component
{
    use AuthorizesPressingActions;
    use WithPagination;

    public string $search = '';

    /** Effectuées par défaut — bascule pro en attente / effectuées */
    public string $listScope = 'waiting'; // waiting | done

    public string $typeTab = 'all'; // all | agence | domicile

    public function updatedListScope(): void
    {
        $this->resetPage();
        $this->closeHandoverPanel();
        $this->closeCreditPanel();
    }

    public function setListScope(string $scope): void
    {
        $this->listScope = in_array($scope, ['waiting', 'done'], true) ? $scope : 'waiting';
        $this->updatedListScope();
    }

    public bool $showForm = false;

    public ?int $order_id = null;

    public string $type = 'agence';

    public ?int $driver_user_id = null;

    public ?string $vehicle = null;

    public ?string $address = null;

    public ?string $notes = null;

    public ?int $creditDeliveryId = null;

    public string $credit_notes = '';

    public string $credit_reject_reason = '';

    public string $payment_method = 'cash';

    public string $payment_amount = '';

    public ?string $payment_reference = null;

    /** @var list<array{amount:float,method:string,reference:?string,paid_at:?string}> */
    public array $existingPayments = [];

    /** Remise : panel cintres / emballages / étiquettes */
    public ?int $handoverDeliveryId = null;

    /** @var array<int, array{item_id:int, name:string, unit:string, stock:float, quantity:string}> */
    public array $handoverLines = [];

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_deliveries.view');
    }

    public function create(): void
    {
        $this->authorizePressingAction('pressing_deliveries.create');
        $this->resetForm();
        $this->listScope = 'waiting';
        $this->showForm = true;
    }

    public function updatedTypeTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_deliveries.create');

        $data = $this->validate([
            'order_id' => ['required', 'integer', 'exists:tenant.pressing_orders,id'],
            'type' => ['required', 'in:agence,domicile'],
            'driver_user_id' => ['nullable', 'integer'],
            'vehicle' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['type'] === 'domicile' && trim((string) ($data['address'] ?? '')) === '') {
            $this->addError('address', __('Adresse requise pour une livraison à domicile.'));

            return;
        }

        $order = PressingOrder::with('client')->findOrFail($data['order_id']);
        if ($order->status !== 'ready') {
            session()->flash('error', __('Seules les commandes emballées (prêtes) peuvent entrer en livraison.'));

            return;
        }

        $exists = PressingDelivery::query()
            ->where('order_id', $order->id)
            ->whereIn('status', ['pending', 'in_transit'])
            ->exists();

        if ($exists) {
            session()->flash('error', __('Une livraison est déjà en cours pour cette commande.'));

            return;
        }

        PressingDelivery::create([
            'order_id' => $order->id,
            'agence_id' => $order->agence_id,
            'type' => $data['type'],
            'status' => 'pending',
            'driver_user_id' => $data['type'] === 'agence'
                ? (Auth::guard('tenant')->id())
                : $data['driver_user_id'],
            'vehicle' => $data['vehicle'],
            'address' => $data['type'] === 'domicile' ? ($data['address'] ?: $order->client?->address) : null,
            'scheduled_at' => now(),
            'notes' => $data['notes'],
            'created_by' => Auth::guard('tenant')->id(),
        ]);

        session()->flash('success', __('Livraison planifiée.'));
        $this->showForm = false;
        $this->resetForm();
        $this->listScope = 'waiting';
        $this->typeTab = $data['type'];
        $this->resetPage();
    }

    public function markInTransit(int $id): void
    {
        $this->authorizePressingAction('pressing_deliveries.update');
        $delivery = PressingDelivery::findOrFail($id);
        $delivery->update(['status' => 'in_transit']);
        session()->flash('success', __('Livraison en cours d’acheminement.'));
    }

    /** Ouvre le panel cintres / emballages / étiquettes avant confirmation. */
    public function markDelivered(int $id): void
    {
        $this->authorizePressingAction('pressing_deliveries.update');

        $delivery = PressingDelivery::with('order')->findOrFail($id);
        $block = app(PressingSettlementService::class)->deliveryBlockReason($delivery->order->fresh());
        if ($block) {
            session()->flash('error', $block);
            $this->creditDeliveryId = $id;

            return;
        }

        $this->openHandoverPanel($id);
    }

    public function openHandoverPanel(int $deliveryId): void
    {
        $this->authorizePressingAction('pressing_deliveries.update');
        $this->creditDeliveryId = null;
        $this->handoverDeliveryId = $deliveryId;

        $service = app(PressingConsumablesService::class);
        $service->seedCatalog();

        $this->handoverLines = collect($service->dashboardRows(PressingConsumablesService::USAGE_LIVRAISON))
            ->map(fn (array $row) => [
                'item_id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'unit' => (string) $row['unit'],
                'stock' => (float) $row['quantity'],
                'quantity' => '0',
            ])
            ->values()
            ->all();
    }

    public function closeHandoverPanel(): void
    {
        $this->handoverDeliveryId = null;
        $this->handoverLines = [];
    }

    public function confirmHandover(): void
    {
        $this->authorizePressingAction('pressing_deliveries.update');

        if (! $this->handoverDeliveryId) {
            return;
        }

        $delivery = PressingDelivery::with('order')->findOrFail($this->handoverDeliveryId);
        $settlement = app(PressingSettlementService::class);
        $order = $delivery->order->fresh();

        $block = $settlement->deliveryBlockReason($order);
        if ($block) {
            session()->flash('error', $block);
            $this->closeHandoverPanel();
            $this->creditDeliveryId = $delivery->id;

            return;
        }

        $lines = collect($this->handoverLines)
            ->map(fn (array $line) => [
                'item_id' => (int) ($line['item_id'] ?? 0),
                'quantity' => (float) ($line['quantity'] ?? 0),
            ])
            ->filter(fn (array $line) => $line['item_id'] > 0 && $line['quantity'] > 0)
            ->values()
            ->all();

        try {
            DB::connection('tenant')->transaction(function () use ($delivery, $lines) {
                if ($lines !== []) {
                    app(PressingConsumablesService::class)->issueForDelivery($delivery, $lines);
                }

                $delivery->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);

                $livre = PressingWorkflow::stageByName(PressingWorkflow::STAGE_LIVRE);

                $delivery->order->update([
                    'status' => 'delivered',
                    'current_stage_id' => $livre?->id ?? $delivery->order->current_stage_id,
                ]);

                app(PressingNotificationDispatcher::class)->dispatch(
                    'order_delivered',
                    $delivery->order->fresh(['client', 'agence'])
                );
            });
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            session()->flash('error', $e->getMessage() ?: __('Impossible de débiter le stock.'));

            return;
        }

        $msg = __('Remise / livraison confirmée.');
        if ($lines !== []) {
            $msg .= ' '.__('Consommables débités du stock.');
        }

        $this->closeHandoverPanel();
        $this->creditDeliveryId = null;
        $this->listScope = 'done';
        $this->resetPage();
        session()->flash('success', $msg);
    }

    public function openCreditPanel(int $deliveryId): void
    {
        $this->closeHandoverPanel();
        $this->creditDeliveryId = $deliveryId;
        $this->credit_notes = '';
        $this->credit_reject_reason = '';
        $this->loadPaymentForm($deliveryId);
    }

    public function closeCreditPanel(): void
    {
        $this->creditDeliveryId = null;
        $this->credit_notes = '';
        $this->credit_reject_reason = '';
        $this->resetPaymentForm();
    }

    public function savePayment(): void
    {
        $this->authorizePressingAction('pressing_orders.pay');

        if (! $this->creditDeliveryId) {
            return;
        }

        $delivery = PressingDelivery::with('order')->findOrFail($this->creditDeliveryId);
        $order = $delivery->order;

        $allowed = array_keys(PressingSettings::paymentMethodsMap());
        $data = $this->validate([
            'payment_method' => ['required', 'in:'.implode(',', $allowed ?: ['cash'])],
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $amount = (float) $data['payment_amount'];
        $balance = (float) $order->balance;
        if ($amount > $balance + 0.009) {
            $this->addError('payment_amount', __('Le montant ne peut pas dépasser le solde (:balance).', [
                'balance' => number_format($balance, 0, ',', ' '),
            ]));

            return;
        }

        PressingPayment::create([
            'order_id' => $order->id,
            'agence_id' => $order->agence_id,
            'method' => $data['payment_method'],
            'amount' => $amount,
            'reference' => $data['payment_reference'],
            'received_by' => Auth::guard('tenant')->id(),
            'paid_at' => now(),
        ]);

        $order->recalculateTotals();
        $order = $order->fresh(['client', 'agence']);
        app(PressingLoyaltyService::class)->syncOrderPoints($order);
        app(PressingNotificationDispatcher::class)->dispatch(
            'payment_received',
            $order,
            ['amount' => $amount]
        );

        $this->loadPaymentForm($this->creditDeliveryId);

        if ((float) $order->balance <= 0.009) {
            session()->flash('success', __('Paiement enregistré. Commande soldée — vous pouvez remettre au client.'));
        } else {
            session()->flash('success', __('Paiement de :amount FCFA enregistré.', [
                'amount' => number_format($amount, 0, ',', ' '),
            ]));
        }
    }

    private function loadPaymentForm(int $deliveryId): void
    {
        $delivery = PressingDelivery::with([
            'order.payments' => fn ($q) => $q->latest('paid_at'),
        ])->find($deliveryId);

        $order = $delivery?->order;
        if (! $order) {
            $this->resetPaymentForm();

            return;
        }

        $methods = array_keys(PressingSettings::paymentMethodsMap());
        $this->payment_method = $methods[0] ?? 'cash';
        $this->payment_amount = (string) max(0, (float) $order->balance);
        $this->payment_reference = null;
        $this->existingPayments = $order->payments->map(fn (PressingPayment $p) => [
            'amount' => (float) $p->amount,
            'method' => PressingSettings::paymentMethodsMap(false)[$p->method] ?? $p->method,
            'reference' => $p->reference,
            'paid_at' => optional($p->paid_at)->format('d/m/Y H:i'),
        ])->all();
    }

    private function resetPaymentForm(): void
    {
        $this->payment_method = 'cash';
        $this->payment_amount = '';
        $this->payment_reference = null;
        $this->existingPayments = [];
        $this->resetErrorBag(['payment_method', 'payment_amount', 'payment_reference']);
    }

    public function requestCredit(int $deliveryId): void
    {
        abort_unless($this->canRequestCredit(), 403, __('Action non autorisée.'));

        $delivery = PressingDelivery::with('order')->findOrFail($deliveryId);

        try {
            app(PressingSettlementService::class)->requestCredit(
                $delivery->order,
                $this->credit_notes ?: null
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', __('Demande de crédit envoyée — en attente de validation.'));
        $this->credit_notes = '';
    }

    public function approveCredit(int $deliveryId): void
    {
        abort_unless($this->canValidateCredit(), 403, __('Action non autorisée.'));

        $delivery = PressingDelivery::with('order')->findOrFail($deliveryId);

        try {
            app(PressingSettlementService::class)->approveCredit($delivery->order);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', __('Crédit validé. Vous pouvez maintenant marquer la commande comme livrée.'));
    }

    public function rejectCredit(int $deliveryId): void
    {
        abort_unless($this->canValidateCredit(), 403, __('Action non autorisée.'));

        $delivery = PressingDelivery::with('order')->findOrFail($deliveryId);

        try {
            app(PressingSettlementService::class)->rejectCredit(
                $delivery->order,
                $this->credit_reject_reason ?: null
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', __('Crédit refusé. Le client doit régler le solde.'));
        $this->credit_reject_reason = '';
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function canRequestCredit(): bool
    {
        return $this->can('pressing_orders.request_credit')
            || $this->can('pressing_orders.create')
            || $this->can('pressing_deliveries.update');
    }

    private function canValidateCredit(): bool
    {
        return $this->can('pressing_orders.validate_credit')
            || $this->can('debts.validate');
    }

    private function resetForm(): void
    {
        $this->order_id = null;
        $this->type = 'agence';
        $this->driver_user_id = null;
        $this->vehicle = null;
        $this->address = null;
        $this->notes = null;
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_deliveries.view');

        $like = DB::connection('tenant')->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $settlement = app(PressingSettlementService::class);

        $agenceScope = function ($q) {
            if (! PressingAgenceContext::canViewAllAgences()) {
                $agenceId = PressingAgenceContext::userAgenceId();
                $q->where('agence_id', $agenceId ?: 0);
            }
        };

        $counts = [
            'waiting' => PressingDelivery::query()->tap($agenceScope)->whereIn('status', ['pending', 'in_transit'])->count(),
            'done' => PressingDelivery::query()->tap($agenceScope)->where('status', 'delivered')->count(),
            'agence_waiting' => PressingDelivery::query()->tap($agenceScope)->where('type', 'agence')->whereIn('status', ['pending', 'in_transit'])->count(),
            'domicile_waiting' => PressingDelivery::query()->tap($agenceScope)->where('type', 'domicile')->whereIn('status', ['pending', 'in_transit'])->count(),
        ];

        $deliveriesQuery = PressingDelivery::query()
            ->with(['order.client', 'agence', 'driver'])
            ->tap($agenceScope)
            ->when($this->listScope === 'waiting', fn ($q) => $q->whereIn('status', ['pending', 'in_transit']))
            ->when($this->listScope === 'done', fn ($q) => $q->where('status', 'delivered'))
            ->when($this->typeTab !== 'all', fn ($q) => $q->where('type', $this->typeTab))
            ->when($this->search !== '', function ($q) use ($like) {
                $term = '%'.$this->search.'%';
                $q->whereHas('order', function ($oq) use ($like, $term) {
                    $oq->where('number', $like, $term)
                        ->orWhereHas('client', fn ($cq) => $cq->where('last_name', $like, $term)->orWhere('first_name', $like, $term));
                });
            });

        $deliveries = $this->listScope === 'done'
            ? $deliveriesQuery->latest('delivered_at')->paginate(12)
            : $deliveriesQuery->latest()->paginate(12);

        $readyOrders = PressingOrder::query()
            ->with('client')
            ->where('status', 'ready')
            ->whereDoesntHave('deliveries', fn ($q) => $q->whereIn('status', ['pending', 'in_transit']))
            ->when(! PressingAgenceContext::canViewAllAgences(), function ($q) {
                $agenceId = PressingAgenceContext::userAgenceId();
                $q->where('agence_id', $agenceId ?: 0);
            })
            ->latest('received_at')
            ->limit(50)
            ->get();

        $creditFocus = null;
        if ($this->creditDeliveryId) {
            $creditFocus = PressingDelivery::with('order.client')->find($this->creditDeliveryId);
        }

        $handoverFocus = null;
        if ($this->handoverDeliveryId) {
            $handoverFocus = PressingDelivery::with('order.client')->find($this->handoverDeliveryId);
        }

        return view('pressing::livewire.deliveries.index', [
            'deliveries' => $deliveries,
            'readyOrders' => $readyOrders,
            'types' => PressingDelivery::TYPES,
            'statuses' => PressingDelivery::STATUSES,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'counts' => $counts,
            'settlement' => $settlement,
            'creditFocus' => $creditFocus,
            'handoverFocus' => $handoverFocus,
            'canCreate' => $this->can('pressing_deliveries.create'),
            'canUpdate' => $this->can('pressing_deliveries.update'),
            'canPay' => $this->can('pressing_orders.pay'),
            'canRequestCredit' => $this->canRequestCredit(),
            'canValidateCredit' => $this->canValidateCredit(),
            'paymentMethods' => PressingSettings::paymentMethodsMap(),
            'tenantCode' => request()->query('tenant') ?? session('tenant_code'),
        ])->layout('layouts.app', [
            'title' => 'Livraisons',
            'subtitle' => 'Remises effectuées et en attente',
        ]);
    }
}
