<?php

namespace Tests\Unit\Pressing;

use PHPUnit\Framework\TestCase;
use Pressing\Models\PressingOrderConstitutionLine;
use Pressing\Support\PressingConstitution;

class PressingConstitutionTest extends TestCase
{
    public function test_format_label_with_color_and_quantity(): void
    {
        $this->assertSame(
            'pantalon noir × 2',
            PressingOrderConstitutionLine::formatLabel('Pantalon', 'noir', null, 2)
        );
    }

    public function test_format_label_with_pattern(): void
    {
        $this->assertSame(
            'chemise rayée × 1',
            PressingOrderConstitutionLine::formatLabel('Chemise', '', 'rayée', 1)
        );
    }

    public function test_format_label_with_color_and_pattern(): void
    {
        $this->assertSame(
            'costume bleu × 1',
            PressingOrderConstitutionLine::formatLabel('Costume', 'bleu', null, 1)
        );

        $this->assertSame(
            'boubou attitude × 1',
            PressingOrderConstitutionLine::formatLabel('Boubou', '', 'attitude', 1)
        );
    }

    public function test_line_valid_requires_type_color_or_pattern(): void
    {
        $this->assertTrue(PressingConstitution::isLineValid([
            'article_type_id' => 1,
            'quantity' => 2,
            'color' => 'noir',
            'pattern' => '',
        ]));

        $this->assertFalse(PressingConstitution::isLineValid([
            'article_type_id' => 1,
            'quantity' => 1,
            'color' => '',
            'pattern' => '',
        ]));
    }

    public function test_summary_joins_lines(): void
    {
        $summary = PressingConstitution::summary([
            ['type_name' => 'Pantalon', 'color' => 'noir', 'pattern' => '', 'quantity' => 2],
            ['type_name' => 'Chemise', 'color' => '', 'pattern' => 'rayée', 'quantity' => 1],
        ]);

        $this->assertStringContainsString('pantalon noir × 2', $summary);
        $this->assertStringContainsString('chemise rayée × 1', $summary);
    }
}
