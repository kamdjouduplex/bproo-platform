<?php

if (!function_exists('fmt_num')) {
    /**
     * French number format without unnecessary trailing decimals.
     * Examples: 1.000 → "1", 10.5 → "10,5", 1000 → "1 000"
     */
    function fmt_num(mixed $value, int $maxDecimals = 4, bool $useThousandsSep = true): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $n = (float) $value;
        if (!is_finite($n)) {
            return '—';
        }

        $thousands = $useThousandsSep ? ' ' : '';

        if (abs($n - round($n)) < 1e-9) {
            return number_format((int) round($n), 0, ',', $thousands);
        }

        $formatted = number_format($n, $maxDecimals, ',', $thousands);

        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }
}

if (!function_exists('fmt_money')) {
    /** Amounts in FCFA / currency (no decimals). */
    function fmt_money(mixed $value, bool $useThousandsSep = true): string
    {
        return fmt_num($value, 0, $useThousandsSep);
    }
}

if (! function_exists('currency_code')) {
    /** Tenant default ISO currency code, or an explicit override. */
    function currency_code(?string $code = null): string
    {
        if (class_exists(\App\Services\TenantCurrencyService::class)) {
            return \App\Services\TenantCurrencyService::resolveCode($code);
        }

        $c = strtoupper(trim((string) $code));
        if ($c !== '') {
            return $c;
        }

        return strtoupper((string) config('inovcom.currency', config('inovcom.default_currency', 'XOF')));
    }
}

if (! function_exists('currency_label')) {
    /**
     * Human label for amounts (USD, EUR, FCFA…).
     * Empty $code → tenant default currency.
     */
    function currency_label(?string $code = null): string
    {
        if (class_exists(\App\Services\TenantCurrencyService::class)) {
            return \App\Services\TenantCurrencyService::displayLabel($code);
        }

        $c = currency_code($code);

        return match ($c) {
            'XOF', 'XAF' => 'FCFA',
            'CDF' => 'FC',
            'USD' => 'USD',
            'EUR' => 'EUR',
            'GNF' => 'GNF',
            default => $c,
        };
    }
}

if (!function_exists('fmt_num_plain')) {
    /**
     * Plain string for HTML inputs (dot decimal, no trailing zeros).
     * Example: 1.000 → "1", 10.5 → "10.5"
     */
    function fmt_num_plain(mixed $value, int $maxDecimals = 4): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $n = (float) $value;
        if (!is_finite($n)) {
            return '';
        }

        if (class_exists(\InovCom\Kernel\Casts\TrimmedDecimal::class)) {
            $trimmed = \InovCom\Kernel\Casts\TrimmedDecimal::trim($n, $maxDecimals);
            if (is_int($trimmed)) {
                return (string) $trimmed;
            }

            return rtrim(rtrim(sprintf('%.' . $maxDecimals . 'f', $trimmed), '0'), '.');
        }

        if (abs($n - round($n)) < 1e-9) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(sprintf('%.' . $maxDecimals . 'f', $n), '0'), '.');
    }
}

if (!function_exists('item_primary_label')) {
    /** Libellé principal article : la référence prime sur la désignation. */
    function item_primary_label(?string $reference, ?string $name, string $fallback = '—'): string
    {
        $ref = trim((string) ($reference ?? ''));
        $label = trim((string) ($name ?? ''));

        if ($ref !== '') {
            return $ref;
        }

        return $label !== '' ? $label : $fallback;
    }
}

if (!function_exists('item_secondary_label')) {
    /** Sous-titre article (désignation quand la référence est affichée en principal). */
    function item_secondary_label(?string $reference, ?string $name): ?string
    {
        $ref = trim((string) ($reference ?? ''));
        $label = trim((string) ($name ?? ''));

        if ($ref !== '' && $label !== '' && strcasecmp($ref, $label) !== 0) {
            return $label;
        }

        return null;
    }
}

if (!function_exists('item_display')) {
    /** Affichage complet : référence — désignation (référence en priorité). */
    function item_display(?string $reference, ?string $name, string $fallback = '—'): string
    {
        $primary = item_primary_label($reference, $name, '');
        $secondary = item_secondary_label($reference, $name);

        if ($primary === '' && $secondary === null) {
            return $fallback;
        }

        if ($secondary === null) {
            return $primary !== '' ? $primary : $fallback;
        }

        return $primary . ' — ' . $secondary;
    }
}

if (!function_exists('item_search_placeholder')) {
    function item_search_placeholder(bool $includeBarcode = true, ?string $extra = null): string
    {
        $parts = ['référence', 'désignation'];
        if ($includeBarcode) {
            $parts[] = 'code-barres';
        }
        if ($extra !== null && trim($extra) !== '') {
            $parts[] = trim($extra);
        }

        return implode(', ', $parts) . '…';
    }
}

if (!function_exists('line_discount_is_percent')) {
    function line_discount_is_percent(object|array $line): bool
    {
        $mode = is_array($line)
            ? ($line['line_discount_mode'] ?? 'amount')
            : ($line->line_discount_mode ?? 'amount');

        return $mode === 'percent';
    }
}

if (!function_exists('line_discount_input_value')) {
    function line_discount_input_value(object|array $line): float
    {
        $input = is_array($line)
            ? ($line['line_discount_input'] ?? null)
            : ($line->line_discount_input ?? null);
        $amount = (float) (is_array($line) ? ($line['line_discount'] ?? 0) : ($line->line_discount ?? 0));
        $unitPrice = (float) (is_array($line) ? ($line['unit_price'] ?? 0) : ($line->unit_price ?? 0));

        if ($input !== null && $input !== '') {
            return max(0, (float) $input);
        }

        if (line_discount_is_percent($line) && $unitPrice > 0 && $amount > 0) {
            return round($amount / $unitPrice * 100, 4);
        }

        return $amount;
    }
}

if (!function_exists('line_discount_has_value')) {
    function line_discount_has_value(object|array $line): bool
    {
        $amount = (float) (is_array($line) ? ($line['line_discount'] ?? 0) : ($line->line_discount ?? 0));
        if ($amount > 0) {
            return true;
        }

        return line_discount_is_percent($line) && line_discount_input_value($line) > 0;
    }
}

if (!function_exists('format_line_discount_label')) {
    /** Remise unitaire : « 10 % » ou montant selon le mode enregistré. */
    function format_line_discount_label(object|array $line): string
    {
        if (!line_discount_has_value($line)) {
            return '—';
        }

        if (line_discount_is_percent($line)) {
            return fmt_num(line_discount_input_value($line), 2) . ' %';
        }

        return fmt_num((float) (is_array($line) ? ($line['line_discount'] ?? 0) : ($line->line_discount ?? 0)), 2);
    }
}

if (!function_exists('document_discount_header_mode')) {
  /** Mode de remise globale : percent ou amount. */
    function document_discount_header_mode(object $document, ?object $fallback = null): string
    {
        $mode = $document->discount_mode ?? null;
        if ($mode === 'amount' || $mode === 'percent') {
            return $mode;
        }

        if ((float) ($document->discount_percent ?? 0) > 0) {
            return 'percent';
        }

        if ((float) ($document->discount_amount ?? 0) > 0) {
            return 'amount';
        }

        return $fallback ? document_discount_header_mode($fallback) : 'percent';
    }
}

if (!function_exists('document_discount_percent_display')) {
    /** Pourcentage affiché pour une remise globale (avec repli devis / calcul inverse). */
    function document_discount_percent_display(object $document, ?object $fallback = null): float
    {
        $percent = (float) ($document->discount_percent ?? 0);
        if ($percent > 0) {
            return $percent;
        }

        $mode = document_discount_header_mode($document, $fallback);
        if ($mode === 'percent' && $fallback && (float) ($fallback->discount_percent ?? 0) > 0) {
            return (float) $fallback->discount_percent;
        }

        if ($mode === 'percent') {
            $subtotal = (float) ($document->subtotal ?? 0);
            $amount = (float) ($document->discount_amount ?? 0);
            if ($subtotal > 0 && $amount > 0) {
                return round($amount / $subtotal * 100, 2);
            }
        }

        return 0.0;
    }
}

if (!function_exists('format_invoice_line_discount_label')) {
    /** Remise unitaire facture avec repli sur la ligne de devis source si besoin. */
    function format_invoice_line_discount_label(object $invoiceLine, ?object $quotationLine = null): string
    {
        if (line_discount_has_value($invoiceLine) && line_discount_is_percent($invoiceLine)) {
            return format_line_discount_label($invoiceLine);
        }

        if ($quotationLine && line_discount_has_value($quotationLine) && line_discount_is_percent($quotationLine)) {
            return format_line_discount_label($quotationLine);
        }

        return format_line_discount_label($invoiceLine);
    }
}
