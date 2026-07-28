<?php

namespace InovCom\Purchases\Services;

use Illuminate\Database\Eloquent\Model;
use InovCom\Purchases\Models\ForeignPurchaseOrder;
use InovCom\Purchases\Models\ForeignReceiptNote;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Models\ReceiptNote;

class PurchaseDocumentNumberService
{
    public const ORDER_PREFIX = 'ACH';
    public const FOREIGN_ORDER_PREFIX = 'AET';
    public const FOREIGN_RECEIPT_PREFIX = 'REA';
    public const RECEIPT_PREFIX = 'REC';
    public const SEQUENCE_LENGTH = 5;

    public function nextOrderNumber(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return $this->format(self::ORDER_PREFIX, $year, $this->maxSequence(PurchaseOrder::class, 'order_number', self::ORDER_PREFIX, $year) + 1);
    }

    public function nextForeignOrderNumber(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return $this->format(
            self::FOREIGN_ORDER_PREFIX,
            $year,
            $this->maxSequence(ForeignPurchaseOrder::class, 'order_number', self::FOREIGN_ORDER_PREFIX, $year) + 1
        );
    }

    public function nextForeignReceiptNumber(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return $this->format(
            self::FOREIGN_RECEIPT_PREFIX,
            $year,
            $this->maxSequence(ForeignReceiptNote::class, 'receipt_number', self::FOREIGN_RECEIPT_PREFIX, $year) + 1
        );
    }

    public function nextReceiptNumber(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return $this->format(self::RECEIPT_PREFIX, $year, $this->maxSequence(ReceiptNote::class, 'receipt_number', self::RECEIPT_PREFIX, $year) + 1);
    }

    public function format(string $prefix, int $year, int $sequence): string
    {
        return sprintf(
            '%s-%d-%s',
            $prefix,
            $year,
            str_pad((string) max(1, $sequence), self::SEQUENCE_LENGTH, '0', STR_PAD_LEFT)
        );
    }

    /**
     * Extrait le numéro séquentiel (partie après le dernier tiret du motif PREFIX-ANNÉE-SEQ).
     */
    public function extractSequence(string $documentNumber, string $prefix, int $year): ?int
    {
        $documentNumber = trim($documentNumber);
        if ($documentNumber === '' || $this->isCorrupted($documentNumber)) {
            return null;
        }

        if (preg_match('/^' . preg_quote($prefix, '/') . '-' . $year . '-(\d+)$/i', $documentNumber, $matches)) {
            $seq = (int) $matches[1];

            return ($seq > 0 && $seq <= 99999) ? $seq : null;
        }

        return null;
    }

    public function isCorrupted(string $documentNumber): bool
    {
        if (str_contains(strtoupper($documentNumber), 'E+')) {
            return true;
        }

        return preg_match('/^(ACH|REC|AET|REA)-\d{4}-\d{1,5}$/i', $documentNumber) !== 1;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function maxSequence(string $modelClass, string $column, string $prefix, int $year): int
    {
        $pattern = $prefix . '-' . $year . '-%';
        $numbers = $modelClass::query()
            ->where($column, 'like', $pattern)
            ->pluck($column);

        $max = 0;
        foreach ($numbers as $number) {
            $seq = $this->extractSequence((string) $number, $prefix, $year);
            if ($seq !== null && $seq > $max) {
                $max = $seq;
            }
        }

        return $max;
    }

    /**
     * Renumérote toutes les commandes / réceptions par année (ordre chronologique id).
     *
     * @return array{orders: int, receipts: int}
     */
    public function renumberAllExisting(): array
    {
        $ordersFixed = 0;
        $receiptsFixed = 0;

        \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use (&$ordersFixed, &$receiptsFixed) {
            $ordersFixed = $this->renumberOrders();
            $receiptsFixed = $this->renumberReceipts();
        });

        return ['orders' => $ordersFixed, 'receipts' => $receiptsFixed];
    }

    private function renumberOrders(): int
    {
        $orders = PurchaseOrder::query()->orderBy('order_date')->orderBy('id')->get();
        if ($orders->isEmpty()) {
            return 0;
        }

        foreach ($orders as $order) {
            $order->order_number = '__TMP_ACH_' . $order->id;
            $order->save();
        }

        $byYear = $orders->groupBy(fn ($o) => (int) $o->order_date->format('Y'));
        $count = 0;

        foreach ($byYear as $year => $yearOrders) {
            $seq = 1;
            foreach ($yearOrders as $order) {
                $order->order_number = $this->format(self::ORDER_PREFIX, (int) $year, $seq);
                $order->save();
                $seq++;
                $count++;
            }
        }

        return $count;
    }

    private function renumberReceipts(): int
    {
        $receipts = ReceiptNote::query()->orderBy('receipt_date')->orderBy('id')->get();
        if ($receipts->isEmpty()) {
            return 0;
        }

        foreach ($receipts as $receipt) {
            $receipt->receipt_number = '__TMP_REC_' . $receipt->id;
            $receipt->save();
        }

        $byYear = $receipts->groupBy(fn ($r) => (int) $r->receipt_date->format('Y'));
        $count = 0;

        foreach ($byYear as $year => $yearReceipts) {
            $seq = 1;
            foreach ($yearReceipts as $receipt) {
                $receipt->receipt_number = $this->format(self::RECEIPT_PREFIX, (int) $year, $seq);
                $receipt->save();
                $seq++;
                $count++;
            }
        }

        return $count;
    }
}
