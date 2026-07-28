<?php

namespace InovCom\Purchases\Http\Livewire;

use InovCom\Items\Models\Item;
use InovCom\Providers\Models\Provider;
use InovCom\Purchases\Concerns\AuthorizesForeignPurchases;
use InovCom\Purchases\Models\ForeignPurchaseOrder;
use InovCom\Purchases\Services\ForeignPurchasesService;
use InovCom\Purchases\Services\PurchasePriceHistoryService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ForeignPurchaseForm extends Component
{
    use AuthorizesForeignPurchases;

    public ?int $orderId = null;
    public bool $readOnly = false;

    public ?int $provider_id = null;
    public string $providerSearch = '';
    public array $providerResults = [];
    public ?array $providerPicker = null;
    public string $order_date = '';
    public ?string $expected_date = null;
    public string $currency_code = 'EUR';
    public string $exchange_rate = '655.957';
    public ?string $notes = null;

    public array $cart = [];
    public string $itemSearch = '';
    public array $searchResults = [];

    public function mount(?ForeignPurchaseOrder $foreignPurchase = null): void
    {
        if (!$foreignPurchase) {
            $this->order_date = now()->format('Y-m-d');
            return;
        }

        if (!app(ForeignPurchasesService::class)->canEditOrder($foreignPurchase)) {
            $this->readOnly = true;
        }

        $this->orderId = $foreignPurchase->id;
        $this->provider_id = $foreignPurchase->provider_id;
        $this->syncProviderPicker($foreignPurchase->provider_id ? Provider::find($foreignPurchase->provider_id) : null);
        $this->order_date = $foreignPurchase->order_date->format('Y-m-d');
        $this->expected_date = $foreignPurchase->expected_date?->format('Y-m-d');
        $this->currency_code = $foreignPurchase->currency_code;
        $this->exchange_rate = (string) $foreignPurchase->exchange_rate;
        $this->notes = $foreignPurchase->notes;

        $foreignPurchase->loadMissing('lines.item');

        foreach ($foreignPurchase->lines as $line) {
            $this->cart[] = [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_sku' => $line->item?->sku,
                'item_name' => $line->item_name,
                'quantity' => (string) $line->quantity,
                'unit_price_foreign' => (string) $line->unit_price_foreign,
                'line_total_foreign' => (string) $line->line_total_foreign,
                'unit_price_local' => (string) $line->unit_price_local,
                'line_total_local' => (string) $line->line_total_local,
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
        $currencyCode = $this->currency_code;

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

        $this->searchResults = $items->map(function (Item $item) use ($priceHistory, $providerId, $currencyCode) {
            $lastPurchaseCost = $priceHistory->latestForeignPurchaseCost((int) $item->id, $currencyCode, $providerId);
            $defaultCost = $lastPurchaseCost !== null
                ? fmt_num_plain($lastPurchaseCost, 4)
                : '0';

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'last_purchase_cost_foreign' => $lastPurchaseCost,
                'default_cost_foreign' => $defaultCost,
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
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        $this->providerResults = $providers->map(fn (Provider $p) => $this->providerToPickerArray($p))->all();
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

        if ($provider->is_foreign && $provider->default_currency) {
            $this->currency_code = $provider->default_currency;
            $this->exchange_rate = (string) ForeignPurchasesService::defaultExchangeRate($this->currency_code);
            $this->recalculateAllCartLines();
        }

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

    public function updatedCurrencyCode(): void
    {
        $this->exchange_rate = (string) ForeignPurchasesService::defaultExchangeRate($this->currency_code);
        $this->recalculateAllCartLines();
        $this->refreshItemSearchForProvider();
    }

    public function updatedExchangeRate(): void
    {
        $this->recalculateAllCartLines();
    }

    private function recalculateAllCartLines(): void
    {
        $rate = (float) $this->exchange_rate;
        foreach (array_keys($this->cart) as $index) {
            $this->recalculateLine($index, $rate);
        }
    }

    private function refreshItemSearchForProvider(): void
    {
        if ($this->itemSearch !== '') {
            $this->updatedItemSearch();
        }
    }

    public function addItemToCart(array $item): void
    {
        $unitPriceForeign = $item['default_cost_foreign'] ?? '0';

        foreach ($this->cart as $index => $cartItem) {
            if ($cartItem['item_id'] == $item['id']) {
                $this->cart[$index]['quantity'] = (string) ((float) $this->cart[$index]['quantity'] + 1);
                $this->recalculateLine($index);
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
            'unit_price_foreign' => (string) $unitPriceForeign,
            'line_total_foreign' => '0',
            'unit_price_local' => '0',
            'line_total_local' => '0',
        ];

        $this->recalculateLine(count($this->cart) - 1);
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
        $this->recalculateLine($index);
    }

    public function updateCartPriceForeign(int $index, string $price): void
    {
        $this->cart[$index]['unit_price_foreign'] = fmt_num_plain(max(0, (float) $price), 4);
        $this->recalculateLine($index);
    }

    private function recalculateLine(int $index, ?float $rate = null): void
    {
        if (!isset($this->cart[$index])) {
            return;
        }

        $rate = $rate ?? (float) $this->exchange_rate;
        $qty = (float) ($this->cart[$index]['quantity'] ?? 1);
        $foreign = (float) ($this->cart[$index]['unit_price_foreign'] ?? 0);
        $local = ForeignPurchasesService::convertToLocal($foreign, $rate);

        $this->cart[$index]['unit_price_local'] = (string) $local;
        $this->cart[$index]['line_total_foreign'] = (string) round($qty * $foreign, 2);
        $this->cart[$index]['line_total_local'] = (string) round($qty * $local, 2);
    }

    private function syncProviderPicker(?Provider $provider): void
    {
        $this->providerPicker = $provider ? $this->providerToPickerArray($provider) : null;
    }

    private function providerToPickerArray(Provider $provider): array
    {
        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'code' => $provider->code,
            'is_foreign' => (bool) $provider->is_foreign,
            'default_currency' => $provider->default_currency,
        ];
    }

    public function getSubtotalForeignProperty(): float
    {
        return array_sum(array_map(fn ($row) => (float) ($row['line_total_foreign'] ?? 0), $this->cart));
    }

    public function getSubtotalLocalProperty(): float
    {
        return array_sum(array_map(fn ($row) => (float) ($row['line_total_local'] ?? 0), $this->cart));
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

        if ($this->canForeignPurchase('foreign_purchases.confirm')) {
            try {
                app(ForeignPurchasesService::class)->confirmOrder($order->id);
                session()->flash('success', 'Achat étranger confirmé : ' . $order->order_number);
            } catch (\Throwable $e) {
                session()->flash('error', $e->getMessage());
            }
        } else {
            session()->flash('success', 'Commande enregistrée : ' . $order->order_number);
        }

        $this->redirect(route('tenant.foreign_purchases.show', [$order->id, 'tenant' => $this->tenantCode()]), navigate: true);
    }

    private function persistOrder(bool $skipRedirect): ?ForeignPurchaseOrder
    {
        if ($this->readOnly) {
            session()->flash('error', 'Cette commande ne peut plus être modifiée.');
            return null;
        }

        if (empty($this->cart)) {
            session()->flash('error', 'Le panier est vide.');
            return null;
        }

        $data = $this->validate([
            'provider_id' => ['nullable', \Illuminate\Validation\Rule::exists(Provider::class, 'id')],
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'currency_code' => ['required', Rule::in(Provider::currencyCodes())],
            'exchange_rate' => 'required|numeric|min:0.000001',
            'notes' => 'nullable|string',
        ]);

        $service = app(ForeignPurchasesService::class);
        $lines = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'item_name' => $row['item_name'],
            'quantity' => (float) $row['quantity'],
            'unit_price_foreign' => (float) $row['unit_price_foreign'],
        ], $this->cart);

        $payload = [
            'order_date' => $data['order_date'],
            'expected_date' => filled($data['expected_date'] ?? null) ? $data['expected_date'] : null,
            'provider_id' => $data['provider_id'] ?: null,
            'currency_code' => $data['currency_code'],
            'exchange_rate' => (float) $data['exchange_rate'],
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
        ];

        if ($this->orderId) {
            $order = ForeignPurchaseOrder::findOrFail($this->orderId);
            $order = $service->updateOrder($order, $payload, $lines);
        } else {
            $order = $service->createOrder(array_merge($payload, [
                'order_number' => $service->nextOrderNumber((int) date('Y', strtotime($data['order_date']))),
                'status' => ForeignPurchasesService::STATUS_DRAFT,
                'created_by' => auth('tenant')->id(),
            ]), $lines);
        }

        if (!$skipRedirect) {
            session()->flash('success', 'Brouillon enregistré : ' . $order->order_number);
            $this->redirect(route('tenant.foreign_purchases.edit', [$order->id, 'tenant' => $this->tenantCode()]), navigate: true);
        }

        return $order;
    }

    public function render()
    {
        return view('inovcom-purchases::livewire.foreign.form')
            ->layout('layouts.app', [
                'title' => $this->orderId ? 'Modifier achat étranger' : 'Nouvel achat étranger',
                'subtitle' => 'Achats en devises',
            ])
            ->with([
                'canConfirm' => $this->canForeignPurchase('foreign_purchases.confirm'),
                'currencies' => Provider::CURRENCIES,
                'readOnly' => $this->readOnly,
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
