<?php

namespace InovCom\Quotations\Services;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use App\Support\DocumentLineNumbers;
use App\Support\DocumentTaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Quotations\Models\Quotation;
use InovCom\Quotations\Models\QuotationLine;
use InovCom\Quotations\Models\QuotationTaxLine;

class QuotationsService
{
    public static function tenantTaxRate(): float
    {
        $tenant = app(TenantManager::class)->tenant();

        return $tenant ? (float) $tenant->getSetting('tax_rate', 0) : 0.0;
    }

    public function create(array $header, array $lines, ?int $userId = null): Quotation
    {
        return DB::connection('tenant')->transaction(function () use ($header, $lines, $userId) {
            $quotation = new Quotation();
            $quotation->number = $this->generateNumber();
            $quotation->client_id = $header['client_id'];
            $quotation->parent_quotation_id = $header['parent_quotation_id'] ?? null;
            $quotation->revision = $header['revision'] ?? 1;
            $quotation->quote_date = $header['quote_date'];
            $quotation->valid_until = $header['valid_until'] ?? null;
            $quotation->status = 'draft';
            $quotation->notes = $header['notes'] ?? null;
            if (Schema::connection('tenant')->hasColumn('quotations', 'customer_purchase_order')) {
                $quotation->customer_purchase_order = $this->normalizePurchaseOrder($header['customer_purchase_order'] ?? null);
            }
            $quotation->created_by = $userId ?? auth('tenant')->id();

            if (Schema::connection('tenant')->hasColumn('quotations', 'store_id')) {
                $quotation->store_id = app(StoreContextService::class)->currentStoreId();
            }

            $this->applyHeaderPricing($quotation, $header);
            $quotation->save();
            $this->syncLines($quotation, $lines, $header['tax_lines'] ?? null);

            return $quotation->fresh(['lines', 'client']);
        });
    }

    public function update(Quotation $quotation, array $header, array $lines): Quotation
    {
        if (!$quotation->isEditable()) {
            throw new \RuntimeException('Ce devis ne peut plus être modifié.');
        }

        return DB::connection('tenant')->transaction(function () use ($quotation, $header, $lines) {
            $quotation->client_id = $header['client_id'];
            $quotation->quote_date = $header['quote_date'];
            $quotation->valid_until = $header['valid_until'] ?? null;
            $quotation->notes = $header['notes'] ?? null;
            if (Schema::connection('tenant')->hasColumn('quotations', 'customer_purchase_order')) {
                $quotation->customer_purchase_order = $this->normalizePurchaseOrder($header['customer_purchase_order'] ?? null);
            }

            $this->applyHeaderPricing($quotation, $header);
            $quotation->lines()->delete();
            $this->syncLines($quotation, $lines, $header['tax_lines'] ?? null);
            $quotation->save();

            return $quotation->fresh(['lines', 'client']);
        });
    }

    /**
     * Copie un devis existant (nouveau numéro, brouillon) pour gagner du temps.
     */
    public function duplicate(Quotation $source): Quotation
    {
        $source->loadMissing('lines');

        $lines = $source->lines->map(fn ($line) => [
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
            'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
            'markup_coefficient' => $line->markup_coefficient !== null ? (float) $line->markup_coefficient : null,
            'line_total' => (float) $line->line_total,
        ])->all();

        $source->loadMissing('taxLines');
        $applyTax = (bool) ($source->apply_tax ?? false);
        $taxLines = $source->taxLines->map(fn ($t) => [
            'tax_name' => (string) $t->tax_name,
            'tax_mode' => (string) ($t->tax_mode ?: 'amount'),
            'tax_rate' => $t->tax_rate !== null ? (float) $t->tax_rate : null,
            'tax_amount' => (float) $t->tax_amount,
        ])->all();

        $discountMode = (string) ($source->discount_mode ?? '');
        if (!in_array($discountMode, ['percent', 'amount'], true)) {
            $discountMode = (float) $source->discount_percent > 0
                ? 'percent'
                : ((float) $source->discount_amount > 0 ? 'amount' : 'percent');
        }

        return $this->create([
            'client_id' => $source->client_id,
            'quote_date' => now()->toDateString(),
            'valid_until' => $source->valid_until?->toDateString(),
            'notes' => $source->notes,
            'customer_purchase_order' => $source->customer_purchase_order,
            'discount_mode' => $discountMode,
            'discount_percent' => (float) $source->discount_percent,
            'discount_amount' => (float) $source->discount_amount,
            'apply_tax' => $applyTax,
            'tax_rate' => $applyTax ? self::tenantTaxRate() : 0,
            'tax_lines' => $taxLines,
            'show_markup_coefficient' => (bool) ($source->show_markup_coefficient ?? true),
        ], $lines);
    }

    public function setStatus(Quotation $quotation, string $status, ?int $userId = null): Quotation
    {
        $allowed = ['draft', 'sent', 'accepted', 'suspended', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Statut invalide.');
        }

        if ($status === 'accepted' && $quotation->status !== 'sent') {
            throw new \RuntimeException('Seuls les devis envoyés au client peuvent être marqués acceptés.');
        }

        if ($status === 'accepted') {
            $purchaseOrder = trim((string) ($quotation->customer_purchase_order ?? ''));
            if ($purchaseOrder === '') {
                throw new \RuntimeException(
                    'Le n° demande achat est obligatoire. Enregistrez le devis avec ce numéro avant de marquer accepté.'
                );
            }
        }

        if ($status === 'sent' && !in_array($quotation->status, ['draft', 'suspended'], true)) {
            throw new \RuntimeException('Ce devis ne peut pas être marqué comme envoyé.');
        }

        $quotation->status = $status;

        if ($status === 'accepted') {
            $quotation->validated_by = $userId ?? auth('tenant')->id();
            $quotation->validated_at = now();
            if (Schema::connection('tenant')->hasColumn('quotations', 'fulfillment_status')) {
                $quotation->fulfillment_status = 'pending';
            }
        } elseif (Schema::connection('tenant')->hasColumn('quotations', 'fulfillment_status')
            && in_array($status, ['draft', 'sent', 'suspended', 'rejected'], true)) {
            $quotation->fulfillment_status = 'none';
        }

        $quotation->save();

        return $quotation->fresh();
    }

    public function createRevision(Quotation $source): Quotation
    {
        $maxRevision = Quotation::where('parent_quotation_id', $source->parent_quotation_id ?? $source->id)
            ->max('revision');

        $parentId = $source->parent_quotation_id ?? $source->id;

        $lines = $source->lines->map(fn ($line) => [
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
            'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
            'markup_coefficient' => $line->markup_coefficient !== null ? (float) $line->markup_coefficient : null,
            'line_total' => (float) $line->line_total,
        ])->all();

        $source->loadMissing('taxLines');
        $taxLines = $source->taxLines->map(fn ($t) => [
            'tax_name' => (string) $t->tax_name,
            'tax_mode' => (string) ($t->tax_mode ?: 'amount'),
            'tax_rate' => $t->tax_rate !== null ? (float) $t->tax_rate : null,
            'tax_amount' => (float) $t->tax_amount,
        ])->all();

        $discountMode = (string) ($source->discount_mode ?? '');
        if (!in_array($discountMode, ['percent', 'amount'], true)) {
            $discountMode = (float) $source->discount_percent > 0
                ? 'percent'
                : ((float) $source->discount_amount > 0 ? 'amount' : 'percent');
        }

        return $this->create([
            'client_id' => $source->client_id,
            'parent_quotation_id' => $parentId,
            'revision' => max((int) $maxRevision, (int) $source->revision) + 1,
            'quote_date' => now()->toDateString(),
            'valid_until' => $source->valid_until?->toDateString(),
            'notes' => $source->notes,
            'customer_purchase_order' => $source->customer_purchase_order,
            'discount_mode' => $discountMode,
            'discount_percent' => (float) $source->discount_percent,
            'discount_amount' => (float) $source->discount_amount,
            'apply_tax' => (bool) ($source->apply_tax ?? false),
            'tax_rate' => ($source->apply_tax ?? false) ? self::tenantTaxRate() : 0,
            'tax_lines' => $taxLines,
            'show_markup_coefficient' => (bool) ($source->show_markup_coefficient ?? true),
        ], $lines);
    }

    private function normalizePurchaseOrder(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function applyHeaderPricing(Quotation $quotation, array $header): void
    {
        $mode = (string) ($header['discount_mode'] ?? 'percent');
        $mode = $mode === 'amount' ? 'amount' : 'percent';

        if (Schema::connection('tenant')->hasColumn('quotations', 'discount_mode')) {
            $quotation->discount_mode = $mode;
        }

        if ($mode === 'amount') {
            $quotation->discount_percent = 0;
            $quotation->discount_amount = max(0, (float) ($header['discount_amount'] ?? 0));
        } else {
            $quotation->discount_percent = (float) ($header['discount_percent'] ?? 0);
        }

        $taxLines = is_array($header['tax_lines'] ?? null) ? $header['tax_lines'] : [];
        $quotation->apply_tax = (bool) ($header['apply_tax'] ?? false) || count($taxLines) > 0;
        $quotation->tax_rate = $quotation->apply_tax ? (float) ($header['tax_rate'] ?? self::tenantTaxRate()) : 0;

        if (Schema::connection('tenant')->hasColumn('quotations', 'show_markup_coefficient')) {
            $quotation->show_markup_coefficient = (bool) ($header['show_markup_coefficient'] ?? true);
        }
    }

    private function syncLines(Quotation $quotation, array $lines, ?array $taxLines = null): void
    {
        $subtotal = 0.0;

        foreach ($lines as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $price = (float) ($row['unit_price'] ?? 0);
            $lineDiscount = (float) ($row['line_discount'] ?? 0);
            $puNet = max(0, $price - $lineDiscount);
            $lineTotal = round($qty * $puNet, 2);
            $subtotal += $lineTotal;

            $lineAttributes = [
                'item_id' => $row['item_id'] ?? null,
                'item_name' => $row['item_name'],
                'item_sku' => $row['item_sku'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'line_discount' => $lineDiscount,
                'unit_price_net' => $puNet,
                'line_total' => $lineTotal,
            ];

            if (Schema::connection('tenant')->hasColumn('quotation_lines', 'line_number')) {
                $lineAttributes['line_number'] = (int) ($row['line_number'] ?? 0) ?: null;
            }

            if (Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_mode')) {
                $mode = (string) ($row['line_discount_mode'] ?? 'amount');
                $lineAttributes['line_discount_mode'] = $mode === 'percent' ? 'percent' : 'amount';
                $lineAttributes['line_discount_input'] = (float) ($row['line_discount_input'] ?? $row['line_discount'] ?? 0);
            }

            $lineAttributes = array_merge($lineAttributes, $this->lineCostFields($row));

            $quotation->lines()->save(new QuotationLine($lineAttributes));
        }

        $this->recalculateTotals($quotation, $subtotal, $taxLines);
        $quotation->save();
    }

    public function recalculateTotals(Quotation $quotation, float $subtotal, ?array $taxLines = null): void
    {
        $discountPercent = (float) ($quotation->discount_percent ?? 0);
        $discountAmount = $discountPercent > 0
            ? round($subtotal * ($discountPercent / 100), 2)
            : (float) ($quotation->discount_amount ?? 0);
        $discountAmount = min($discountAmount, max(0, $subtotal));

        $netHt = max(0, $subtotal - $discountAmount);
        [$normalizedTaxLines, $taxAmount, $fallbackTaxRate] = $this->normalizeTaxLines($quotation, $netHt, $taxLines);
        $this->syncTaxLines($quotation, $normalizedTaxLines);

        $computed = DocumentTaxCalculator::summarize($netHt, array_map(fn ($line) => [
            'name' => $line['tax_name'],
            'mode' => $line['tax_mode'],
            'rate' => $line['tax_rate'],
            'amount' => $line['tax_amount'],
            'effect' => $line['tax_effect'] ?? DocumentTaxCalculator::EFFECT_ADD,
        ], $normalizedTaxLines));

        $quotation->subtotal = $subtotal;
        $quotation->discount_amount = $discountAmount;
        $quotation->apply_tax = ($computed['additive'] + $computed['subtractive']) > 0;
        $quotation->tax_rate = $fallbackTaxRate;
        $quotation->tax_amount = $computed['tax_amount'];
        $quotation->total = $computed['total'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, float|null>
     */
    private function lineCostFields(array $row): array
    {
        if (!Schema::connection('tenant')->hasColumn('quotation_lines', 'unit_cost')) {
            return [];
        }

        return [
            'unit_cost' => ($row['unit_cost'] ?? '') !== '' && $row['unit_cost'] !== null
                ? (float) $row['unit_cost']
                : null,
            'markup_coefficient' => ($row['markup_coefficient'] ?? '') !== '' && $row['markup_coefficient'] !== null
                ? (float) $row['markup_coefficient']
                : null,
        ];
    }

    /**
     * @param array<int, array{tax_name?:string, tax_mode?:string, tax_rate?:mixed, tax_amount?:mixed}>|null $taxLines
     * @return array{0: array<int, array{tax_name:string,tax_mode:string,tax_rate:?float,tax_amount:float}>, 1: float, 2: float}
     */
    private function normalizeTaxLines(Quotation $quotation, float $netHt, ?array $taxLines = null): array
    {
        $source = is_array($taxLines) ? $taxLines : [];
        $computed = DocumentTaxCalculator::summarize($netHt, array_map(function ($line) {
            return [
                'name' => $line['tax_name'] ?? '',
                'mode' => $line['tax_mode'] ?? 'amount',
                'rate' => $line['tax_rate'] ?? null,
                'amount' => $line['tax_amount'] ?? 0,
                'effect' => $line['tax_effect'] ?? DocumentTaxCalculator::EFFECT_ADD,
            ];
        }, $source));

        if (count($computed['lines']) === 0 && (bool) ($quotation->apply_tax ?? false)) {
            $rate = max(0, (float) ($quotation->tax_rate ?? self::tenantTaxRate()));
            if ($rate > 0) {
                $computed = DocumentTaxCalculator::summarize($netHt, [[
                    'name' => 'TVA',
                    'mode' => 'percent',
                    'rate' => $rate,
                    'effect' => DocumentTaxCalculator::EFFECT_ADD,
                ]]);
            }
        }

        return [$computed['lines'], $computed['tax_amount'], (float) ($computed['tax_rate'] ?? 0)];
    }

    /**
     * @param array<int, array{tax_name:string,tax_mode:string,tax_rate:?float,tax_amount:float}> $taxLines
     */
    private function syncTaxLines(Quotation $quotation, array $taxLines): void
    {
        if (!Schema::connection('tenant')->hasTable('quotation_tax_lines')) {
            return;
        }

        $quotation->taxLines()->delete();
        $sort = 0;
        foreach ($taxLines as $line) {
            $quotation->taxLines()->save(new QuotationTaxLine([
                'tax_name' => $line['tax_name'],
                'tax_mode' => $line['tax_mode'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'tax_effect' => DocumentTaxCalculator::normalizeEffect($line['tax_effect'] ?? DocumentTaxCalculator::EFFECT_ADD),
                'sort_order' => $sort++,
            ]));
        }
    }

    private function generateNumber(): string
    {
        $year = now()->year;
        $last = Quotation::whereYear('created_at', $year)->orderByDesc('id')->value('number');
        $next = 1;

        if ($last && preg_match('/^DEV-\d{4}-(\d+)$/i', (string) $last, $matches)) {
            $seq = (int) $matches[1];
            if ($seq > 0 && $seq < 999999) {
                $next = $seq + 1;
            }
        }

        return 'DEV-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
