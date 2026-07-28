<?php

namespace InovCom\Invoicing\Support;

use App\Support\DocumentTaxCalculator;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Models\InvoiceLine;
use InovCom\Quotations\Models\QuotationLine;

class DeliveryNotePrintData
{
    /**
     * @return array{
     *   purchase_order: ?string,
     *   quotation_number: ?string,
     *   invoice_number: ?string,
     *   lines: array<int, array<string, mixed>>,
     *   subtotal: float,
     *   discount_percent: float,
     *   discount_amount: float,
     *   net_ht: float,
     *   tax_lines: array<int, array{tax_name:string,tax_mode:string,tax_rate:?float,tax_amount:float,tax_effect:string}>,
     *   tax_amount: float,
     *   ttc: float,
     *   total: float
     * }
     */
    public static function build(DeliveryNote $deliveryNote, ?string $purchaseOrderOverride = null): array
    {
        $deliveryNote->loadMissing([
            'lines.quotationLine',
            'lines.invoiceLine',
            'quotation.taxLines',
            'invoice.taxLines',
            'quotation',
            'invoice',
        ]);

        $purchaseOrder = trim((string) ($purchaseOrderOverride ?? ''));
        if ($purchaseOrder === '') {
            $purchaseOrder = trim((string) ($deliveryNote->customer_purchase_order ?? ''));
        }
        if ($purchaseOrder === '') {
            $purchaseOrder = trim((string) ($deliveryNote->quotation?->customer_purchase_order ?? ''));
        }
        if ($purchaseOrder === '') {
            $purchaseOrder = trim((string) ($deliveryNote->invoice?->customer_reference ?? ''));
        }

        $lines = [];
        $subtotal = 0.0;
        $lineIndex = 0;

        foreach ($deliveryNote->lines as $line) {
            $qty = (float) $line->quantity;
            /** @var QuotationLine|InvoiceLine|null $src */
            $src = $line->quotationLine ?? $line->invoiceLine;

            $unitPrice = $src ? (float) $src->unit_price : 0.0;
            $discountPerUnit = $src ? max(0.0, (float) ($src->line_discount ?? 0)) : 0.0;
            $orderedQty = $src ? max(0.001, (float) $src->quantity) : $qty;
            $unitPriceNet = $src && $src->unit_price_net !== null
                ? (float) $src->unit_price_net
                : max(0.0, $unitPrice - $discountPerUnit);

            $lineDiscountMode = 'amount';
            $lineDiscountInput = $discountPerUnit;
            if ($src instanceof QuotationLine || $src instanceof InvoiceLine) {
                $lineDiscountMode = (string) ($src->line_discount_mode ?? 'amount');
                if ($src->line_discount_input !== null) {
                    $lineDiscountInput = (float) $src->line_discount_input;
                }
            }

            if ($src && $orderedQty > 0 && abs($qty - $orderedQty) > 0.0001) {
                $lineTotal = round(($unitPriceNet * $qty), 2);
                if ($src instanceof QuotationLine || $src instanceof InvoiceLine) {
                    $lineTotal = round(((float) $src->line_total / $orderedQty) * $qty, 2);
                }
            } elseif ($src) {
                $lineTotal = (float) $src->line_total;
            } else {
                $lineTotal = round($unitPriceNet * $qty, 2);
            }

            $subtotal += $lineTotal;

            $lineNumber = 0;
            if ($src && isset($src->line_number) && (int) $src->line_number > 0) {
                $lineNumber = (int) $src->line_number;
            } else {
                $lineNumber = ($lineIndex + 1) * 10;
            }

            $lines[] = [
                'line_number' => $lineNumber,
                'item_name' => $line->item_name,
                'item_sku' => $line->item_sku,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'unit_price_net' => $unitPriceNet,
                'line_discount' => $discountPerUnit,
                'line_discount_mode' => $lineDiscountMode,
                'line_discount_input' => $lineDiscountInput,
                'line_discount_per_unit' => $discountPerUnit,
                'line_discount_total' => round($discountPerUnit * $qty, 2),
                'line_total' => $lineTotal,
            ];
            $lineIndex++;
        }

        $discountPercent = 0.0;
        $discountAmount = 0.0;
        $netHt = round($subtotal, 2);
        $taxSummary = DocumentTaxCalculator::summarizeFromStoredTaxLines($netHt, [], null);

        if ($deliveryNote->quotation) {
            $q = $deliveryNote->quotation;
            $discountPercent = (float) $q->discount_percent;
            if ($discountPercent > 0 && $subtotal > 0) {
                $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            } elseif ((float) $q->discount_amount > 0 && (float) $q->subtotal > 0) {
                $ratio = $subtotal / (float) $q->subtotal;
                $discountAmount = round((float) $q->discount_amount * $ratio, 2);
            }
            $taxSummary = self::taxSummaryForDelivery(
                $subtotal,
                $discountAmount,
                (float) $q->subtotal,
                (float) $q->discount_amount,
                $q->taxLines,
                $q->taxLines->isEmpty() ? (float) $q->tax_amount : null
            );
        } elseif ($deliveryNote->invoice) {
            $inv = $deliveryNote->invoice;
            $discountPercent = (float) $inv->discount_percent;
            if ($discountPercent > 0 && $subtotal > 0) {
                $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            } elseif ((float) $inv->discount_amount > 0 && (float) $inv->subtotal > 0) {
                $ratio = $subtotal / (float) $inv->subtotal;
                $discountAmount = round((float) $inv->discount_amount * $ratio, 2);
            }
            $taxSummary = self::taxSummaryForDelivery(
                $subtotal,
                $discountAmount,
                (float) $inv->subtotal,
                (float) $inv->discount_amount,
                $inv->taxLines,
                $inv->taxLines->isEmpty() ? (float) $inv->tax_amount : null
            );
        }

        $netHt = max(0, round($subtotal - $discountAmount, 2));

        return [
            'purchase_order' => $purchaseOrder !== '' ? $purchaseOrder : null,
            'quotation_number' => $deliveryNote->quotation?->number,
            'invoice_number' => $deliveryNote->invoice?->invoice_number,
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
            'discount_percent' => $discountPercent,
            'discount_amount' => round($discountAmount, 2),
            'net_ht' => $netHt,
            'tax_lines' => $taxSummary['lines'],
            'tax_amount' => $taxSummary['tax_amount'],
            'ttc' => $taxSummary['ttc'],
            'total' => $taxSummary['total'],
        ];
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $taxLines
     * @return array{
     *     lines: array<int, array{tax_name:string,tax_mode:string,tax_rate:?float,tax_amount:float,tax_effect:string}>,
     *     additive: float,
     *     subtractive: float,
     *     tax_amount: float,
     *     tax_rate: float,
     *     ttc: float,
     *     total: float
     * }
     */
    private static function taxSummaryForDelivery(
        float $deliveredSubtotal,
        float $deliveredDiscount,
        float $sourceSubtotal,
        float $sourceDiscount,
        iterable $taxLines,
        ?float $legacyTaxAmount
    ): array {
        $netHt = max(0, round($deliveredSubtotal - $deliveredDiscount, 2));
        $sourceNetHt = max(0.01, round($sourceSubtotal - $sourceDiscount, 2));
        $ratio = $sourceNetHt > 0 ? ($netHt / $sourceNetHt) : 1.0;

        $scaled = [];
        foreach ($taxLines as $line) {
            $isArray = is_array($line);
            $amount = round((float) ($isArray ? ($line['tax_amount'] ?? 0) : ($line->tax_amount ?? 0)) * $ratio, 2);
            if ($amount <= 0) {
                continue;
            }

            $scaled[] = [
                'tax_name' => (string) ($isArray ? ($line['tax_name'] ?? '') : ($line->tax_name ?? '')),
                'tax_mode' => (string) ($isArray ? ($line['tax_mode'] ?? 'amount') : ($line->tax_mode ?? 'amount')),
                'tax_rate' => $isArray ? ($line['tax_rate'] ?? null) : ($line->tax_rate ?? null),
                'tax_amount' => $amount,
                'tax_effect' => DocumentTaxCalculator::normalizeEffect(
                    (string) ($isArray ? ($line['tax_effect'] ?? DocumentTaxCalculator::EFFECT_ADD) : ($line->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD))
                ),
            ];
        }

        $legacy = $legacyTaxAmount !== null && abs($legacyTaxAmount) > 0
            ? round($legacyTaxAmount * $ratio, 2)
            : null;

        return DocumentTaxCalculator::summarizeFromStoredTaxLines($netHt, $scaled, $legacy);
    }
}
