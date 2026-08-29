<?php

namespace InovCom\InvoicePayments\Support;

class WithholdingCalculator
{
    /**
     * FCFA (XAF/XOF) has no circulating centimes: amounts are whole francs,
     * as banks and the tax administration book them (ISO 4217 minor units = 0).
     */
    public static function roundMoney(float $amount): float
    {
        return round($amount, 0, PHP_ROUND_HALF_UP);
    }

    public static function amountFromBaseAndRate(float $base, float $ratePercent): float
    {
        return self::roundMoney(max(0, $base) * max(0, $ratePercent) / 100);
    }

    /**
     * Cash the cashier should receive so that cash + withholdings = balance.
     *
     * @param  list<array{amount?: float|int|string|null}>  $withholdings
     */
    public static function cashDue(float $balance, array $withholdings): float
    {
        $balance = self::roundMoney(max(0, $balance));
        $withholdingTotal = self::withholdingTotal($withholdings);

        return max(0, self::roundMoney($balance - $withholdingTotal));
    }

    /**
     * @param  list<array{amount?: float|int|string|null}>  $withholdings
     */
    public static function withholdingTotal(array $withholdings): float
    {
        $total = 0.0;
        foreach ($withholdings as $row) {
            $total += self::roundMoney((float) ($row['amount'] ?? 0));
        }

        return self::roundMoney($total);
    }

    /**
     * @param  list<array{amount?: float|int|string|null}>  $withholdings
     * @return array{
     *     invoice_total: float,
     *     cash_received: float,
     *     withholding_total: float,
     *     settled: float,
     *     balance_before: float,
     *     remaining: float,
     *     exceeds: bool
     * }
     */
    public static function summarize(
        float $invoiceTotal,
        float $alreadyPaid,
        float $cashReceived,
        array $withholdings
    ): array {
        $withholdingTotal = self::withholdingTotal($withholdings);
        $cashReceived = self::roundMoney(max(0, $cashReceived));
        $settled = self::roundMoney($cashReceived + $withholdingTotal);
        $balanceBefore = self::roundMoney($invoiceTotal - $alreadyPaid);
        $remaining = self::roundMoney($balanceBefore - $settled);

        return [
            'invoice_total' => self::roundMoney($invoiceTotal),
            'cash_received' => $cashReceived,
            'withholding_total' => $withholdingTotal,
            'settled' => $settled,
            'balance_before' => $balanceBefore,
            'remaining' => $remaining,
            'exceeds' => $settled > $balanceBefore,
        ];
    }
}
