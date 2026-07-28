<?php

namespace InovCom\Facturation\Support;

use App\Services\TenantManager;
use InovCom\Facturation\Models\Invoice;

class InvoiceCodeGenerator
{
    public function prefixForType(string $invoiceType): string
    {
        $tenant = app(TenantManager::class)->tenant();
        $defaults = config('inovcom.invoice_prefixes', [
            'invoice'     => 'FAC',
            'proforma'    => 'PRO',
            'credit_note' => 'AV',
        ]);

        $key = match ($invoiceType) {
            'credit_note' => 'credit_note',
            'proforma'    => 'proforma',
            default       => 'invoice',
        };

        $settingKey = match ($invoiceType) {
            'credit_note' => 'credit_note_prefix',
            'proforma'    => 'proforma_prefix',
            default       => 'invoice_prefix',
        };

        $prefix = $tenant?->getSetting($settingKey, $defaults[$key] ?? 'FAC');

        return strtoupper(trim((string) $prefix));
    }

    public function padding(): int
    {
        $tenant = app(TenantManager::class)->tenant();
        $padding = (int) ($tenant?->getSetting('invoice_number_padding', config('inovcom.invoice_number_padding', 5)) ?? 5);

        return max(3, min(8, $padding));
    }

    public function nextCode(string $invoiceType): string
    {
        $prefix  = $this->prefixForType($invoiceType);
        $padding = $this->padding();

        $max = Invoice::on('tenant')
            ->where('code', 'like', $prefix . '%')
            ->pluck('code')
            ->map(fn (string $code): int => $this->extractSequence($code, $prefix))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return $prefix . str_pad((string) (($max ?? 0) + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function codeMatchesType(string $code, string $invoiceType): bool
    {
        $prefix = $this->prefixForType($invoiceType);

        return str_starts_with(strtoupper(trim($code)), $prefix);
    }

    public function extractSequence(string $code, ?string $prefix = null): int
    {
        $prefix ??= '';
        $suffix = $prefix !== '' && str_starts_with(strtoupper($code), $prefix)
            ? substr($code, strlen($prefix))
            : $code;

        return (int) preg_replace('/\D/', '', $suffix);
    }
}
