<?php

namespace InovCom\Purchases\Http\Livewire;

use InovCom\Purchases\Concerns\AuthorizesPurchases;
use InovCom\Items\Models\Item;
use InovCom\Providers\Models\Provider;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Services\PurchasePriceHistoryService;
use InovCom\Purchases\Services\PurchaseDocumentNumberService;
use InovCom\Purchases\Services\PurchasesService;
use InovCom\Purchases\Support\PurchaseVatCalculator;
use InovCom\Quotations\Services\QuotationsService;
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

    public bool $has_vat = false;
    public string $price_mode = PurchaseVatCalculator::MODE_HT;
    public string $vat_rate = '0';
    public bool $vat_deductible = true;

    public function mount(?PurchaseOrder $purchase = null): void
    {
        if (!$purchase) {
            $this->order_date = now()->format('Y-m-d');
            $this->vat_rate = (string) QuotationsService::tenantTaxRate();
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
        $this->has_vat = (bool) ($purchase->has_vat ?? false);
        $this->price_mode = in_array($purchase->price_mode ?? 'ht', ['ht', 'ttc'], true)
            ? (string) $purchase->price_mode
            : PurchaseVatCalculator::MODE_HT;
        $this->vat_rate = (string) ($purchase->vat_rate ?? QuotationsService::tenantTaxRate());
        $this->vat_deductible = (bool) ($purchase->vat_deductible ?? true);

        $purchase->loadMissing('lines.item');

        foreach ($purchase->lines as $line) {
            $this->cart[] = [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_sku' => $line->item?->sku,
                'item_name' => $line->item_name,
                'quantity' => (string) $line->quantity,
                'unit_price' => fmt_num_plain($line->entered_unit_price ?? $line->unit_price, 0),
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
                $this->recalculateCartLine($index);
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
        $this->recalculateCartLine(count($this->cart) - 1);

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
        $this->recalculateCartLine($index);
    }

    public function updateCartPrice(int $index, string $price): void
    {
        $this->cart[$index]['unit_price'] = fmt_num_plain(max(0, (float) $price), 0);
        $this->recalculateCartLine($index);
    }

    public function updatedHasVat(): void
    {
        $this->recalculateCart();
    }

    public function updatedPriceMode(): void
    {
        $this->recalculateCart();
    }

    public function updatedVatRate(): void
    {
        $this->recalculateCart();
    }

    public function updatedVatDeductible(): void
    {
        $this->recalculateCart();
    }

    public function getVatBreakdownProperty(): array
    {
        $ht = 0.0;
        $vat = 0.0;
        $ttc = 0.0;
        $cost = 0.0;

        foreach ($this->cart as $index => $row) {
            $line = $this->lineVat((float) ($row['unit_price'] ?? 0), (float) ($row['quantity'] ?? 0));
            $ht += $line['line_total_ht'];
            $vat += $line['vat_amount'];
            $ttc += $line['line_total_ttc'];
            $cost += $line['line_total'];
        }

        return [
            'ht' => round($ht, 2),
            'vat' => round($vat, 2),
            'ttc' => round($ttc, 2),
            'stock_cost' => round($cost, 2),
        ];
    }

    public function getSubtotalProperty(): float
    {
        return $this->vatBreakdown['ht'];
    }

    public function getTotalProperty(): float
    {
        return $this->vatBreakdown['ttc'];
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
            'has_vat' => 'boolean',
            'price_mode' => 'required|in:ht,ttc',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'vat_deductible' => 'boolean',
        ]);

        $vatFields = [];
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('purchase_orders', 'has_vat')) {
            $vatFields = [
                'has_vat' => (bool) $this->has_vat,
                'price_mode' => $this->price_mode,
                'vat_rate' => $this->has_vat ? (float) $this->vat_rate : 0,
                'vat_deductible' => (bool) $this->vat_deductible,
            ];
        }

        $purchasesService = app(PurchasesService::class);

        if ($this->purchaseId) {
            $order = PurchaseOrder::findOrFail($this->purchaseId);
            if (!$purchasesService->canEditOrder($order)) {
                session()->flash('error', 'Cette commande ne peut plus être modifiée.');
                return null;
            }
            $order->fill([
                'provider_id' => $data['provider_id'] ?: null,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'],
                'notes' => $data['notes'],
            ] + $vatFields);
            $order->save();
            $order->lines()->delete();
        } else {
            $order = $purchasesService->createPurchaseOrder(array_merge([
                'order_number' => app(PurchaseDocumentNumberService::class)->nextOrderNumber(
                    (int) date('Y', strtotime($data['order_date']))
                ),
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'],
                'provider_id' => $data['provider_id'] ?: null,
                'status' => PurchasesService::STATUS_DRAFT,
                'notes' => $data['notes'],
                'created_by' => auth('tenant')->id(),
            ], $vatFields));
        }

        foreach ($this->cart as $cartItem) {
            $item = Item::find($cartItem['item_id']);
            $computed = $this->lineVat((float) $cartItem['unit_price'], (float) $cartItem['quantity']);
            $lineData = [
                'item_id' => $cartItem['item_id'],
                'item_name' => $item->name,
                'quantity' => (float) $cartItem['quantity'],
                'unit_price' => $computed['unit_price'],
                'line_total' => $computed['line_total'],
            ];
            if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('purchase_lines', 'unit_price_ht')) {
                $lineData += [
                    'entered_unit_price' => (float) $cartItem['unit_price'],
                    'unit_price_ht' => $computed['unit_price_ht'],
                    'unit_price_ttc' => $computed['unit_price_ttc'],
                    'vat_rate' => $computed['vat_rate'],
                    'vat_amount' => $computed['vat_amount'],
                    'line_total_ht' => $computed['line_total_ht'],
                    'line_total_ttc' => $computed['line_total_ttc'],
                ];
            }
            $purchasesService->addLineToOrder($order->id, $lineData);
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
                'vatBreakdown' => $this->vatBreakdown,
            ]);
    }

    private function recalculateCart(): void
    {
        foreach (array_keys($this->cart) as $index) {
            $this->recalculateCartLine((int) $index);
        }
    }

    private function recalculateCartLine(int $index): void
    {
        if (!isset($this->cart[$index])) {
            return;
        }

        $computed = $this->lineVat(
            (float) ($this->cart[$index]['unit_price'] ?? 0),
            (float) ($this->cart[$index]['quantity'] ?? 0)
        );
        $this->cart[$index]['line_total'] = (string) $computed['line_total'];
    }

    /**
     * @return array<string, float>
     */
    private function lineVat(float $enteredUnitPrice, float $quantity): array
    {
        return PurchaseVatCalculator::fromEntered(
            $enteredUnitPrice,
            $quantity,
            (float) $this->vat_rate,
            $this->price_mode === PurchaseVatCalculator::MODE_TTC
                ? PurchaseVatCalculator::MODE_TTC
                : PurchaseVatCalculator::MODE_HT,
            (bool) $this->has_vat,
            (bool) $this->vat_deductible
        );
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
