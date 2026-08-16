<?php

namespace Tests\Unit\School;

use PHPUnit\Framework\TestCase;
use School\Support\AcademicYearCarryOver;

class AcademicYearCarryOverTest extends TestCase
{
    public function test_service_class_exists_and_is_instantiable(): void
    {
        $svc = new AcademicYearCarryOver;
        $this->assertInstanceOf(AcademicYearCarryOver::class, $svc);
    }
}
