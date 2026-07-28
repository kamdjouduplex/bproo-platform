<?php

namespace Tests\Unit\Pressing;

use PHPUnit\Framework\TestCase;
use Pressing\Models\PressingOrderConstitutionLine;

class PressingConstitutionMultiDetailTest extends TestCase
{
    public function test_split_and_join_lists(): void
    {
        $this->assertSame(['noir', 'bleu'], PressingOrderConstitutionLine::splitList('noir, bleu'));
        $this->assertSame('noir, bleu', PressingOrderConstitutionLine::joinList(['noir', 'bleu', 'noir']));
    }

    public function test_format_label_with_multiple_colors_and_patterns(): void
    {
        $label = PressingOrderConstitutionLine::formatLabel(
            'Chemise',
            'noir, blanc',
            'jean, rayée',
            2
        );

        $this->assertSame('chemise noir/blanc jean rayée × 2', $label);
    }
}
