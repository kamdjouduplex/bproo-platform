<?php

namespace Tests\Unit;

use Tests\TestCase;

class AutoPrintPartialTest extends TestCase
{
    public function test_print_tab_closes_after_print_when_opened_from_main_app(): void
    {
        $html = view('partials.print.auto-print', ['returnUrl' => '/invoices/123'])->render();

        $this->assertStringContainsString('window.opener', $html);
        $this->assertStringContainsString('window.close()', $html);
    }
}
