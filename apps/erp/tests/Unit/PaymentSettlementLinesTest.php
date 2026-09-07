<?php

namespace Tests\Unit;

use InovCom\InvoicePayments\Support\PaymentSettlementLines;
use PHPUnit\Framework\TestCase;

class PaymentSettlementLinesTest extends TestCase
{
    public function test_full_vat_withholding_explodes_ht_vat_and_cash(): void
    {
        $result = PaymentSettlementLines::build(
            770_000,
            918_225,
            [['name' => 'TVA', 'rate' => 19.25, 'amount' => 148_225, 'effect' => 'add', 'is_vat' => true]],
            770_000,
            918_225,
            [['type_code' => 'tva_retenue', 'type_name' => 'TVA retenue', 'kind' => 'vat', 'amount' => 148_225, 'rate' => 19.25]],
            0,
        );

        $byNature = [];
        foreach ($result['lines'] as $line) {
            $byNature[$line['nature']] = $line;
        }

        $this->assertSame('Montant HT', $byNature['ht']['label']);
        $this->assertSame(770_000.0, $byNature['ht']['amount']);
        $this->assertSame('TVA facturée 19,25 %', $byNature['vat_billed']['label']);
        $this->assertSame(148_225.0, $byNature['vat_billed']['amount']);
        $this->assertSame('TVA retenue à la source (non encaissée)', $byNature['vat_withheld']['label']);
        $this->assertSame(148_225.0, $byNature['vat_withheld']['amount']);
        $this->assertSame('minus', $byNature['vat_withheld']['display']);
        $this->assertSame(770_000.0, $byNature['cash']['amount']);
        $this->assertSame(918_225.0, $byNature['ttc']['amount']);
        $this->assertSame('Soldé', $result['status_label']);
    }

    public function test_full_cash_payment_without_withholding(): void
    {
        $result = PaymentSettlementLines::build(
            770_000,
            918_225,
            [['name' => 'TVA', 'rate' => 19.25, 'amount' => 148_225, 'effect' => 'add', 'is_vat' => true]],
            918_225,
            918_225,
            [],
            0,
        );

        $labels = array_column($result['lines'], 'label');
        $this->assertContains('Montant HT', $labels);
        $this->assertContains('TVA facturée 19,25 %', $labels);
        $this->assertContains('Montant perçu en caisse', $labels);
        $this->assertContains('Total TTC de cet encaissement', $labels);
        $this->assertSame('Soldé', $result['status_label']);
        $this->assertFalse(in_array('vat_withheld', array_column($result['lines'], 'nature'), true));
    }

    public function test_partial_payment_is_prorated_and_marked_partial(): void
    {
        $result = PaymentSettlementLines::build(
            770_000,
            918_225,
            [['name' => 'TVA', 'rate' => 19.25, 'amount' => 148_225, 'effect' => 'add', 'is_vat' => true]],
            400_000,
            400_000,
            [],
            518_225,
        );

        $this->assertSame('Partiellement soldé', $result['status_label']);
        $this->assertSame(335_430.0, $this->amountByNature($result, 'ht'));
        $this->assertSame(64_570.0, $this->amountByNature($result, 'vat_billed'));
        $this->assertSame(400_000.0, $this->amountByNature($result, 'ttc'));
    }

    public function test_fiscal_slice_splits_collected_and_withheld_vat(): void
    {
        $result = PaymentSettlementLines::build(
            770_000,
            918_225,
            [['name' => 'TVA', 'rate' => 19.25, 'amount' => 148_225, 'effect' => 'add', 'is_vat' => true]],
            770_000,
            918_225,
            [['type_code' => 'tva_retenue', 'type_name' => 'TVA retenue', 'kind' => 'vat', 'amount' => 148_225, 'rate' => 19.25]],
            0,
        );

        $slice = PaymentSettlementLines::fiscalSlice($result);
        $this->assertSame(770_000.0, $slice['ht']);
        $this->assertSame(918_225.0, $slice['ttc']);
        $this->assertSame(148_225.0, $slice['vat_billed']);
        $this->assertSame(148_225.0, $slice['vat_withheld']);
        $this->assertSame(0.0, $slice['vat_collected']);
    }

    public function test_fiscal_slice_full_cash_counts_vat_as_collected(): void
    {
        $result = PaymentSettlementLines::build(
            770_000,
            918_225,
            [['name' => 'TVA', 'rate' => 19.25, 'amount' => 148_225, 'effect' => 'add', 'is_vat' => true]],
            918_225,
            918_225,
            [],
            0,
        );

        $slice = PaymentSettlementLines::fiscalSlice($result);
        $this->assertSame(148_225.0, $slice['vat_collected']);
        $this->assertSame(0.0, $slice['vat_withheld']);
    }

    public function test_other_billed_tax_and_other_withholding_have_own_lines(): void
    {
        $result = PaymentSettlementLines::build(
            100_000,
            102_000,
            [
                ['name' => 'TVA', 'rate' => 19.25, 'amount' => 0, 'effect' => 'add', 'is_vat' => true],
                ['name' => 'CSS', 'rate' => 2, 'amount' => 2_000, 'effect' => 'add', 'is_vat' => false],
            ],
            97_000,
            102_000,
            [['type_code' => 'ircm', 'type_name' => 'IRCM', 'kind' => 'other', 'amount' => 5_000, 'rate' => 0]],
            0,
        );

        $this->assertSame('CSS 2 %', $this->lineByNature($result, 'tax_billed')['label']);
        $this->assertSame(2_000.0, $this->amountByNature($result, 'tax_billed'));
        $this->assertSame('IRCM (retenue à la source, non encaissée)', $this->lineByNature($result, 'withheld')['label']);
        $this->assertSame(5_000.0, $this->amountByNature($result, 'withheld'));
        $this->assertSame('Soldé', $result['status_label']);
    }

    public function test_refund_does_not_explode_taxes(): void
    {
        $result = PaymentSettlementLines::build(
            770_000,
            918_225,
            [['name' => 'TVA', 'rate' => 19.25, 'amount' => 148_225, 'effect' => 'add', 'is_vat' => true]],
            -50_000,
            -50_000,
            [],
            50_000,
        );

        $this->assertSame('Montant remboursé', $result['lines'][0]['label']);
        $this->assertSame(50_000.0, $result['lines'][0]['amount']);
        $this->assertSame('Partiellement soldé', $result['status_label']);
    }

    /**
     * @param  array{lines: list<array{nature: string, amount: float, label: string}>}  $result
     */
    private function amountByNature(array $result, string $nature): float
    {
        return $this->lineByNature($result, $nature)['amount'];
    }

    /**
     * @param  array{lines: list<array{nature: string, amount: float, label: string}>}  $result
     * @return array{nature: string, amount: float, label: string}
     */
    private function lineByNature(array $result, string $nature): array
    {
        foreach ($result['lines'] as $line) {
            if ($line['nature'] === $nature) {
                return $line;
            }
        }

        $this->fail('Missing settlement line nature: '.$nature);
    }
}
