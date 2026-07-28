<?php

namespace Tests\Unit\Pressing;

use PHPUnit\Framework\TestCase;
use Pressing\Support\PressingWorkflow;

class PressingWorkflowTest extends TestCase
{
    public function test_kanban_stage_names(): void
    {
        $this->assertSame([
            'Mise en Production',
            'Lavage',
            'Séchage',
            'Repassage',
            'Fin de production',
        ], PressingWorkflow::kanbanStageNames());
    }

    public function test_stage_constants(): void
    {
        $this->assertSame('Tri', PressingWorkflow::STAGE_TRI);
        $this->assertSame('Mise en Production', PressingWorkflow::STAGE_MISE_EN_PRODUCTION);
        $this->assertSame('Repassage', PressingWorkflow::STAGE_REPASSAGE);
        $this->assertSame('Fin de production', PressingWorkflow::STAGE_FIN_PRODUCTION);
    }
}
