<?php

namespace Tests\Unit;

use InovCom\InvoicePayments\Support\InvoiceFiscalBreakdown;
use InovCom\InvoicePayments\Support\WithholdingKind;
use PHPUnit\Framework\TestCase;

class InvoiceFiscalBreakdownTest extends TestCase
{
    public function test_cannot_withhold_vat_when_invoice_has_none(): void
    {
        $fiscal = new InvoiceFiscalBreakdown(770_000, 0, 0, 770_000, 770_000);

        $this->assertSame(
            'Cette facture n’a pas de TVA. Impossible de la retenir à l’encaissement.',
            $fiscal->withholdingError(WithholdingKind::VAT)
        );
    }

    public function test_cannot_withhold_is_when_invoice_has_none(): void
    {
        $fiscal = new InvoiceFiscalBreakdown(770_000, 148_225, 19.25, 918_225, 918_225);

        $this->assertSame(
            'Cette facture n’a pas d’IS. Impossible de le retenir à l’encaissement.',
            $fiscal->withholdingError(WithholdingKind::IS)
        );
        $this->assertNull($fiscal->withholdingError(WithholdingKind::VAT));
    }

    public function test_cannot_withhold_is_again_when_already_deducted_at_issue(): void
    {
        $fiscal = new InvoiceFiscalBreakdown(
            1_620_000,
            311_850,
            19.25,
            1_931_850,
            1_584_360,
            [],
            35_640,
            2.2,
            true,
        );

        $this->assertStringContainsString('déjà été déduit à l’émission', (string) $fiscal->withholdingError(WithholdingKind::IS));
        $this->assertStringContainsString('35 640', (string) $fiscal->withholdingError(WithholdingKind::IS));
    }

    public function test_additive_invoice_is_can_be_withheld_once(): void
    {
        $fiscal = new InvoiceFiscalBreakdown(
            770_000,
            148_225,
            19.25,
            918_225,
            918_225,
            [],
            16_940,
            2.2,
            false,
        );

        $this->assertNull($fiscal->withholdingError(WithholdingKind::IS));
        $this->assertSame('L’IS de cette facture a déjà été retenu.', $fiscal->withholdingError(WithholdingKind::IS, 16_940));
    }
}
