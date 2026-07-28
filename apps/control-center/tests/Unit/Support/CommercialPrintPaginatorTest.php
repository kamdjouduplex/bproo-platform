<?php

namespace Tests\Unit\Support;

use App\Support\CommercialPrintPaginator;
use PHPUnit\Framework\TestCase;

class CommercialPrintPaginatorTest extends TestCase
{
    public function test_pages_splits_lines_without_gaps_or_duplicates(): void
    {
        $lines = collect(range(1, 30))->map(fn (int $n) => (object) ['line_number' => $n * 10]);

        $pages = CommercialPrintPaginator::pages($lines, 11, 17);

        $this->assertCount(3, $pages);
        $this->assertCount(11, $pages[0]['lines']);
        $this->assertCount(17, $pages[1]['lines']);
        $this->assertCount(2, $pages[2]['lines']);
        $this->assertSame(0, $pages[0]['offset']);
        $this->assertSame(11, $pages[1]['offset']);
        $this->assertSame(28, $pages[2]['offset']);

        $merged = collect($pages)->flatMap(fn (array $page) => $page['lines']);
        $this->assertCount(30, $merged);
        $this->assertSame(
            range(10, 300, 10),
            $merged->map(fn ($line) => $line->line_number)->all()
        );
    }

    public function test_pages_returns_single_empty_page_when_no_lines(): void
    {
        $pages = CommercialPrintPaginator::pages([]);

        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]['lines']->isEmpty());
        $this->assertSame(1, $pages[0]['total']);
    }
}
