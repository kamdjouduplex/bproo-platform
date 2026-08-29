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
                'quotation_id' => $invoice->quotation_id,
                'client_id' => $invoice->client_id,
                'status' => DeliveryNote::STATUS_DRAFT,
                'delivery_date' => now()->toDateString(),
                'notes' => $notes,
                'store_id' => $invoice->store_id,
                'created_by' => $userId ?? auth('tenant')->id(),
            ], $this->printOptionsPayload($options, $invoice->customer_reference)));

            $invoice->loadMissing('quotation.lines');

            foreach ($normalized as $row) {
                /** @var InvoiceLine $invoiceLine */
                $invoiceLine = $row['invoice_line'];
                DeliveryNoteLine::create([
                    'delivery_note_id' => $note->id,
                    'invoice_line_id' => $invoiceLine->id,
                    'quotation_line_id' => $this->quotationLineIdForInvoiceLine($invoice, $invoiceLine),
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
            if (!$note->quotation_id && $invoice->quotation_id) {
                $note->quotation_id = $invoice->quotation_id;
            }
            $this->applyPrintOptions($note, $options);
            $note->save();

            $invoice->loadMissing('quotation.lines');

            foreach ($normalized as $row) {
                $invoiceLine = $row['invoice_line'];
                DeliveryNoteLine::create([
                    'delivery_note_id' => $note->id,
                    'invoice_line_id' => $invoiceLine->id,
                    'quotation_line_id' => $this->quotationLineIdForInvoiceLine($invoice, $invoiceLine),
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
            $existingInvoice = Invoice::openForQuotation($quotation->id);

            $note = DeliveryNote::create(array_merge([
                'delivery_number' => $this->generateDeliveryNumber(),
                'invoice_id' => $existingInvoice?->id,
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

            $fresh = $note->fresh(['lines', 'quotation.client']);
            if ($existingInvoice) {
                $this->attachToInvoice($fresh, $existingInvoice);
            }

            return $fresh->fresh(['lines', 'quotation.client']);
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
            $note = DeliveryNote::lockForUpdate()->with(['lines', 'invoice.quotation.lines'])->findOrFail($note->id);
            $invoice = Invoice::lockForUpdate()->findOrFail($note->invoice_id);

            $this->assertInvoiceAllowsDelivery($invoice);

            if ($note->lines->isEmpty()) {
                throw new \RuntimeException('Le bon de livraison ne contient aucune ligne.');
            }

            if (!$note->quotation_id && $invoice->quotation_id) {
                $note->quotation_id = $invoice->quotation_id;
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

            $invoice->loadMissing(['lines', 'quotation.lines']);
            foreach ($note->lines as $line) {
                if ($line->quotation_line_id) {
                    continue;
                }
                $invoiceLine = $invoice->lines->firstWhere('id', $line->invoice_line_id);
                $qLineId = $this->quotationLineIdForInvoiceLine($invoice, $invoiceLine);
                if ($qLineId) {
                    $line->quotation_line_id = $qLineId;
                    $line->save();
                }
            }

            $this->syncInvoiceDeliveryNumber($invoice);

            $quotation = $invoice->quotation;
            if (!$quotation && $invoice->quotation_id) {
                $quotation = Quotation::find($invoice->quotation_id);
            }
            if ($quotation) {
                $this->syncQuotationFulfillment($quotation);
            }

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
        if ($note->isCancelled()) {
            throw new \RuntimeException('Un bon de livraison annulé ne peut plus être modifié.');
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

            $fresh = $note->fresh(['lines', 'quotation.client']);
            if ($fresh->quotation) {
                $invoice = Invoice::openForQuotation($fresh->quotation->id);
                if ($invoice) {
                    $this->attachToInvoice($fresh, $invoice);
                    $this->syncInvoiceDeliveryNumber($invoice);
                }
                $this->syncQuotationFulfillment($fresh->quotation);
            }

            return $fresh;
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

        $qLine = QuotationLine::query()->find($quotationLineId);
        if (!$qLine) {
            return 0.0;
        }

        $quotationId = (int) ($qLine->quotation_id ?: 0);

        return (float) DeliveryNoteLine::query()
            ->where(function ($q) use ($qLine) {
                $q->where('quotation_line_id', $qLine->id);
                if ($qLine->item_id) {
                    $q->orWhere('item_id', $qLine->item_id);
                }
                $name = trim((string) $qLine->item_name);
                if ($name !== '') {
                    $q->orWhere('item_name', $qLine->item_name);
                }
            })
            ->whereHas('deliveryNote', function ($q) use ($quotationId, $includeDraft, $excludeNoteId) {
                $this->constrainNoteStatus($q, $includeDraft, $excludeNoteId);
                $this->constrainNotesBelongingToQuotation($q, $quotationId);
            })
            ->sum('quantity');
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
        $invoice = Invoice::openForQuotation($quotation->id);
        if ($invoice) {
            return $this->invoiceDeliveryProgress($invoice, $includeDraft)['remaining'] > 0.0001;
        }

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
            ->where(function ($q) use ($quotation) {
                $this->constrainNotesBelongingToQuotation($q, (int) $quotation->id);
            })
            ->where('status', DeliveryNote::STATUS_CONFIRMED)
            ->exists();
    }

    public function syncQuotationFulfillment(Quotation $quotation): void
    {
        if (!Schema::connection('tenant')->hasColumn('quotations', 'fulfillment_status')) {
            return;
        }

        if (!$quotation->isAccepted()) {
            $quotation->fulfillment_status = 'none';
            $quotation->save();
            return;
        }

        $progress = $this->quotationFulfillmentProgress($quotation);
        $quotation->fulfillment_status = match ($progress['status']) {
            'delivered' => 'delivered',
            'partial' => 'partial',
            default => 'pending',
        };
        $quotation->save();
    }

    /**
     * @return array{lines: list<array<string, mixed>>, ordered: float, delivered: float, remaining: float, status: string}
     */
    public function quotationFulfillmentProgress(Quotation $quotation): array
    {
        $quotation->loadMissing('lines');

        $invoice = Invoice::openForQuotation($quotation->id);
        if ($invoice) {
            return $this->quotationFulfillmentFromInvoice($quotation, $invoice);
        }

        $lines = [];
        $ordered = 0.0;
        $delivered = 0.0;

        foreach ($quotation->lines as $line) {
            $qtyOrdered = (float) $line->quantity;
            $qtyDelivered = min($qtyOrdered, $this->deliveredQuantityForQuotationLine($line->id, false));
            $qtyRemaining = max(0, $qtyOrdered - $qtyDelivered);
            $ordered += $qtyOrdered;
            $delivered += $qtyDelivered;
            $lines[] = [
                'id' => $line->id,
                'item_name' => $line->item_name,
                'ordered' => $qtyOrdered,
                'delivered' => $qtyDelivered,
                'remaining' => $qtyRemaining,
            ];
        }

        $remaining = max(0, $ordered - $delivered);
        $status = 'pending';
        if ($delivered > 0.0001 && $remaining > 0.0001) {
            $status = 'partial';
        } elseif ($delivered > 0.0001 && $remaining <= 0.0001) {
            $status = 'delivered';
        }

        return [
            'lines' => $lines,
            'ordered' => $ordered,
            'delivered' => $delivered,
            'remaining' => $remaining,
            'status' => $status,
        ];
    }

    /**
     * Une fois la facture créée, le devis suit le même reliquat que la facture.
     *
     * @return array{lines: list<array<string, mixed>>, ordered: float, delivered: float, remaining: float, status: string}
     */
    private function quotationFulfillmentFromInvoice(Quotation $quotation, Invoice $invoice): array
    {
        $invoiceProgress = $this->invoiceDeliveryProgress($invoice);
        $invoice->loadMissing('lines');

        $dnLines = DeliveryNoteLine::query()
            ->whereHas('deliveryNote', function ($q) use ($invoice) {
                $this->constrainNotesBelongingToInvoice($q, $invoice);
                $this->constrainNoteStatus($q, false);
            })
            ->get();

        $lines = [];
        foreach ($quotation->lines as $line) {
            $qtyOrdered = (float) $line->quantity;
            $qtyDelivered = (float) $dnLines
                ->filter(function (DeliveryNoteLine $dnLine) use ($line, $invoice) {
                    if ($dnLine->quotation_line_id && (int) $dnLine->quotation_line_id === (int) $line->id) {
                        return true;
                    }
                    $invoiceLine = $invoice->lines->firstWhere('id', $dnLine->invoice_line_id);
                    if ($invoiceLine) {
                        if ($line->item_id && $invoiceLine->item_id) {
                            return (int) $invoiceLine->item_id === (int) $line->item_id;
                        }

                        return trim((string) $invoiceLine->item_name) === trim((string) $line->item_name);
                    }
                    if ($line->item_id && $dnLine->item_id) {
                        return (int) $dnLine->item_id === (int) $line->item_id;
                    }

                    return trim((string) $dnLine->item_name) === trim((string) $line->item_name);
                })
                ->sum(fn (DeliveryNoteLine $dnLine) => (float) $dnLine->quantity);
            $qtyDelivered = min($qtyOrdered, $qtyDelivered);

            $lines[] = [
                'id' => $line->id,
                'item_name' => $line->item_name,
                'ordered' => $qtyOrdered,
                'delivered' => $qtyDelivered,
                'remaining' => max(0, $qtyOrdered - $qtyDelivered),
            ];
        }

        $status = $invoiceProgress['status'];
        if ($status === 'n/a') {
            $status = 'pending';
        }

        return [
            'lines' => $lines,
            'ordered' => $invoiceProgress['ordered'] > 0
                ? $invoiceProgress['ordered']
                : (float) $quotation->lines->sum(fn ($l) => (float) $l->quantity),
            'delivered' => $invoiceProgress['delivered'],
            'remaining' => $invoiceProgress['remaining'],
            'status' => $status,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DeliveryNote>
     */
    public function notesForQuotation(Quotation $quotation)
    {
        if (!$this->isAvailable()) {
            return DeliveryNote::query()->whereRaw('1 = 0')->get();
        }

        return DeliveryNote::query()
            ->with(['creator', 'confirmer', 'lines'])
            ->where(function ($q) use ($quotation) {
                $this->constrainNotesBelongingToQuotation($q, (int) $quotation->id);
            })
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->get();
    }

    public function deliverableQuantity(InvoiceLine $line, bool $includeDraft = false, ?int $excludeNoteId = null): float
    {
        $invoiced = (float) $line->quantity;
        $delivered = $this->deliveredQuantityForInvoiceLine($line, $includeDraft, $excludeNoteId);

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

    /**
     * Quantité déjà livrée pour une ligne facture, y compris les BL
     * rattachés à la facture sans invoice_line_id (cas Devis → BL → Facture).
     */
    public function deliveredQuantityForInvoiceLine(InvoiceLine $line, bool $includeDraft = false, ?int $excludeNoteId = null): float
    {
        $direct = $this->deliveredQuantityForLine((int) $line->id, $includeDraft, $excludeNoteId);
        $invoice = $line->relationLoaded('invoice') ? $line->invoice : $line->invoice()->first();
        if (!$invoice) {
            return $direct;
        }

        $unlinked = $this->unlinkedDeliveredQuantityForInvoiceItem($invoice, $line, $includeDraft, $excludeNoteId);
        if ($unlinked <= 0) {
            return $direct;
        }

        $invoice->loadMissing('lines');
        $siblings = $invoice->lines
            ->filter(function (InvoiceLine $sibling) use ($line) {
                if ($line->item_id && $sibling->item_id) {
                    return (int) $sibling->item_id === (int) $line->item_id;
                }

                return trim((string) $sibling->item_name) === trim((string) $line->item_name);
            })
            ->sortBy('id');

        $remainingUnlinked = $unlinked;
        foreach ($siblings as $sibling) {
            $sibDirect = $this->deliveredQuantityForLine((int) $sibling->id, $includeDraft, $excludeNoteId);
            $need = max(0, (float) $sibling->quantity - $sibDirect);
            $take = min($need, $remainingUnlinked);
            $remainingUnlinked -= $take;
            if ((int) $sibling->id === (int) $line->id) {
                return $direct + $take;
            }
        }

        return $direct;
    }

    public function invoiceDeliveryStatus(Invoice $invoice): string
    {
        return $this->invoiceDeliveryProgress($invoice)['status'];
    }

    /**
     * @return array{status: string, ordered: float, delivered: float, remaining: float}
     */
    public function invoiceDeliveryProgress(Invoice $invoice, bool $includeDraft = false): array
    {
        if (!in_array($invoice->status, ['issued', 'partial', 'paid', 'draft'], true)) {
            return ['status' => 'n/a', 'ordered' => 0.0, 'delivered' => 0.0, 'remaining' => 0.0];
        }

        $invoice->loadMissing('lines');
        $ordered = round((float) $invoice->lines->sum(fn (InvoiceLine $line) => (float) $line->quantity), 3);

        if (!$this->isAvailable()) {
            return ['status' => 'pending', 'ordered' => $ordered, 'delivered' => 0.0, 'remaining' => $ordered];
        }

        $delivered = (float) DeliveryNoteLine::query()
            ->whereHas('deliveryNote', function ($q) use ($invoice, $includeDraft) {
                $this->constrainNotesBelongingToInvoice($q, $invoice);
                $this->constrainNoteStatus($q, $includeDraft);
            })
            ->sum('quantity');

        $delivered = round($delivered, 3);
        $remaining = max(0, round($ordered - $delivered, 3));

        $status = 'pending';
        if ($ordered <= 0 || $delivered + 0.0001 >= $ordered) {
            $status = 'delivered';
        } elseif ($delivered > 0.0001) {
            $status = 'partial';
        }

        return [
            'status' => $status,
            'ordered' => $ordered,
            'delivered' => $delivered,
            'remaining' => $remaining,
        ];
    }

    public function notesForInvoice(Invoice $invoice)
    {
        if (!$this->isAvailable()) {
            return collect();
        }

        return DeliveryNote::query()
            ->with(['creator', 'confirmer', 'lines'])
            ->where(function ($q) use ($invoice) {
                $this->constrainNotesBelongingToInvoice($q, $invoice);
            })
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Relie tous les BL confirmés d'un devis à la facture de commande.
     */
    public function attachQuotationDeliveriesToInvoice(Quotation $quotation, Invoice $invoice): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $notes = DeliveryNote::query()
            ->with('lines')
            ->where('quotation_id', $quotation->id)
            ->where('status', DeliveryNote::STATUS_CONFIRMED)
            ->where(function ($q) use ($invoice) {
                $q->whereNull('invoice_id')->orWhere('invoice_id', $invoice->id);
            })
            ->get();

        foreach ($notes as $note) {
            $this->attachToInvoice($note, $invoice);
        }
    }

    /**
     * Relie les lignes d'un BL aux lignes de la facture.
     * Plusieurs BL partiels peuvent pointer vers la même ligne de facture (reliquat).
     */
    public function attachToInvoice(DeliveryNote $note, Invoice $invoice): void
    {
        $note->invoice_id = $invoice->id;
        $note->save();

        $invoice->loadMissing('lines');
        $note->loadMissing('lines');

        foreach ($note->lines as $dnLine) {
            if ($dnLine->invoice_line_id) {
                continue;
            }

            $match = $invoice->lines->first(function ($il) use ($dnLine) {
                if ($dnLine->item_id && $il->item_id) {
                    return (int) $il->item_id === (int) $dnLine->item_id;
                }

                return trim((string) $il->item_name) === trim((string) $dnLine->item_name);
            });

            if ($match) {
                $dnLine->invoice_line_id = $match->id;
                $dnLine->save();
            }
        }
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
        return $this->invoiceDeliveryProgress($invoice, $includeDraft)['remaining'] > 0.0001;
    }

    private function unlinkedDeliveredQuantityForInvoiceItem(
        Invoice $invoice,
        InvoiceLine $line,
        bool $includeDraft,
        ?int $excludeNoteId
    ): float {
        return (float) DeliveryNoteLine::query()
            ->whereNull('invoice_line_id')
            ->where(function ($q) use ($line) {
                if ($line->item_id) {
                    $q->where('item_id', $line->item_id);
                } else {
                    $q->where('item_name', $line->item_name);
                }
            })
            ->whereHas('deliveryNote', function ($q) use ($invoice, $includeDraft, $excludeNoteId) {
                $this->constrainNotesBelongingToInvoice($q, $invoice);
                $this->constrainNoteStatus($q, $includeDraft, $excludeNoteId);
            })
            ->sum('quantity');
    }

    private function constrainNotesBelongingToInvoice($query, Invoice $invoice)
    {
        $query->where(function ($q) use ($invoice) {
            $q->where('invoice_id', $invoice->id);
            $number = trim((string) ($invoice->delivery_note_number ?? ''));
            if ($number !== '') {
                $q->orWhere('delivery_number', $number);
            }
            if ($invoice->quotation_id) {
                $q->orWhere('quotation_id', $invoice->quotation_id);
            }
        });

        return $query;
    }

    private function constrainNotesBelongingToQuotation($query, int $quotationId)
    {
        if ($quotationId <= 0) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        $query->where(function ($q) use ($quotationId) {
            $q->where('quotation_id', $quotationId)
                ->orWhereHas('invoice', function ($iq) use ($quotationId) {
                    $iq->where('quotation_id', $quotationId)
                        ->whereNotIn('status', ['cancelled']);
                });
        });

        return $query;
    }

    private function quotationLineIdForInvoiceLine(Invoice $invoice, ?InvoiceLine $invoiceLine): ?int
    {
        if (!$invoiceLine || !$invoice->quotation_id) {
            return null;
        }

        $invoice->loadMissing('quotation.lines');
        $qLines = $invoice->quotation?->lines;
        if (!$qLines || $qLines->isEmpty()) {
            return null;
        }

        $match = $qLines->first(function ($ql) use ($invoiceLine) {
            if ($invoiceLine->item_id && $ql->item_id) {
                return (int) $ql->item_id === (int) $invoiceLine->item_id;
            }

            return trim((string) $ql->item_name) === trim((string) $invoiceLine->item_name);
        });

        return $match ? (int) $match->id : null;
    }

    private function constrainNoteStatus($query, bool $includeDraft, ?int $excludeNoteId = null)
    {
        $query->whereIn('status', $includeDraft
            ? [DeliveryNote::STATUS_CONFIRMED, DeliveryNote::STATUS_DRAFT]
            : [DeliveryNote::STATUS_CONFIRMED]);

        if ($excludeNoteId) {
            $query->where('id', '!=', $excludeNoteId);
        }

        return $query;
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
