<?php

namespace School\Support\Sms;

use Illuminate\Support\Facades\Http;

class HttpWebhookSmsGateway implements SmsGateway
{
    public function __construct(
        protected string $url,
        protected string $apiKey,
        protected string $defaultFrom = 'BprooSchool'
    ) {}

    public function name(): string
    {
        return 'http';
    }

    public function configured(): bool
    {
        return $this->url !== '' && $this->apiKey !== '';
    }

    public function send(string $to, string $message, ?string $from = null): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'skipped' => true, 'error' => 'Webhook SMS non configuré (URL / clé)', 'provider' => $this->name()];
        }

        if (trim($to) === '') {
            return ['ok' => false, 'skipped' => true, 'error' => 'Numéro manquant', 'provider' => $this->name()];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(12)
                ->post(rtrim($this->url, '/'), [
                    'to' => $to,
                    'from' => $from ?: $this->defaultFrom,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return ['ok' => true, 'provider' => $this->name()];
            }

            return ['ok' => false, 'error' => $response->body(), 'provider' => $this->name()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'provider' => $this->name()];
        }
    }
}
