<?php

namespace InovCom\Sales\Services;

use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Item;
use InovCom\Items\Services\ItemSetService;
use InovCom\Kernel\Contracts\BatchesApi;
use InovCom\Sales\Models\Payment;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleLine;
use InovCom\Sales\Models\SaleReturn;
use InovCom\Sales\Models\SaleReturnLine;
use InovCom\Sales\Models\SaleReturnRefund;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaleReturnsService
{
    public function __construct(
        private StockService $stockService
    ) {
    }

    /**
     * @param  array<int, array{sale_line_id: int, quantity: float}>  $lineInputs
     */
    public function createReturn(
        Sale $sale,
        array $lineInputs,
        ?string $reason = null,
        ?string $notes = null,
        ?int $userId = null
    ): SaleReturn {
        $sale->loadMissing(['lines', 'payments', 'client']);

        return DB::connection('tenant')->transaction(function () use ($sale, $lineInputs, $reason, $notes, $userId) {
            $userId = $userId ?? auth('tenant')->id();
            $normalized = $this->normalizeLineInputs($sale, $lineInputs);

            if ($normalized === []) {
                throw new \InvalidArgumentException('Sélectionnez au moins une ligne à retourner.');
            }

            [$subtotalRefund, $discountRefund, $totalRefund, $returnLinesData] = $this->calculateRefunds($sale, $normalized);
            $isFull = $this->isFullReturn($sale, $normalized);

            $saleReturn = SaleReturn::create([
                'return_number' => $this->generateReturnNumber(),
                'sale_id' => $sale->id,
                'status' => SaleReturn::STATUS_CONFIRMED,
                'type' => $isFull ? SaleReturn::TYPE_FULL : SaleReturn::TYPE_PARTIAL,
                'return_date' => now()->toDateString(),
                'subtotal_refund' => $subtotalRefund,
                'discount_refund' => $discountRefund,
                'total_refund' => $totalRefund,
                'reason' => $reason,
                'notes' => $notes,
                'store_id' => $sale->store_id,
                'created_by' => $userId,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            $batchesApi = app()->bound(BatchesApi::class) ? app(BatchesApi::class) : null;
            $batchesAvailable = $batchesApi && $batchesApi->isAvailable();
            $storeId = $sale->store_id;

            foreach ($returnLinesData as $row) {
                /** @var SaleLine $saleLine */
                $saleLine = $row['sale_line'];

                SaleReturnLine::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_line_id' => $saleLine->id,
                    'item_id' => $saleLine->item_id,
                    'batch_id' => $saleLine->batch_id,
                    'quantity' => $row['quantity'],
                    'quantity_base' => $row['quantity_base'],
                    'unit_price' => $saleLine->unit_price,
                    'line_refund' => $row['line_refund'],
                ]);

                $this->restoreStock(
                    $saleLine,
                    $row['quantity_base'],
                    $saleReturn,
                    $batchesApi,
                    $batchesAvailable,
                    $storeId,
                    $userId
                );
            }

            $this->applyFinancialReversals($sale, $saleReturn, $totalRefund, $userId);

            return $saleReturn->fresh(['lines', 'refunds', 'sale']);
        });
    }

    public function returnableQuantity(SaleLine $line): float
    {
        $sold = (float) $line->quantity;
        if ($sold <= 0) {
            return 0.0;
        }

        if (!Schema::connection('tenant')->hasTable('sale_return_lines')) {
            return $sold;
        }

        $returned = (float) SaleReturnLine::query()
            ->where('sale_line_id', $line->id)
            ->whereHas('saleReturn', fn ($q) => $q->where('status', SaleReturn::STATUS_CONFIRMED))
            ->sum('quantity');

        return max(0, round($sold - $returned, 3));
    }

    /**
     * @return array<int, array{sale_line: SaleLine, quantity: float, quantity_base: float, line_refund: float}>
     */
    private function normalizeLineInputs(Sale $sale, array $lineInputs): array
    {
        $byLineId = [];
        foreach ($lineInputs as $input) {
            $lineId = (int) ($input['sale_line_id'] ?? 0);
            $qty = round((float) ($input['quantity'] ?? 0), 3);
            if ($lineId <= 0 || $qty <= 0) {
                continue;
            }
            $byLineId[$lineId] = ($byLineId[$lineId] ?? 0) + $qty;
        }

        $normalized = [];
        foreach ($byLineId as $lineId => $qty) {
            $saleLine = $sale->lines->firstWhere('id', $lineId);
            if (!$saleLine) {
                throw new \InvalidArgumentException("Ligne de vente introuvable (#{$lineId}).");
            }

            $max = $this->returnableQuantity($saleLine);
            if ($qty > $max + 0.0001) {
                throw new \InvalidArgumentException(
                    "Quantité retournée trop élevée pour « {$saleLine->item_name} » (max {$max})."
                );
            }

            $factor = (float) ($saleLine->conversion_factor ?: 1);
            $normalized[] = [
                'sale_line' => $saleLine,
                'quantity' => $qty,
                'quantity_base' => round($qty * $factor, 3),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{sale_line: SaleLine, quantity: float, quantity_base: float}>  $normalized
     * @return array{0: float, 1: float, 2: float, 3: array<int, array{sale_line: SaleLine, quantity: float, quantity_base: float, line_refund: float}>}
     */
    private function calculateRefunds(Sale $sale, array $normalized): array
    {
        $returnLinesData = [];
        $subtotalRefund = 0.0;

        foreach ($normalized as $row) {
            /** @var SaleLine $saleLine */
            $saleLine = $row['sale_line'];
            $soldQty = (float) $saleLine->quantity;
            $ratio = $soldQty > 0 ? $row['quantity'] / $soldQty : 0;
            $lineRefund = round((float) $saleLine->line_total * $ratio, 2);

            $returnLinesData[] = [
                'sale_line' => $saleLine,
                'quantity' => $row['quantity'],
                'quantity_base' => $row['quantity_base'],
                'line_refund' => $lineRefund,
            ];
            $subtotalRefund += $lineRefund;
        }

        $saleSubtotal = (float) $sale->subtotal;
        $discountRefund = 0.0;
        if ($saleSubtotal > 0 && (float) $sale->discount_amount > 0) {
            $discountRefund = round(((float) $sale->discount_amount) * ($subtotalRefund / $saleSubtotal), 2);
        }

        $totalRefund = max(0, round($subtotalRefund - $discountRefund, 2));

        return [$subtotalRefund, $discountRefund, $totalRefund, $returnLinesData];
    }

    /**
     * @param  array<int, array{sale_line: SaleLine, quantity: float, quantity_base: float}>  $normalized
     */
    private function isFullReturn(Sale $sale, array $normalized): bool
    {
        foreach ($sale->lines as $line) {
            $requested = 0.0;
            foreach ($normalized as $row) {
                if ($row['sale_line']->id === $line->id) {
                    $requested = $row['quantity'];
                    break;
                }
            }
            if (abs($requested - $this->returnableQuantity($line)) > 0.0001) {
                return false;
            }
        }

        return true;
    }

    private function restoreStock(
        SaleLine $saleLine,
        float $quantityBase,
        SaleReturn $saleReturn,
        ?BatchesApi $batchesApi,
        bool $batchesAvailable,
        ?int $storeId,
        ?int $userId
    ): void {
        if ($quantityBase <= 0 || !Schema::connection('tenant')->hasTable('stock_levels')) {
            return;
        }

        $meta = is_array($saleLine->metadata ?? null) ? $saleLine->metadata : [];
        if (!empty($meta['is_set']) && !empty($meta['set_components']) && app()->bound(ItemSetService::class)) {
            $factor = max(0.0001, (float) ($saleLine->conversion_factor ?: 1));
            $returnedSets = $quantityBase / $factor;

            foreach ($meta['set_components'] as $component) {
                $perSet = (float) ($component['quantity_per_set'] ?? 0);
                $componentId = (int) ($component['item_id'] ?? 0);
                if ($componentId <= 0 || $perSet <= 0) {
                    continue;
                }

                $qtyRestore = round($perSet * $returnedSets, 3);
                if ($qtyRestore <= 0) {
                    continue;
                }

                if (!app()->bound(StockService::class)) {
                    continue;
                }

                $this->stockService->addStock(
                    $componentId,
                    $qtyRestore,
                    'in',
                    'sale_return',
                    $saleReturn->id,
                    "Retour lot {$saleReturn->return_number}",
                    $userId,
                    $storeId
                );
            }

            return;
        }

        $itemId = (int) $saleLine->item_id;
        $item = Item::find($itemId);
        $batchTracked = $item && is_array($item->metadata ?? null) && !empty($item->metadata['batch_tracked']);

        if ($batchesAvailable && $batchTracked && $saleLine->batch_id && $batchesApi) {
            $batchesApi->restoreToBatch(
                (int) $saleLine->batch_id,
                $quantityBase,
                'sale_return_line',
                $saleReturn->id
            );

            return;
        }

        if (!app()->bound(StockService::class)) {
            return;
        }

        $this->stockService->addStock(
            $itemId,
            $quantityBase,
            'in',
            'sale_return',
            $saleReturn->id,
            "Retour vente {$saleReturn->return_number}",
            $userId,
            $storeId
        );
    }

    private function applyFinancialReversals(Sale $sale, SaleReturn $saleReturn, float $totalRefund, ?int $userId): void
    {
        if ($totalRefund <= 0) {
            return;
        }

        $saleTotal = (float) $sale->total;
        if ($saleTotal <= 0) {
            return;
        }

        $payments = $sale->payments->filter(fn ($p) => (float) $p->amount > 0);
        if ($payments->isEmpty()) {
            return;
        }

        $paymentsTotal = (float) $payments->sum('amount');
        $ratio = min(1, $totalRefund / $saleTotal);
        $allocated = 0.0;
        $refundRows = [];
        $count = $payments->count();
        $index = 0;

        foreach ($payments as $payment) {
            $index++;
            $share = $paymentsTotal > 0
                ? round(((float) $payment->amount / $paymentsTotal) * $totalRefund, 2)
                : 0.0;

            if ($index === $count) {
                $share = round($totalRefund - $allocated, 2);
            } else {
                $allocated += $share;
            }

            if ($share <= 0) {
                continue;
            }

            $refundRows[] = ['payment' => $payment, 'amount' => $share, 'method' => $payment->method];
        }

        $tillRefund = 0.0;

        foreach ($refundRows as $row) {
            $refundRecord = SaleReturnRefund::create([
                'sale_return_id' => $saleReturn->id,
                'payment_id' => $row['payment']->id,
                'method' => $row['method'],
                'amount' => $row['amount'],
            ]);

            if ($row['method'] === 'credit' && $sale->client_id) {
                $client = Client::on('tenant')->find($sale->client_id);
                if ($client) {
                    $client->current_balance = max(0, (float) $client->current_balance - $row['amount']);
                    $client->save();
                }
            } else {
                $tillRefund += $row['amount'];

                // Auto-capture caisse : seul le remboursement en espèces sort du tiroir.
                if ($row['method'] === 'cash') {
                    \App\Support\CashLedger::recordOut(
                        \App\Support\CashLedger::SALE_RETURN_CASH_OUT,
                        (float) $row['amount'],
                        'Remboursement retour ' . $saleReturn->return_number,
                        'sale_return',
                        SaleReturnRefund::class,
                        (int) $refundRecord->id,
                        $saleReturn->return_number,
                        ['sale_id' => $sale->id, 'sale_return_id' => $saleReturn->id],
                        $userId
                    );
                }
            }
        }

    }

    public static function totalRefundsBetween(string $startDate, string $endDate): float
    {
        if (!Schema::connection('tenant')->hasTable('sale_returns')) {
            return 0.0;
        }

        $query = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_CONFIRMED)
            ->whereBetween('return_date', [$startDate, $endDate]);

        if (Schema::connection('tenant')->hasColumn('sale_returns', 'store_id')) {
            $storeId = app(\App\Services\StoreContextService::class)->currentStoreId();
            if ($storeId) {
                $query->where('store_id', $storeId);
            }
        }

        return (float) $query->sum('total_refund');
    }

    private function generateReturnNumber(): string
    {
        $year = now()->year;
        $last = SaleReturn::whereYear('return_date', $year)->orderByDesc('id')->first();
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last->return_number, $m)) {
            $next = (int) $m[1] + 1;
        }

        return 'RET-' . $year . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
