<?php

namespace InovCom\InvoicePayments\Support;

use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\Invoicing\Models\Invoice;

class PaymentSettlementLines
{
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIAL = 'partial';

    /**
     * @return array{
     *     lines: list<array{label: string, amount: float, nature: string, display: string}>,
     *     ttc: float,
     *     cash: float,
     *     status: string,
     *     status_label: string,
     *     cancelled: bool
     * }
     */
    public static function fromModels(Invoice $invoice, InvoicePayment $payment): array
    {
        $invoice->loadMissing('taxLines');
        $fiscal = InvoiceFiscalBreakdown::fromInvoice($invoice);

        $withholdings = [];
        foreach ($payment->withholdings as $row) {
            $withholdings[] = [
                'type_code' => $row->type_code ?? null,
                'type_name' => $row->type_name ?? null,
                'kind' => $row->type?->kind ?? null,
                'amount' => (float) $row->amount,
                'rate' => (float) ($row->rate ?? 0),
            ];
        }

        $balanceAfter = $payment->balance_after;
        if ($balanceAfter === null) {
            $paidAfter = (float) ($payment->amount_paid_before ?? 0) + $payment->settledAmount();
            $balanceAfter = max(0, (float) $invoice->total - $paidAfter);
        }

        return self::build(
            $fiscal->ht,
            $fiscal->netToPay,
            $fiscal->taxes,
            (float) $payment->amount,
            $payment->settledAmount(),
            $withholdings,
            (float) $balanceAfter,
            $payment->isCancelled(),
        );
    }

    /**
     * @param  list<array{name: string, rate?: float, amount: float, effect?: string, is_vat?: bool}>  $taxes
     * @param  list<array{type_code?: ?string, type_name?: ?string, kind?: ?string, amount: float, rate?: float}>  $withholdings
     * @return array{
     *     lines: list<array{label: string, amount: float, nature: string, display: string}>,
     *     ttc: float,
     *     cash: float,
     *     status: string,
     *     status_label: string,
     *     cancelled: bool
     * }
     */
    public static function build(
        float $ht,
        float $invoiceTotal,
        array $taxes,
        float $cash,
        float $settled,
        array $withholdings = [],
        float $balanceAfter = 0,
        bool $cancelled = false,
    ): array {
        $ht = WithholdingCalculator::roundMoney($ht);
        $invoiceTotal = WithholdingCalculator::roundMoney($invoiceTotal);
        $cash = WithholdingCalculator::roundMoney($cash);
        $settled = WithholdingCalculator::roundMoney($settled);
        $balanceAfter = WithholdingCalculator::roundMoney($balanceAfter);

        $status = abs($balanceAfter) <= 0.5 ? self::STATUS_PAID : self::STATUS_PARTIAL;
        $statusLabel = $status === self::STATUS_PAID ? 'Soldé' : 'Partiellement soldé';

        if ($cash < 0) {
            return [
                'lines' => [
                    self::line('Montant remboursé', abs($cash), 'refund', 'minus'),
                    self::line('Montant TTC', abs($settled) > 0 ? abs($settled) : abs($cash), 'ttc', 'plain'),
                ],
                'ttc' => abs($settled) > 0 ? abs($settled) : abs($cash),
                'cash' => $cash,
                'status' => $status,
                'status_label' => $statusLabel,
                'cancelled' => $cancelled,
            ];
        }

        $shareBase = $invoiceTotal > 0 ? $invoiceTotal : 0.0;
        $shareOf = $shareBase > 0 ? min(max($settled, 0), $shareBase) : 0.0;

        $lines = [];
        $htShare = self::prorate($ht, $shareOf, $shareBase);
        if ($htShare > 0) {
            $lines[] = self::line('Montant HT', $htShare, 'ht', 'plain');
        }

        foreach ($taxes as $tax) {
            $amount = WithholdingCalculator::roundMoney((float) ($tax['amount'] ?? 0));
            $share = self::prorate($amount, $shareOf, $shareBase);
            if ($share <= 0) {
                continue;
            }

            $name = (string) ($tax['name'] ?? 'Taxe');
            $rate = (float) ($tax['rate'] ?? 0);
            $isVat = (bool) ($tax['is_vat'] ?? false);
            $effect = (($tax['effect'] ?? 'add') === 'subtract') ? 'subtract' : 'add';
            $label = self::billedTaxLabel($name, $rate, $isVat);

            if ($effect === 'subtract') {
                $lines[] = self::line($label.' (déduite de la facture)', $share, 'tax_subtract', 'minus');
            } else {
                $lines[] = self::line($label, $share, $isVat ? 'vat_billed' : 'tax_billed', 'plain');
            }
        }

        foreach ($withholdings as $row) {
            $amount = WithholdingCalculator::roundMoney((float) ($row['amount'] ?? 0));
            if ($amount <= 0) {
                continue;
            }

            $kind = WithholdingKind::resolve(
                $row['kind'] ?? null,
                $row['type_code'] ?? null,
                $row['type_name'] ?? null,
            );
            $lines[] = self::line(
                self::withholdingLabel($kind, $row['type_name'] ?? null, (float) ($row['rate'] ?? 0)),
                $amount,
                $kind === WithholdingKind::VAT ? 'vat_withheld' : 'withheld',
                'minus',
            );
        }

        $lines[] = self::line('Montant perçu en caisse', abs($cash), 'cash', $cash < 0 ? 'minus' : 'plus');
        $lines[] = self::line('Total TTC de cet encaissement', $settled, 'ttc', 'plain');

        return [
            'lines' => $lines,
            'ttc' => $settled,
            'cash' => $cash,
            'status' => $status,
            'status_label' => $statusLabel,
            'cancelled' => $cancelled,
        ];
    }

    public static function statusLabelFromBalance(?float $balanceAfter, bool $cancelled = false): string
    {
        if ($cancelled) {
            return 'Annulé';
        }

        if ($balanceAfter === null) {
            return '—';
        }

        return WithholdingCalculator::roundMoney((float) $balanceAfter) <= 0.5
            ? 'Soldé'
            : 'Partiellement soldé';
    }

    private static function prorate(float $amount, float $shareOf, float $shareBase): float
    {
        $amount = WithholdingCalculator::roundMoney($amount);
        if ($amount <= 0 || $shareOf <= 0 || $shareBase <= 0) {
            return 0.0;
        }

        if (abs($shareOf - $shareBase) < 0.5) {
            return $amount;
        }

        return WithholdingCalculator::roundMoney($amount * ($shareOf / $shareBase));
    }

    /**
     * @param  array{lines?: list<array{nature?: string, amount?: float}>, ttc?: float}  $settlement
     * @return array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float}
     */
    public static function fiscalSlice(array $settlement): array
    {
        $ht = 0.0;
        $vatBilled = 0.0;
        $vatWithheld = 0.0;

        foreach ($settlement['lines'] ?? [] as $line) {
            $amount = (float) ($line['amount'] ?? 0);
            match ($line['nature'] ?? '') {
                'ht' => $ht += $amount,
                'vat_billed' => $vatBilled += $amount,
                'vat_withheld' => $vatWithheld += $amount,
                default => null,
            };
        }

        $vatBilled = WithholdingCalculator::roundMoney($vatBilled);
        $vatWithheld = WithholdingCalculator::roundMoney($vatWithheld);

        return [
            'ht' => WithholdingCalculator::roundMoney($ht),
            'ttc' => WithholdingCalculator::roundMoney((float) ($settlement['ttc'] ?? 0)),
            'vat_billed' => $vatBilled,
            'vat_withheld' => $vatWithheld,
            'vat_collected' => WithholdingCalculator::roundMoney(max(0, $vatBilled - $vatWithheld)),
        ];
    }

    /**
     * @return array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float}
     */
    public static function emptyFiscalSlice(): array
    {
        return [
            'ht' => 0.0,
            'ttc' => 0.0,
            'vat_collected' => 0.0,
            'vat_withheld' => 0.0,
            'vat_billed' => 0.0,
        ];
    }

    /**
     * @param  array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float}  $left
     * @param  array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float}  $right
     * @return array{ht: float, ttc: float, vat_collected: float, vat_withheld: float, vat_billed: float}
     */
    public static function addFiscalSlices(array $left, array $right): array
    {
        return [
            'ht' => WithholdingCalculator::roundMoney(($left['ht'] ?? 0) + ($right['ht'] ?? 0)),
            'ttc' => WithholdingCalculator::roundMoney(($left['ttc'] ?? 0) + ($right['ttc'] ?? 0)),
            'vat_collected' => WithholdingCalculator::roundMoney(($left['vat_collected'] ?? 0) + ($right['vat_collected'] ?? 0)),
            'vat_withheld' => WithholdingCalculator::roundMoney(($left['vat_withheld'] ?? 0) + ($right['vat_withheld'] ?? 0)),
            'vat_billed' => WithholdingCalculator::roundMoney(($left['vat_billed'] ?? 0) + ($right['vat_billed'] ?? 0)),
        ];
    }

    /**
     * @return array{label: string, amount: float, nature: string, display: string}
     */
    private static function line(string $label, float $amount, string $nature, string $display): array
    {
        return [
            'label' => $label,
            'amount' => WithholdingCalculator::roundMoney($amount),
            'nature' => $nature,
            'display' => $display,
        ];
    }

    private static function billedTaxLabel(string $name, float $rate, bool $isVat): string
    {
        $rateLabel = $rate > 0 ? self::formatRate($rate).' %' : '';

        if ($isVat) {
            return $rateLabel !== '' ? 'TVA facturée '.$rateLabel : 'TVA facturée';
        }

        $name = trim($name) !== '' ? trim($name) : 'Taxe';

        return $rateLabel !== '' ? $name.' '.$rateLabel : $name;
    }

    private static function withholdingLabel(string $kind, ?string $name, float $rate): string
    {
        if ($kind === WithholdingKind::VAT) {
            return 'TVA retenue à la source (non encaissée)';
        }

        if ($kind === WithholdingKind::IS) {
            return 'IS retenu à la source (non encaissé)';
        }

        $label = trim((string) $name);
        if ($label === '') {
            $label = 'Retenue fiscale';
        }

        $normalized = mb_strtolower($label);
        if (! str_contains($normalized, 'retenu')) {
            $label .= ' (retenue à la source, non encaissée)';
        } elseif (! str_contains($normalized, 'non encaiss')) {
            $label .= ' (non encaissée)';
        }

        return $label;
    }

    public static function formatRate(float $rate): string
    {
        $text = number_format(round($rate, 4), 4, ',', '');

        return rtrim(rtrim($text, '0'), ',');
    }
}
