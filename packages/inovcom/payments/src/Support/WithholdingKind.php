<?php

namespace InovCom\InvoicePayments\Support;

class WithholdingKind
{
    public const VAT = 'vat';
    public const IS = 'is';
    public const OTHER = 'other';

    public static function labels(): array
    {
        return [
            self::VAT => 'TVA',
            self::IS => 'IS',
            self::OTHER => 'Autre',
        ];
    }

    public static function infer(?string $code, ?string $name): string
    {
        $code = mb_strtolower(trim((string) $code));
        $name = mb_strtolower(trim((string) $name));

        if (self::isVatLabel($code) || self::isVatLabel($name)) {
            return self::VAT;
        }

        if ($code === 'is_retenu'
            || $code === 'is'
            || str_starts_with($code, 'is_')
            || self::isIsLabel($code)
            || self::isIsLabel($name)) {
            return self::IS;
        }

        return self::OTHER;
    }

    public static function resolve(?string $kind, ?string $code = null, ?string $name = null): string
    {
        $inferred = self::infer($code, $name);
        if ($inferred === self::VAT || $inferred === self::IS) {
            return $inferred;
        }

        $kind = trim((string) $kind);
        if (in_array($kind, [self::VAT, self::IS, self::OTHER], true)) {
            return $kind;
        }

        return self::OTHER;
    }

    public static function isVatLabel(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return $normalized !== ''
            && (str_contains($normalized, 'tva') || str_contains($normalized, 'vat'));
    }

    public static function isIsLabel(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }

        if (str_contains($normalized, 'impôt sur les sociétés')
            || str_contains($normalized, 'impot sur les societes')) {
            return true;
        }

        return (bool) preg_match('/(^|[^a-zà-ÿ])is($|[^a-zà-ÿ])/u', $normalized);
    }
}
