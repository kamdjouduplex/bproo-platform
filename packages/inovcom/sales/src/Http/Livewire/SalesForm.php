<?php

namespace InovCom\Sales\Http\Livewire;

use App\Services\StoreContextService;
use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Item;
use InovCom\Items\Services\ItemSetService;
use InovCom\Kernel\Contracts\BatchesApi;
use InovCom\Kernel\Contracts\ClientsApi;
use InovCom\Kernel\Contracts\ItemsApi;
use InovCom\Kernel\Contracts\PrescriptionsApi;
use InovCom\Sales\Models\Payment;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleLine;
use InovCom\Sales\Models\SuspendedSale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\Stock\Services\StorageLocationService;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Facades\DB;
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

    /** POS ordonnance modal (only when prescriptions module + Rx products in cart). */
    public bool $showRxModal = false;
    public string $rxModalTab = 'create'; // create | search
    public string $rxSearch = '';
    /** @var list<array<string, mixed>> */
    public array $rxSearchResults = [];
    public string $rx_prescriber_name = '';
    public string $rx_valid_until = '';
    /** @var list<array{item_id: int|null, item_name: string, quantity: string, instructions: string}> */
    public array $rx_lines = [];
    /** @var array{id:int,number:string,status_label:string,client_name:?string,valid_until:?string,lines_summary:string,remaining_total:float}|null */
    public ?array $rxAttached = null;

    /** Quick client create (ClientsApi) — independent of prescriptions. */
    public bool $showQuickClientModal = false;
    public string $quick_client_name = '';
    public string $quick_client_phone = '';
    public bool $highlightClientField = false;

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
                $this->prescription_id = isset($payload['prescription_id']) ? (int) $payload['prescription_id'] : null;
                if ($this->prescription_id) {
                    $this->refreshAttachedPrescription();
                }
                foreach (array_keys($this->cart) as $idx) {
                    $this->refreshCartBatchInfo((int) $idx);
                }
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
        $batchesApi = $this->batchesApiOrNull();

        $results = [];
        foreach ($items as $item) {
            $isSet = $setService->isSet($item);
            if ($isSet) {
                $availableQty = $stockEnabled ? $setService->maxSellableQuantity($item->id, $storeId) : null;
            } else {
                $availableQty = $stockService ? $stockService->getAvailableQuantity($item->id, $storeId) : null;
            }
            $locationLabel = $isSet ? null : ($locationService ? ($locationService->codesForItem($item->id, $storeId) ?: null) : null);

            $batchHint = null;
            $meta = is_array($item->metadata ?? null) ? $item->metadata : [];
            if ($batchesApi && ! $isSet && ! empty($meta['batch_tracked'])) {
                $next = $batchesApi->getBatchesForItem($item->id, true, true)->first();
                if ($next) {
                    $batchHint = $next->batch_number.' · exp. '.$next->expiry_date->format('d/m/Y');
                } else {
                    $batchHint = 'Aucun lot non périmé';
                }
            }

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
                    'batch_hint' => $batchHint,
                ];
            }
        }
        $this->searchResults = $results;
    }

    public function addItemToCart(array $variant): void
    {
        $existingIndex = null;
        foreach ($this->cart as $index => $cartItem) {
            // Same item+unit without an explicit lot choice can merge; different lots stay separate.
            $sameItem = (int) $cartItem['item_id'] === (int) $variant['id']
                && (int) ($cartItem['unit_id'] ?? 0) === (int) ($variant['unit_id'] ?? 0);
            $sameLot = (int) ($cartItem['batch_id'] ?? 0) === (int) ($variant['batch_id'] ?? 0);
            if ($sameItem && $sameLot) {
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
        $meta = is_array($item?->metadata ?? null) ? $item->metadata : [];
        $requiresPrescription = ! empty($meta['requires_prescription']);

        if ($existingIndex !== null) {
            $this->cart[$existingIndex]['quantity'] = (string) ((float) $this->cart[$existingIndex]['quantity'] + 1);
            $this->cart[$existingIndex]['line_total'] = (string) ((float) $this->cart[$existingIndex]['quantity'] * (float) $this->cart[$existingIndex]['unit_price']);
            $this->cart[$existingIndex]['requires_prescription'] = $requiresPrescription
                || ! empty($this->cart[$existingIndex]['requires_prescription']);
            $this->refreshCartBatchInfo($existingIndex);
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
                'is_set' => ! empty($variant['is_set']),
                'requires_prescription' => $requiresPrescription,
                'batch_tracked_flag' => ! empty($meta['batch_tracked']),
                'batch_tracked' => false,
                'batch_id' => null,
                'batch_options' => [],
                'batch_summary' => null,
            ];
            $this->refreshCartBatchInfo(count($this->cart) - 1);
        }

        $this->itemSearch = '';
        $this->searchResults = [];
        $this->syncDefaultCashPayment();
    }

    public function setCartBatch(int $index, ?string $batchId): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }
        $this->cart[$index]['batch_id'] = ($batchId !== null && $batchId !== '') ? (int) $batchId : null;
        $this->refreshCartBatchInfo($index);
    }

    public function updateCartQuantity(int $index, string $quantity): void
    {
        if ((float) $quantity <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['line_total'] = (string) ((float) $quantity * (float) $this->cart[$index]['unit_price']);
        $this->refreshCartBatchInfo($index);
        $this->syncDefaultCashPayment();
    }

    /**
     * Attach lot options + FEFO preview when Batches module is on and item is tracked.
     * No-op for commerce without lots (keeps POS unchanged).
     */
    private function refreshCartBatchInfo(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        $cartItem = &$this->cart[$index];
        $cartItem['batch_tracked'] = false;
        $cartItem['batch_options'] = [];
        $cartItem['batch_summary'] = null;

        if (! empty($cartItem['is_set'])) {
            return;
        }

        $batchesApi = $this->batchesApiOrNull();
        if (! $batchesApi) {
            $cartItem['batch_id'] = null;

            return;
        }

        // Prefer cart flag when present (avoids Item::find on every qty change).
        $tracked = array_key_exists('batch_tracked_flag', $cartItem)
            ? (bool) $cartItem['batch_tracked_flag']
            : null;
        if ($tracked === null) {
            $item = Item::find((int) $cartItem['item_id']);
            $tracked = $item && is_array($item->metadata ?? null) && ! empty($item->metadata['batch_tracked']);
            $cartItem['batch_tracked_flag'] = $tracked;
            if ($item && is_array($item->metadata ?? null) && array_key_exists('requires_prescription', $item->metadata)) {
                $cartItem['requires_prescription'] = ! empty($item->metadata['requires_prescription']);
            }
        }
        if (! $tracked) {
            $cartItem['batch_id'] = null;

            return;
        }

        $cartItem['batch_tracked'] = true;
        $batches = $batchesApi->getBatchesForItem((int) $cartItem['item_id'], true, true);
        $cartItem['batch_options'] = $batches->map(function ($b) {
            $days = now()->startOfDay()->diffInDays($b->expiry_date->copy()->startOfDay(), false);

            return [
                'id' => (int) $b->id,
                'label' => $b->batch_number
                    .' · exp. '.$b->expiry_date->format('d/m/Y')
                    .' · stock '.fmt_num_plain((float) $b->quantity)
                    .($days <= 30 ? ' ⚠' : ''),
                'batch_number' => (string) $b->batch_number,
                'expiry_date' => $b->expiry_date->format('Y-m-d'),
                'quantity' => (float) $b->quantity,
            ];
        })->values()->all();

        // Drop invalid selection
        $selectedId = isset($cartItem['batch_id']) ? (int) $cartItem['batch_id'] : null;
        if ($selectedId && ! $batches->contains('id', $selectedId)) {
            $selectedId = null;
            $cartItem['batch_id'] = null;
        }

        // Auto-pick when only one sellable lot — clearer for the cashier
        if ($selectedId === null && count($cartItem['batch_options']) === 1) {
            $selectedId = (int) $cartItem['batch_options'][0]['id'];
            $cartItem['batch_id'] = $selectedId;
        }

        $qtyBase = $this->quantityInBaseUnit($cartItem);
        $preview = $batchesApi->previewAllocation((int) $cartItem['item_id'], $qtyBase, $selectedId);
        $cartItem['batch_summary'] = $this->formatBatchPreviewSummary($preview, $selectedId !== null);
    }

    /**
     * @param  list<array{batch_id: int, batch_number: string, expiry_date: string, quantity: float}>  $preview
     */
    private function formatBatchPreviewSummary(array $preview, bool $forcedLot): ?string
    {
        if ($preview === []) {
            return $forcedLot
                ? 'Lot sélectionné insuffisant pour cette quantité'
                : 'Aucun lot non périmé disponible';
        }

        $parts = [];
        foreach ($preview as $row) {
            $exp = \Carbon\Carbon::parse($row['expiry_date'])->format('d/m/Y');
            $parts[] = $row['batch_number'].' (exp. '.$exp.', ×'.fmt_num_plain((float) $row['quantity']).')';
        }

        $prefix = $forcedLot ? 'Lot choisi : ' : 'Lot FEFO : ';

        return $prefix.implode(' → ', $parts);
    }

    private function batchesApiOrNull(): ?BatchesApi
    {
        if (! app()->bound(BatchesApi::class)) {
            return null;
        }
        $api = app(BatchesApi::class);

        return $api->isAvailable() ? $api : null;
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
            'prescription_id' => $this->prescription_id,
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
        $this->rxAttached = null;
        $this->showRxModal = false;
        $this->rxSearch = '';
        $this->rxSearchResults = [];
        $this->rx_prescriber_name = '';
        $this->rx_valid_until = '';
        $this->rx_lines = [];
        $this->showQuickClientModal = false;
        $this->quick_client_name = '';
        $this->quick_client_phone = '';
        $this->highlightClientField = false;
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
        $this->highlightClientField = false;
        $this->showQuickClientModal = false;
    }

    public function clearClient(): void
    {
        $this->client_id = null;
        $this->clientSearch = '';
        $this->clientResults = [];
    }

    public function canQuickCreateClient(): bool
    {
        return app()->bound(ClientsApi::class);
    }

    public function openQuickClientModal(?string $prefillName = null): void
    {
        if (! $this->canQuickCreateClient()) {
            return;
        }
        $this->quick_client_name = $prefillName !== null && $prefillName !== ''
            ? $prefillName
            : trim($this->clientSearch);
        $this->quick_client_phone = '';
        $this->showQuickClientModal = true;
        $this->showRxModal = false;
    }

    public function closeQuickClientModal(): void
    {
        $this->showQuickClientModal = false;
    }

    public function createQuickClient(): void
    {
        if (! $this->canQuickCreateClient()) {
            return;
        }

        try {
            $client = app(ClientsApi::class)->createQuickClient([
                'name' => $this->quick_client_name,
                'phone' => $this->quick_client_phone ?: null,
            ]);
            $this->selectClient((int) $client->id);
            $this->quick_client_name = '';
            $this->quick_client_phone = '';
            session()->flash('success', 'Client '.$client->code.' créé et sélectionné.');
        } catch (\InvalidArgumentException|\RuntimeException|\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
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

                    $batchesApi = app()->bound(BatchesApi::class) ? app(BatchesApi::class) : null;
                    if (! empty($component['batch_tracked']) && $batchesApi && $batchesApi->isAvailable()) {
                        $sellable = $batchesApi->sellableQuantity((int) $component['item_id']);
                        if ((float) $component['quantity_base'] > $sellable + 0.0001) {
                            session()->flash(
                                'error',
                                'Impossible de vendre « '.$component['item_name'].' » : '
                                .($sellable <= 0.0001
                                    ? 'le stock est périmé.'
                                    : 'seulement '.fmt_num_plain($sellable).' non périmé(s) disponible(s).')
                            );

                            return false;
                        }
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

            // Pharmacy: only non-expired batch qty is sellable
            $item = Item::find((int) $cartItem['item_id']);
            $batchTracked = $item && is_array($item->metadata ?? null) && ! empty($item->metadata['batch_tracked']);
            $batchesApi = app()->bound(BatchesApi::class) ? app(BatchesApi::class) : null;
            if ($batchTracked && $batchesApi && $batchesApi->isAvailable()) {
                $sellable = $batchesApi->sellableQuantity((int) $cartItem['item_id']);
                if ($qtyBase > $sellable + 0.0001) {
                    session()->flash(
                        'error',
                        'Impossible de vendre « '.$name.' » : '
                        .($sellable <= 0.0001
                            ? 'le stock est périmé.'
                            : 'seulement '.fmt_num_plain($sellable).' non périmé(s) disponible(s).')
                    );

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Enforce Rx only when the Prescriptions bridge is available.
     * Commerce tenants without Ordonnances: no block, no UI — sales unchanged.
     */
    private function prescriptionsApi(): ?PrescriptionsApi
    {
        if (! app()->bound(PrescriptionsApi::class)) {
            return null;
        }

        $api = app(PrescriptionsApi::class);

        return $api->isAvailable() ? $api : null;
    }

    private function validatePrescriptionRequired(): bool
    {
        $rxApi = $this->prescriptionsApi();
        if (! $rxApi) {
            return true;
        }

        if (! $this->cartRequiresPrescription()) {
            return true;
        }

        if ($this->prescription_id) {
            return true;
        }

        $names = $this->prescriptionRequiredItemNames();
        session()->flash(
            'error',
            'Ordonnance obligatoire pour : '.implode(', ', $names).'. Utilisez « Ajouter une ordonnance ».'
        );

        return false;
    }

    public function cartRequiresPrescription(): bool
    {
        if (! $this->prescriptionsApi()) {
            return false;
        }

        return $this->prescriptionRequiredItemNames() !== [];
    }

    /**
     * @return list<string>
     */
    private function prescriptionRequiredItemNames(): array
    {
        $names = [];
        $missingIds = [];

        foreach ($this->cart as $cartItem) {
            if (array_key_exists('requires_prescription', $cartItem)) {
                if (! empty($cartItem['requires_prescription'])) {
                    $names[] = $cartItem['item_name'] ?? ('Article #'.($cartItem['item_id'] ?? ''));
                }
                continue;
            }
            $itemId = (int) ($cartItem['item_id'] ?? 0);
            if ($itemId > 0) {
                $missingIds[$itemId] = $cartItem;
            }
        }

        // Legacy suspended carts without the flag — one query max
        if ($missingIds !== []) {
            $items = Item::query()
                ->whereIn('id', array_keys($missingIds))
                ->get(['id', 'name', 'metadata'])
                ->keyBy('id');
            foreach ($missingIds as $itemId => $cartItem) {
                $item = $items->get($itemId);
                $needs = $item && is_array($item->metadata ?? null) && ! empty($item->metadata['requires_prescription']);
                // hydrate flag for next renders
                foreach ($this->cart as $idx => $row) {
                    if ((int) ($row['item_id'] ?? 0) === $itemId && ! array_key_exists('requires_prescription', $row)) {
                        $this->cart[$idx]['requires_prescription'] = $needs;
                    }
                }
                if ($needs) {
                    $names[] = $cartItem['item_name'] ?? $item->name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    public function openRxModal(string $tab = 'create'): void
    {
        if (! $this->prescriptionsApi()) {
            return;
        }

        $tab = in_array($tab, ['create', 'search'], true) ? $tab : 'create';

        // Création : patient obligatoire avant d’ouvrir le modal (évite crash / UX confuse)
        if ($tab === 'create' && ! $this->client_id) {
            $this->highlightClientField = true;
            session()->flash(
                'error',
                'Sélectionnez ou créez d’abord le patient (client), puis cliquez sur « Ajouter une ordonnance ».'
            );

            return;
        }

        $this->rxModalTab = $tab;
        $this->showRxModal = true;
        $this->rxSearch = '';
        $this->rxSearchResults = [];
        $this->highlightClientField = false;

        if ($this->rxModalTab === 'create') {
            $this->prepareRxLinesFromCart();
            if ($this->rx_valid_until === '') {
                $this->rx_valid_until = now()->addDays(30)->format('Y-m-d');
            }
        } else {
            $this->runRxSearch();
        }
    }

    public function closeRxModal(): void
    {
        $this->showRxModal = false;
    }

    public function setRxModalTab(string $tab): void
    {
        $tab = in_array($tab, ['create', 'search'], true) ? $tab : 'create';
        if ($tab === 'create' && ! $this->client_id) {
            session()->flash('error', 'Sélectionnez ou créez d’abord le patient (client) pour créer une ordonnance.');
            $this->rxModalTab = 'search';

            return;
        }

        $this->rxModalTab = $tab;
        if ($this->rxModalTab === 'create') {
            $this->prepareRxLinesFromCart();
        } else {
            $this->runRxSearch();
        }
    }

    public function updatedRxSearch(): void
    {
        $this->runRxSearch();
    }

    public function prepareRxLinesFromCart(): void
    {
        $lines = [];
        foreach ($this->cart as $cartItem) {
            $itemId = (int) ($cartItem['item_id'] ?? 0);
            if ($itemId <= 0 || ! empty($cartItem['is_set'])) {
                continue;
            }

            $needsRx = array_key_exists('requires_prescription', $cartItem)
                ? ! empty($cartItem['requires_prescription'])
                : null;
            if ($needsRx === null) {
                $item = Item::find($itemId);
                $needsRx = $item && is_array($item->metadata ?? null) && ! empty($item->metadata['requires_prescription']);
            }
            if (! $needsRx) {
                continue;
            }

            $qtyBase = $this->quantityInBaseUnit($cartItem);
            $lines[] = [
                'item_id' => $itemId,
                'item_name' => $cartItem['item_name'] ?? ('Article #'.$itemId),
                'quantity' => (string) max(1, $qtyBase),
                'instructions' => '',
            ];
        }

        if ($lines === []) {
            $lines[] = ['item_id' => null, 'item_name' => '', 'quantity' => '1', 'instructions' => ''];
        }

        $this->rx_lines = $lines;
    }

    public function addRxLine(): void
    {
        $this->rx_lines[] = ['item_id' => null, 'item_name' => '', 'quantity' => '1', 'instructions' => ''];
    }

    public function removeRxLine(int $index): void
    {
        if (count($this->rx_lines) <= 1) {
            return;
        }
        unset($this->rx_lines[$index]);
        $this->rx_lines = array_values($this->rx_lines);
    }

    public function createAndAttachPrescription(): void
    {
        $rxApi = $this->prescriptionsApi();
        if (! $rxApi) {
            return;
        }

        if (! $this->client_id) {
            session()->flash('error', 'Sélectionnez d’abord le patient (client) pour créer l’ordonnance.');
            $this->showRxModal = false;
            $this->highlightClientField = true;

            return;
        }

        try {
            $created = $rxApi->createQuickForSale([
                'client_id' => (int) $this->client_id,
                'prescriber_name' => $this->rx_prescriber_name ?: null,
                'valid_until' => $this->rx_valid_until ?: null,
                'lines' => $this->rx_lines,
            ]);
            $this->attachPrescription((int) $created['id']);
            session()->flash('success', 'Ordonnance '.$created['number'].' créée et rattachée à cette vente.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage() ?: 'Impossible de créer l’ordonnance.');
        }
    }

    public function attachPrescription(int $prescriptionId): void
    {
        $rxApi = $this->prescriptionsApi();
        if (! $rxApi || $prescriptionId <= 0) {
            return;
        }

        $snap = $rxApi->snapshotForSale($prescriptionId);
        if (! $snap || empty($snap['attachable'])) {
            session()->flash('error', 'Ordonnance introuvable ou non délivrable (expirée, clôturée ou déjà complète).');

            return;
        }

        $this->prescription_id = (int) $snap['id'];
        $this->rxAttached = $snap;
        $this->showRxModal = false;

        // Patient de l’ordonnance = client de la vente (évite les rattachements croisés)
        if (! empty($snap['client_id'])) {
            $this->selectClient((int) $snap['client_id']);
        }
    }

    public function detachPrescription(): void
    {
        $this->prescription_id = null;
        $this->rxAttached = null;
    }

    public function refreshAttachedPrescription(): void
    {
        if (! $this->prescription_id) {
            $this->rxAttached = null;

            return;
        }
        $rxApi = $this->prescriptionsApi();
        $this->rxAttached = $rxApi?->snapshotForSale((int) $this->prescription_id);
        if (! $this->rxAttached) {
            $this->prescription_id = null;
        }
    }

    private function runRxSearch(): void
    {
        $rxApi = $this->prescriptionsApi();
        if (! $rxApi) {
            $this->rxSearchResults = [];

            return;
        }

        // Recherche texte = globale (réf. RX / nom). Sans texte + client sélectionné = ses restes.
        $term = trim($this->rxSearch);
        $clientFilter = ($term === '' && $this->client_id) ? (int) $this->client_id : null;

        $this->rxSearchResults = $rxApi->searchForSale($term, $clientFilter, 12);
    }

    /**
     * Persist cart lines and deduct stock in one pass so FEFO allocations
     * are written to sale_lines.batch_id (split when several lots are used).
     *
     * @return array<int, SaleLine>
     */
    private function createSaleLinesAndDeductStock(
        Sale $sale,
        ?StockService $stockService,
        ItemSetService $setService,
        ?BatchesApi $batchesApi,
        bool $batchesAvailable
    ): array {
        $created = [];
        $hasStock = $stockService
            && Schema::connection('tenant')->hasTable('stock_levels');

        foreach ($this->cart as $cartItem) {
            $isSet = ! empty($cartItem['is_set']) && $setService->isSet((int) $cartItem['item_id']);

            if ($isSet) {
                $batchAllocations = [];
                if ($hasStock) {
                    $setQty = (float) ($cartItem['quantity'] ?? 0);
                    $factor = (float) ($cartItem['conversion_factor'] ?? 1);
                    foreach ($setService->expandForStock((int) $cartItem['item_id'], $setQty, $factor) as $component) {
                        $itemId = (int) $component['item_id'];
                        $qtyBase = (float) $component['quantity_base'];
                        if ($qtyBase <= 0) {
                            continue;
                        }

                        if ($batchesAvailable && ! empty($component['batch_tracked']) && $batchesApi) {
                            $batchAllocations[$itemId] = $batchesApi->consumeFromBatches(
                                $itemId,
                                $qtyBase,
                                'sale',
                                (int) $sale->id
                            );
                            continue;
                        }

                        $stockService->removeStock($itemId, $qtyBase, 'sale', 'sale', (int) $sale->id);
                    }
                }

                $line = $this->makeSaleLine($sale, $cartItem, null);
                if (Schema::connection('tenant')->hasColumn('sale_lines', 'metadata')) {
                    $meta = [
                        'is_set' => true,
                        'set_components' => $setService->componentSnapshot((int) $cartItem['item_id']),
                    ];
                    if ($batchAllocations !== []) {
                        $meta['batch_allocations'] = $batchAllocations;
                    }
                    $line->metadata = $meta;
                }
                $line->save();
                $created[] = $line;

                continue;
            }

            $item = Item::find((int) $cartItem['item_id']);
            $batchTracked = $item && is_array($item->metadata ?? null) && ! empty($item->metadata['batch_tracked']);

            if ($hasStock && $batchesAvailable && $batchTracked && $batchesApi) {
                $consumed = $batchesApi->consumeFromBatches(
                    (int) $cartItem['item_id'],
                    $this->quantityInBaseUnit($cartItem),
                    'sale',
                    (int) $sale->id,
                    ! empty($cartItem['batch_id']) ? (int) $cartItem['batch_id'] : null
                );

                foreach ($this->splitCartItemByBatches($cartItem, $consumed) as $split) {
                    $line = $this->makeSaleLine($sale, $cartItem, (int) $split['batch_id']);
                    $line->quantity = $split['quantity'];
                    $line->line_total = $split['line_total'];
                    $line->save();
                    $created[] = $line;
                }

                continue;
            }

            $line = $this->makeSaleLine($sale, $cartItem, null);
            $line->save();
            $created[] = $line;

            if ($hasStock) {
                $stockService->removeStock(
                    (int) $cartItem['item_id'],
                    $this->quantityInBaseUnit($cartItem),
                    'sale',
                    'sale',
                    (int) $sale->id
                );
            }
        }

        return $created;
    }

    private function makeSaleLine(Sale $sale, array $cartItem, ?int $batchId): SaleLine
    {
        $line = new SaleLine();
        $line->sale_id = $sale->id;
        $line->item_id = $cartItem['item_id'];
        $line->batch_id = $batchId;
        $line->item_name = $cartItem['item_name'];
        $line->item_sku = $cartItem['item_sku'];
        $line->unit_id = $cartItem['unit_id'] ?? null;
        $line->unit_name = $cartItem['unit_name'] ?? null;
        $line->conversion_factor = (float) ($cartItem['conversion_factor'] ?? 1);
        $line->quantity = (float) $cartItem['quantity'];
        $line->unit_price = (float) $cartItem['unit_price'];
        $line->line_total = (float) $cartItem['line_total'];

        return $line;
    }

    /**
     * @param  array<int, float>  $consumed  batch_id => quantity in base unit
     * @return list<array{batch_id: int, quantity: float, line_total: float}>
     */
    private function splitCartItemByBatches(array $cartItem, array $consumed): array
    {
        if ($consumed === []) {
            throw new \RuntimeException('Aucun lot consommé pour '.($cartItem['item_name'] ?? 'article').'.');
        }

        $factor = max(0.0001, (float) ($cartItem['conversion_factor'] ?? 1));
        $unitPrice = (float) ($cartItem['unit_price'] ?? 0);
        $targetQty = (float) ($cartItem['quantity'] ?? 0);
        $targetTotal = (float) ($cartItem['line_total'] ?? 0);
        $batchIds = array_keys($consumed);
        $lastBatchId = (int) end($batchIds);

        $splits = [];
        $allocatedQty = 0.0;
        $allocatedTotal = 0.0;

        foreach ($consumed as $batchId => $qtyBase) {
            $batchId = (int) $batchId;
            if ($batchId === $lastBatchId) {
                $qtySell = round($targetQty - $allocatedQty, 3);
                $lineTotal = round($targetTotal - $allocatedTotal, 2);
            } else {
                $qtySell = round(((float) $qtyBase) / $factor, 3);
                $lineTotal = round($qtySell * $unitPrice, 2);
                $allocatedQty += $qtySell;
                $allocatedTotal += $lineTotal;
            }

            $splits[] = [
                'batch_id' => $batchId,
                'quantity' => $qtySell,
                'line_total' => $lineTotal,
            ];
        }

        return $splits;
    }

    private function applyPrescriptionDispensation(Sale $sale): void
    {
        $rxApi = $this->prescriptionsApi();
        if (! $rxApi || ! $sale->prescription_id) {
            return;
        }

        $cartForRx = [];
        foreach ($this->cart as $cartItem) {
            $cartForRx[] = [
                'item_id' => (int) ($cartItem['item_id'] ?? 0),
                'quantity' => $this->quantityInBaseUnit($cartItem),
                'is_set' => ! empty($cartItem['is_set']),
            ];
        }

        $dispensed = $rxApi->applyDispensationFromSale((int) $sale->prescription_id, $cartForRx);
        if ($dispensed === []) {
            return;
        }

        $remainingByItem = [];
        foreach ($dispensed as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $remainingByItem[$itemId] = ($remainingByItem[$itemId] ?? 0) + (float) ($row['quantity'] ?? 0);
        }

        $sale->loadMissing('lines');
        foreach ($sale->lines as $line) {
            $itemId = (int) $line->item_id;
            if (empty($remainingByItem[$itemId])) {
                continue;
            }
            $lineBase = (float) $line->quantity * (float) ($line->conversion_factor ?? 1);
            $take = min($lineBase, $remainingByItem[$itemId]);
            if ($take <= 0) {
                continue;
            }
            $meta = is_array($line->metadata) ? $line->metadata : [];
            $meta['prescription_id'] = (int) $sale->prescription_id;
            $meta['rx_dispensed_qty'] = round($take, 3);
            $line->metadata = $meta;
            $line->save();
            $remainingByItem[$itemId] -= $take;
        }
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

        if (! $this->validatePrescriptionRequired()) {
            return;
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

        $batchesApi = app()->bound(BatchesApi::class) ? app(BatchesApi::class) : null;
        $batchesAvailable = $batchesApi && $batchesApi->isAvailable();
        $stockService = Schema::connection('tenant')->hasTable('stock_levels')
            && \Illuminate\Support\Facades\App::bound(StockService::class)
            ? app(StockService::class)
            : null;
        $setService = app(ItemSetService::class);

        try {
            $sale = DB::connection('tenant')->transaction(function () use ($data, $saleNumber, $stockService, $setService, $batchesApi, $batchesAvailable) {
                $sale = new Sale();
                $sale->sale_number = $saleNumber;
                $sale->sale_date = now()->toDateString();
                $sale->client_id = $data['client_id'];
                if (Schema::connection('tenant')->hasColumn('sales', 'prescription_id')) {
                    $sale->prescription_id = $this->prescription_id ?: null;
                }
                $sale->subtotal = $this->subtotal;
                $sale->discount_amount = $this->discount;
                $sale->discount_percent = !empty($this->discount_percent) ? (float) $this->discount_percent : null;
                $sale->total = $this->total;
                $sale->created_by = auth('tenant')->id();
                if (Schema::connection('tenant')->hasColumn('sales', 'store_id')) {
                    $sale->store_id = app(StoreContextService::class)->currentStoreId();
                }
                $sale->save();

                $this->createSaleLinesAndDeductStock(
                    $sale,
                    $stockService,
                    $setService,
                    $batchesApi,
                    $batchesAvailable
                );

                $this->applyPrescriptionDispensation($sale);

                return $sale;
            });
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
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
                'rxRequiredNames' => $rxNames = $this->prescriptionRequiredItemNames(),
                'cartNeedsPrescription' => $rxNames !== [],
                'canQuickCreateClient' => $this->canQuickCreateClient(),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
