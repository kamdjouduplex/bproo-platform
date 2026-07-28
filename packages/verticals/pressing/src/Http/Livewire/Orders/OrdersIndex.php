<?php

namespace Pressing\Http\Livewire\Orders;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\Agence;
use Pressing\Models\PressingOrder;
use Pressing\Models\PressingPayment;
use Pressing\Support\PressingAgenceContext;
use Pressing\Support\PressingSettings;

class OrdersIndex extends Component
{
    use AuthorizesPressingActions;
    use WithPagination;

    public string $search = '';

    public ?int $agenceFilter = null;

    public string $statusFilter = 'all';

    public int $perPage = 15;

    public bool $showPayment = false;

    public ?int $paymentOrderId = null;

    public string $payment_method = 'cash';

    public string $payment_amount = '';

    public ?string $payment_reference = null;

    public array $existingPayments = [];

    public string $paymentOrderNumber = '';

    public string $paymentOrderTotal = '0';

    public string $paymentOrderPaid = '0';

    public string $paymentOrderBalance = '0';

    public bool $canViewAllAgences = false;

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_orders.view');
        $this->canViewAllAgences = PressingAgenceContext::canViewAllAgences();

        if (! $this->canViewAllAgences) {
            $this->agenceFilter = PressingAgenceContext::userAgenceId();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAgenceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedAgenceFilter($value): void
    {
        if (! $this->canViewAllAgences) {
            $this->agenceFilter = PressingAgenceContext::userAgenceId();
        } else {
            $this->agenceFilter = $value === '' || $value === null ? null : (int) $value;
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->agenceFilter = $this->canViewAllAgences ? null : PressingAgenceContext::userAgenceId();
        $this->resetPage();
    }

    public function openPayment(int $orderId): void
    {
        $this->authorizePressingAction('pressing_orders.pay');
        $order = PressingOrder::with(['payments' => fn ($q) => $q->latest('paid_at')])->findOrFail($orderId);
        $this->paymentOrderId = $order->id;
        $this->paymentOrderNumber = $order->number;
        $this->paymentOrderTotal = (string) $order->total;
        $this->paymentOrderPaid = (string) $order->amount_paid;
        $this->paymentOrderBalance = (string) $order->balance;
        $this->payment_amount = (string) $order->balance;
        $methods = array_keys(PressingSettings::paymentMethodsMap());
        $this->payment_method = $methods[0] ?? 'cash';
        $this->payment_reference = null;
        $this->existingPayments = $order->payments->map(fn (PressingPayment $p) => [
            'amount' => (float) $p->amount,
            'method' => PressingSettings::paymentMethodsMap(false)[$p->method] ?? $p->method,
            'reference' => $p->reference,
            'paid_at' => optional($p->paid_at)->format('d/m/Y H:i'),
            'notes' => $p->notes,
        ])->all();
        $this->showPayment = true;
    }

    public function savePayment(): void
    {
        $this->authorizePressingAction('pressing_orders.pay');
        $order = PressingOrder::findOrFail($this->paymentOrderId);

        $allowed = array_keys(PressingSettings::paymentMethodsMap());
        $data = $this->validate([
            'payment_method' => ['required', 'in:' . implode(',', $allowed ?: ['cash'])],
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        PressingPayment::create([
            'order_id' => $order->id,
            'agence_id' => $order->agence_id,
            'method' => $data['payment_method'],
            'amount' => (float) $data['payment_amount'],
            'reference' => $data['payment_reference'],
            'received_by' => Auth::guard('tenant')->id(),
            'paid_at' => now(),
        ]);

        $order->recalculateTotals();
        app(\Pressing\Services\PressingLoyaltyService::class)->syncOrderPoints($order->fresh());
        app(\Pressing\Services\PressingNotificationDispatcher::class)->dispatch(
            'payment_received',
            $order->fresh(['client', 'agence']),
            ['amount' => (float) $data['payment_amount']]
        );
        session()->flash('success', 'Paiement enregistré.');
        $this->closePayment();
    }

    public function closePayment(): void
    {
        $this->showPayment = false;
        $this->paymentOrderId = null;
        $this->existingPayments = [];
    }

    private function baseQuery()
    {
        $like = DB::connection('tenant')->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return PressingOrder::query()
            ->when($this->canViewAllAgences, fn ($q) => $q->when($this->agenceFilter, fn ($inner) => $inner->where('agence_id', $this->agenceFilter)))
            ->when(! $this->canViewAllAgences && $this->agenceFilter, fn ($q) => $q->where('agence_id', $this->agenceFilter))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q) use ($like) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function ($inner) use ($like, $term) {
                    $inner->where('number', $like, $term)
                        ->orWhereHas('client', function ($cq) use ($like, $term) {
                            $cq->where('last_name', $like, $term)
                                ->orWhere('first_name', $like, $term)
                                ->orWhere('whatsapp', $like, $term)
                                ->orWhere('code', $like, $term);
                        });
                });
            });
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_orders.view');

        $orders = $this->baseQuery()
            ->with(['client', 'agence', 'currentStage'])
            ->withCount('items')
            ->latest('received_at')
            ->paginate($this->perPage);

        $statusCounts = PressingOrder::query()
            ->when($this->canViewAllAgences && $this->agenceFilter, fn ($q) => $q->where('agence_id', $this->agenceFilter))
            ->when(! $this->canViewAllAgences && $this->agenceFilter, fn ($q) => $q->where('agence_id', $this->agenceFilter))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $todayStats = (clone $this->baseQuery())
            ->whereDate('received_at', today())
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total),0) as revenue, COALESCE(SUM(amount_paid),0) as collected')
            ->first();

        $filteredTotal = (clone $this->baseQuery())->sum('total');
        $filteredBalance = (clone $this->baseQuery())->sum('balance');

        return view('pressing::livewire.orders.index', [
            'orders' => $orders,
            'agences' => $this->canViewAllAgences
                ? Agence::where('is_active', true)->orderBy('name')->get()
                : collect(),
            'lockedAgence' => $this->canViewAllAgences ? null : PressingAgenceContext::userAgence(),
            'paymentMethods' => PressingSettings::paymentMethodsMap(),
            'canCreate' => $this->can('pressing_orders.create'),
            'canUpdate' => $this->can('pressing_orders.update'),
            'canPay' => $this->can('pressing_orders.pay'),
            'canSort' => $this->can('pressing_orders.sort') || $this->can('pressing_orders.create'),
            'statusCounts' => $statusCounts,
            'todayOrders' => (int) ($todayStats->orders_count ?? 0),
            'todayRevenue' => (float) ($todayStats->revenue ?? 0),
            'todayCollected' => (float) ($todayStats->collected ?? 0),
            'filteredTotal' => (float) $filteredTotal,
            'filteredBalance' => (float) $filteredBalance,
        ])->layout('layouts.app', [
            'title' => 'Commandes',
            'subtitle' => 'Suivi des réceptions et paiements',
        ]);
    }
}
