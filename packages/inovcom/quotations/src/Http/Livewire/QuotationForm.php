<?php

namespace InovCom\Quotations\Http\Livewire;

use App\Support\DocumentLineNumbers;
use App\Support\DocumentMargin;
use App\Support\DocumentTaxCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Item;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Quotations\Models\Quotation;
use InovCom\Quotations\Services\QuotationsService;
use Livewire\Component;

class QuotationForm extends Component
{
    public ?int $quotationId = null;

    public ?int $client_id = null;
    public string $quote_date = '';
    public string $valid_until = '';
    public ?string $notes = null;
    public string $customer_purchase_order = '';
    public string $discount_mode = 'percent';
    public string $discount_percent = '0';
    public string $discount_amount = '0';
    public bool $apply_tax = false;
    /** @var array<int, array{name?:string|null, mode?:string|null, rate?:string|int|float|null, amount?:string|int|float|null, effect?:string|null}> */
    public array $tax_lines = [];

    public string $clientSearch = '';
    public array $clientResults = [];
    /** Données affichées du client choisi (éviter le nom "selectedClient" — conflit hook Livewire). */
    public ?array $clientPicker = null;

    public array $cart = [];
    /** Mode de remise par ligne pour tout le panier : percent|amount */
    public string $lines_discount_mode = 'percent';
    public bool $show_markup_coefficient = true;
    public string $itemSearch = '';
    public array $searchResults = [];

    public function mount(?Quotation $quotation = null): void
    {
        if (!$quotation) {
            $this->quote_date = now()->format('Y-m-d');
            $this->valid_until = now()->addDays(30)->format('Y-m-d');
            $this->tax_lines = [[
                'name' => 'TVA',
                'mode' => 'percent',
                'rate' => (string) QuotationsService::tenantTaxRate(),
                'amount' => '0',
                'effect' => DocumentTaxCalculator::EFFECT_ADD,
            ]];

            $prefillClientId = (int) request()->query('client_id', 0);
            if ($prefillClientId > 0) {
                $client = Client::query()->find($prefillClientId);
                if ($client) {
                    $this->selectClient($client->id);
                }
            }

            return;
        }

        $quotation->loadMissing('taxLines');
        $this->quotationId = $quotation->id;
        $this->client_id = $quotation->client_id;
        $this->quote_date = $quotation->quote_date->format('Y-m-d');
        $this->valid_until = $quotation->valid_until?->format('Y-m-d') ?? '';
        $this->notes = $quotation->notes;
        $this->customer_purchase_order = (string) ($quotation->customer_purchase_order ?? '');
        $storedPercent = (float) $quotation->discount_percent;
        $storedAmount = (float) $quotation->discount_amount;
        $storedMode = Schema::connection('tenant')->hasColumn('quotations', 'discount_mode')
            ? (string) ($quotation->discount_mode ?? 'percent')
            : null;

        if ($storedMode === 'amount' || ($storedMode === null && $storedPercent <= 0 && $storedAmount > 0)) {
            $this->discount_mode = 'amount';
            $this->discount_amount = fmt_num_plain($storedAmount);
            $this->discount_percent = '0';
        } elseif ($storedMode === 'percent' || $storedPercent > 0) {
            $this->discount_mode = 'percent';
            $this->discount_percent = fmt_num_plain($storedPercent > 0 ? $storedPercent : 0);
            $this->discount_amount = '0';
        } else {
            $this->discount_mode = 'percent';
            $this->discount_percent = '0';
            $this->discount_amount = '0';
        }
        $this->apply_tax = (bool) ($quotation->apply_tax ?? false);
        $this->show_markup_coefficient = Schema::connection('tenant')->hasColumn('quotations', 'show_markup_coefficient')
            ? (bool) ($quotation->show_markup_coefficient ?? true)
            : true;
        $this->tax_lines = $quotation->taxLines->count() > 0
            ? $quotation->taxLines->map(fn ($t) => [
                'name' => $t->tax_name,
                'mode' => $t->tax_mode ?? 'amount',
                'rate' => $t->tax_rate !== null ? (string) $t->tax_rate : '',
                'amount' => (string) $t->tax_amount,
                'effect' => DocumentTaxCalculator::normalizeEffect($t->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD),
            ])->toArray()
            : [[
                'name' => 'TVA',
                'mode' => 'percent',
                'rate' => (string) ($quotation->tax_rate ?? QuotationsService::tenantTaxRate()),
                'amount' => (string) $quotation->tax_amount,
                'effect' => DocumentTaxCalculator::EFFECT_ADD,
            ]];
        $this->syncClientPicker($quotation->client);

        foreach ($quotation->lines as $line) {
            $lineMode = 'amount';
            $unitPrice = (float) ($line->unit_price ?? 0);
            $discountAmount = max(0, (float) ($line->line_discount ?? 0));
            $unitPriceNet = $line->unit_price_net !== null
                ? (float) $line->unit_price_net
                : max(0, $unitPrice - $discountAmount);
            $discountAmount = round(max(0, $unitPrice - $unitPriceNet), 2);
            $lineInput = $discountAmount;

            if (Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_mode')) {
                $storedMode = (string) ($line->line_discount_mode ?? 'amount');
                $lineMode = $storedMode === 'percent' ? 'percent' : 'amount';

                if ($lineMode === 'percent' && $unitPrice > 0 && $discountAmount > 0) {
                    // Recalcule depuis le montant FCFA exact pour éviter la dérive d'arrondi.
                    $lineInput = round($discountAmount / $unitPrice * 100, 6);
                } elseif (Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_input')
                    && $line->line_discount_input !== null
                    && $discountAmount <= 0) {
                    $lineInput = (float) $line->line_discount_input;
                }
            }

            $savedPrice = $this->cartUnitPricePlain($line->unit_price);
            $this->cart[] = array_merge($this->enrichCartRow([
                'item_id' => $line->item_id,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'quantity' => (string) $line->quantity,
                'unit_price' => $savedPrice,
                'unit_cost' => $line->unit_cost !== null ? (string) $line->unit_cost : '',
                'markup_coefficient' => $line->markup_coefficient !== null ? (string) $line->markup_coefficient : '',
                'line_discount_mode' => $lineMode,
                'line_discount' => (string) ($lineInput > 0 ? fmt_num_plain($lineInput, 6) : '0'),
                'unit_price_net' => $this->cartUnitPricePlain($unitPriceNet),
                'net_locked' => $discountAmount > 0,
                'line_total' => (string) $line->line_total,
                'line_number' => Schema::connection('tenant')->hasColumn('quotation_lines', 'line_number')
                    ? (int) ($line->line_number ?? 0)
                    : 0,
                'pu_override' => true,
            ]), [
                'unit_price' => $savedPrice,
            ]);
        }

        $this->syncLinesDiscountModeFromCart();
        $this->recalculateCartLines();
    }

    public function updatedClientSearch(): void
    {
        if ($this->clientPicker !== null) {
            return;
        }

        $term = trim($this->clientSearch);
        if (strlen($term) < 2) {
            $this->clientResults = [];
            return;
        }

        $termLower = mb_strtolower($term);
        $like = '%' . $termLower . '%';
        $clients = Client::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(tax_id, \'\')) LIKE ?', [$like]);

                if (Schema::connection('tenant')->hasColumn('clients', 'rccm')) {
                    $q->orWhereRaw('LOWER(COALESCE(rccm, \'\')) LIKE ?', [$like]);
                }
                if (Schema::connection('tenant')->hasColumn('clients', 'niu')) {
                    $q->orWhereRaw('LOWER(COALESCE(niu, \'\')) LIKE ?', [$like]);
                }
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        $this->clientResults = $clients->map(fn (Client $c) => $this->clientToPickerArray($c))->all();

        foreach ($this->clientResults as $row) {
            if (mb_strtolower((string) $row['code']) === $termLower) {
                $this->selectClient((int) $row['id']);
                return;
            }
        }
    }

    public function selectClient(int $id): void
    {
        $client = Client::find($id);
        if (!$client) {
            return;
        }

        $this->client_id = $client->id;
        $this->clientPicker = $this->clientToPickerArray($client);
        $this->clientSearch = '';
        $this->clientResults = [];
        $this->resetValidation('client_id');
    }

    public function clearClient(): void
    {
        $this->client_id = null;
        $this->clientPicker = null;
        $this->clientSearch = '';
        $this->clientResults = [];
    }

    private function syncClientPicker(?Client $client): void
    {
        if (!$client) {
            $this->clientPicker = null;
            return;
        }

        $this->clientPicker = $this->clientToPickerArray($client);
    }

    private function clientToPickerArray(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'code' => $client->code,
            'type' => $client->type,
            'type_label' => $client->type === 'company' ? 'Entreprise' : 'Particulier',
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
        ];
    }

    public function updatedItemSearch(): void
    {
        $term = trim($this->itemSearch);
        if (strlen($term) < 2) {
            $this->searchResults = [];
            return;
        }

        $termLower = mb_strtolower($term);
        $like = '%' . $termLower . '%';

        $items = Item::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        $this->searchResults = $items->map(fn (Item $item) => $this->itemToPickerArray($item))->all();

        $stock = $this->stockLevelsFor(array_map(fn ($r) => (int) $r['id'], $this->searchResults));
        foreach ($this->searchResults as $i => $row) {
            $info = $stock[(int) $row['id']] ?? ['qty' => 0.0, 'status' => 'na', 'label' => 'Non suivi'];
            $this->searchResults[$i]['stock_qty'] = $info['qty'];
            $this->searchResults[$i]['stock_status'] = $info['status'];
            $this->searchResults[$i]['stock_label'] = $info['label'];
        }

        foreach ($this->searchResults as $row) {
            $skuMatch = !empty($row['sku']) && mb_strtolower((string) $row['sku']) === $termLower;
            $barcodeMatch = !empty($row['barcode']) && mb_strtolower((string) $row['barcode']) === $termLower;
            if ($skuMatch || $barcodeMatch) {
                $this->addItemToCart((int) $row['id']);
                return;
            }
        }
    }

    public function addItemToCart(int $itemId): void
    {
        $item = Item::query()->where('is_active', true)->find($itemId);
        if (!$item) {
            return;
        }

        $pricing = $this->linePricingFromItem($item);

        foreach ($this->cart as $index => $cartItem) {
            if ((int) ($cartItem['item_id'] ?? 0) === (int) $item->id) {
                $qty = (float) ($cartItem['quantity'] ?? 0) + 1;
                $this->cart[$index]['quantity'] = (string) $qty;
                $this->recalculateCartLine($index);
                $this->clearItemSearch();
                return;
            }
        }

        $this->cart[] = array_merge([
            'item_id' => $item->id,
            'item_name' => $item->name,
            'item_sku' => $item->sku,
            'quantity' => '1',
            'line_discount_mode' => $this->lines_discount_mode === 'amount' ? 'amount' : 'percent',
            'line_discount' => '0',
            'line_number' => DocumentLineNumbers::nextNumber(array_column($this->cart, 'line_number')),
            'pu_override' => false,
        ], $pricing, [
            'unit_price_net' => $pricing['unit_price'] !== '' ? $pricing['unit_price'] : '',
            'line_total' => $pricing['unit_price'] !== '' ? $pricing['unit_price'] : '',
        ]);
        $this->resetValidation('cart');
        $this->clearItemSearch();
    }

    private function clearItemSearch(): void
    {
        $this->itemSearch = '';
        $this->searchResults = [];
    }

    private function itemToPickerArray(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'price' => (string) ($item->price ?? 0),
        ];
    }

    /**
     * Récupère le stock disponible et le statut pour un ensemble d'articles (magasin courant).
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, array{qty: float, reorder: ?float, status: string, label: string, tracked: bool}>
     */
    private function stockLevelsFor(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($itemIds === [] || !Schema::connection('tenant')->hasTable('stock_levels')) {
            return [];
        }

        $query = \InovCom\Stock\Models\StockLevel::query()->whereIn('item_id', $itemIds);

        if (Schema::connection('tenant')->hasColumn('stock_levels', 'store_id')) {
            $storeId = app(\App\Services\StoreContextService::class)->currentStoreId();
            if ($storeId) {
                $query->where('store_id', $storeId);
            }
        }

        $rows = $query->get()->keyBy('item_id');

        $result = [];
        foreach ($itemIds as $id) {
            $level = $rows->get($id);
            if (!$level) {
                $result[$id] = ['qty' => 0.0, 'reorder' => null, 'status' => 'na', 'label' => 'Non suivi', 'tracked' => false];
                continue;
            }

            $qty = (float) $level->available_quantity;
            $reorder = $level->reorder_point !== null ? (float) $level->reorder_point : null;
            $status = $this->stockStatus($qty, $reorder);
            $result[$id] = [
                'qty' => $qty,
                'reorder' => $reorder,
                'status' => $status['status'],
                'label' => $status['label'],
                'tracked' => true,
            ];
        }

        return $result;
    }

    /**
     * @return array{status: string, label: string}
     */
    private function stockStatus(float $qty, ?float $reorder): array
    {
        if ($qty <= 0) {
            return ['status' => 'out', 'label' => 'Rupture'];
        }
        if ($reorder !== null && $qty <= $reorder) {
            return ['status' => 'low', 'label' => 'Stock faible'];
        }

        return ['status' => 'in', 'label' => 'En stock'];
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function updatedShowMarkupCoefficient(): void
    {
        if ($this->show_markup_coefficient) {
            foreach ($this->cart as $i => $row) {
                $this->cart[$i] = $this->enrichCartRow($row);
            }
        }

        $this->recalculateCartLines();
    }

    public function updatedCart($value, $key = null): void
    {
        if (is_string($key)) {
            if (preg_match('/^(\d+)\.(unit_cost|markup_coefficient)$/', $key, $matches)) {
                $index = (int) $matches[1];
                if (isset($this->cart[$index])) {
                    $this->cart[$index]['pu_override'] = false;
                    unset($this->cart[$index]['net_locked']);
                }
            } elseif (preg_match('/^(\d+)\.unit_price$/', $key, $matches)) {
                $index = (int) $matches[1];
                if (isset($this->cart[$index])) {
                    $this->cart[$index]['pu_override'] = true;
                    unset($this->cart[$index]['net_locked']);
                }
            } elseif (preg_match('/^(\d+)\.line_discount$/', $key, $matches)) {
                $index = (int) $matches[1];
                if (isset($this->cart[$index])) {
                    unset($this->cart[$index]['net_locked']);
                }
            } elseif (preg_match('/^(\d+)\.unit_price_net$/', $key, $matches)) {
                $this->applyDiscountFromUnitPriceNet((int) $matches[1]);
            }
        }

        $this->recalculateCartLines();
    }

    public function setLinesDiscountMode(string $mode): void
    {
        $mode = $mode === 'amount' ? 'amount' : 'percent';
        if ($this->lines_discount_mode === $mode) {
            return;
        }

        $previous = $this->lines_discount_mode;
        $this->lines_discount_mode = $mode;

        foreach ($this->cart as $index => $row) {
            $this->cart[$index]['line_discount'] = $this->convertLineDiscountInput(
                (float) ($row['unit_price'] ?? 0),
                max(0, (float) ($row['line_discount'] ?? 0)),
                $previous,
                $mode
            );
            $this->cart[$index]['line_discount_mode'] = $mode;
            unset($this->cart[$index]['net_locked']);
        }

        $this->recalculateCartLines();
    }

    /**
     * Aligne le switch global sur le mode des lignes chargées (édition).
     */
    private function syncLinesDiscountModeFromCart(): void
    {
        if ($this->cart === []) {
            $this->lines_discount_mode = 'percent';

            return;
        }

        $mode = 'percent';
        foreach ($this->cart as $row) {
            if (max(0, (float) ($row['line_discount'] ?? 0)) <= 0) {
                continue;
            }

            $mode = ($row['line_discount_mode'] ?? 'percent') === 'amount' ? 'amount' : 'percent';
            break;
        }

        $this->lines_discount_mode = $mode;

        foreach ($this->cart as $index => $row) {
            $this->cart[$index]['line_discount_mode'] = $mode;
        }
    }

    private function convertLineDiscountInput(float $unitPrice, float $input, string $fromMode, string $toMode): string
    {
        if ($input <= 0 || $fromMode === $toMode) {
            return $input > 0 ? (string) fmt_num_plain($input) : '0';
        }

        if ($toMode === 'percent') {
            if ($unitPrice <= 0) {
                return '0';
            }

            return (string) fmt_num_plain(min(100, round($input / $unitPrice * 100, 4)));
        }

        $percent = min(100, $input);

        return (string) fmt_num_plain(round($unitPrice * ($percent / 100), 2));
    }

    private function recalculateCartLines(): void
    {
        foreach (array_keys($this->cart) as $i) {
            $this->recalculateCartLine((int) $i);
        }
    }

    private function recalculateCartLine(int $index): void
    {
        if (!isset($this->cart[$index])) {
            return;
        }

        $row = $this->cart[$index];
        if ($this->show_markup_coefficient && !($row['pu_override'] ?? false)) {
            $this->cart[$index] = $this->applyMarkupUnitPrice($row);
        }

        $qty = max(0, (float) ($this->cart[$index]['quantity'] ?? 0));
        $price = max(0, (float) ($this->cart[$index]['unit_price'] ?? 0));
        $this->cart[$index]['line_discount_mode'] = $this->lines_discount_mode === 'amount' ? 'amount' : 'percent';

        if (!empty($this->cart[$index]['net_locked'])) {
            $puNet = max(0, min($price, (float) ($this->cart[$index]['unit_price_net'] ?? $price)));
            $this->cart[$index]['unit_price_net'] = $this->cartUnitPricePlain($puNet);
        } else {
            $discountAmount = $this->resolveLineDiscountAmount($price, $this->cart[$index]);
            $puNet = max(0, $price - $discountAmount);
            $this->cart[$index]['unit_price_net'] = $this->cartUnitPricePlain($puNet);
        }

        $this->cart[$index]['line_total'] = (string) round($qty * $puNet, 2);
        $this->cart[$index]['unit_price'] = $this->cartUnitPricePlain($this->cart[$index]['unit_price'] ?? 0);
    }

    /**
     * À partir d'un P.U. net saisi (souvent un prix « rond »), recalcule la remise
     * dans le mode courant (% ou FCFA).
     */
    private function applyDiscountFromUnitPriceNet(int $index): void
    {
        if (!isset($this->cart[$index])) {
            return;
        }

        $price = max(0, (float) ($this->cart[$index]['unit_price'] ?? 0));
        $net = max(0, (float) ($this->cart[$index]['unit_price_net'] ?? 0));
        if ($net > $price) {
            $net = $price;
        }

        $amount = round($price - $net, 2);
        $mode = $this->lines_discount_mode === 'amount' ? 'amount' : 'percent';
        $this->cart[$index]['line_discount_mode'] = $mode;
        $this->cart[$index]['net_locked'] = true;

        if ($amount <= 0) {
            $this->cart[$index]['line_discount'] = '0';
            $this->cart[$index]['unit_price_net'] = $this->cartUnitPricePlain($price);

            return;
        }

        if ($mode === 'percent') {
            $this->cart[$index]['line_discount'] = $price > 0
                ? (string) fmt_num_plain(min(100, round($amount / $price * 100, 6)), 6)
                : '0';
        } else {
            $this->cart[$index]['line_discount'] = (string) fmt_num_plain($amount);
        }

        $this->cart[$index]['unit_price_net'] = $this->cartUnitPricePlain($net);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveLineDiscountAmount(float $unitPrice, array $row): float
    {
        $input = max(0, (float) ($row['line_discount'] ?? 0));
        $mode = ($row['line_discount_mode'] ?? $this->lines_discount_mode) === 'percent' ? 'percent' : 'amount';

        if ($mode === 'percent') {
            $percent = min(100, $input);

            return min($unitPrice, round($unitPrice * ($percent / 100), 2));
        }

        return min($unitPrice, $input);
    }

    public function addTaxLine(): void
    {
        if (count($this->tax_lines) >= 12) {
            return;
        }
        $this->tax_lines[] = [
            'name' => '',
            'mode' => 'percent',
            'rate' => '',
            'amount' => '0',
            'effect' => DocumentTaxCalculator::EFFECT_ADD,
        ];
    }

    public function removeTaxLine(int $index): void
    {
        unset($this->tax_lines[$index]);
        $this->tax_lines = array_values($this->tax_lines);
    }

    /**
     * @return array{lines: array<int, array{tax_name:string,tax_mode:string,tax_rate:?float,tax_amount:float,tax_effect:string}>, amount: float, ttc: float, total: float}
     */
    private function normalizeTaxLines(float $netHt): array
    {
        $computed = DocumentTaxCalculator::summarize($netHt, $this->tax_lines);

        foreach ($computed['lines'] as $i => $line) {
            if (!isset($this->tax_lines[$i])) {
                continue;
            }
            if (($this->tax_lines[$i]['mode'] ?? 'amount') === 'percent') {
                $this->tax_lines[$i]['amount'] = (string) $line['tax_amount'];
            }
        }

        return [
            'lines' => $computed['lines'],
            'amount' => $computed['tax_amount'],
            'ttc' => $computed['ttc'],
            'total' => $computed['total'],
        ];
    }

    public function updatedDiscountMode(): void
    {
        if ($this->discount_mode === 'percent') {
            $this->discount_amount = '0';
            $this->resetValidation('discount_amount');
        } else {
            $this->discount_percent = '0';
            $this->resetValidation('discount_percent');
        }
    }

    /**
     * @return array{percent: float, amount: float}
     */
    private function resolveDocumentDiscount(float $subtotal): array
    {
        if ($this->discount_mode === 'amount') {
            $amount = min(max(0, (float) $this->discount_amount), max(0, $subtotal));

            return ['percent' => 0.0, 'amount' => round($amount, 2)];
        }

        $percent = max(0, min(100, (float) $this->discount_percent));
        $amount = $percent > 0 ? round($subtotal * ($percent / 100), 2) : 0.0;

        return ['percent' => $percent, 'amount' => $amount];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQuotationForm(): array
    {
        return $this->withValidator(function ($validator) {
            $validator->after(function ($v) {
                if (count($this->cart) === 0) {
                    $v->errors()->add('cart', 'Ajoutez au moins une ligne au devis.');
                }

                foreach ($this->cart as $index => $row) {
                    $qty = (float) ($row['quantity'] ?? 0);
                    if ($qty <= 0) {
                        $v->errors()->add("cart.{$index}.quantity", 'La quantité doit être supérieure à 0 (ligne ' . ($index + 1) . ').');
                    }

                    $price = (float) ($row['unit_price'] ?? 0);
                    if ($price < 0) {
                        $v->errors()->add("cart.{$index}.unit_price", 'Le prix unitaire ne peut pas être négatif (ligne ' . ($index + 1) . ').');
                    }
                }
            });
        })->validate([
            'client_id' => 'required|exists:tenant.clients,id',
            'quote_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:quote_date',
            'notes' => 'nullable|string|max:2000',
            'customer_purchase_order' => 'nullable|string|max:120',
            'discount_mode' => 'required|in:percent,amount',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'show_markup_coefficient' => 'boolean',
            'tax_lines' => 'array',
            'tax_lines.*.name' => 'nullable|string|max:100',
            'tax_lines.*.mode' => 'nullable|in:percent,amount',
            'tax_lines.*.rate' => 'nullable|numeric|min:0|max:1000',
            'tax_lines.*.amount' => 'nullable|numeric|min:0',
        ], $this->validationMessages(), $this->validationAttributes());
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'client_id.required' => 'Veuillez sélectionner un client.',
            'client_id.exists' => 'Le client sélectionné est invalide.',
            'quote_date.required' => 'La date du devis est obligatoire.',
            'quote_date.date' => 'La date du devis n\'est pas valide.',
            'valid_until.date' => 'La date de validité n\'est pas valide.',
            'valid_until.after_or_equal' => 'La date de validité doit être égale ou postérieure à la date du devis.',
            'customer_purchase_order.max' => 'Le n° demande achat ne peut pas dépasser 120 caractères.',
            'notes.max' => 'Les notes ne peuvent pas dépasser 2000 caractères.',
            'discount_percent.numeric' => 'La remise en pourcentage doit être un nombre.',
            'discount_percent.max' => 'La remise ne peut pas dépasser 100 %.',
            'discount_amount.numeric' => 'La remise en montant doit être un nombre.',
            'tax_lines.*.rate.max' => 'Le taux de taxe est trop élevé.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'client_id' => 'client',
            'quote_date' => 'date du devis',
            'valid_until' => 'date de validité',
            'customer_purchase_order' => 'n° demande achat',
            'discount_percent' => 'remise (%)',
            'discount_amount' => 'remise (FCFA)',
        ];
    }

    public function save(): void
    {
        $perm = $this->quotationId ? 'quotations.update' : 'quotations.create';
        if (!$this->can($perm)) {
            $this->addError('form', 'Permission refusée.');
            return;
        }

        $data = $this->validateQuotationForm();

        $subtotal = array_sum(array_map(fn ($r) => (float) ($r['line_total'] ?? 0), $this->cart));
        $discountResolved = $this->resolveDocumentDiscount($subtotal);
        $discountAmount = $discountResolved['amount'];
        $netHt = max(0, $subtotal - $discountAmount);
        $normalizedTax = $this->normalizeTaxLines($netHt);
        $taxRateFallback = 0.0;
        foreach ($normalizedTax['lines'] as $line) {
            if (($line['tax_mode'] ?? 'amount') === 'percent') {
                $taxRateFallback += (float) ($line['tax_rate'] ?? 0);
            }
        }
        $data['tax_lines'] = $normalizedTax['lines'];
        $data['apply_tax'] = $normalizedTax['amount'] > 0;
        $data['tax_rate'] = round($taxRateFallback, 3);

        $data['discount_mode'] = $this->discount_mode;
        $data['discount_percent'] = $discountResolved['percent'];
        $data['discount_amount'] = $discountResolved['amount'];

        $lines = array_map(function ($row) {
            $mode = ($row['line_discount_mode'] ?? $this->lines_discount_mode) === 'percent' ? 'percent' : 'amount';
            $input = max(0, (float) ($row['line_discount'] ?? 0));
            $unitPrice = (float) $row['unit_price'];
            $unitPriceNet = array_key_exists('unit_price_net', $row) && $row['unit_price_net'] !== '' && $row['unit_price_net'] !== null
                ? max(0, min($unitPrice, (float) $row['unit_price_net']))
                : max(0, $unitPrice - $this->resolveLineDiscountAmount($unitPrice, $row));

            $payload = [
                'item_id' => $row['item_id'] ?? null,
                'item_name' => $row['item_name'],
                'item_sku' => $row['item_sku'] ?? null,
                'quantity' => (float) $row['quantity'],
                'unit_price' => $unitPrice,
                'unit_cost' => ($row['unit_cost'] ?? '') !== '' ? (float) $row['unit_cost'] : null,
                'markup_coefficient' => ($row['markup_coefficient'] ?? '') !== '' ? (float) $row['markup_coefficient'] : null,
                'line_discount' => round($unitPrice - $unitPriceNet, 2),
                'line_discount_mode' => $mode,
                'line_discount_input' => $input,
            ];

            if (Schema::connection('tenant')->hasColumn('quotation_lines', 'line_number')) {
                $payload['line_number'] = (int) ($row['line_number'] ?? 0);
            }

            return $payload;
        }, DocumentLineNumbers::assignMissing($this->cart));

        $service = app(QuotationsService::class);

        try {
            if ($this->quotationId) {
                $quotation = Quotation::findOrFail($this->quotationId);
                if (!$quotation->isEditable()) {
                    session()->flash('error', 'Ce devis ne peut plus être modifié.');
                    return;
                }
                $service->update($quotation, $data, $lines);
                session()->flash('success', 'Devis mis à jour.');
            } else {
                $service->create($data, $lines);
                session()->flash('success', 'Devis créé.');
            }

            $this->redirect(route('tenant.quotations.index', ['tenant' => $this->tenantCode()]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function createRevision(): void
    {
        if (!$this->quotationId || !$this->can('quotations.create')) {
            return;
        }

        $source = Quotation::with('lines')->findOrFail($this->quotationId);
        $new = app(QuotationsService::class)->createRevision($source);
        session()->flash('success', 'Nouvelle révision créée : ' . $new->number);
        $this->redirect(route('tenant.quotations.edit', ['tenant' => $this->tenantCode(), 'quotation' => $new->id]), navigate: true);
    }

    public function setStatus(string $status): void
    {
        if (!$this->quotationId || !$this->can('quotations.validate')) {
            $this->notifyActionError('Permission refusée.');
            return;
        }

        $quotation = Quotation::findOrFail($this->quotationId);

        if (!$quotation->isEditable() && $status !== 'accepted') {
            $this->notifyActionError('Ce devis ne peut plus changer de statut.');
            return;
        }

        if ($status === 'accepted') {
            $savedPo = trim((string) ($quotation->customer_purchase_order ?? ''));
            $formPo = trim($this->customer_purchase_order);

            if ($formPo !== '' && $formPo !== $savedPo) {
                $this->notifyActionError(
                    'Le n° demande achat a été modifié mais pas encore enregistré. Cliquez sur « Enregistrer », puis réessayez « Marquer accepté ».',
                    'customer_purchase_order'
                );
                return;
            }

            if ($savedPo === '') {
                $message = $formPo === ''
                    ? 'Le n° demande achat est obligatoire pour accepter le devis. Remplissez-le, enregistrez, puis cliquez sur « Marquer accepté ».'
                    : 'Le n° demande achat n’est pas encore enregistré. Cliquez sur « Enregistrer », puis réessayez « Marquer accepté ».';

                $this->notifyActionError($message, 'customer_purchase_order');
                return;
            }
        }

        try {
            app(QuotationsService::class)->setStatus($quotation, $status);
            session()->flash('success', 'Statut mis à jour : ' . Quotation::statusLabel($status));
            $this->redirect(route('tenant.quotations.edit', [
                'tenant' => $this->tenantCode(),
                'quotation' => $this->quotationId,
            ]), navigate: true);
        } catch (\Throwable $e) {
            $this->notifyActionError($e->getMessage());
        }
    }

    /**
     * Affiche une erreur d’action bien visible (bandeau + scroll vers le champ).
     */
    private function notifyActionError(string $message, ?string $field = null): void
    {
        session()->flash('error', $message);

        if ($field === 'customer_purchase_order') {
            $this->dispatch('focus-document-field', id: 'customer-purchase-order');
        } elseif ($field) {
            $this->dispatch('focus-document-field', id: $field);
        } else {
            $this->dispatch('focus-document-field', id: 'quotation-action-alert');
        }
    }

    public function deleteQuotation(): void
    {
        if (!$this->quotationId || !$this->can('quotations.delete')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $quotation = Quotation::findOrFail($this->quotationId);

        if ($quotation->status !== 'draft') {
            session()->flash('error', 'Seuls les devis en brouillon peuvent être supprimés.');
            return;
        }

        $quotation->lines()->delete();
        $quotation->delete();
        session()->flash('success', 'Devis supprimé.');
        $this->redirect(route('tenant.quotations.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function render()
    {
        $quotation = $this->quotationId
            ? Quotation::with(['client', 'creator', 'validator', 'lines', 'taxLines'])->find($this->quotationId)
            : null;

        $subtotal = array_sum(array_map(fn ($r) => (float) ($r['line_total'] ?? 0), $this->cart));
        $discountResolved = $this->resolveDocumentDiscount($subtotal);
        $discountPct = $discountResolved['percent'];
        $discount = $discountResolved['amount'];
        $netHt = max(0, $subtotal - $discount);
        $tenantTaxRate = QuotationsService::tenantTaxRate();
        $taxComputed = $this->normalizeTaxLines($netHt);
        $taxAmount = $taxComputed['amount'];
        $taxRate = 0.0;
        foreach ($taxComputed['lines'] as $line) {
            if (($line['tax_mode'] ?? 'amount') === 'percent' && ($line['tax_effect'] ?? DocumentTaxCalculator::EFFECT_ADD) === DocumentTaxCalculator::EFFECT_ADD) {
                $taxRate += (float) ($line['tax_rate'] ?? 0);
            }
        }
        $taxRate = round($taxRate, 3);
        $ttc = $taxComputed['ttc'];
        $total = $taxComputed['total'];

        $savedTotals = null;
        $linkedDeliveryNote = null;
        if ($quotation && $quotation->isAccepted() && Schema::connection('tenant')->hasTable('delivery_notes')) {
            $linkedDeliveryNote = DeliveryNote::query()
                ->where('quotation_id', $quotation->id)
                ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->first(['id', 'delivery_number', 'status']);
        }

        if ($quotation && !$quotation->isEditable()) {
            $savedTotals = [
                'subtotal' => (float) $quotation->subtotal,
                'discount_percent' => (float) $quotation->discount_percent,
                'discount_amount' => (float) $quotation->discount_amount,
                'net_ht' => max(0, (float) $quotation->subtotal - (float) $quotation->discount_amount),
                'apply_tax' => (bool) $quotation->apply_tax,
                'tax_rate' => (float) $quotation->tax_rate,
                'tax_amount' => (float) $quotation->tax_amount,
                'total' => (float) $quotation->total,
                'tax_lines' => $quotation->taxLines->map(fn ($t) => [
                    'tax_name' => (string) $t->tax_name,
                    'tax_mode' => (string) ($t->tax_mode ?: 'amount'),
                    'tax_rate' => $t->tax_rate !== null ? (float) $t->tax_rate : null,
                    'tax_amount' => (float) $t->tax_amount,
                    'tax_effect' => DocumentTaxCalculator::normalizeEffect($t->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD),
                ])->toArray(),
            ];
        }

        $cartStock = $this->stockLevelsFor(array_map(fn ($r) => (int) ($r['item_id'] ?? 0), $this->cart));

        $revenueHt = (float) ($savedTotals['net_ht'] ?? $netHt);
        $marginSummary = $this->buildMarginSummary($revenueHt);

        return view('inovcom-quotations::livewire.quotations.form')
            ->layout('layouts.app', [
                'title' => $this->quotationId ? 'Devis ' . ($quotation->number ?? '') : 'Nouveau devis',
                'subtitle' => 'Gestion des devis',
            ])
            ->with([
                'quotation' => $quotation,
                'cartStock' => $cartStock,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'discountPct' => $discountPct,
                'discountMode' => $this->discount_mode,
                'netHt' => $netHt,
                'taxRate' => $taxRate,
                'tenantTaxRate' => $tenantTaxRate,
                'taxAmount' => $taxAmount,
                'taxLinesComputed' => $taxComputed['lines'],
                'ttc' => $ttc,
                'total' => $total,
                'savedTotals' => $savedTotals,
                'marginSummary' => $marginSummary,
                'canEdit' => !$quotation || $quotation->isEditable(),
                'canValidate' => $this->can('quotations.validate'),
                'canDelete' => $this->can('quotations.delete'),
                'canCreate' => $this->can('quotations.create'),
                'linkedDeliveryNote' => $linkedDeliveryNote,
            ]);
    }

    /**
     * @return array{total_cost: float, margin: float, margin_percent: ?float, revenue_ht: float}
     */
    private function buildMarginSummary(float $revenueHt): array
    {
        $itemIds = collect($this->cart)
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $costs = $itemIds === []
            ? []
            : Item::query()->whereIn('id', $itemIds)->pluck('cost', 'id')->all();

        return DocumentMargin::fromCart($this->cart, $revenueHt, $costs);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichCartRow(array $row): array
    {
        $item = !empty($row['item_id']) ? Item::find($row['item_id']) : null;
        $cost = ($row['unit_cost'] ?? '') !== ''
            ? (float) $row['unit_cost']
            : (float) ($item->cost ?? 0);
        $coef = ($row['markup_coefficient'] ?? '') !== ''
            ? (float) $row['markup_coefficient']
            : 1.0;

        $row['unit_cost'] = fmt_num_plain($cost, 2);
        $row['markup_coefficient'] = fmt_num_plain($coef, 4);

        return $row;
    }

    /**
     * @return array{unit_cost: string, markup_coefficient: string, unit_price: string}
     */
    private function linePricingFromItem(Item $item): array
    {
        $cost = (float) ($item->cost ?? 0);
        $price = (float) ($item->price ?? 0);
        $coef = 1.0;
        $unitPrice = $this->show_markup_coefficient
            ? round($cost * $coef, 2)
            : $price;

        return [
            'unit_cost' => fmt_num_plain($cost, 2),
            'markup_coefficient' => fmt_num_plain($coef, 4),
            'unit_price' => $this->cartUnitPricePlain($unitPrice),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyMarkupUnitPrice(array $row): array
    {
        if (!$this->show_markup_coefficient || ($row['pu_override'] ?? false)) {
            return $row;
        }

        $cost = (float) ($row['unit_cost'] ?? 0);
        $coef = (float) ($row['markup_coefficient'] ?? 0);
        if ($cost > 0 && $coef > 0) {
            $row['unit_price'] = $this->cartUnitPricePlain(round($cost * $coef, 2));
        }

        $row['unit_price'] = $this->cartUnitPricePlain($row['unit_price'] ?? 0);

        return $row;
    }

    private function cartUnitPricePlain(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $n = (float) $value;

        return abs($n) < 1e-9 ? '' : fmt_num_plain($n, 2);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
