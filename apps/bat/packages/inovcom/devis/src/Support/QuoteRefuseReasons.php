<?php

namespace InovCom\Devis\Support;

class QuoteRefuseReasons
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'price'       => __('Prix trop élevé'),
            'delay'       => __('Délai non compatible'),
            'competitor'  => __('Concurrence retenue'),
            'budget'      => __('Budget insuffisant'),
            'scope'       => __('Périmètre / prestation non adaptée'),
            'no_response' => __('Sans suite du client'),
            'other'       => __('Autre'),
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::options());
    }

    public static function label(string $key): ?string
    {
        return self::options()[$key] ?? null;
    }

    public static function compose(string $category, ?string $comment = null): string
    {
        $label = self::label($category) ?? $category;
        $comment = trim((string) $comment);

        if ($comment === '') {
            return $label;
        }

        return $label . ' — ' . $comment;
    }
}
