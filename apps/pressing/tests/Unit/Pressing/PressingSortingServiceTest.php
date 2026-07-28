<?php

namespace Tests\Unit\Pressing;

use PHPUnit\Framework\TestCase;
use Pressing\Services\PressingSortingService;

class PressingSortingServiceTest extends TestCase
{
    private PressingSortingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PressingSortingService;
    }

    public function test_all_lines_valid(): void
    {
        $lines = [
            ['article_type_id' => 1, 'quantity' => 2, 'color' => 'noir', 'pattern' => ''],
            ['article_type_id' => 2, 'quantity' => 1, 'color' => '', 'pattern' => 'rayée'],
        ];

        $this->assertTrue($this->service->allLinesValid($lines));
        $this->assertSame(2, $this->service->validLineCount($lines));
        $this->assertSame(3, $this->service->totalQuantityFromLines($lines));
    }

    public function test_all_lines_invalid_when_empty(): void
    {
        $this->assertFalse($this->service->allLinesValid([]));
    }
}
