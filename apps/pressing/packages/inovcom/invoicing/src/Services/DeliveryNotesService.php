<?php

namespace InovCom\Invoicing\Services;

use App\Services\StoreContextService;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Models\DeliveryNoteLine;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Models\InvoiceLine;
use InovCom\Quotations\Models\Quotation;
use InovCom\Quotations\Models\QuotationLine;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeliveryNotesService
{
    public function __construct(
        private StockService $stockService
    ) {
    }

    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('delivery_notes');
    }

    /**
     * @param  array<int, array{invoice_line_id: int, quantity: float}>  $lineInputs
     */
    public function createDraft(
        Invoice $invoice,
        array $lineInputs,
        ?string $notes = null,
        ?int $userId = null,
        array $options = []
    ): DeliveryNote {
        $this->assertInvoiceAllowsDelivery($invoice);
        $invoice->loadMissing('lines');

        return DB::connection('tenant')->transaction(function () use ($invoice, $lineInputs, $notes, $userId, $options) {
            $normalized = $this->normalizeLineInputs($invoice, $lineInputs, includeDraft: true);

            if ($normalized === []) {
                throw new \InvalidArgumentException('Sélectionnez au moins une ligne à livrer.');
            }

            $note = DeliveryNote::create(array_merge([
                'delivery_number' => $this->generateDeliveryNumber(),
                'invoice_id' => $invoice->id,
                'status' => DeliveryNote::STATUS_DRAFT,
                'delivery_date' => now()->toDateString(),
                'notes' => $notes,
                'store_id' => $invoice->store_id,
                'created_by' => $userId ?? auth('tenant')->id(),
            ], $this->printOptionsPayload($options, $invoice->customer_reference)));

            foreach ($normalized as $row) {
                /** @var InvoiceLine $invoiceLine */
                $invoiceLine = $row['invoice_line'];
                DeliveryNoteLine::create([
                    'delivery_note_id' => $note->id,
                    'invoice_line_id' => $invoiceLine->id,
                    'item_id' => $invoiceLine->item_id,
                    'item_name' => $invoiceLine->item_name,
                    'item_sku' => $invoiceLine->item_sku,
                    'quantity' => $row['quantity'],
                ]);
            }

            return $note->fresh(['lines', 'invoice.client']);
        });
    }

    /**
     * @param  array<int, array{invoice_line_id: int, quantity: float}>  $lineInputs
     */
    public function updateDraft(DeliveryNote $note, array $lineInputs, ?string $notes = null, array $options = []): DeliveryNote
    {
        if (!$note->isDraft()) {
            throw new \RuntimeException('Seul un bon de livraison en brouillon peut être modifié.');
        }

        $invoice = Invoice::with('lines')->findOrFail($note->invoice_id);
        $this->assertInvoiceAllowsDelivery($invoice);

        return DB::connection('tenant')->transaction(function () use ($note, $invoice, $lineInputs, $notes, $options) {
            $note->lines()->delete();
            $normalized = $this->normalizeLineInputs($invoice, $lineInputs, includeDraft: true, excludeNoteId: $note->id);

            if ($normalized === []) {
                throw new \InvalidArgumentException('Sélectionnez au moins une ligne à livrer.');
            }

            $note->notes = $notes;
            $this->applyPrintOptions($note, $options);
            $note->save();

            foreach ($normalized as $row) {
                $invoiceLine = $row['invoice_line'];
                DeliveryNoteLine::create([
                    'delivery_note_id' => $note->id,
                    'invoice_line_id' => $invoiceLine->id,
                    'item_id' => $invoiceLine->item_id,
                    'item_name' => $invoiceLine->item_name,
                    'item_sku' => $invoiceLine->item_sku,
                    'quantity' => $row['quantity'],
                ]);
            }

            return $note->fresh(['lines', 'invoice.client']);
        });
    }

    /**
     * Crée un bon de livraison directement à partir d'un devis accepté
     * (workflow Devis → Livraison → Facture). Aucune facture n'est requise.
     *
     * @param  array<int, array{quotation_line_id: int, quantity: float}>  $lineInputs
     */
    public function createDraftFromQuotation(
        Quotation $quotation,
        array $lineInputs,
        ?string $notes = null,
        ?int $userId = null,
        array $options = []
    ): DeliveryNote {
        if (!$quotation->isAccepted()) {
            throw new \RuntimeException('Seul un devis accepté peut être livré.');
        }
        $quotation->loadMissing('lines');

        return DB::connection('tenant')->transaction(function () use ($quotation, $lineInputs, $notes, $userId, $options) {
            $normalized = $this->normalizeQuotationLineInputs($quotation, $lineInputs, includeDraft: true);

            if ($normalized === []) {
                throw new \InvalidArgumentException('Sélectionnez au moins une ligne à livrer.');
            }

            $storeId = Schema::connection('tenant')->hasColumn('delivery_notes', 'store_id')
                ? app(StoreContextService::class)->currentStoreId()
                : null;

            $defaultPo = $quotation->customer_purchase_order ?? null;

            $note = DeliveryNote::create(array_merge([
                'delivery_number' => $this->generateDeliveryNumber(),
                'invoice_id' => null,
                'quotation_id' => $quotation->id,
                'client_id' => $quotation->client_id,
                'status' => DeliveryNote::STATUS_DRAFT,
                'delivery_date' => now()->toDateString(),
                'notes' => $notes,
                'store_id' => $storeId,
                'created_by' => $userId ?? auth('tenant')->id(),
            ], $this->printOptionsPayload($options, $defaultPo)));

            foreach ($normalized as $row) {
                /** @var QuotationLine $qLine */
                $qLine = $row['quotation_line'];
                DeliveryNoteLine::create([
                    'delivery_note_id' => $note->id,
                    'quotation_line_id' => $qLine->id,
                    'item_id' => $qLine->item_id,
                    'item_name' => $qLine->item_name,
                    'item_sku' => $qLine->item_sku,
                    'quantity' => $row['quantity'],
                ]);
            }

            return $note->fresh(['lines', 'quotation.client']);
        });
    }

    /**
     * @param  array<int, array{quotation_line_id: int, quantity: float}>  $lineInputs
     */
    public function updateDraftFromQuotation(DeliveryNote $note, array $lineInputs, ?string $notes = null, array $options = []): DeliveryNote
    {
        if (!$note->isDraft()) {
            throw new \RuntimeException('Seul un bon de livraison en brouillon peut être modifié.');
        }

        $quotation = Quotation::with('lines')->findOrFail($note->quotation_id);

        return DB::connection('tenant')->transaction(function () use ($note, $quotation, $lineInputs, $notes, $options) {
            $note->lines()->delete();
            $normalized = $this->normalizeQuotationLineInputs($quotation, $lineInputs, includeDraft: true, excludeNoteId: $note->id);

            if ($normalized === []) {
                throw new \InvalidArgumentException('Sélectionnez au moins une ligne à livrer.');
            }

            $note->notes = $notes;
            $this->applyPrintOptions($note, $options);
            $note->save();

            foreach ($normalized as $row) {
                $qLine = $row['quotation_line'];
                DeliveryNoteLine::create([
                    'delivery_note_id' => $note->id,
                    'quotation_line_id' => $qLine->id,
                    'item_id' => $qLine->item_id,
                    'item_name' => $qLine->item_name,
                    'item_sku' => $qLine->item_sku,
                    'quantity' => $row['quantity'],
                ]);
            }

            return $note->fresh(['lines', 'quotation.client']);
        });
    }

    public function confirmDelivery(DeliveryNote $note, ?int $userId = null): DeliveryNote
    {
        if (!$note->isDraft()) {
            throw new \RuntimeException('Seul un bon de livraison en brouillon peut être validé.');
        }

        $userId = $userId ?? auth('tenant')->id();

        if ($note->quotation_id && !$note->invoice_id) {
            return $this->confirmQuotationDelivery($note, $userId);
        }

        return DB::connection('tenant')->transaction(function () use ($note, $userId) {
            $note = DeliveryNote::lockForUpdate()->with(['lines', 'invoice'])->findOrFail($note->id);
            $invoice = Invoice::lockForUpdate()->findOrFail($note->invoice_id);

            $this->assertInvoiceAllowsDelivery($invoice);

            if ($note->lines->isEmpty()) {
                throw new \RuntimeException('Le bon de livraison ne contient aucune ligne.');
            }

            $storeId = $invoice->store_id;

            foreach ($note->lines as $line) {
                if (!$line->item_id) {
                    continue;
                }

                $qty = (float) $line->quantity;
                if ($qty <= 0) {
                    continue;
                }

                if (Schema::connection('tenant')->hasTable('stock_levels')) {
                    if (!$this->stockService->hasStock((int) $line->item_id, $qty, $storeId)) {
                        $available = $this->stockService->getAvailableQuantity((int) $line->item_id, $storeId);
                        throw new \RuntimeException(
                            "Stock insuffisant pour « {$line->item_name} » : demandé {$qty}, disponible {$available}."
                        );
                    }

                    $this->stockService->removeStock(
                        (int) $line->item_id,
                        $qty,
                        'delivery',
                        'delivery_note',
                        $note->id,
                        "Livraison {$note->delivery_number} — facture {$invoice->invoice_number}",
                        $userId,
                        $storeId
                    );
                }
            }

            $note->status = DeliveryNote::STATUS_CONFIRMED;
            $note->confirmed_by = $userId;
            $note->confirmed_at = now();
            $note->save();

            $this->syncInvoiceDeliveryNumber($invoice);

            return $note->fresh(['lines', 'invoice.client']);
        });
    }

    /**
     * Met à jour BC client et options d'impression sans toucher aux lignes.
     *
     * @param  array{customer_purchase_order?: ?string, show_prices?: bool, show_discounts?: bool}  $options
     */
    public function updatePrintOptions(DeliveryNote $note, array $options): DeliveryNote
    {
        if (!$note->isDraft()) {
            throw new \RuntimeException('Seul un brouillon peut être modifié.');
        }

        $this->applyPrintOptions($note, $options);
        $note->save();

        return $note->fresh(['lines', 'quotation.client', 'invoice.client']);
    }

    public function cancelDraft(DeliveryNote $note): DeliveryNote
    {
        if (!$note->isDraft()) {
            throw new \RuntimeException('Seul un brouillon peut être annulé.');
        }

        $note->status = DeliveryNote::STATUS_CANCELLED;
        $note->save();

        return $note;
    }

    /**
     * Confirme un BL issu d'un devis (sans facture associée) : sortie de stock + statut livré.
     */
    private function confirmQuotationDelivery(DeliveryNote $note, int $userId): DeliveryNote
    {
        return DB::connection('tenant')->transaction(function () use ($note, $userId) {
            $note = DeliveryNote::lockForUpdate()->with('lines')->findOrFail($note->id);

            if ($note->lines->isEmpty()) {
                throw new \RuntimeException('Le bon de livraison ne contient aucune ligne.');
            }

            $storeId = $note->store_id;

            foreach ($note->lines as $line) {
                if (!$line->item_id) {
                    continue;
                }
                $qty = (float) $line->quantity;
                if ($qty <= 0) {
                    continue;
                }

                if (Schema::connection('tenant')->hasTable('stock_levels')) {
                    if (!$this->stockService->hasStock((int) $line->item_id, $qty, $storeId)) {
                        $available = $this->stockService->getAvailableQuantity((int) $line->item_id, $storeId);
                        throw new \RuntimeException(
                            "Stock insuffisant pour « {$line->item_name} » : demandé {$qty}, disponible {$available}."
                        );
                    }

                    $this->stockService->removeStock(
                        (int) $line->item_id,
                        $qty,
                        'delivery',
                        'delivery_note',
                        $note->id,
                        "Livraison {$note->delivery_number} — devis " . ($note->quotation->number ?? ''),
                        $userId,
                        $storeId
                    );
                }
            }

            $note->status = DeliveryNote::STATUS_CONFIRMED;
            $note->confirmed_by = $userId;
            $note->confirmed_at = now();
            $note->save();

            return $note->fresh(['lines', 'quotation.client']);
        });
    }

    public function deliverableQuantityForQuotationLine(QuotationLine $line, bool $includeDraft = false, ?int $excludeNoteId = null): float
    {
        $ordered = (float) $line->quantity;
        $delivered = $this->deliveredQuantityForQuotationLine($line->id, $includeDraft, $excludeNoteId);

        return max(0, $ordered - $delivered);
    }

    public function deliveredQuantityForQuotationLine(int $quotationLineId, bool $includeDraft = false, ?int $excludeNoteId = null): float
    {
        if (!$this->isAvailable()) {
            return 0.0;
        }

        $query = DeliveryNoteLine::query()
            ->where('quotation_line_id', $quotationLineId)
            ->whereHas('deliveryNote', function ($q) use ($includeDraft, $excludeNoteId) {
                $q->whereIn('status', $includeDraft
                    ? [DeliveryNote::STATUS_CONFIRMED, DeliveryNote::STATUS_DRAFT]
                    : [DeliveryNote::STATUS_CONFIRMED]);
                if ($excludeNoteId) {
                    $q->where('id', '!=', $excludeNoteId);
                }
            });

        return (float) $query->sum('quantity');
    }

    /**
     * @param  array<int, array{quotation_line_id: int, quantity: float}>  $lineInputs
     * @return array<int, array{quotation_line: QuotationLine, quantity: float}>
     */
    private function normalizeQuotationLineInputs(
        Quotation $quotation,
        array $lineInputs,
        bool $includeDraft = false,
        ?int $excludeNoteId = null
    ): array {
        $linesById = $quotation->lines->keyBy('id');
        $normalized = [];

        foreach ($lineInputs as $input) {
            $lineId = (int) ($input['quotation_line_id'] ?? 0);
            $qty = (float) ($input['quantity'] ?? 0);

            if ($lineId <= 0 || $qty <= 0) {
                continue;
            }

            /** @var QuotationLine|null $qLine */
            $qLine = $linesById->get($lineId);
            if (!$qLine) {
                continue;
            }

            $max = $this->deliverableQuantityForQuotationLine($qLine, $includeDraft, $excludeNoteId);
            if ($qty > $max + 0.0001) {
                throw new \InvalidArgumentException(
                    "Quantité trop élevée pour « {$qLine->item_name} » (max {$max})."
                );
            }

            $normalized[] = [
                'quotation_line' => $qLine,
                'quantity' => $qty,
            ];
        }

        return $normalized;
    }

    /**
     * Quantités restant à livrer sur un devis (toutes lignes confondues).
     */
    public function quotationHasDeliverableLines(Quotation $quotation, bool $includeDraft = true): bool
    {
        $quotation->loadMissing('lines');
        foreach ($quotation->lines as $line) {
            if ($this->deliverableQuantityForQuotationLine($line, $includeDraft) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Le devis a-t-il au moins un BL confirmé ? (condition pour facturer depuis un BL)
     */
    public function quotationHasConfirmedDelivery(Quotation $quotation): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return DeliveryNote::query()
            ->where('quotation_id', $quotation->id)
            ->where('status', DeliveryNote::STATUS_CONFIRMED)
            ->exists();
    }

    public function deliverableQuantity(InvoiceLine $line, bool $includeDraft = false, ?int $excludeNoteId = null): float
    {
        $invoiced = (float) $line->quantity;
        $delivered = $this->deliveredQuantityForLine($line->id, $includeDraft, $excludeNoteId);

        return max(0, $invoiced - $delivered);
    }

    public function deliveredQuantityForLine(int $invoiceLineId, bool $includeDraft = false, ?int $excludeNoteId = null): float
    {
        if (!$this->isAvailable()) {
            return 0.0;
        }

        $query = DeliveryNoteLine::query()
            ->where('invoice_line_id', $invoiceLineId)
            ->whereHas('deliveryNote', function ($q) use ($includeDraft, $excludeNoteId) {
                $q->whereIn('status', $includeDraft
                    ? [DeliveryNote::STATUS_CONFIRMED, DeliveryNote::STATUS_DRAFT]
                    : [DeliveryNote::STATUS_CONFIRMED]);
                if ($excludeNoteId) {
                    $q->where('id', '!=', $excludeNoteId);
                }
            });

        return (float) $query->sum('quantity');
    }

    public function invoiceDeliveryStatus(Invoice $invoice): string
    {
        if (!in_array($invoice->status, ['issued', 'partial', 'paid'], true)) {
            return 'n/a';
        }

        if (!$this->isAvailable()) {
            return 'pending';
        }

        $invoice->loadMissing('lines');
        $totalInvoiced = 0.0;
        $totalDelivered = 0.0;

        foreach ($invoice->lines as $line) {
            $totalInvoiced += (float) $line->quantity;
            $totalDelivered += $this->deliveredQuantityForLine($line->id, false);
        }

        if ($totalInvoiced <= 0) {
            return 'delivered';
        }

        if ($totalDelivered <= 0.0001) {
            return 'pending';
        }

        if ($totalDelivered + 0.0001 >= $totalInvoiced) {
            return 'delivered';
        }

        return 'partial';
    }

    public static function deliveryStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'À livrer',
            'partial' => 'Livraison partielle',
            'delivered' => 'Livré',
            default => '—',
        };
    }

    public function invoiceHasDeliverableLines(Invoice $invoice, bool $includeDraft = true): bool
    {
        $invoice->loadMissing('lines');
        foreach ($invoice->lines as $line) {
            if ($this->deliverableQuantity($line, $includeDraft) > 0) {
                return true;
            }
        }

        return false;
    }

    private function syncInvoiceDeliveryNumber(Invoice $invoice): void
    {
        $latest = DeliveryNote::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', DeliveryNote::STATUS_CONFIRMED)
            ->orderByDesc('confirmed_at')
            ->value('delivery_number');

        if ($latest) {
            $invoice->delivery_note_number = $latest;
            $invoice->save();
        }
    }

    private function assertInvoiceAllowsDelivery(Invoice $invoice): void
    {
        if (!in_array($invoice->status, ['issued', 'partial', 'paid'], true)) {
            throw new \RuntimeException('Seules les factures émises peuvent être livrées.');
        }
    }

    /**
     * @param  array<int, array{invoice_line_id: int, quantity: float}>  $lineInputs
     * @return array<int, array{invoice_line: InvoiceLine, quantity: float}>
     */
    private function normalizeLineInputs(
        Invoice $invoice,
        array $lineInputs,
        bool $includeDraft = false,
        ?int $excludeNoteId = null
    ): array {
        $linesById = $invoice->lines->keyBy('id');
        $normalized = [];

        foreach ($lineInputs as $input) {
            $lineId = (int) ($input['invoice_line_id'] ?? 0);
            $qty = (float) ($input['quantity'] ?? 0);

            if ($lineId <= 0 || $qty <= 0) {
                continue;
            }

            /** @var InvoiceLine|null $invoiceLine */
            $invoiceLine = $linesById->get($lineId);
            if (!$invoiceLine) {
                continue;
            }

            $max = $this->deliverableQuantity($invoiceLine, $includeDraft, $excludeNoteId);
            if ($qty > $max + 0.0001) {
                throw new \InvalidArgumentException(
                    "Quantité trop élevée pour « {$invoiceLine->item_name} » (max {$max})."
                );
            }

            $normalized[] = [
                'invoice_line' => $invoiceLine,
                'quantity' => $qty,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array{customer_purchase_order?: ?string, show_prices?: bool, show_discounts?: bool}  $options
     * @return array<string, mixed>
     */
    private function printOptionsPayload(array $options, ?string $fallbackPurchaseOrder = null): array
    {
        if (!Schema::connection('tenant')->hasColumn('delivery_notes', 'show_prices')) {
            return [];
        }

        $po = trim((string) ($options['customer_purchase_order'] ?? ''));
        if ($po === '' && $fallbackPurchaseOrder !== null) {
            $po = trim((string) $fallbackPurchaseOrder);
        }

        return [
            'customer_purchase_order' => $po !== '' ? $po : null,
            'show_prices' => (bool) ($options['show_prices'] ?? false),
            'show_discounts' => (bool) ($options['show_discounts'] ?? false),
        ];
    }

    /**
     * @param  array{customer_purchase_order?: ?string, show_prices?: bool, show_discounts?: bool}  $options
     */
    private function applyPrintOptions(DeliveryNote $note, array $options): void
    {
        if (!Schema::connection('tenant')->hasColumn('delivery_notes', 'show_prices')) {
            return;
        }

        if (array_key_exists('customer_purchase_order', $options)) {
            $po = trim((string) ($options['customer_purchase_order'] ?? ''));
            $note->customer_purchase_order = $po !== '' ? $po : null;
        }

        if (array_key_exists('show_prices', $options)) {
            $note->show_prices = (bool) $options['show_prices'];
        }

        if (array_key_exists('show_discounts', $options)) {
            $note->show_discounts = (bool) $options['show_discounts'];
        }
    }

    private function generateDeliveryNumber(): string
    {
        $year = now()->year;
        $prefix = 'BL-' . $year . '-';

        $last = DeliveryNote::query()
            ->where('delivery_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('delivery_number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
