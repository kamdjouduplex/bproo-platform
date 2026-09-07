<?php

namespace InovCom\InvoicePayments\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InovCom\InvoicePayments\Models\InvoicePayment;

class CollectionPeriodRecap
{
    private const MONTHS_FR = [
        1 => 'janvier',
        2 => 'février',
        3 => 'mars',
        4 => 'avril',
        5 => 'mai',
        6 => 'juin',
        7 => 'juillet',
        8 => 'août',
        9 => 'septembre',
        10 => 'octobre',
        11 => 'novembre',
        12 => 'décembre',
    ];

    /**
     * @return array{
     *     previous_label: string,
     *     current_label: string,
     *     previous: array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float},
     *     current: array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float}
     * }
     */
    public static function forNow(?CarbonInterface $now = null): array
    {
        $windows = self::windows($now ?? now());

        return [
            'previous_label' => $windows['previous']['label'],
            'current_label' => $windows['current']['label'],
            'previous' => self::totalsBetween($windows['previous']['from'], $windows['previous']['to']),
            'current' => self::totalsBetween($windows['current']['from'], $windows['current']['to']),
        ];
    }

    /**
     * @return array{
     *     previous: array{from: string, to: string, label: string},
     *     current: array{from: string, to: string, label: string}
     * }
     */
    public static function windows(CarbonInterface $now): array
    {
        $currentStart = Carbon::parse($now)->copy()->startOfMonth();
        $previousStart = $currentStart->copy()->subMonthNoOverflow();

        return [
            'previous' => [
                'from' => $previousStart->toDateString(),
                'to' => $previousStart->copy()->endOfMonth()->toDateString(),
                'label' => self::monthLabel($previousStart),
            ],
            'current' => [
                'from' => $currentStart->toDateString(),
                'to' => $currentStart->copy()->endOfMonth()->toDateString(),
                'label' => self::monthLabel($currentStart),
            ],
        ];
    }

    /**
     * @param  iterable<InvoicePayment>  $payments
     * @return array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float}
     */
    public static function fromPayments(iterable $payments): array
    {
        $totals = PaymentSettlementLines::emptyFiscalSlice();

        foreach ($payments as $payment) {
            $invoice = $payment->invoice;
            if (! $invoice) {
                continue;
            }

            $totals = PaymentSettlementLines::addFiscalSlices(
                $totals,
                PaymentSettlementLines::fiscalSlice(PaymentSettlementLines::fromModels($invoice, $payment))
            );
        }

        return $totals;
    }

    /**
     * @return array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float}
     */
    public static function totalsBetween(string $from, string $to): array
    {
        $payments = InvoicePayment::query()
            ->active()
            ->with(array_merge(
                ['invoice.taxLines'],
                InvoicePayment::optionalWithholdingsRelation(),
            ))
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->get();

        return self::fromPayments($payments);
    }

    public static function monthLabel(CarbonInterface $date): string
    {
        $month = self::MONTHS_FR[(int) $date->month] ?? $date->format('m');

        return $month.' '.$date->year;
    }
}
