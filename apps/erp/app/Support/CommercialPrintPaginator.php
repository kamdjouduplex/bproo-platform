<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Découpe les lignes d'un document commercial pour l'impression multi-pages
 * (évite les lignes manquantes causées par le pied de page fixe + sauts CSS).
 */
final class CommercialPrintPaginator
{
    /** Capacité page 1 (en-tête + client) — calibré A4, padding 10 mm. */
    public const FIRST_PAGE_LINES = 16;

    /** Capacité pages suivantes (en-tête réduit). */
    public const CONTINUATION_PAGE_LINES = 22;

    /** Page 1 BL (titre + sous-titre en plus). */
    public const DELIVERY_NOTE_FIRST_PAGE_LINES = 13;

    /**
     * @param  iterable<int, mixed>  $lines
     * @return array<int, array{lines: Collection<int, mixed>, offset: int, index: int, total: int}>
     */
    public static function pages(iterable $lines, ?int $firstPageLines = null, ?int $continuationLines = null): array
    {
        $first = $firstPageLines ?? self::FIRST_PAGE_LINES;
        $next = $continuationLines ?? self::CONTINUATION_PAGE_LINES;

        $sorted = collect($lines)
            ->sortBy(fn (mixed $line) => self::lineSortKey($line))
            ->values();

        if ($sorted->isEmpty()) {
            return [[
                'lines' => collect(),
                'offset' => 0,
                'index' => 0,
                'total' => 1,
            ]];
        }

        $chunks = [];
        $chunks[] = $sorted->slice(0, $first)->values();
        $offset = $first;
        while ($offset < $sorted->count()) {
            $chunks[] = $sorted->slice($offset, $next)->values();
            $offset += $next;
        }

        $total = count($chunks);
        $pages = [];
        $lineOffset = 0;
        foreach ($chunks as $index => $chunk) {
            $pages[] = [
                'lines' => $chunk,
                'offset' => $lineOffset,
                'index' => $index,
                'total' => $total,
            ];
            $lineOffset += $chunk->count();
        }

        return $pages;
    }

    private static function lineSortKey(mixed $line): int
    {
        if (is_array($line)) {
            return (int) ($line['line_number'] ?? 0);
        }

        return (int) ($line->line_number ?? 0);
    }
}
