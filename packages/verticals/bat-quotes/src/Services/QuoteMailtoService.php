<?php

namespace InovCom\Devis\Services;

use App\Services\TenantManager;
use InovCom\Devis\Models\Quote;

/**
 * Builds mailto: links that open the user's default mail client (no attachment).
 */
class QuoteMailtoService
{
    public function buildUrl(Quote $quote, string $type = 'send'): string
    {
        $quote->loadMissing('client');

        if (!$quote->client?->email) {
            throw new \RuntimeException(__('Aucune adresse e-mail enregistrée pour ce client.'));
        }

        $tenant = app(TenantManager::class)->tenant();
        $subject = $this->subject($quote, $type);
        $body = $this->body($quote, $type, $tenant);

        $query = http_build_query([
            'subject' => $subject,
            'body'    => $body,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'mailto:' . $quote->client->email . '?' . $query;
    }

    private function subject(Quote $quote, string $type): string
    {
        $company = config('app.name', 'ERP');

        return match ($type) {
            'reminder' => __('Rappel : devis :code — :title', [
                'code'  => $quote->code,
                'title' => $quote->title,
            ]),
            default => __('Devis :code — :title — :company', [
                'code'    => $quote->code,
                'title'   => $quote->title,
                'company' => $company,
            ]),
        };
    }

    private function body(Quote $quote, string $type, $tenant): string
    {
        $clientName = $quote->client?->name ?? __('Client');
        $company    = $tenant?->getSetting('company_name', config('app.name')) ?? config('app.name');
        $validUntil = $quote->valid_until?->format('d/m/Y');
        $totalTtc   = number_format((float) $quote->total_ttc, 0, ',', ' ');
        $currency   = $quote->currency ?? 'XOF';

        if ($type === 'reminder') {
            $lines = [
                __('Bonjour :name,', ['name' => $clientName]),
                '',
                __('Nous nous permettons de revenir vers vous concernant notre devis :code.', ['code' => $quote->code]),
                __('Objet : :title', ['title' => $quote->title]),
                __('Montant TTC : :amount :currency', ['amount' => $totalTtc, 'currency' => $currency]),
            ];
            if ($validUntil) {
                $lines[] = __('Validité : jusqu\'au :date', ['date' => $validUntil]);
            }
            $lines[] = '';
            $lines[] = __('Cordialement,');
            $lines[] = $company;

            return implode("\n", $lines);
        }

        $lines = [
            __('Bonjour :name,', ['name' => $clientName]),
            '',
            __('Veuillez trouver ci-dessous notre devis :code.', ['code' => $quote->code]),
            __('Objet : :title', ['title' => $quote->title]),
            __('Montant TTC : :amount :currency', ['amount' => $totalTtc, 'currency' => $currency]),
        ];
        if ($validUntil) {
            $lines[] = __('Ce devis est valable jusqu\'au :date.', ['date' => $validUntil]);
        }
        if ($quote->notes) {
            $lines[] = '';
            $lines[] = $quote->notes;
        }
        $lines[] = '';
        $lines[] = __('Nous restons à votre disposition pour toute question.');
        $lines[] = '';
        $lines[] = __('Cordialement,');
        $lines[] = $company;

        return implode("\n", $lines);
    }
}
