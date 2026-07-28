<?php

namespace InovCom\Sales\Http\Livewire;

use App\Services\StoreContextService;
use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Item;
use InovCom\Items\Services\ItemSetService;
use InovCom\Kernel\Contracts\BatchesApi;
use InovCom\Kernel\Contracts\ClientsApi;
use InovCom\Kernel\Contracts\ItemsApi;
use InovCom\Sales\Models\Payment;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleLine;
use InovCom\Sales\Models\SuspendedSale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\Stock\Services\StorageLocationService;
use InovCom\Stock\Services\StockService;
use Livewire\Component;

class SalesForm extends Component
{
    public ?int $saleId = null;

    // Sale fields
    public ?int $client_id = null;
    public ?int $prescription_id = null;
    public string $discount_amount = '0';
    public string $discount_percent = '';
    public bool $showDiscount = false;

    // Cart items (each: item_id, item_name, item_sku, unit_id, unit_name, conversion_factor, quantity, unit_price, line_total)
    public array $cart = [];

    // Item search
    public string $itemSearch = '';
    public array $searchResults = [];

    // Client search (not a full dropdown)
    public string $clientSearch = '';
    public array $clientResults = [];

    // Payment: flexible split (Cash, Orange Money, MTN Money, Credit)
    public array $payment_rows = [];

    public function canModifyPrice(): bool
    {
        $user = Auth::guard('tenant')->user();
        return $user && $user->hasPermission('sales.modify_price');
    }

    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'orange_money' => 'Orange Money',
        'mtn_money' => 'MTN Money',
        'credit' => 'Crédit',
    ];

    public function addPaymentRow(): void
    {
        $this->payment_rows[] = [
            'method' => 'cash',
            'amount' => '0',
            'transaction_reference' => '',
        ];
    }

    public function removePaymentRow(int $index): void
    {
        unset($this->payment_rows[$index]);
        $this->payment_rows = array_values($this->payment_rows);
    }

    public function fillRemainingWithMethod(string $method): void
    {
        $remaining = $this->remaining;
        if ($remaining <= 0) {
            return;
        }
        $this->payment_rows[] = [
            'method' => $method,
            'amount' => (string) round($remaining, 2),
            'transaction_reference' => '',
        ];
    }

    /**
     * Default: full amount in cash. When there is only one payment row and it's cash,
     * keep its amount in sync with the sale total (cart/discount changes). Speeds up sales.
     */
    protected function syncDefaultCashPayment(): void
    {
        if (count($this->payment_rows) !== 1) {
            return;
        }
        if (($this->payment_rows[0]['method'] ?? '') !== 'cash') {
            return;
        }
        $this->payment_rows[0]['amount'] = (string) round($this->total, 2);
    }

    public function getTotalAllocatedProperty(): float
    {
        $sum = 0;
        foreach ($this->payment_rows as $row) {
            $sum += (float) ($row['amount'] ?? 0);
        }
        return round($sum, 2);
    }

    public function getRemainingProperty(): float
    {
        return round($this->total - $this->totalAllocated, 2);
    }

    public function getCreditAmountProperty(): float
    {
        $sum = 0;
        foreach ($this->payment_rows as $row) {
            if (($row['method'] ?? '') === 'credit') {
                $sum += (float) ($row['amount'] ?? 0);
            }
        }
        return round($sum, 2);
    }

    public function mount(?Sale $sale = null): void
    {
        if ($sale) {
            $this->saleId = $sale->id;
            $this->client_id = $sale->client_id;
            if ($sale->client) {
                $this->clientSearch = $sale->client->name . ' (' . $sale->client->code . ')';
            }
            $this->discount_amount = (string) $sale->discount_amount;
            $this->discount_percent = $sale->discount_percent ? (string) $sale->discount_percent : '';
            $this->showDiscount = $sale->discount_amount > 0 || $sale->discount_percent > 0;

            foreach ($sale->lines as $line) {
                $this->cart[] = [
                    'item_id' => $line->item_id,
                    'item_name' => $line->item_name,
                    'item_sku' => $line->item_sku,
                    'unit_id' => $line->unit_id,
                    'unit_name' => $line->unit_name ?? '',
                    'conversion_factor' => (string) ($line->conversion_factor ?? 1),
                    'quantity' => (string) $line->quantity,
                    'unit_price' => (string) $line->unit_price,
                    'line_total' => (string) $line->line_total,
                ];
            }
            return;
        }

        $resumeId = request()->query('resume');
        if ($resumeId && Schema::connection('tenant')->hasTable('suspended_sales')) {
            $suspended = SuspendedSale::find($resumeId);
            if ($suspended) {
                $payload = $suspended->payload ?? [];
                $this->client_id = $payload['client_id'] ?? null;
                if ($this->client_id) {
                    $resumedClient = Client::on('tenant')->find($this->client_id);
                    if ($resumedClient) {
                        $this->clientSearch = $resumedClient->name . ' (' . $resumedClient->code . ')';
                    }
                }
                $this->discount_amount = (string) ($payload['discount_amount'] ?? '0');
                $this->discount_percent = (string) ($payload['discount_percent'] ?? '');
                $this->showDiscount = (bool) ($payload['showDiscount'] ?? false);
                $this->cart = $payload['cart'] ?? [];
                $this->payment_rows = $payload['payment_rows'] ?? [
                    ['method' => 'cash', 'amount' => '0', 'transaction_reference' => ''],
                ];
                $this->syncDefaultCashPayment();
                $suspended->delete();
                session()->flash('success', 'Vente reprise.');
            }
        }

        if (empty($this->payment_rows)) {
            $this->payment_rows = [
                ['method' => 'cash', 'amount' => '0', 'transaction_reference' => ''],
            ];
            $this->syncDefaultCashPayment();
        }
    }

    public function updatedItemSearch(): void
    {
        if (strlen(trim($this->itemSearch)) < 1) {
            $this->searchResults = [];
            return;
        }

        $searchTerm = trim($this->itemSearch);
        $items = Item::query()
            ->with('unitPrices.unit')
            ->where('is_active', true)
            ->where(function ($query) use ($searchTerm) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
            })
            ->orderBy('name')
            ->limit(15)
            ->get();

        $storeId = app(StoreContextService::class)->currentStoreId();
        $stockEnabled = Schema::connection('tenant')->hasTable('stock_levels');
        $locationsEnabled = Schema::connection('tenant')->hasTable('storage_locations');
        $stockService = $stockEnabled ? app(StockService::class) : null;
        $locationService = $locationsEnabled ? app(StorageLocationService::class) : null;
        $setService = app(ItemSetService::class);

        $results = [];
        foreach ($items as $item) {
            $isSet = $setService->isSet($item);
            if ($isSet) {
                $availableQty = $stockEnabled ? $setService->maxSellableQuantity($item->id, $storeId) : null;
            } else {
                $availableQty = $stockService ? $stockService->getAvailableQuantity($item->id, $storeId) : null;
            }
            $locationLabel = $isSet ? null : ($locationService ? ($locationService->codesForItem($item->id, $storeId) ?: null) : null);

            $units = $item->selling_units;
            foreach ($units as $u) {
                $results[] = [
                    'id' => $item->id,
                    'name' => $item->name . ($isSet ? ' (Lot)' : ''),
                    'sku' => $item->sku,
                    'barcode' => $item->barcode,
                    'unit_id' => $u['unit_id'],
                    'unit_name' => $u['unit_abbr'] ?? $u['unit_name'],
                    'conversion_factor' => (string) $u['conversion_factor'],
                    'price' => (string) $u['price'],
                    'available_qty' => $availableQty,
                    'location_label' => $locationLabel,
                    'is_set' => $isSet,
                ];
            }
        }
        $this->searchResults = $results;
    }

    public function addItemToCart(array $variant): void
    {
        $existingIndex = null;
        foreach ($this->cart as $index => $cartItem) {
            if ((int) $cartItem['item_id'] === (int) $variant['id'] && (int) ($cartItem['unit_id'] ?? 0) === (int) ($variant['unit_id'] ?? 0)) {
                $existingIndex = $index;
                break;
            }
        }

        $item = Item::find($variant['id']);
        $itemName = $item?->name ?? $variant['name'] ?? '';
        $itemSku = $variant['sku'] ?? null;
        $unitName = $variant['unit_name'] ?? 'pc';
        $conversionFactor = (string) ($variant['conversion_factor'] ?? 1);
        $price = (string) ($variant['price'] ?? '0');

        if ($existingIndex !== null) {
            $this->cart[$existingIndex]['quantity'] = (string) ((float) $this->cart[$existingIndex]['quantity'] + 1);
            $this->cart[$existingIndex]['line_total'] = (string) ((float) $this->cart[$existingIndex]['quantity'] * (float) $this->cart[$existingIndex]['unit_price']);
        } else {
            $this->cart[] = [
                'item_id' => $variant['id'],
                'item_name' => $itemName,
                'item_sku' => $itemSku,
                'unit_id' => $variant['unit_id'] ?? null,
                'unit_name' => $unitName,
                'conversion_factor' => $conversionFactor,
                'quantity' => '1',
                'unit_price' => $price,
                'line_total' => $price,
                'is_set' => !empty($variant['is_set']),
            ];
        }

        $this->itemSearch = '';
        $this->searchResults = [];
        $this->syncDefaultCashPayment();
    }

    public function updateCartPrice(int $index, string $unitPrice): void
    {
        if (!$this->canModifyPrice() || !isset($this->cart[$index])) {
            return;
        }
        $price = (float) $unitPrice;
        if ($price < 0) {
            return;
        }
        $this->cart[$index]['unit_price'] = (string) $price;
        $qty = (float) ($this->cart[$index]['quantity'] ?? 1);
        $this->cart[$index]['line_total'] = (string) round($qty * $price, 2);
        $this->syncDefaultCashPayment();
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->syncDefaultCashPayment();
    }

    public function updateCartQuantity(int $index, string $quantity): void
    {
        if ((float) $quantity <= 0) {
            $this->removeFromCart($index);
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['line_total'] = (string) ((float) $quantity * (float) $this->cart[$index]['unit_price']);
        $this->syncDefaultCashPayment();
    }

    public function updatedShowDiscount(): void
    {
        if (!$this->showDiscount) {
            $this->discount_amount = '0';
            $this->discount_percent = '';
        }
        $this->syncDefaultCashPayment();
    }

    public function updatedDiscountAmount(): void
    {
        if ((float) $this->discount_amount > 0) {
            $this->discount_percent = '';
        }
        $this->syncDefaultCashPayment();
    }

    public function updatedDiscountPercent(): void
    {
        if (!empty($this->discount_percent) && (float) $this->discount_percent > 0) {
            $this->discount_amount = '0';
        }
        $this->syncDefaultCashPayment();
    }

    public function getSubtotalProperty(): float
    {
        return array_sum(array_column($this->cart, 'line_total'));
    }

    public function getDiscountProperty(): float
    {
        if (!empty($this->discount_percent)) {
            return $this->subtotal * ((float) $this->discount_percent / 100);
        }
        return (float) $this->discount_amount;
    }

    public function getTotalProperty(): float
    {
        return max(0, $this->subtotal - $this->discount);
    }

    public function suspend(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Le panier est vide. Rien à suspendre.');
            return;
        }

        if (!Schema::connection('tenant')->hasTable('suspended_sales')) {
            session()->flash('error', 'Table des ventes suspendues absente. Exécutez : php artisan tenant:migrate VOTRE_CODE_TENANT');
            return;
        }

        $payload = [
            'cart' => $this->cart,
            'client_id' => $this->client_id,
            'discount_amount' => $this->discount_amount,
            'discount_percent' => $this->discount_percent,
            'showDiscount' => $this->showDiscount,
            'payment_rows' => $this->payment_rows,
            'total' => $this->total,
        ];

        SuspendedSale::create([
            'user_id' => auth('tenant')->id(),
            'payload' => $payload,
        ]);

        $this->resetFormForNewSale();
        session()->flash('success', 'Vente suspendue. Vous pouvez la reprendre plus tard.');
    }

    public function deleteSuspended(int $id): void
    {
        if (!Schema::connection('tenant')->hasTable('suspended_sales')) {
            return;
        }
        $suspended = SuspendedSale::find($id);
        if ($suspended) {
            $suspended->delete();
            session()->flash('success', 'Vente suspendue supprimée.');
        }
    }

    private function resetFormForNewSale(): void
    {
        $this->saleId = null;
        $this->client_id = null;
        $this->clientSearch = '';
        $this->clientResults = [];
        $this->prescription_id = null;
        $this->discount_amount = '0';
        $this->discount_percent = '';
        $this->showDiscount = false;
        $this->cart = [];
        $this->itemSearch = '';
        $this->searchResults = [];
        $this->payment_rows = [
            ['method' => 'cash', 'amount' => '0', 'transaction_reference' => ''],
        ];
        $this->syncDefaultCashPayment();
    }

    public function updatedClientSearch(): void
    {
        $term = trim($this->clientSearch);
        if ($this->client_id) {
            $selected = Client::on('tenant')->find($this->client_id);
            $selectedLabel = $selected
                ? ($selected->name . ' (' . $selected->code . ')')
                : '';
            if ($selectedLabel !== '' && $term === $selectedLabel) {
                $this->clientResults = [];

                return;
            }
            // User started typing again → clear previous selection
            $this->client_id = null;
        }

        if (strlen($term) < 2) {
            $this->clientResults = [];

            return;
        }

        $like = '%' . mb_strtolower($term) . '%';
        $this->clientResults = Client::on('tenant')
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'phone' => $c->phone,
            ])
            ->all();
    }

    public function selectClient(int $id): void
    {
        $client = Client::on('tenant')->find($id);
        if (! $client) {
            return;
        }
        $this->client_id = $client->id;
        $this->clientSearch = $client->name . ' (' . $client->code . ')';
        $this->clientResults = [];
    }

    public function clearClient(): void
    {
        $this->client_id = null;
        $this->clientSearch = '';
        $this->clientResults = [];
    }

    private function quantityInBaseUnit(array $cartItem): float
    {
        $qty = (float) ($cartItem['quantity'] ?? 0);
        $factor = (float) ($cartItem['conversion_factor'] ?? 1);

        return $qty * $factor;
    }

    private function validateCartStock(): bool
    {
        if (!Schema::connection('tenant')->hasTable('stock_levels')
            || !\Illuminate\Support\Facades\App::bound(StockService::class)) {
            return true;
        }

        $stockService = app(StockService::class);
        $setService = app(ItemSetService::class);
        $storeId = app(StoreContextService::class)->currentStoreId();

        foreach ($this->cart as $cartItem) {
            $name = $cartItem['item_name'] ?? 'Article';
            $qtyEntered = (float) ($cartItem['quantity'] ?? 0);
            $factor = max(0.0001, (float) ($cartItem['conversion_factor'] ?? 1));
            $unitLabel = trim((string) ($cartItem['unit_name'] ?? ''));

            if (!empty($cartItem['is_set']) && $setService->isSet((int) $cartItem['item_id'])) {
                $maxSets = $setService->maxSellableQuantity((int) $cartItem['item_id'], $storeId);
                if ($maxSets !== null && $qtyEntered > $maxSets + 0.0001) {
                    session()->flash(
                        'error',
                        'Stock insuffisant pour le lot « ' . $name . ' ». Demandé : '
                        . fmt_num_plain($qtyEntered)
                        . ($unitLabel !== '' ? ' ' . $unitLabel : '')
                        . ', disponible : ' . fmt_num_plain($maxSets) . '.'
                    );
                    return false;
                }

                foreach ($setService->expandForStock((int) $cartItem['item_id'], $qtyEntered, $factor) as $component) {
                    if (!$stockService->hasStock($component['item_id'], $component['quantity_base'])) {
                        session()->flash(
                            'error',
                            'Stock insuffisant pour le composant « ' . $component['item_name']
                            . ' » du lot « ' . $name . ' » (besoin : '
                            . fmt_num_plain($component['quantity_base']) . ' en unité de base).'
                        );
                        return false;
                    }
                }

                continue;
            }

            $qtyBase = $this->quantityInBaseUnit($cartItem);
            if (!$stockService->hasStock((int) $cartItem['item_id'], $qtyBase)) {
                $availableBase = $stockService->getAvailableQuantity((int) $cartItem['item_id'], $storeId);
                $availableInSelectedUnit = $availableBase / $factor;
                $unitPart = $unitLabel !== '' ? ' ' . $unitLabel : '';
                session()->flash(
                    'error',
                    'Stock insuffisant pour « ' . $name . ' ». Demandé : '
                    . fmt_num_plain($qtyEntered) . $unitPart
                    . ', disponible : ' . fmt_num_plain($availableInSelectedUnit) . $unitPart
                    . ' (stock réel: ' . fmt_num_plain($availableBase) . ' unité(s) de base).'
                );
                return false;
            }
        }

        return true;
    }

    private function deductStockForCartLine(
        array $cartItem,
        int $saleId,
        StockService $stockService,
        ItemSetService $setService,
        ?BatchesApi $batchesApi,
        bool $batchesAvailable
    ): void {
        $setQty = (float) ($cartItem['quantity'] ?? 0);
        $factor = (float) ($cartItem['conversion_factor'] ?? 1);

        if (!empty($cartItem['is_set']) && $setService->isSet((int) $cartItem['item_id'])) {
            foreach ($setService->expandForStock((int) $cartItem['item_id'], $setQty, $factor) as $component) {
                $itemId = (int) $component['item_id'];
                $qtyBase = (float) $component['quantity_base'];
                if ($qtyBase <= 0) {
                    continue;
                }

                if ($batchesAvailable && $component['batch_tracked'] && $batchesApi) {
                    $batchesApi->consumeFromBatches($itemId, $qtyBase, 'sale', $saleId);
                    continue;
                }

                $stockService->removeStock($itemId, $qtyBase, 'sale', 'sale', $saleId);
            }

            return;
        }

        $item = Item::find((int) $cartItem['item_id']);
        $batchTracked = $item && is_array($item->metadata ?? null) && !empty($item->metadata['batch_tracked']);
        if ($batchesAvailable && $batchTracked && $batchesApi) {
            $batchesApi->consumeFromBatches((int) $cartItem['item_id'], $this->quantityInBaseUnit($cartItem), 'sale', $saleId);

            return;
        }

        $stockService->removeStock(
            (int) $cartItem['item_id'],
            $this->quantityInBaseUnit($cartItem),
            'sale',
            'sale',
            $saleId
        );
    }

    public function save(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Le panier est vide.');
            return;
        }

        $totalDue = round($this->total, 2);
        $totalAllocated = $this->totalAllocated;
        if (abs($totalAllocated - $totalDue) > 0.01) {
            session()->flash('error', 'La somme des paiements (' . fmt_money($totalAllocated) . ' FCFA) doit être égale au total dû (' . fmt_money($totalDue) . ' FCFA).');
            return;
        }

        $creditAmount = $this->creditAmount;
        if ($creditAmount > 0 && !$this->client_id) {
            session()->flash('error', 'Veuillez sélectionner un client pour la partie crédit.');
            return;
        }

        if ($creditAmount > 0 && $this->client_id) {
            $clientsApi = app(ClientsApi::class);
            if (!$clientsApi->canMakePurchase((int) $this->client_id, $creditAmount)) {
                $client = Client::on('tenant')->find($this->client_id);
                $limit = $client ? (float) $client->credit_limit : 0;
                $balance = $client ? (float) $client->current_balance : 0;
                session()->flash('error', 'Limite de crédit dépassée. Solde actuel : ' . fmt_money($balance) . ' FCFA, limite : ' . fmt_money($limit) . ' FCFA. Crédit demandé : ' . fmt_money($creditAmount) . ' FCFA.');
                return;
            }
        }

        if (!$this->validateCartStock()) {
            return;
        }

        $data = $this->validate([
            'client_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (!Client::on('tenant')->where('id', $value)->exists()) {
                        $fail(__('validation.exists', ['attribute' => 'client']));
                    }
                },
            ],
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $saleNumber = $this->generateSaleNumber();

        $sale = new Sale();
        $sale->sale_number = $saleNumber;
        $sale->sale_date = now()->toDateString();
        $sale->client_id = $data['client_id'];
        $sale->prescription_id = $this->prescription_id ?: null;
        $sale->subtotal = $this->subtotal;
        $sale->discount_amount = $this->discount;
        $sale->discount_percent = !empty($this->discount_percent) ? (float) $this->discount_percent : null;
        $sale->total = $this->total;
        $sale->created_by = auth('tenant')->id();
        if (Schema::connection('tenant')->hasColumn('sales', 'store_id')) {
            $sale->store_id = app(StoreContextService::class)->currentStoreId();
        }
        $sale->save();

        $batchesApi = app()->bound(BatchesApi::class) ? app(BatchesApi::class) : null;
        $batchesAvailable = $batchesApi && $batchesApi->isAvailable();

        foreach ($this->cart as $cartItem) {
            $line = new SaleLine();
            $line->sale_id = $sale->id;
            $line->item_id = $cartItem['item_id'];
            $line->item_name = $cartItem['item_name'];
            $line->item_sku = $cartItem['item_sku'];
            $line->unit_id = $cartItem['unit_id'] ?? null;
            $line->unit_name = $cartItem['unit_name'] ?? null;
            $line->conversion_factor = (float) ($cartItem['conversion_factor'] ?? 1);
            $line->quantity = (float) $cartItem['quantity'];
            $line->unit_price = (float) $cartItem['unit_price'];
            $line->line_total = (float) $cartItem['line_total'];
            if (Schema::connection('tenant')->hasColumn('sale_lines', 'metadata') && !empty($cartItem['is_set'])) {
                $setService = app(ItemSetService::class);
                $line->metadata = [
                    'is_set' => true,
                    'set_components' => $setService->componentSnapshot((int) $cartItem['item_id']),
                ];
            }
            $line->save();
        }

        $userId = auth('tenant')->id();
        $tillCollected = 0.0;
        $cashPosted = 0.0;
        $cashFailed = 0.0;
        foreach ($this->payment_rows as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $method = $row['method'] ?? 'cash';
            $payment = new Payment();
            $payment->sale_id = $sale->id;
            $payment->method = $method;
            $payment->mobile_money_provider = in_array($method, ['orange_money', 'mtn_money'], true) ? $method : null;
            $payment->transaction_reference = trim($row['transaction_reference'] ?? '');
            $payment->amount = $amount;
            $payment->received_by = $userId;
            $payment->save();

            // Everything except credit impacts till accounting.
            if ($method !== 'credit') {
                $tillCollected += $amount;
            }

            // Auto-capture caisse : seules les espèces alimentent le tiroir physique.
            if ($method === 'cash') {
                $posted = \App\Support\CashLedger::recordIn(
                    \App\Support\CashLedger::SALE_CASH_IN,
                    $amount,
                    'Encaissement vente ' . $sale->sale_number,
                    'sale',
                    Payment::class,
                    (int) $payment->id,
                    $sale->sale_number,
                    ['sale_id' => $sale->id, 'client_id' => $sale->client_id],
                    $userId
                );
                if ($posted) {
                    $cashPosted += $amount;
                } else {
                    $cashFailed += $amount;
                }
            }

            if ($method === 'credit' && $this->client_id) {
                $client = Client::on('tenant')->find($this->client_id);
                if ($client) {
                    $client->current_balance += $amount;
                    $client->save();
                }
            }
        }

        if (Schema::connection('tenant')->hasTable('stock_levels') && \Illuminate\Support\Facades\App::bound(StockService::class)) {
            $stockService = app(StockService::class);
            $setService = app(ItemSetService::class);
            foreach ($this->cart as $cartItem) {
                $this->deductStockForCartLine(
                    $cartItem,
                    $sale->id,
                    $stockService,
                    $setService,
                    $batchesApi,
                    $batchesAvailable
                );
            }
        }

        $successMsg = 'Vente enregistrée: ' . $saleNumber;
        if ($cashPosted > 0) {
            $successMsg .= ' — ' . fmt_money($cashPosted) . ' FCFA ajoutés à la caisse.';
        }
        session()->flash('success', $successMsg);
        if ($cashFailed > 0) {
            session()->flash(
                'error',
                'Attention : ' . fmt_money($cashFailed) . ' FCFA en espèces n\'ont pas pu être enregistrés en caisse. Vérifiez que le module Caisse est actif et qu\'une session est ouverte.'
            );
        }
        $this->redirect(route('tenant.sales.show', [$sale->id, 'tenant' => $this->tenantCode()]), navigate: true);
    }

    private function generateSaleNumber(): string
    {
        $year = now()->year;
        $lastSale = Sale::whereYear('sale_date', $year)
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastSale) {
            $nextNumber = 1;
        } else {
            // Format: VTE-2026-000001 — extract the sequence (part after last hyphen)
            $parts = explode('-', $lastSale->sale_number);
            $lastPart = end($parts);
            $seq = (int) $lastPart;
            // Fallback: if corrupted (e.g. scientific notation), use count + 1
            if ($seq > 999999 || $seq <= 0) {
                $nextNumber = Sale::whereYear('sale_date', $year)->count() + 1;
            } else {
                $nextNumber = $seq + 1;
            }
        }

        return 'VTE-' . $year . '-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        $suspendedSales = collect([]);
        if (!$this->saleId && Schema::connection('tenant')->hasTable('suspended_sales')) {
            $suspendedSales = SuspendedSale::with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        return view('inovcom-sales::livewire.sales.form')
            ->layout('layouts.app', [
                'title' => $this->saleId ? 'Détail vente' : 'Nouvelle vente',
                'subtitle' => 'Point de vente',
            ])
            ->with([
                'paymentMethodLabels' => self::PAYMENT_METHODS,
                'suspendedSales' => $suspendedSales,
                'activePrescriptions' => $this->getActivePrescriptions(),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    /**
     * Active prescriptions for dropdown (when Prescriptions module is enabled).
     */
    private function getActivePrescriptions(): \Illuminate\Support\Collection
    {
        if (!Schema::connection('tenant')->hasTable('prescriptions') || !class_exists(\InovCom\Prescriptions\Models\Prescription::class)) {
            return collect([]);
        }
        return \InovCom\Prescriptions\Models\Prescription::where('status', 'active')
            ->orderBy('number')
            ->get(['id', 'number', 'client_id']);
    }
}
