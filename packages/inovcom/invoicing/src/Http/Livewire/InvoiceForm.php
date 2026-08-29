<?php

namespace InovCom\Invoicing\Http\Livewire;

use App\Support\DocumentLineNumbers;
use App\Support\DocumentMargin;
use App\Support\DocumentTaxCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Item;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Services\DeliveryNotesService;
use InovCom\Invoicing\Services\InvoiceScheduleService;
use InovCom\Invoicing\Services\InvoicingService;
use Illuminate\Support\Facades\Schema;
use InovCom\Quotations\Models\Quotation;
use Livewire\Component;

class InvoiceForm extends Component
{
    public ?int $invoiceId = null;
    public ?int $deliveryNoteId = null;

    /** BL sélectionné sur la page « nouvelle facture » avant chargement. */
    public ?int $pendingDeliveryNoteId = null;

    public ?int $client_id = null;
    public ?int $quotation_id = null;
    public string $declaration_type = 'non_declared';
    public string $invoice_date = '';
    public string $due_date = '';
    public string $due_days_base = 'today'; // today|next_month_start
    // Non typé volontairement : tolère la saisie vide et les valeurs Carbon (float) sans casser l'hydratation Livewire.
    public $due_days_custom = 0;
    public ?string $notes = null;
    public ?string $customer_reference = null;
    public ?string $quotation_reference = null;
    public ?string $delivery_note_number = null;
    public ?string $additional_info = null;
    public ?string $payment_mode = null;
    public string $discount_mode = 'percent';
    public string $discount_percent = '0';
    public string $discount_amount = '0';
    public string $tax_amount = '0'; // somme des tax_lines (pour compatibilité et totaux)
    /** @var array<int, array{name?:string|null, mode?:string|null, rate?:string|int|float|null, amount?:string|int|float|null, effect?:string|null}> */
    public array $tax_lines = [];

    /** Paiement échelonné (optionnel, factures émises). */
    public int $schedule_months = 3;
    public string $schedule_first_due = '';
    public bool $schedule_replace = false;

    public string $clientSearch = '';
    public array $clientResults = [];
    public ?array $clientPicker = null;
    public array $cart = [];
    /** Mode de remise par ligne pour tout le panier : percent|amount */
    public string $lines_discount_mode = 'percent';
    public string $itemSearch = '';
    public array $searchResults = [];

    public function mount(?Invoice $invoice = null): void
    {
        $quotationId = request()->query('quotation_id');
        $deliveryNoteId = request()->query('delivery_note');

        if ($invoice) {
            $this->loadInvoice($invoice);
            return;
        }

        $this->invoice_date = now()->format('Y-m-d');
        $this->due_date = now()->format('Y-m-d');
        $this->due_days_base = 'today';
        $this->due_days_custom = 0;
        $this->tax_lines = [
            ['name' => 'TVA', 'mode' => 'percent', 'rate' => '', 'amount' => '0', 'effect' => DocumentTaxCalculator::EFFECT_ADD],
        ];

        if ($deliveryNoteId) {
            $this->loadFromDeliveryNote((int) $deliveryNoteId);
        } elseif ($quotationId) {
            $this->loadFromQuotation((int) $quotationId);
        }
    }

    private function loadInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing(['taxLines', 'lines', 'quotation.lines']);

        $this->invoiceId = $invoice->id;
        $this->client_id = $invoice->client_id;
        $this->quotation_id = $invoice->quotation_id;
        $this->declaration_type = $invoice->declaration_type;
        $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
        $this->due_date = $invoice->due_date?->format('Y-m-d') ?? '';
        $this->applyDueDaysBaseFromDueDate();
        $this->notes = $invoice->notes;
        $this->customer_reference = $invoice->customer_reference;
        $this->quotation_reference = $invoice->quotation_reference;
        $this->delivery_note_number = $invoice->delivery_note_number;
        $this->additional_info = $invoice->additional_info;
        $this->payment_mode = $invoice->payment_mode;
        $this->applyDocumentDiscountFromModel($invoice, $invoice->quotation);

        $this->schedule_first_due = $invoice->due_date?->format('Y-m-d')
            ?? now()->addMonthNoOverflow()->startOfMonth()->format('Y-m-d');
        $this->schedule_months = 3;
        $this->schedule_replace = false;

        $this->tax_amount = (string) $invoice->tax_amount;

        $this->tax_lines = $invoice->taxLines->count() > 0
            ? $invoice->taxLines->map(fn ($t) => [
                'name' => $t->tax_name,
                'mode' => ($t->tax_mode ?? (($t->tax_rate !== null && (float) $t->tax_rate > 0) ? 'percent' : 'amount')),
                'rate' => $t->tax_rate !== null ? (string) $t->tax_rate : '',
                'amount' => (string) $t->tax_amount,
                'effect' => DocumentTaxCalculator::normalizeEffect($t->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD),
            ])->toArray()
            : [['name' => 'TVA', 'mode' => 'percent', 'rate' => '', 'amount' => (string) $invoice->tax_amount, 'effect' => DocumentTaxCalculator::EFFECT_ADD]];

        $this->syncClientPicker($invoice->client);

        $quotationLinesByItem = $invoice->quotation
            ? $invoice->quotation->lines->keyBy(fn ($line) => (int) ($line->item_id ?? 0))
            : collect();

        foreach ($invoice->lines as $line) {
            $quotationLine = $quotationLinesByItem->get((int) ($line->item_id ?? 0));
            $discountFields = $this->mapLineDiscountFromSource($line, $quotationLine);
            $this->cart[] = [
                'item_id' => $line->item_id,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'quantity' => (string) $line->quantity,
                'unit_price' => $this->cartUnitPricePlain($line->unit_price),
                'unit_cost' => $line->unit_cost !== null
                    ? (string) $line->unit_cost
                    : ($quotationLine?->unit_cost !== null ? (string) $quotationLine->unit_cost : ''),
                'line_discount_mode' => $discountFields['line_discount_mode'],
                'line_discount' => $discountFields['line_discount'],
                'unit_price_net' => $discountFields['unit_price_net'],
                'net_locked' => $discountFields['net_locked'],
                'line_total' => (string) $line->line_total,
                'line_number' => Schema::connection('tenant')->hasColumn('invoice_lines', 'line_number')
                    ? (int) ($line->line_number ?? 0)
                    : 0,
            ];
        }

        $this->syncLinesDiscountModeFromCart();
        $this->updatedCart();
    }

    private function loadFromQuotation(int $quotationId): void
    {
        $quotation = Quotation::with(['client', 'lines'])->findOrFail($quotationId);

        if (!$quotation->canCreateInvoice()) {
            session()->flash('error', 'Ce devis doit être accepté par le client avant facturation.');
            return;
        }

        $existing = Invoice::openForQuotation($quotation->id);
        if ($existing) {
            $this->redirectToExistingInvoice(
                $existing,
                'Ce devis a déjà une facture ('.$existing->invoice_number.'). Complétez les livraisons depuis cette facture.'
            );
            return;
        }

        $this->quotation_id = $quotation->id;
        $this->client_id = $quotation->client_id;
        $this->syncClientPicker($quotation->client);
        $this->due_date = $quotation->valid_until?->format('Y-m-d') ?? $this->due_date;
        $this->applyDueDaysBaseFromDueDate();
        $this->notes = $quotation->notes;
        $this->quotation_reference = $quotation->number ?? null;
        $this->customer_reference = $quotation->customer_purchase_order;
        $this->applyDocumentDiscountFromModel($quotation);

        $quotation->loadMissing('taxLines');
        if ($quotation->taxLines->count() > 0) {
            $this->tax_lines = $quotation->taxLines
                ->map(fn ($t) => [
                    'name' => (string) $t->tax_name,
                    'mode' => ($t->tax_mode ?? 'amount') === 'percent' ? 'percent' : 'amount',
                    'rate' => $t->tax_rate !== null ? (string) $t->tax_rate : '',
                    'amount' => (string) $t->tax_amount,
                    'effect' => DocumentTaxCalculator::normalizeEffect($t->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD),
                ])
                ->toArray();
            $this->tax_amount = (string) $quotation->tax_amount;
        } elseif ($quotation->apply_tax ?? false) {
            $this->tax_lines = [
                ['name' => 'TVA', 'mode' => 'percent', 'rate' => (string) ($quotation->tax_rate ?? ''), 'amount' => (string) $quotation->tax_amount, 'effect' => DocumentTaxCalculator::EFFECT_ADD],
            ];
            $this->tax_amount = (string) $quotation->tax_amount;
        } else {
            $this->tax_lines = [
                ['name' => 'TVA', 'mode' => 'percent', 'rate' => '', 'amount' => '0', 'effect' => DocumentTaxCalculator::EFFECT_ADD],
            ];
            $this->tax_amount = '0';
        }

        $this->cart = $quotation->lines->map(function ($line) {
            $discountFields = $this->mapLineDiscountFromSource($line);

            return [
                'item_id' => $line->item_id,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'quantity' => (string) $line->quantity,
                'unit_price' => $this->cartUnitPricePlain($line->unit_price),
                'unit_cost' => $line->unit_cost !== null ? (string) $line->unit_cost : '',
                'line_discount_mode' => $discountFields['line_discount_mode'],
                'line_discount' => $discountFields['line_discount'],
                'unit_price_net' => $discountFields['unit_price_net'],
                'net_locked' => $discountFields['net_locked'],
                'line_total' => (string) $line->line_total,
                'line_number' => Schema::connection('tenant')->hasColumn('quotation_lines', 'line_number')
                    ? (int) ($line->line_number ?? 0)
                    : 0,
            ];
        })->toArray();

        $this->syncLinesDiscountModeFromCart();
        $this->updatedCart();
    }

    private function loadFromDeliveryNote(int $deliveryNoteId): void
    {
        $note = \InovCom\Invoicing\Models\DeliveryNote::with([
            'lines',
            'client',
            'quotation.lines',
            'quotation.taxLines',
            'invoice',
        ])->findOrFail($deliveryNoteId);

        if (!$note->isConfirmed()) {
            session()->flash('error', 'Le bon de livraison doit être validé avant facturation.');
            return;
        }
        if ($note->invoice_id && $note->invoice) {
            $this->redirectToExistingInvoice(
                $note->invoice,
                'Ce bon de livraison est déjà rattaché à la facture '.$note->invoice->invoice_number.'.'
            );
            return;
        }
        if (!$note->quotation_id) {
            session()->flash('error', 'Seuls les bons de livraison issus d\'un devis peuvent être facturés ici.');
            return;
        }

        $existing = Invoice::openForQuotation($note->quotation_id);
        if ($existing) {
            $this->redirectToExistingInvoice(
                $existing,
                'Ce devis a déjà une facture ('.$existing->invoice_number.'). Les livraisons s’y rattachent — complétez-les depuis la facture.'
            );
            return;
        }

        $this->deliveryNoteId = $note->id;
        $this->delivery_note_number = $note->delivery_number;
        $this->loadFromQuotation((int) $note->quotation_id);
    }

    private function redirectToExistingInvoice(Invoice $invoice, string $message): void
    {
        session()->flash('success', $message);
        $this->redirect(route('tenant.invoicing.edit', [
            $invoice->id,
            'tenant' => request()->query('tenant'),
        ]), navigate: true);
    }

    public function loadDeliveryNote(): void
    {
        if (!$this->pendingDeliveryNoteId) {
            session()->flash('error', 'Sélectionnez un bon de livraison validé.');
            return;
        }

        $this->loadFromDeliveryNote((int) $this->pendingDeliveryNoteId);
        $this->pendingDeliveryNoteId = null;
    }

    public function clearDeliveryNoteSource(): void
    {
        if ($this->invoiceId) {
            return;
        }

        $this->deliveryNoteId = null;
        $this->delivery_note_number = null;
        $this->quotation_id = null;
        $this->quotation_reference = null;
        $this->cart = [];
        $this->client_id = null;
        $this->clientPicker = null;
        $this->clientSearch = '';
        session()->flash('success', 'Bon de livraison détaché — saisie manuelle.');
    }

    public function updatedClientSearch(): void
    {
        if ($this->clientPicker !== null || $this->quotation_id) {
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
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like]);
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
    }

    public function clearClient(): void
    {
        if ($this->quotation_id) {
            return;
        }
        $this->client_id = null;
        $this->clientPicker = null;
        $this->clientSearch = '';
        $this->clientResults = [];
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

        $this->searchResults = $items->map(fn (Item $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'price' => (string) ($item->price ?? 0),
        ])->all();

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

        $price = $this->cartUnitPricePlain($item->price ?? 0);

        foreach ($this->cart as $index => $cartItem) {
            if ((int) ($cartItem['item_id'] ?? 0) === (int) $item->id) {
                $qty = (float) ($cartItem['quantity'] ?? 0) + 1;
                $price = (float) ($cartItem['unit_price'] ?? 0);
                $discount = $this->resolveLineDiscountAmount($price, $cartItem);
                $puNet = max(0, $price - $discount);
                $this->cart[$index]['quantity'] = (string) $qty;
                $this->cart[$index]['line_total'] = (string) round($qty * $puNet, 2);
                $this->clearItemSearch();
                return;
            }
        }

        $this->cart[] = [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'item_sku' => $item->sku,
            'quantity' => '1',
            'unit_price' => $price,
            'unit_cost' => (string) ((float) ($item->cost ?? 0)),
            'line_discount_mode' => $this->lines_discount_mode === 'amount' ? 'amount' : 'percent',
            'line_discount' => '0',
            'unit_price_net' => $price !== '' ? $price : '',
            'line_total' => $price !== '' ? $price : '',
            'line_number' => DocumentLineNumbers::nextNumber(array_column($this->cart, 'line_number')),
        ];
        $this->clearItemSearch();
    }

    private function clearItemSearch(): void
    {
        $this->itemSearch = '';
        $this->searchResults = [];
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
            'type_label' => $client->type === 'company' ? 'Entreprise' : 'Particulier',
            'phone' => $client->phone,
            'email' => $client->email,
        ];
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
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
        $this->updatedTaxLines();
    }

    public function updatedDiscountPercent(): void
    {
        $this->updatedTaxLines();
    }

    public function updatedDiscountAmount(): void
    {
        $this->updatedTaxLines();
    }

    public function updatedCart($value = null, $key = null): void
    {
        if (is_string($key)) {
            if (preg_match('/^(\d+)\.unit_price_net$/', $key, $matches)) {
                $this->applyDiscountFromUnitPriceNet((int) $matches[1]);
            } elseif (preg_match('/^(\d+)\.(unit_price|line_discount)$/', $key, $matches)) {
                unset($this->cart[(int) $matches[1]]['net_locked']);
            }
        }

        foreach ($this->cart as $i => $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $price = max(0, (float) ($row['unit_price'] ?? 0));
            $this->cart[$i]['line_discount_mode'] = $this->lines_discount_mode === 'amount' ? 'amount' : 'percent';

            if (!empty($this->cart[$i]['net_locked'])) {
                $puNet = max(0, min($price, (float) ($this->cart[$i]['unit_price_net'] ?? $price)));
                $this->cart[$i]['unit_price_net'] = $this->cartUnitPricePlain($puNet);
            } else {
                $discount = $this->resolveLineDiscountAmount($price, $this->cart[$i]);
                $puNet = max(0, $price - $discount);
                $this->cart[$i]['unit_price_net'] = $this->cartUnitPricePlain($puNet);
            }

            $this->cart[$i]['line_total'] = (string) round($qty * $puNet, 2);
            $this->cart[$i]['unit_price'] = $this->cartUnitPricePlain($this->cart[$i]['unit_price'] ?? 0);
        }
        $this->updatedTaxLines();
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

        $this->updatedCart();
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

    public function updatedTaxLines(): void
    {
        $subtotal = array_sum(array_map(fn ($r) => (float) ($r['line_total'] ?? 0), $this->cart));
        $discountResolved = $this->resolveDocumentDiscount($subtotal);
        $netHt = max(0, $subtotal - $discountResolved['amount']);
        $computed = $this->normalizedTaxLines($netHt);
        $this->tax_amount = (string) $computed['tax_amount'];

        foreach ($computed['taxLines'] as $i => $line) {
            if (!isset($this->tax_lines[$i])) {
                continue;
            }
            if (($this->tax_lines[$i]['mode'] ?? 'amount') === 'percent') {
                $this->tax_lines[$i]['amount'] = (string) $line['tax_amount'];
            }
        }
    }

    public function addTaxLine(): void
    {
        if (count($this->tax_lines) >= 12) {
            return;
        }

        $this->tax_lines[] = ['name' => '', 'mode' => 'percent', 'rate' => '', 'amount' => '0', 'effect' => DocumentTaxCalculator::EFFECT_ADD];
    }

    public function removeTaxLine(int $index): void
    {
        unset($this->tax_lines[$index]);
        $this->tax_lines = array_values($this->tax_lines);
        $this->updatedTaxLines();
    }

    public function setDueDateFromDays(int $days): void
    {
        $days = max(0, (int) $days);
        $this->due_days_custom = $days;

        $base = $this->due_days_base === 'next_month_start'
            ? now()->addMonthNoOverflow()->startOfMonth()
            : now()->startOfDay();

        $this->due_date = $base->copy()->addDays($days)->format('Y-m-d');
    }

    public function applyCustomDueDays(): void
    {
        $this->setDueDateFromDays((int) $this->due_days_custom);
    }

    public function updatedDueDaysCustom(): void
    {
        // Permet un calcul immédiat quand l'utilisateur modifie la valeur personnalisée.
        $this->setDueDateFromDays((int) $this->due_days_custom);
    }

    public function updatedDueDaysBase(): void
    {
        // Recalcule la date lorsque l'utilisateur change la base.
        $this->setDueDateFromDays((int) $this->due_days_custom);
    }

    public function updatedDueDate(): void
    {
        // La saisie manuelle d'une date recalcule l'offset (et donc la surbrillance du raccourci actif).
        $this->applyDueDaysBaseFromDueDate();
    }

    private function applyDueDaysBaseFromDueDate(): void
    {
        if (empty($this->due_date)) {
            $this->due_days_base = 'today';
            $this->due_days_custom = 0;
            return;
        }

        try {
            $due = \Carbon\Carbon::parse($this->due_date)->startOfDay();
        } catch (\Throwable) {
            $this->due_days_base = 'today';
            $this->due_days_custom = 0;
            return;
        }

        $nextMonthStart = now()->addMonthNoOverflow()->startOfMonth();
        if ($due->greaterThanOrEqualTo($nextMonthStart->copy()->startOfDay())) {
            $this->due_days_base = 'next_month_start';
            $this->due_days_custom = (int) round($nextMonthStart->copy()->startOfDay()->diffInDays($due, true));
        } else {
            $this->due_days_base = 'today';
            $this->due_days_custom = (int) round(now()->startOfDay()->diffInDays($due, true));
        }
    }

    /**
     * @return array{taxLines: array<int, array{tax_name:string,tax_mode:string,tax_amount:float,tax_rate:?float,tax_effect:string}>, tax_amount: float, ttc: float, total: float}
     */
    private function normalizedTaxLines(float $netHt): array
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
            'taxLines' => array_map(fn ($line) => [
                'tax_name' => $line['tax_name'],
                'tax_mode' => $line['tax_mode'],
                'tax_amount' => $line['tax_amount'],
                'tax_rate' => $line['tax_rate'],
                'tax_effect' => $line['tax_effect'],
            ], $computed['lines']),
            'tax_amount' => $computed['tax_amount'],
            'ttc' => $computed['ttc'],
            'total' => $computed['total'],
        ];
    }

    public function generateInstallmentSchedule(): void
    {
        if (!$this->can('invoicing.manage_schedule') && !$this->can('invoicing.update')) {
            session()->flash('error', 'Permission refusée pour gérer l\'échéancier.');
            return;
        }

        if (!$this->invoiceId) {
            return;
        }

        $data = $this->validate([
            'schedule_months' => 'required|integer|min:2|max:36',
            'schedule_first_due' => 'required|date',
            'schedule_replace' => 'boolean',
        ], [
            'schedule_months.min' => 'Indiquez au moins 2 mois.',
            'schedule_months.max' => 'Maximum 36 mois.',
            'schedule_first_due.required' => 'Indiquez la date de la première échéance.',
        ]);

        try {
            $invoice = Invoice::findOrFail($this->invoiceId);
            $hasExisting = $invoice->schedules()->exists();
            $replace = (bool) ($data['schedule_replace'] ?? false) || $hasExisting;

            app(InvoiceScheduleService::class)->generateMonthlySchedule(
                $invoice,
                (int) $data['schedule_months'],
                $data['schedule_first_due'],
                $replace
            );

            $this->due_date = $data['schedule_first_due'];
            $this->schedule_replace = false;
            notify()->success('Échéancier mensuel créé. Les relances suivront chaque tranche due.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function clearInstallmentSchedule(): void
    {
        if (!$this->can('invoicing.manage_schedule') && !$this->can('invoicing.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        if (!$this->invoiceId) {
            return;
        }

        try {
            app(InvoiceScheduleService::class)->clearSchedule(Invoice::findOrFail($this->invoiceId));
            notify()->success('Échéancier supprimé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function save(bool $andIssue = false): void
    {
        $perm = $this->invoiceId ? 'invoicing.update' : 'invoicing.create';
        if (!$this->can($perm)) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        if (count($this->cart) === 0) {
            session()->flash('error', 'Ajoutez au moins une ligne à la facture.');
            return;
        }

        $requiresReferences = $this->requiresManualDocumentReferences();

        $data = $this->validate([
            'client_id' => 'required|exists:tenant.clients,id',
            'declaration_type' => 'required|in:declared,non_declared',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string|max:2000',
            'customer_reference' => ($requiresReferences ? 'required' : 'nullable') . '|string|max:100',
            'quotation_reference' => ($requiresReferences ? 'required' : 'nullable') . '|string|max:50',
            'delivery_note_number' => ($requiresReferences ? 'required' : 'nullable') . '|string|max:50',
            'additional_info' => 'nullable|string|max:2000',
            'payment_mode' => ['nullable', 'string', Rule::in(array_keys($this->paymentModeOptionsForForm()))],
            'discount_mode' => 'required|in:percent,amount',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_lines' => 'array',
            'tax_lines.*.name' => 'nullable|string|max:100',
            'tax_lines.*.mode' => 'nullable|in:percent,amount',
            'tax_lines.*.rate' => 'nullable|numeric|min:0|max:1000',
            'tax_lines.*.amount' => 'nullable|numeric|min:0',
            'tax_lines.*.effect' => 'nullable|in:add,subtract',
        ], [
            'client_id.required' => 'Sélectionnez un client.',
            'customer_reference.required' => 'Indiquez le numéro de commande / demande achat.',
            'quotation_reference.required' => 'Indiquez le numéro de devis.',
            'delivery_note_number.required' => 'Indiquez le numéro du bon de livraison.',
            'invoice_date.required' => 'La date de facturation est obligatoire.',
            'due_date.after_or_equal' => 'La date d\'échéance doit être postérieure ou égale à la date de facturation.',
        ]);

        $data['payment_mode'] = ($data['payment_mode'] ?? '') !== '' ? $data['payment_mode'] : null;

        $subtotal = array_sum(array_map(fn ($r) => (float) ($r['line_total'] ?? 0), $this->cart));
        $discountResolved = $this->resolveDocumentDiscount($subtotal);
        $data['discount_mode'] = $this->discount_mode;
        $data['discount_percent'] = $discountResolved['percent'];
        $data['discount_amount'] = $discountResolved['amount'];

        $netHt = max(0, $subtotal - $data['discount_amount']);
        $normalizedTax = $this->normalizedTaxLines($netHt);
        $data['tax_lines'] = $normalizedTax['taxLines'];
        $data['tax_amount'] = $normalizedTax['tax_amount'];

        $lines = array_map(function ($row) {
            $price = (float) $row['unit_price'];
            $mode = ($row['line_discount_mode'] ?? $this->lines_discount_mode) === 'percent' ? 'percent' : 'amount';
            $input = max(0, (float) ($row['line_discount'] ?? 0));
            $unitPriceNet = array_key_exists('unit_price_net', $row) && $row['unit_price_net'] !== '' && $row['unit_price_net'] !== null
                ? max(0, min($price, (float) $row['unit_price_net']))
                : max(0, $price - $this->resolveLineDiscountAmount($price, $row));

            $payload = [
                'item_id' => $row['item_id'] ?? null,
                'item_name' => $row['item_name'],
                'item_sku' => $row['item_sku'] ?? null,
                'quantity' => (float) $row['quantity'],
                'unit_price' => $price,
                'unit_cost' => ($row['unit_cost'] ?? '') !== '' ? (float) $row['unit_cost'] : null,
                'line_discount' => round($price - $unitPriceNet, 2),
                'line_discount_mode' => $mode,
                'line_discount_input' => $input,
            ];

            if (Schema::connection('tenant')->hasColumn('invoice_lines', 'line_number')) {
                $payload['line_number'] = (int) ($row['line_number'] ?? 0);
            }

            return $payload;
        }, DocumentLineNumbers::assignMissing($this->cart));

        $service = app(InvoicingService::class);

        try {
            if ($this->deliveryNoteId && !$this->invoiceId) {
                $note = \InovCom\Invoicing\Models\DeliveryNote::findOrFail($this->deliveryNoteId);
                $invoice = $service->createFromDeliveryNote($note, $data, $lines, $andIssue);
                session()->flash(
                    'success',
                    'Facture de la commande complète créée : '.$invoice->invoice_number.'. Elle reste en livraison partielle tant qu’il reste un reliquat.'
                );
                $this->redirect(route('tenant.invoicing.edit', [
                    $invoice->id,
                    'tenant' => $this->tenantCode(),
                ]), navigate: true);
                return;
            } elseif ($this->quotation_id && !$this->invoiceId) {
                $quotation = Quotation::findOrFail($this->quotation_id);
                $invoice = $service->createFromQuotation($quotation, $data, $lines, $andIssue);
                session()->flash('success', 'Facture créée depuis le devis : ' . $invoice->invoice_number);
            } elseif ($this->invoiceId) {
                $invoice = Invoice::findOrFail($this->invoiceId);
                if (!$invoice->isEditable()) {
                    session()->flash('error', 'Cette facture ne peut plus être modifiée.');
                    return;
                }
                $service->update($invoice, array_merge($data, [
                    'quotation_id' => $this->quotation_id,
                ]), $lines);
                if ($andIssue && $this->can('invoicing.issue')) {
                    $service->issue($invoice->fresh());
                }
                session()->flash('success', 'Facture mise à jour.');
            } else {
                $invoice = $service->create(array_merge($data, [
                    'quotation_id' => $this->quotation_id,
                    'issue' => $andIssue,
                ]), $lines);
                session()->flash('success', 'Facture créée : ' . $invoice->invoice_number);
            }

            $this->redirect(route('tenant.invoicing.index', ['tenant' => $this->tenantCode()]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelInvoice(): void
    {
        if (!$this->invoiceId || !$this->can('invoicing.cancel')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $invoice = Invoice::findOrFail($this->invoiceId);
            app(InvoicingService::class)->cancel($invoice);
            session()->flash('success', 'Facture annulée.');
            $this->redirect(route('tenant.invoicing.index', ['tenant' => $this->tenantCode()]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function deleteInvoice(): void
    {
        if (!$this->invoiceId || !$this->can('invoicing.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $invoice = Invoice::findOrFail($this->invoiceId);
            app(InvoicingService::class)->deleteDraft($invoice);
            session()->flash('success', 'Brouillon supprimé.');
            $this->redirect(route('tenant.invoicing.index', ['tenant' => $this->tenantCode()]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $invoice = $this->invoiceId
            ? Invoice::with(['client', 'quotation', 'lines', 'schedules'])->find($this->invoiceId)
            : null;

        $invoicePayments = collect();
        if ($invoice && Schema::connection('tenant')->hasTable('invoice_payments')
            && class_exists(\InovCom\InvoicePayments\Models\InvoicePayment::class)) {
            $invoicePayments = \InovCom\InvoicePayments\Models\InvoicePayment::query()
                ->where('invoice_id', $invoice->id)
                ->with('creator')
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->get();
        }

        if ($invoice && $invoice->schedules->isNotEmpty() && class_exists(InvoiceScheduleService::class)) {
            app(InvoiceScheduleService::class)->refreshOverdueStatuses($invoice);
            $invoice->load('schedules');
        }

        $subtotal = array_sum(array_map(fn ($r) => (float) ($r['line_total'] ?? 0), $this->cart));
        $discountResolved = $this->resolveDocumentDiscount($subtotal);
        $discount = $discountResolved['amount'];
        $discountPct = $discountResolved['percent'];
        $tax = (float) $this->tax_amount;
        $normalizedPreview = DocumentTaxCalculator::summarize(max(0, $subtotal - $discount), $this->tax_lines);
        $ttc = $normalizedPreview['ttc'];
        $total = $normalizedPreview['total'];
        $totalHt = max(0, $subtotal - $discount);
        $marginSummary = $this->buildMarginSummary($totalHt);

        $availableDeliveryNotes = collect();
        if (!$invoice && !$this->deliveryNoteId) {
            $invoicedQuotationIds = Invoice::query()
                ->whereNotNull('quotation_id')
                ->whereNotIn('status', ['cancelled'])
                ->pluck('quotation_id');

            $availableDeliveryNotes = \InovCom\Invoicing\Models\DeliveryNote::query()
                ->where('status', \InovCom\Invoicing\Models\DeliveryNote::STATUS_CONFIRMED)
                ->whereNull('invoice_id')
                ->whereNotNull('quotation_id')
                ->when($invoicedQuotationIds->isNotEmpty(), fn ($q) => $q->whereNotIn('quotation_id', $invoicedQuotationIds))
                ->with(['client', 'quotation.client'])
                ->orderByDesc('delivery_date')
                ->limit(100)
                ->get();
        }

        $canManageSchedule = $this->can('invoicing.manage_schedule') || $this->can('invoicing.update');
        $scheduleAmountDueNow = $invoice && $invoice->schedules->isNotEmpty()
            ? app(InvoiceScheduleService::class)->amountCurrentlyDue($invoice)
            : null;

        $invoiceDeliveryProgress = ['status' => 'n/a', 'ordered' => 0.0, 'delivered' => 0.0, 'remaining' => 0.0];
        $invoiceDeliveryNotes = collect();
        $canCreateDelivery = false;
        $invoiceStatusSummary = ['facts' => [], 'next' => null];
        if ($invoice && Schema::connection('tenant')->hasTable('delivery_notes')) {
            $deliveryService = app(DeliveryNotesService::class);
            $invoiceDeliveryProgress = $deliveryService->invoiceDeliveryProgress($invoice);
            $invoiceDeliveryNotes = $deliveryService->notesForInvoice($invoice);
            $canCreateDelivery = in_array($invoice->status, ['issued', 'partial', 'paid'], true)
                && $this->can('invoicing.delivery.create')
                && $invoiceDeliveryProgress['remaining'] > 0.0001;
            if ($invoice->quotation_id) {
                $quotation = $invoice->quotation ?? Quotation::find($invoice->quotation_id);
                if ($quotation) {
                    $deliveryService->syncQuotationFulfillment($quotation);
                }
            }
        }
        if ($invoice) {
            $invoiceStatusSummary = $this->invoiceStatusSummary($invoice, $invoiceDeliveryProgress);
        }

        $sourceDeliveryHint = null;
        if (!$invoice && $this->deliveryNoteId && $this->quotation_id) {
            $note = \InovCom\Invoicing\Models\DeliveryNote::with('lines')->find($this->deliveryNoteId);
            $quotation = Quotation::with('lines')->find($this->quotation_id);
            if ($note && $quotation) {
                $progress = app(DeliveryNotesService::class)->quotationFulfillmentProgress($quotation);
                $sourceDeliveryHint = [
                    'bl_number' => $note->delivery_number,
                    'bl_qty' => (float) $note->lines->sum('quantity'),
                    'ordered' => $progress['ordered'],
                    'delivered' => $progress['delivered'],
                    'remaining' => $progress['remaining'],
                    'quotation_number' => $quotation->number,
                ];
            }
        }

        return view('inovcom-invoicing::livewire.invoices.form')
            ->layout('layouts.app', [
                'title' => $invoice ? 'Facture ' . $invoice->invoice_number : 'Nouvelle facture',
                'subtitle' => 'Facturation',
            ])
            ->with([
                'invoice' => $invoice,
                'subtotal' => $subtotal,
                'totalHt' => $totalHt,
                'discount' => $discount,
                'discountPct' => $discountPct,
                'discountMode' => $this->discount_mode,
                'tax' => $tax,
                'taxLinesComputed' => $normalizedPreview['lines'],
                'ttc' => $ttc,
                'total' => $total,
                'marginSummary' => $marginSummary,
                'availableDeliveryNotes' => $availableDeliveryNotes,
                'canEdit' => !$invoice || $invoice->isEditable(),
                'canIssue' => $this->can('invoicing.issue'),
                'canUpdate' => $this->can('invoicing.update'),
                'canCancel' => $this->can('invoicing.cancel'),
                'canPay' => $invoice && $invoice->canReceivePayment() && $this->can('invoice_payments.receive'),
                'invoicePayments' => $invoicePayments,
                'hasPaymentHistory' => $invoicePayments->contains(
                    fn ($p) => $p->isActive() && $p->settledAmount() > 0
                ),
                'paymentModes' => $this->paymentModeOptionsForForm(),
                'requiresDocumentReferences' => $this->requiresManualDocumentReferences(),
                'canManageSchedule' => $canManageSchedule,
                'invoiceSchedules' => $invoice?->schedules ?? collect(),
                'scheduleAmountDueNow' => $scheduleAmountDueNow,
                'canCreateDelivery' => $canCreateDelivery,
                'invoiceDeliveryStatus' => $invoiceDeliveryProgress['status'],
                'invoiceDeliveryProgress' => $invoiceDeliveryProgress,
                'invoiceDeliveryNotes' => $invoiceDeliveryNotes,
                'invoiceStatusSummary' => $invoiceStatusSummary,
                'sourceDeliveryHint' => $sourceDeliveryHint,
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

    private function requiresManualDocumentReferences(): bool
    {
        return !$this->invoiceId && !$this->quotation_id && !$this->deliveryNoteId;
    }

    /**
     * @param  array{status: string, ordered: float, delivered: float, remaining: float}  $deliveryProgress
     * @return array{facts: list<array{key: string, label: string, value: string, tone: string}>, next: ?string}
     */
    private function invoiceStatusSummary(Invoice $invoice, array $deliveryProgress): array
    {
        $cancelled = $invoice->status === 'cancelled';
        $isDraft = $invoice->isDraft();
        $issued = !in_array($invoice->status, ['draft', 'cancelled'], true);
        $paid = $invoice->status === 'paid';
        $deliveryStatus = $deliveryProgress['status'] ?? 'n/a';

        if ($cancelled) {
            $issueValue = 'Annulée';
            $issueTone = 'danger';
        } elseif ($isDraft) {
            $issueValue = 'Brouillon';
            $issueTone = 'warn';
        } else {
            $issueValue = 'Émise';
            $issueTone = 'ok';
        }

        if ($isDraft || $cancelled || $deliveryStatus === 'n/a') {
            $deliveryValue = '—';
            $deliveryTone = 'muted';
        } elseif ($deliveryStatus === 'delivered') {
            $deliveryValue = 'Terminée';
            $deliveryTone = 'ok';
        } elseif ($deliveryStatus === 'partial') {
            $deliveryValue = fmt_num($deliveryProgress['delivered'] ?? 0) . ' / ' . fmt_num($deliveryProgress['ordered'] ?? 0);
            $deliveryTone = 'warn';
        } else {
            $deliveryValue = 'À livrer';
            $deliveryTone = 'warn';
        }

        if ($cancelled || $isDraft) {
            $payValue = '—';
            $payTone = 'muted';
            $payLabel = 'Solde';
        } elseif ($paid) {
            $payValue = 'Soldée';
            $payTone = 'ok';
            $payLabel = 'Paiement';
        } else {
            $payValue = fmt_money(max(0, (float) $invoice->balance));
            $payTone = 'warn';
            $payLabel = 'Solde dû';
        }

        $next = null;
        if ($cancelled) {
            $next = null;
        } elseif ($isDraft) {
            $next = 'Enregistrez puis émettez la facture.';
        } elseif ($deliveryStatus === 'partial') {
            $next = 'Reliquat à livrer : ' . fmt_num($deliveryProgress['remaining'] ?? 0) . ' article(s).';
        } elseif ($issued && in_array($deliveryStatus, ['pending', 'n/a'], true) && ($deliveryProgress['ordered'] ?? 0) > 0) {
            $next = 'Créer un bon de livraison.';
        } elseif (!$paid) {
            $next = 'Encaisser le solde restant.';
        }

        return [
            'facts' => [
                [
                    'key' => 'created',
                    'label' => 'Créée le',
                    'value' => $invoice->invoice_date?->format('d/m/Y') ?? '—',
                    'tone' => 'ok',
                ],
                [
                    'key' => 'issue',
                    'label' => 'Facture',
                    'value' => $issueValue,
                    'tone' => $issueTone,
                ],
                [
                    'key' => 'delivery',
                    'label' => 'Livraison',
                    'value' => $deliveryValue,
                    'tone' => $deliveryTone,
                ],
                [
                    'key' => 'payment',
                    'label' => $payLabel,
                    'value' => $payValue,
                    'tone' => $payTone,
                ],
            ],
            'next' => $next,
        ];
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

    /**
     * @return array{line_discount_mode: string, line_discount: string, unit_price_net: string, net_locked: bool}
     */
    private function mapLineDiscountFromSource(object $line, ?object $fallbackLine = null): array
    {
        $fields = $this->extractLineDiscountFields($line);

        if ($fields['line_discount_mode'] !== 'percent' && $fallbackLine !== null) {
            $fallbackFields = $this->extractLineDiscountFields($fallbackLine);
            if ($fallbackFields['line_discount_mode'] === 'percent') {
                $unitPrice = (float) ($line->unit_price ?? 0);
                $unitPriceNet = (float) $fields['unit_price_net'];
                $amount = round(max(0, $unitPrice - $unitPriceNet), 2);
                $fields['line_discount_mode'] = 'percent';
                $fields['line_discount'] = $unitPrice > 0 && $amount > 0
                    ? (string) fmt_num_plain(round($amount / $unitPrice * 100, 6), 6)
                    : '0';
            }
        }

        return $fields;
    }

    /**
     * @return array{line_discount_mode: string, line_discount: string, unit_price_net: string, net_locked: bool}
     */
    private function extractLineDiscountFields(object $line): array
    {
        $unitPrice = (float) ($line->unit_price ?? 0);
        $discountAmount = max(0, (float) ($line->line_discount ?? 0));
        $unitPriceNet = $line->unit_price_net !== null
            ? (float) $line->unit_price_net
            : max(0, $unitPrice - $discountAmount);
        $discountAmount = round(max(0, $unitPrice - $unitPriceNet), 2);

        $lineMode = 'amount';
        $lineInput = $discountAmount;
        $table = $line->getTable();

        if (Schema::connection('tenant')->hasColumn($table, 'line_discount_mode')) {
            $storedMode = (string) ($line->line_discount_mode ?? 'amount');
            $lineMode = $storedMode === 'percent' ? 'percent' : 'amount';

            if ($lineMode === 'percent' && $unitPrice > 0 && $discountAmount > 0) {
                // Depuis le montant FCFA / P.U. net exact — évite 174999.96 après rechargement.
                $lineInput = round($discountAmount / $unitPrice * 100, 6);
            } elseif (Schema::connection('tenant')->hasColumn($table, 'line_discount_input')
                && $line->line_discount_input !== null
                && $discountAmount <= 0) {
                $lineInput = (float) $line->line_discount_input;
            }
        }

        return [
            'line_discount_mode' => $lineMode,
            'line_discount' => (string) ($lineInput > 0 ? fmt_num_plain($lineInput, 6) : '0'),
            'unit_price_net' => $this->cartUnitPricePlain($unitPriceNet),
            'net_locked' => $discountAmount > 0,
        ];
    }

    private function applyDocumentDiscountFromModel(Invoice|Quotation $document, ?Quotation $quotationFallback = null): void
    {
        $mode = document_discount_header_mode($document, $quotationFallback);
        $percent = document_discount_percent_display($document, $quotationFallback);
        $storedAmount = (float) $document->discount_amount;

        if ($mode === 'amount') {
            $this->discount_mode = 'amount';
            $this->discount_amount = fmt_num_plain($storedAmount);
            $this->discount_percent = '0';
        } else {
            $this->discount_mode = 'percent';
            $this->discount_percent = fmt_num_plain($percent);
            $this->discount_amount = '0';
        }
    }

    /**
     * @return array<string, string>
     */
    private function paymentModeOptionsForForm(): array
    {
        $options = Invoice::paymentModeOptions();
        $current = $this->payment_mode;
        if ($current !== null && $current !== '' && !array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    /**
     * Valeur affichée dans le champ PU : vide si 0 ou non défini.
     */
    private function cartUnitPricePlain(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $n = (float) $value;

        return abs($n) < 1e-9 ? '' : fmt_num_plain($n, 2);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
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
