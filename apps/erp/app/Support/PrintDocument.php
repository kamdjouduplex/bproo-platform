<?php

namespace App\Support;

use Illuminate\Http\Request;

class PrintDocument
{
    public static function filename(string $label, string $number): string
    {
        $labelPart = self::titleSlug($label);
        $numberPart = self::documentNumberSlug($number);

        if ($labelPart !== '' && $numberPart !== '') {
            return $labelPart . '-' . $numberPart;
        }

        return $labelPart !== '' ? $labelPart : ($numberPart !== '' ? $numberPart : 'Document');
    }

    public static function titleSlug(string $value): string
    {
        $slug = self::slug($value);
        if ($slug === '') {
            return '';
        }

        $parts = explode('-', $slug);

        return implode('-', array_map(function (string $word): string {
            if ($word === '') {
                return '';
            }

            return mb_strtoupper(mb_substr($word, 0, 1)) . mb_strtolower(mb_substr($word, 1));
        }, $parts));
    }

    public static function documentNumberSlug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $slug = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $value);
        $slug = preg_replace('/-+/', '-', (string) $slug);

        return trim((string) $slug, '-');
    }

    public static function slug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);
        $slug = preg_replace('/-+/', '-', (string) $slug);

        return trim((string) $slug, '-');
    }

    public static function returnUrl(Request $request, string $defaultRoute, array $routeParams = []): string
    {
        $custom = $request->query('return');
        if (is_string($custom) && $custom !== '' && self::isSafeRelativeUrl($custom)) {
            return $custom;
        }

        if (!isset($routeParams['tenant']) && ($tenant = $request->query('tenant'))) {
            $routeParams['tenant'] = $tenant;
        }

        return route($defaultRoute, $routeParams);
    }

    public static function isSafeRelativeUrl(string $url): bool
    {
        return str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_contains($url, '://');
    }

    /**
     * @return array{printTitle: string, returnUrl: string}
     */
    public static function context(
        Request $request,
        string $label,
        string $number,
        string $returnRoute,
        array $routeParams = []
    ): array {
        return [
            'printTitle' => self::filename($label, $number),
            'returnUrl' => self::returnUrl($request, $returnRoute, $routeParams),
        ];
    }
}
