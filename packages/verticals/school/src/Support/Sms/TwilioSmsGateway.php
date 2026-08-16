<?php

namespace School\Support\Sms;

use Illuminate\Support\Facades\Http;

class TwilioSmsGateway implements SmsGateway
{
    public function __construct(
        protected string $sid,
        protected string $token,
        protected string $from
    ) {}

    public function name(): string
    {
        return 'twilio';
    }

    public function configured(): bool
    {
        return $this->sid !== '' && $this->token !== '' && $this->from !== '';
    }

    public function send(string $to, string $message, ?string $from = null): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'skipped' => true, 'error' => 'Twilio non configuré (SID / token / from)', 'provider' => $this->name()];
        }

        $to = $this->normalizePhone($to);
        if ($to === '') {
            return ['ok' => false, 'skipped' => true, 'error' => 'Numéro invalide', 'provider' => $this->name()];
        }

        $from = $from ?: $this->from;
        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $this->sid);

        try {
            $response = Http::withBasicAuth($this->sid, $this->token)
                ->asForm()
                ->timeout(15)
                ->post($url, [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return ['ok' => true, 'provider' => $this->name()];
            }

            return [
                'ok' => false,
                'error' => $response->json('message') ?: $response->body(),
                'provider' => $this->name(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'provider' => $this->name()];
        }
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        // Keep leading + ; strip spaces/dashes
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }

        return $phone;
    }
}
