<?php

namespace Tests\Unit;

use InovCom\InvoicePayments\Support\WithholdingKind;
use PHPUnit\Framework\TestCase;

class WithholdingKindTest extends TestCase
{
    public function test_infers_vat_from_code_or_name(): void
    {
        $this->assertSame(WithholdingKind::VAT, WithholdingKind::infer('tva_retenue', 'TVA retenue'));
        $this->assertSame(WithholdingKind::VAT, WithholdingKind::infer('precompte_tva', 'Précompte'));
    }

    public function test_infers_corporate_tax(): void
    {
        $this->assertSame(WithholdingKind::IS, WithholdingKind::infer('is_retenu', 'IS retenu'));
        $this->assertSame(WithholdingKind::IS, WithholdingKind::infer('is', 'Impôt sur les sociétés'));
    }

    public function test_is_label_detection(): void
    {
        $this->assertTrue(WithholdingKind::isIsLabel('IS'));
        $this->assertTrue(WithholdingKind::isIsLabel('IS retenu'));
        $this->assertTrue(WithholdingKind::isIsLabel('Impôt sur les sociétés'));
        $this->assertFalse(WithholdingKind::isIsLabel('IRCM'));
        $this->assertFalse(WithholdingKind::isIsLabel('TVA'));
    }

    public function test_vat_code_wins_over_stale_other_kind(): void
    {
        $this->assertSame(
            WithholdingKind::VAT,
            WithholdingKind::resolve(WithholdingKind::OTHER, 'tva_retenue', 'TVA retenue')
        );
    }
}
