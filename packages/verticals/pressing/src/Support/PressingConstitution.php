<?php

namespace Pressing\Support;

use Illuminate\Support\Collection;
use Pressing\Models\PressingOrderConstitutionLine;

class PressingConstitution
{
    public static function isLineValid(array $line): bool
    {
        $typeId = (int) ($line['article_type_id'] ?? 0);
        $qty = (int) ($line['quantity'] ?? 0);
        $color = trim((string) ($line['color'] ?? ''));
        $pattern = trim((string) ($line['pattern'] ?? ''));

        return $typeId > 0
            && $qty >= 1
            && ($color !== '' || $pattern !== '');
    }

    /** @param Collection<int, PressingOrderConstitutionLine>|array<int, array<string, mixed>> $lines */
    public static function summary(Collection|array $lines): string
    {
        $items = $lines instanceof Collection ? $lines : collect($lines);

        if ($items->isEmpty()) {
            return '';
        }

        return $items->map(function ($line) {
            if ($line instanceof PressingOrderConstitutionLine) {
                return $line->label();
            }

            return PressingOrderConstitutionLine::formatLabel(
                (string) ($line['type_name'] ?? $line['article_type_name'] ?? 'Article'),
                $line['color'] ?? null,
                $line['pattern'] ?? null,
                (int) ($line['quantity'] ?? 1)
            );
        })->implode(' · ');
    }

    public static function totalQuantity(Collection|array $lines): int
    {
        $items = $lines instanceof Collection ? $lines : collect($lines);

        return (int) $items->sum(fn ($line) => max(1, (int) (
            $line instanceof PressingOrderConstitutionLine
                ? $line->quantity
                : ($line['quantity'] ?? 1)
        )));
    }
}
