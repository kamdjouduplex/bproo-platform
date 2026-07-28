<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;

class PressingOrderConstitutionLine extends TenantModel
{
    public const COLOR_PRESETS = [
        'noir', 'bleu', 'blanc', 'gris', 'kaki', 'beige', 'rouge', 'vert', 'marron', 'jaune',
    ];

    public const PATTERN_PRESETS = [
        'jean', 'rayée', 'rayé', 'wax', 'attitude', 'uni', 'imprimé', 'coton', 'soie',
    ];

    protected $table = 'pressing_order_constitution_lines';

    protected $fillable = [
        'order_id',
        'article_type_id',
        'color',
        'pattern',
        'quantity',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function articleType(): BelongsTo
    {
        return $this->belongsTo(ArticleType::class, 'article_type_id');
    }

    public function label(): string
    {
        return self::formatLabel(
            $this->articleType?->name ?? 'Article',
            $this->color,
            $this->pattern,
            (int) $this->quantity
        );
    }

    /** @return list<string> */
    public static function splitList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[,;\/|]+/u', $value) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $key = mb_strtolower($part);
            if (! isset($out[$key])) {
                $out[$key] = $part;
            }
        }

        return array_values($out);
    }

    /** @param list<string>|string|null $items */
    public static function joinList(array|string|null $items): string
    {
        if (is_string($items) || $items === null) {
            $items = self::splitList($items);
        }

        $clean = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $key = mb_strtolower($item);
            if (! isset($clean[$key])) {
                $clean[$key] = $item;
            }
        }

        return implode(', ', array_values($clean));
    }

    public static function formatLabel(string $typeName, ?string $color, ?string $pattern, int $quantity): string
    {
        $parts = [mb_strtolower(trim($typeName))];

        $colors = self::splitList($color);
        $patterns = self::splitList($pattern);

        if ($colors !== []) {
            $parts[] = implode('/', array_map(fn ($c) => mb_strtolower($c), $colors));
        }

        foreach ($patterns as $patternItem) {
            $patternLower = mb_strtolower($patternItem);
            $alreadyColor = collect($colors)->contains(
                fn ($c) => mb_strtolower($c) === $patternLower
            );
            if (! $alreadyColor) {
                $parts[] = $patternLower;
            }
        }

        return implode(' ', $parts).' × '.max(1, $quantity);
    }
}
