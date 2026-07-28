<?php

namespace InovCom\Purchases\Http\Livewire;

use InovCom\Purchases\Concerns\AuthorizesPurchases;
use InovCom\Items\Models\Item;
use InovCom\Providers\Models\Provider;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Services\PurchasePriceHistoryService;
use InovCom\Purchases\Services\PurchaseDocumentNumberService;
use InovCom\Purchases\Services\PurchasesService;
use Livewire\Component;

class PurchaseForm extends Component
{
    use AuthorizesPurchases;

    public ?int $purchaseId = null;
    public bool $readOnly = false;

    public ?int $provider_id = null;
    public string $providerSearch = '';
    public array $providerResults = [];
    public ?array $providerPicker = null;
    public string $order_date = '';
    public ?string $expected_date = null;
    public ?string $notes = null;

    public array $cart = [];
    public string $itemSearch = '';
    public array $searchResults = [];

    public function mount(?PurchaseOrder $purchase = null): void
    {
        if (!$purchase) {
            $this->order_date = now()->format('Y-m-d');
            return;
        }

        if (!app(PurchasesService::class)->canEditOrder($purchase)) {
            $this->redirect(route('tenant.purchases.show', [
                $purchase->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);

            return;
        }

        $this->purchaseId = $purchase->id;
        $this->provider_id = $purchase->provider_id;
        $this->syncProviderPicker($purchase->provider_id ? Provider::find($purchase->provider_id) : null);
        $this->order_date = $purchase->order_date->format('Y-m-d');
        $this->expected_date = $purchase->expected_date?->format('Y-m-d');
        $this->notes = $purchase->notes;

        $purchase->loadMissing('lines.item');

        foreach ($purchase->lines as $line) {
            $this->cart[] = [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_sku' => $line->item?->sku,
                'item_name' => $line->item_name,
                'quantity' => (string) $line->quantity,
                'unit_price' => fmt_num_plain($line->unit_price, 0),
                'line_total' => (string) $line->line_total,
            ];
        }
    }

    public function updatedItemSearch(): void
    {
        if (strlen(trim($this->itemSearch)) < 1) {
            $this->searchResults = [];
            return;
        }

        $searchTerm = trim($this->itemSearch);
        $priceHistory = app(PurchasePriceHistoryService::class);
        $providerId = $this->provider_id ? (int) $this->provider_id : null;

        $items = Item::query()
            ->where('is_active', true)
            ->where(function ($query) use ($searchTerm) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        $this->searchResults = $items->map(function ($item) use ($priceHistory, $providerId) {
            $lastPurchaseCost = $priceHistory->latestPurchaseCost((int) $item->id, $providerId);
            $defaultCost = $priceHistory->defaultPurchaseCostForItem($item, $providerId);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'cost' => fmt_num_plain((float) ($item->cost ?? 0), 0),
                'last_purchase_cost' => $lastPurchaseCost,
                'default_cost' => fmt_num_plain($defaultCost, 0),
                'has_last_purchase_cost' => $lastPurchaseCost !== null,
            ];
        })->toArray();
    }

    public function updatedProviderSearch(): void
    {
        if ($this->providerPicker !== null) {
            return;
        }

        $term = trim($this->providerSearch);
        if (strlen($term) < 2) {
            $this->providerResults = [];
            return;
        }

        $termLower = mb_strtolower($term);
        $like = '%' . $termLower . '%';

        $providers = Provider::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(tax_id, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        $this->providerResults = $providers->map(fn (Provider $p) => $this->providerToPickerArray($p))->all();

        foreach ($this->providerResults as $row) {
            if (mb_strtolower((string) $row['code']) === $termLower) {
                $this->selectProvider((int) $row['id']);
                return;
            }
        }
    }

    public function selectProvider(int $id): void
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return;
        }

        $this->provider_id = $provider->id;
        $this->providerPicker = $this->providerToPickerArray($provider);
        $this->providerSearch = '';
        $this->providerResults = [];
        $this->resetValidation('provider_id');
        $this->refreshItemSearchForProvider();
    }

    public function clearProvider(): void
    {
        $this->provider_id = null;
        $this->providerPicker = null;
        $this->providerSearch = '';
        $this->providerResults = [];
        $this->refreshItemSearchForProvider();
    }

    private function syncProviderPicker(?Provider $provider): void
    {
        if (!$provider) {
            $this->providerPicker = null;
            return;
        }

        $this->providerPicker = $this->providerToPickerArray($provider);
    }

    private function providerToPickerArray(Provider $provider): array
    {
        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'code' => $provider->code,
            'phone' => $provider->phone,
            'email' => $provider->email,
            'city' => $provider->city,
            'payment_method_label' => Provider::paymentMethodLabel($provider->payment_method),
        ];
    }

    private function refreshItemSearchForProvider(): void
    {
        if ($this->itemSearch !== '') {
            $this->updatedItemSearch();
        }
    }

    public function addItemToCart(array $item): void
    {
        $unitCost = $item['default_cost'] ?? $item['cost'] ?? '0';

        foreach ($this->cart as $index => $cartItem) {
            if ($cartItem['item_id'] == $item['id']) {
                $this->cart[$index]['quantity'] = (string) ((float) $this->cart[$index]['quantity'] + 1);
                $this->cart[$index]['line_total'] = (string) ((float) $this->cart[$index]['quantity'] * (float) $this->cart[$index]['unit_price']);
                $this->itemSearch = '';
                $this->searchResults = [];
                return;
            }
        }

        $this->cart[] = [
            'id' => null,
            'item_id' => $item['id'],
            'item_sku' => $item['sku'] ?? null,
            'item_name' => $item['name'],
            'quantity' => '1',
            'unit_price' => $unitCost,
            'line_total' => $unitCost,
        ];

        $this->itemSearch = '';
        $this->searchResults = [];
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function updateCartQuantity(int $index, string $quantity): void
    {
        if ((float) $quantity <= 0) {
            $this->removeFromCart($index);
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['line_total'] = (string) ((float) $quantity * (float) $this->cart[$index]['unit_price']);
    }

    public function updateCartPrice(int $index, string $price): void
    {
        $normalized = fmt_num_plain(max(0, (float) $price), 0);
        $this->cart[$index]['unit_price'] = $normalized;
        $this->cart[$index]['line_total'] = (string) ((float) $this->cart[$index]['quantity'] * (float) $normalized);
    }

    public function getSubtotalProperty(): float
    {
        return array_sum(array_map(fn ($row) => (float) ($row['line_total'] ?? 0), $this->cart));
    }

    public function getTotalProperty(): float
    {
        return $this->subtotal;
    }

    public function saveDraft(): void
    {
        $this->persistOrder(false);
    }

    public function saveAndConfirm(): void
    {
        $order = $this->persistOrder(true);
        if (!$order) {
            return;
        }

        if ($this->canPurchase('purchases.confirm')) {
            try {
                app(PurchasesService::class)->confirmOrder($order->id);
                session()->flash('success', 'Achat confirmé : ' . $order->order_number);
            } catch (\Throwable $e) {
                session()->flash('error', $e->getMessage());
            }
        } else {
            session()->flash('success', 'Commande enregistrée : ' . $order->order_number);
        }

        $this->redirect(route('tenant.purchases.show', [$order->id, 'tenant' => $this->tenantCode()]), navigate: true);
    }

    private function persistOrder(bool $skipRedirect): ?PurchaseOrder
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Le panier est vide.');
            return null;
        }

        $this->provider_id = $this->provider_id ? (int) $this->provider_id : null;

        $data = $this->validate([
            'provider_id' => ['nullable', \Illuminate\Validation\Rule::exists(Provider::class, 'id')],
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
        ]);

        $purchasesService = app(PurchasesService::class);

        if ($this->purchaseId) {
            $order = PurchaseOrder::findOrFail($this->purchaseId);
            if (!$purchasesService->canEditOrder($order)) {
                session()->flash('error', 'Cette commande ne peut plus être modifiée.');
                return null;
            }
            $order->fill($data);
            $order->save();
            $order->lines()->delete();
        } else {
            $order = $purchasesService->createPurchaseOrder([
                'order_number' => app(PurchaseDocumentNumberService::class)->nextOrderNumber(
                    (int) date('Y', strtotime($data['order_date']))
                ),
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'],
                'provider_id' => $data['provider_id'] ?: null,
                'status' => PurchasesService::STATUS_DRAFT,
                'notes' => $data['notes'],
                'created_by' => auth('tenant')->id(),
            ]);
        }

        foreach ($this->cart as $cartItem) {
            $item = Item::find($cartItem['item_id']);
            $purchasesService->addLineToOrder($order->id, [
                'item_id' => $cartItem['item_id'],
                'item_name' => $item->name,
                'quantity' => (float) $cartItem['quantity'],
                'unit_price' => (float) $cartItem['unit_price'],
                'line_total' => (float) $cartItem['line_total'],
            ]);
        }

        if (!$skipRedirect) {
            session()->flash('success', 'Brouillon enregistré : ' . $order->order_number);
            $this->redirect(route('tenant.purchases.show', [$order->id, 'tenant' => $this->tenantCode()]), navigate: true);
        }

        return $order;
    }

    public function render()
    {
        return view('inovcom-purchases::livewire.purchases.form')
            ->layout('layouts.app', [
                'title' => $this->purchaseId ? 'Modifier commande' : 'Nouvelle commande',
                'subtitle' => 'Commandes d\'achat',
            ])
            ->with([
                'canConfirm' => $this->canPurchase('purchases.confirm'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
