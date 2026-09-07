<?php

namespace InovCom\InvoicePayments\Support;

use App\Support\DocumentTaxCalculator;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\Invoicing\Models\Invoice;

class InvoiceFiscalBreakdown
{
    /**
     * @param  list<array{name: string, rate: float, amount: float, effect: string, is_vat: bool}>  $taxes
     */
    public function __construct(
        public float $ht,
        public float $vat,
        public float $vatRate,
        public float $ttc,
        public float $netToPay,
        public array $taxes = [],
        public float $is = 0,
        public float $isRate = 0,
        public bool $isSubtractive = false,
    ) {
    }

    public static function fromInvoice(Invoice $invoice): self
    {
        $invoice->loadMissing('taxLines');

        $ht = WithholdingCalculator::roundMoney(
            max(0, (float) $invoice->subtotal - (float) $invoice->discount_amount)
        );

        $vat = 0.0;
        $vatRate = 0.0;
        $taxes = [];

        foreach ($invoice->taxLines as $line) {
            $amount = WithholdingCalculator::roundMoney((float) ($line->tax_amount ?? 0));
            if ($amount <= 0) {
                continue;
            }

            $name = trim((string) ($line->tax_name ?? '')) ?: 'Taxe';
            $effect = DocumentTaxCalculator::normalizeEffect($line->tax_effect ?? DocumentTaxCalculator::EFFECT_ADD);
            $rate = (float) ($line->tax_rate ?? 0);
            $isVat = WithholdingKind::isVatLabel($name);

            $taxes[] = [
                'name' => $name,
                'rate' => $rate,
                'amount' => $amount,
                'effect' => $effect,
                'is_vat' => $isVat,
            ];

            if ($effect === DocumentTaxCalculator::EFFECT_SUBTRACT || ! $isVat) {
                continue;
            }

            $vat += $amount;
            if ($vatRate <= 0 && $rate > 0) {
                $vatRate = $rate;
            }
        }

        if ($vat <= 0 && $invoice->taxLines->isEmpty() && (float) $invoice->tax_amount > 0) {
            $vat = WithholdingCalculator::roundMoney((float) $invoice->tax_amount);
        }

        $vat = WithholdingCalculator::roundMoney($vat);
        if ($vatRate <= 0 && $ht > 0 && $vat > 0) {
            $vatRate = round($vat / $ht * 100, 2);
        }

        if ($taxes === [] && $vat > 0) {
            $taxes[] = [
                'name' => 'TVA',
                'rate' => $vatRate,
                'amount' => $vat,
                'effect' => DocumentTaxCalculator::EFFECT_ADD,
                'is_vat' => true,
            ];
        }

        $ttc = WithholdingCalculator::roundMoney($ht + $vat);
        $netToPay = WithholdingCalculator::roundMoney((float) $invoice->total);

        $is = 0.0;
        $isRate = 0.0;
        $isSubtractive = false;
        foreach ($taxes as $tax) {
            if (WithholdingKind::infer(null, $tax['name']) !== WithholdingKind::IS) {
                continue;
            }
            $is += (float) $tax['amount'];
            if ($isRate <= 0 && (float) $tax['rate'] > 0) {
                $isRate = (float) $tax['rate'];
            }
            if (($tax['effect'] ?? 'add') === DocumentTaxCalculator::EFFECT_SUBTRACT) {
                $isSubtractive = true;
            }
        }

        return new self(
            $ht,
            $vat,
            $vatRate,
            $ttc,
            $netToPay,
            $taxes,
            WithholdingCalculator::roundMoney($is),
            $isRate,
            $isSubtractive,
        );
    }

    public static function alreadyWithheldVat(Invoice $invoice, array $pendingRows = []): float
    {
        return self::alreadyWithheldKind($invoice, WithholdingKind::VAT, $pendingRows);
    }

    public function remainingVat(float $alreadyWithheld = 0): float
    {
        return WithholdingCalculator::roundMoney(max(0, $this->vat - $alreadyWithheld));
    }

    public static function alreadyWithheldIs(Invoice $invoice, array $pendingRows = []): float
    {
        return self::alreadyWithheldKind($invoice, WithholdingKind::IS, $pendingRows);
    }

    public function remainingIs(float $alreadyWithheld = 0): float
    {
        return WithholdingCalculator::roundMoney(max(0, $this->is - $alreadyWithheld));
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     */
    public function withholdingError(string $kind, float $alreadyWithheld = 0): ?string
    {
        if ($kind === WithholdingKind::VAT) {
            if ($this->vat <= 0) {
                return 'Cette facture n’a pas de TVA. Impossible de la retenir à l’encaissement.';
            }
            if ($this->remainingVat($alreadyWithheld) <= 0) {
                return 'La TVA de cette facture a déjà été retenue.';
            }

            return null;
        }

        if ($kind === WithholdingKind::IS) {
            $formatted = number_format($this->is, 0, ',', ' ');
            if ($this->is <= 0) {
                return 'Cette facture n’a pas d’IS. Impossible de le retenir à l’encaissement.';
            }
            if ($this->isSubtractive) {
                return 'L’IS a déjà été déduit à l’émission ('.$formatted.' F). Impossible de le retenir à l’encaissement.';
            }
            if ($this->remainingIs($alreadyWithheld) <= 0) {
                return 'L’IS de cette facture a déjà été retenu.';
            }

            return null;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     */
    private static function alreadyWithheldKind(Invoice $invoice, string $kind, array $pendingRows = []): float
    {
        $stored = 0.0;

        if (InvoicePayment::hasWithholdingsTable()) {
            $payments = InvoicePayment::query()
                ->where('invoice_id', $invoice->id)
                ->active()
                ->with(InvoicePayment::optionalWithholdingsRelation())
                ->get();

            foreach ($payments as $payment) {
                foreach ($payment->withholdings as $row) {
                    $resolved = WithholdingKind::resolve($row->type?->kind ?? null, $row->type_code, $row->type_name);
                    if ($resolved === $kind) {
                        $stored += (float) $row->amount;
                    }
                }
            }
        }

        $pending = 0.0;
        foreach ($pendingRows as $row) {
            $resolved = WithholdingKind::resolve($row['kind'] ?? null, $row['type_code'] ?? null, $row['type_name'] ?? null);
            if ($resolved === $kind) {
                $pending += (float) ($row['amount'] ?? 0);
            }
        }

        return WithholdingCalculator::roundMoney($stored + $pending);
    }
}
