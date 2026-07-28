<?php

namespace InovCom\Invoicing\Services;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use App\Support\DocumentTaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Models\InvoiceLine;
use InovCom\Invoicing\Models\InvoiceTaxLine;
use InovCom\Invoicing\Models\InvoiceSequence;
use InovCom\Quotations\Models\Quotation;

class InvoicingService
{
    public function createFromQuotation(Quotation $quotation, array $header, array $lines = [], bool $issue = true, ?int $userId = null): Invoice
    {
        if (!$quotation->canCreateInvoice()) {
            throw new \RuntimeException('Seul un devis validé peut être converti en facture.');
        }

        $declarationType = (string) ($header['declaration_type'] ?? '');
        if (!in_array($declarationType, ['declared', 'non_declared'], true)) {
            throw new \InvalidArgumentException('Type de facture invalide.');
        }

        if ($lines === []) {
            $lines = $quotation->lines->map(fn ($line) => [
                'item_id' => $line->item_id,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'line_discount' => (float) ($line->line_discount ?? 0),
                'line_discount_mode' => (string) ($line->line_discount_mode ?? 'amount'),
                'line_discount_input' => $line->line_discount_input !== null
                    ? (float) $line->line_discount_input
                    : (float) ($line->line_discount ?? 0),
                'line_total' => (float) $line->line_total,
            ])->all();
        }

        $header['client_id'] = $header['client_id'] ?? $quotation->client_id;
        $header['quotation_id'] = $quotation->id;
        $header['declaration_type'] = $declarationType;
        $header['invoice_date'] = $header['invoice_date'] ?? now()->toDateString();
        $header['due_date'] = $header['due_date'] ?? $quotation->valid_until?->toDateString();
        $header['notes'] = $header['notes'] ?? $quotation->notes;
        $header['quotation_reference'] = $header['quotation_reference'] ?? $quotation->number;
        $header['customer_reference'] = $header['customer_reference'] ?? $quotation->customer_purchase_order;
        $this->mergeQuotationDiscountHeader($quotation, $header);
        $header['issue'] = $issue;

        if (!array_key_exists('tax_lines', $header)) {
            $quotation->loadMissing('taxLines');
            $header['tax_lines'] = $quotation->taxLines
                ->map(fn ($t) => [
                    'tax_name' => (string) $t->tax_name,
                    'tax_mode' => ($t->tax_mode ?? 'amount') === 'percent' ? 'percent' : 'amount',
                    'tax_amount' => (float) $t->tax_amount,
                    'tax_rate' => ($t->tax_mode ?? 'amount') === 'percent' && $t->tax_rate !== null
                        ? (float) $t->tax_rate
                        : null,
                    'tax_effect' => DocumentTaxCalculator::normalizeEffect($t->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD),
                ])
                ->filter(fn ($t) => $t['tax_amount'] > 0)
                ->values()
                ->all();
            $header['tax_amount'] = (float) $quotation->tax_amount;
        }

        return $this->create($header, $lines, $userId);
    }

    /**
     * Crée une facture à partir d'un bon de livraison confirmé (workflow Devis → Livraison → Facture).
     * Les quantités facturées correspondent aux quantités livrées ; les prix/remises proviennent du devis.
     */
    public function createFromDeliveryNote(DeliveryNote $deliveryNote, array $header, array $lines = [], bool $issue = true, ?int $userId = null): Invoice
    {
        if (!$deliveryNote->isConfirmed()) {
            throw new \RuntimeException('Seul un bon de livraison validé peut être facturé.');
        }
        if ($deliveryNote->invoice_id) {
            throw new \RuntimeException('Ce bon de livraison est déjà rattaché à une facture.');
        }

        $declarationType = (string) ($header['declaration_type'] ?? '');
        if (!in_array($declarationType, ['declared', 'non_declared'], true)) {
            throw new \InvalidArgumentException('Type de facture invalide.');
        }

        $quotation = $deliveryNote->quotation;
        if ($quotation) {
            $quotation->loadMissing(['lines', 'taxLines']);
        }
        $deliveryNote->loadMissing('lines');

        // Les lignes (avec remises) viennent du formulaire ; on ne reconstruit depuis le devis
        // que si le formulaire n'en a fourni aucune (appel direct au service).
        if ($lines === []) {
            $quotationLinesById = $quotation ? $quotation->lines->keyBy('id') : collect();
            $lines = $deliveryNote->lines->map(function ($dnLine) use ($quotationLinesById) {
                $qLine = $dnLine->quotation_line_id ? $quotationLinesById->get($dnLine->quotation_line_id) : null;

                return [
                    'item_id' => $dnLine->item_id,
                    'item_name' => $dnLine->item_name,
                    'item_sku' => $dnLine->item_sku,
                    'quantity' => (float) $dnLine->quantity,
                    'unit_price' => $qLine ? (float) $qLine->unit_price : 0.0,
                    'line_discount' => $qLine ? (float) ($qLine->line_discount ?? 0) : 0.0,
                    'line_discount_mode' => $qLine ? (string) ($qLine->line_discount_mode ?? 'amount') : 'amount',
                    'line_discount_input' => $qLine && $qLine->line_discount_input !== null
                        ? (float) $qLine->line_discount_input
                        : ($qLine ? (float) ($qLine->line_discount ?? 0) : 0.0),
                ];
            })->all();
        }

        $header['client_id'] = $header['client_id'] ?? $deliveryNote->client_id ?? ($quotation->client_id ?? null);
        $header['quotation_id'] = $header['quotation_id'] ?? ($quotation->id ?? null);
        $header['declaration_type'] = $declarationType;
        $header['invoice_date'] = $header['invoice_date'] ?? now()->toDateString();
        $header['due_date'] = $header['due_date'] ?? ($quotation->valid_until?->toDateString() ?? null);
        $header['notes'] = $header['notes'] ?? ($quotation->notes ?? null);
        $header['quotation_reference'] = $header['quotation_reference'] ?? ($quotation->number ?? null);
        $header['delivery_note_number'] = $header['delivery_note_number'] ?? $deliveryNote->delivery_number;
        $header['customer_reference'] = $header['customer_reference'] ?? ($quotation->customer_purchase_order ?? null);
        if ($quotation) {
            $this->mergeQuotationDiscountHeader($quotation, $header);
        }
        $header['issue'] = $issue;

        // Taxes : repli sur celles du devis si le formulaire n'en a transmis aucune.
        if (empty($header['tax_lines']) && $quotation) {
            $header['tax_lines'] = $quotation->taxLines
                ->map(fn ($t) => [
                    'tax_name' => (string) $t->tax_name,
                    'tax_mode' => ($t->tax_mode ?? 'amount') === 'percent' ? 'percent' : 'amount',
                    'tax_amount' => (float) $t->tax_amount,
                    'tax_rate' => ($t->tax_mode ?? 'amount') === 'percent' && $t->tax_rate !== null
                        ? (float) $t->tax_rate
                        : null,
                    'tax_effect' => DocumentTaxCalculator::normalizeEffect($t->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD),
                ])
                ->filter(fn ($t) => $t['tax_amount'] > 0)
                ->values()
                ->all();
            $header['tax_amount'] = (float) $quotation->tax_amount;
        }

        $invoice = $this->create($header, $lines, $userId);

        $deliveryNote->invoice_id = $invoice->id;
        $deliveryNote->save();

        return $invoice;
    }

    public function create(array $header, array $lines, ?int $userId = null): Invoice
    {
        return DB::connection('tenant')->transaction(function () use ($header, $lines, $userId) {
            $declarationType = $header['declaration_type'];
            $issue = (bool) ($header['issue'] ?? false);

            $invoice = new Invoice();
            $invoice->invoice_number = $this->nextInvoiceNumber($declarationType);
            $invoice->declaration_type = $declarationType;
            $invoice->client_id = $header['client_id'];
            $invoice->quotation_id = $header['quotation_id'] ?? null;
            $invoice->invoice_date = $header['invoice_date'];
            $invoice->due_date = $header['due_date'] ?? null;
            $invoice->notes = $header['notes'] ?? null;
            $invoice->customer_reference = $header['customer_reference'] ?? null;
            $invoice->quotation_reference = $header['quotation_reference'] ?? null;
            $invoice->delivery_note_number = $header['delivery_note_number'] ?? null;
            $invoice->additional_info = $header['additional_info'] ?? null;
            $invoice->payment_mode = $header['payment_mode'] ?? null;
            $this->applyHeaderDiscount($invoice, $header);
            $invoice->tax_amount = (float) ($header['tax_amount'] ?? 0);
            $invoice->created_by = $userId ?? auth('tenant')->id();
            $invoice->status = $issue ? 'issued' : 'draft';
            $invoice->amount_paid = 0;
            $invoice->balance = 0;

            if (Schema::connection('tenant')->hasColumn('invoices', 'store_id')) {
                $invoice->store_id = app(StoreContextService::class)->currentStoreId();
            }

            $invoice->save();
            $this->syncLines($invoice, $lines);
            $this->syncTaxLines($invoice, $header['tax_lines'] ?? null, (float) ($header['tax_amount'] ?? 0));
            $this->recalculateInvoiceTotals($invoice);
            $invoice->balance = $invoice->total;
            $invoice->save();

            return $invoice->fresh(['lines', 'client', 'quotation']);
        });
    }

    public function update(Invoice $invoice, array $header, array $lines): Invoice
    {
        if (!$invoice->isEditable()) {
            throw new \RuntimeException('Cette facture ne peut plus être modifiée.');
        }

        return DB::connection('tenant')->transaction(function () use ($invoice, $header, $lines) {
            $invoice->client_id = $header['client_id'];
            $invoice->invoice_date = $header['invoice_date'];
            $invoice->due_date = $header['due_date'] ?? null;
            $invoice->notes = $header['notes'] ?? null;
            $invoice->customer_reference = $header['customer_reference'] ?? null;
            $invoice->quotation_reference = $header['quotation_reference'] ?? null;
            $invoice->delivery_note_number = $header['delivery_note_number'] ?? null;
            $invoice->additional_info = $header['additional_info'] ?? null;
            $invoice->payment_mode = $header['payment_mode'] ?? null;
            $this->applyHeaderDiscount($invoice, $header);
            $invoice->tax_amount = (float) ($header['tax_amount'] ?? 0);

            $invoice->lines()->delete();
            $this->syncLines($invoice, $lines);
            $this->syncTaxLines($invoice, $header['tax_lines'] ?? null, (float) ($header['tax_amount'] ?? 0));
            $this->recalculateInvoiceTotals($invoice);
            $invoice->balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
            $invoice->save();

            return $invoice->fresh(['lines', 'client', 'quotation']);
        });
    }

    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw new \RuntimeException('Seules les factures brouillon peuvent être émises.');
        }

        $invoice->status = 'issued';
        $invoice->balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
        $invoice->save();

        return $invoice->fresh();
    }

    /**
     * @return array<int, array{tax_name: string, tax_rate: mixed, tax_amount: float}>
     */
    public function proportionalTaxLines(Invoice $source, float $keptSubtotal): array
    {
        $source->loadMissing('taxLines');
        $sourceSubtotal = (float) $source->subtotal;
        if ($sourceSubtotal <= 0 || $source->taxLines->isEmpty()) {
            return [];
        }

        $ratio = $keptSubtotal / $sourceSubtotal;

        return $source->taxLines->map(fn ($t) => [
            'tax_name' => (string) $t->tax_name,
            'tax_rate' => $t->tax_rate,
            'tax_amount' => round((float) $t->tax_amount * $ratio, 2),
        ])->filter(fn ($t) => abs($t['tax_amount']) > 0.001)->values()->all();
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
            throw new \RuntimeException('Cette facture ne peut pas être annulée.');
        }

        if ($invoice->isDraft()) {
            throw new \RuntimeException('Supprimez le brouillon au lieu de l\'annuler.');
        }

        if ((float) $invoice->amount_paid > 0) {
            throw new \RuntimeException('Annulez d\'abord les paiements enregistrés.');
        }

        $invoice->status = 'cancelled';
        $invoice->balance = 0;
        $invoice->save();

        return $invoice->fresh();
    }

    public function deleteDraft(Invoice $invoice): void
    {
        if (!$invoice->isDraft()) {
            throw new \RuntimeException('Seuls les brouillons peuvent être supprimés.');
        }

        if ((float) $invoice->amount_paid > 0) {
            throw new \RuntimeException('Cette facture a des paiements enregistrés.');
        }

        DB::connection('tenant')->transaction(function () use ($invoice) {
            $invoice->lines()->delete();
            if (Schema::connection('tenant')->hasTable('invoice_tax_lines')) {
                $invoice->taxLines()->delete();
            }
            $invoice->delete();
        });
    }

    private function syncLines(Invoice $invoice, array $lines): void
    {
        $subtotal = 0.0;
        $allowNegative = $invoice->isCancellationDocument();

        foreach ($lines as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $price = (float) ($row['unit_price'] ?? 0);
            // Le formulaire envoie toujours line_discount en FCFA (PU − PU net).
            // Ne pas recalculer depuis le % (arrondi → perte de la remise exacte).
            if (array_key_exists('line_discount', $row)) {
                $lineDiscount = min($price, max(0, (float) $row['line_discount']));
            } else {
                $lineDiscount = $this->resolveLineDiscountAmount($price, $row);
            }
            $puNet = max(0, $price - $lineDiscount);
            if (array_key_exists('line_total', $row) && $allowNegative) {
                $lineTotal = round((float) $row['line_total'], 2);
            } else {
                $lineTotal = round($qty * $puNet, 2);
            }
            $subtotal += $lineTotal;

            $lineAttributes = array_filter([
                'item_id' => $row['item_id'] ?? null,
                'item_name' => $row['item_name'],
                'item_sku' => $row['item_sku'] ?? null,
                'line_number' => Schema::connection('tenant')->hasColumn('invoice_lines', 'line_number')
                    ? ((int) ($row['line_number'] ?? 0) ?: null)
                    : null,
                'quantity' => $qty,
                'unit_price' => $price,
                'unit_cost' => Schema::connection('tenant')->hasColumn('invoice_lines', 'unit_cost')
                    && array_key_exists('unit_cost', $row)
                    && $row['unit_cost'] !== null
                    && $row['unit_cost'] !== ''
                        ? (float) $row['unit_cost']
                        : null,
                'line_discount' => $lineDiscount,
                'unit_price_net' => $puNet,
                'line_total' => $lineTotal,
            ], fn ($v) => $v !== null);

            if (Schema::connection('tenant')->hasColumn('invoice_lines', 'line_discount_mode')) {
                $mode = (string) ($row['line_discount_mode'] ?? 'amount');
                $lineAttributes['line_discount_mode'] = $mode === 'percent' ? 'percent' : 'amount';
                $lineAttributes['line_discount_input'] = (float) ($row['line_discount_input'] ?? $row['line_discount'] ?? 0);
            }

            $invoice->lines()->save(new InvoiceLine($lineAttributes));
        }

        $discountAmount = $this->resolveDocumentDiscountAmount($invoice, $subtotal);

        $invoice->subtotal = round($subtotal, 2);
        $invoice->discount_amount = $discountAmount;
        if (Schema::connection('tenant')->hasColumn('invoices', 'discount_mode')
            && ($invoice->discount_mode ?? '') === 'percent'
            && (float) ($invoice->discount_percent ?? 0) <= 0
            && $subtotal > 0
            && $discountAmount > 0) {
            $invoice->discount_percent = round($discountAmount / $subtotal * 100, 2);
        }
    }

    private function recalculateInvoiceTotals(Invoice $invoice): void
    {
        $netHt = max(0, round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2));
        $invoice->loadMissing('taxLines');
        $computed = DocumentTaxCalculator::summarizeFromStoredTaxLines(
            $netHt,
            $invoice->taxLines,
            (float) ($invoice->tax_amount ?? 0)
        );

        $invoice->tax_amount = $computed['tax_amount'];
        $total = $computed['total'];
        $invoice->total = $invoice->isCancellationDocument() ? $total : max(0, $total);
    }

    /**
     * @param array<int, array{tax_name?:string, tax_rate?:mixed, tax_amount?:mixed}>|null $taxLines
     */
    private function syncTaxLines(Invoice $invoice, ?array $taxLines, float $taxAmountFallback): void
    {
        if (!Schema::connection('tenant')->hasTable('invoice_tax_lines')) {
            return;
        }

        $taxLines = is_array($taxLines) ? $taxLines : [];

        // Si aucune décomposition n'est fournie (anciens appels), on crée une ligne "TVA".
        if (count($taxLines) === 0 && $taxAmountFallback > 0) {
            $taxLines = [['tax_name' => 'TVA', 'tax_amount' => $taxAmountFallback]];
        }

        $invoice->taxLines()->delete();

        $sort = 0;
        foreach ($taxLines as $t) {
            $name = trim((string) ($t['tax_name'] ?? ''));
            $amount = (float) ($t['tax_amount'] ?? 0);
            $rate = $t['tax_rate'] ?? null;

            $allowNegative = $invoice->isCancellationDocument();
            if (!$allowNegative && $amount <= 0) {
                continue;
            }
            if ($allowNegative && abs($amount) < 0.001) {
                continue;
            }

            if ($name === '') {
                $name = 'Taxe';
            }

            $mode = ($t['tax_mode'] ?? null);
            if ($mode === null) {
                $mode = ($rate !== null && (float) $rate > 0) ? 'percent' : 'amount';
            }

            $payload = [
                'tax_name' => $name,
                'tax_rate' => $rate,
                'tax_amount' => round($amount, 2),
                'sort_order' => $sort++,
            ];

            if (Schema::connection('tenant')->hasColumn('invoice_tax_lines', 'tax_mode')) {
                $payload['tax_mode'] = in_array($mode, ['percent', 'amount'], true) ? $mode : 'amount';
            }
            if (Schema::connection('tenant')->hasColumn('invoice_tax_lines', 'tax_effect')) {
                $payload['tax_effect'] = DocumentTaxCalculator::normalizeEffect($t['tax_effect'] ?? DocumentTaxCalculator::EFFECT_ADD);
            }

            $invoice->taxLines()->save(new InvoiceTaxLine($payload));
        }
    }

    public function nextInvoiceNumber(string $declarationType): string
    {
        return DB::connection('tenant')->transaction(function () use ($declarationType) {
            $year = (int) now()->year;
            $prefix = $this->numberPrefix($declarationType);

            $sequence = InvoiceSequence::where('declaration_type', $declarationType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequence = InvoiceSequence::create([
                    'declaration_type' => $declarationType,
                    'year' => $year,
                    'last_number' => 0,
                ]);
                $sequence = InvoiceSequence::where('id', $sequence->id)->lockForUpdate()->first();
            }

            $sequence->last_number = (int) $sequence->last_number + 1;
            $sequence->save();

            // Format type TRAMS : FTH240011 / FTN240018 (préfixe + AA + séquence 4 chiffres)
            return $prefix . sprintf('%02d%04d', $year % 100, $sequence->last_number);
        });
    }

    private function numberPrefix(string $declarationType): string
    {
        $tenant = app(TenantManager::class)->tenant();

        if ($declarationType === 'declared') {
            return strtoupper((string) ($tenant?->getSetting('invoice_prefix_declared', 'FTH') ?? 'FTH'));
        }

        return strtoupper((string) ($tenant?->getSetting('invoice_prefix_non_declared', 'FTN') ?? 'FTN'));
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function mergeQuotationDiscountHeader(Quotation $quotation, array &$header): void
    {
        if (!array_key_exists('discount_mode', $header)) {
            $discountMode = (string) ($quotation->discount_mode ?? '');
            if ($discountMode === '' && Schema::connection('tenant')->hasColumn('quotations', 'discount_mode')) {
                $discountMode = (float) $quotation->discount_percent > 0
                    ? 'percent'
                    : ((float) $quotation->discount_amount > 0 ? 'amount' : 'percent');
            } elseif ($discountMode === '') {
                $discountMode = (float) $quotation->discount_percent > 0
                    ? 'percent'
                    : ((float) $quotation->discount_amount > 0 ? 'amount' : 'percent');
            }
            $header['discount_mode'] = $discountMode;
        }

        if (!array_key_exists('discount_percent', $header)) {
            $header['discount_percent'] = (float) $quotation->discount_percent;
        }

        if (!array_key_exists('discount_amount', $header)) {
            $header['discount_amount'] = (float) $quotation->discount_amount;
        }
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function applyHeaderDiscount(Invoice $invoice, array $header): void
    {
        $mode = (string) ($header['discount_mode'] ?? 'percent');
        if (Schema::connection('tenant')->hasColumn('invoices', 'discount_mode')) {
            $invoice->discount_mode = $mode === 'amount' ? 'amount' : 'percent';
        } elseif ($mode === '') {
            $mode = (float) ($header['discount_percent'] ?? 0) > 0
                ? 'percent'
                : ((float) ($header['discount_amount'] ?? 0) > 0 ? 'amount' : 'percent');
        }

        if ($mode === 'amount') {
            $invoice->discount_percent = 0;
            $invoice->discount_amount = max(0, (float) ($header['discount_amount'] ?? 0));
        } else {
            $invoice->discount_percent = (float) ($header['discount_percent'] ?? 0);
            if (Schema::connection('tenant')->hasColumn('invoices', 'discount_mode')) {
                $invoice->discount_amount = 0;
            }
        }
    }

    private function resolveDocumentDiscountAmount(Invoice $invoice, float $subtotal): float
    {
        $discountPercent = (float) ($invoice->discount_percent ?? 0);
        $storedAmount = (float) ($invoice->discount_amount ?? 0);
        $mode = 'percent';

        if (Schema::connection('tenant')->hasColumn('invoices', 'discount_mode')) {
            $mode = (string) ($invoice->discount_mode ?? 'percent');
        } elseif ($discountPercent <= 0 && $storedAmount > 0) {
            $mode = 'amount';
        }

        if ($mode === 'amount') {
            return min(max(0, $storedAmount), max(0, $subtotal));
        }

        if ($discountPercent > 0) {
            return round($subtotal * ($discountPercent / 100), 2);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveLineDiscountAmount(float $unitPrice, array $row): float
    {
        $input = max(0, (float) ($row['line_discount_input'] ?? $row['line_discount'] ?? 0));
        $mode = 'amount';

        if (Schema::connection('tenant')->hasColumn('invoice_lines', 'line_discount_mode')) {
            $mode = (string) ($row['line_discount_mode'] ?? 'amount') === 'percent' ? 'percent' : 'amount';
            if (array_key_exists('line_discount_input', $row)) {
                $input = max(0, (float) $row['line_discount_input']);
            }
        }

        if ($mode === 'percent') {
            return min($unitPrice, round($unitPrice * (min(100, $input) / 100), 2));
        }

        return min($unitPrice, max(0, (float) ($row['line_discount'] ?? $input)));
    }
}
