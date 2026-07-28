<?php

namespace InovCom\Devis\Services;

use App\Services\PdfService;
use App\Services\TenantManager;
use InovCom\Devis\Models\Quote;

/**
 * Builds RFC822 .eml files that open in the user's default mail client (Outlook, Mail, Thunderbird…)
 * with the quote PDF pre-attached — no SMTP server required.
 */
class QuoteEmailDraftService
{
    public function __construct(
        private readonly PdfService $pdf = new PdfService(),
    ) {}

    public function build(Quote $quote, string $type = 'send'): string
    {
        $quote->loadMissing(['lines', 'client']);

        $client = $quote->client;
        if (!$client?->email) {
            throw new \RuntimeException(__('Aucune adresse e-mail enregistrée pour ce client.'));
        }

        $tenant = app(TenantManager::class)->tenant();
        $fromEmail = (string) ($tenant?->getSetting('company_email', config('mail.from.address', '')) ?: 'commercial@example.com');
        $fromName  = (string) ($tenant?->getSetting('company_name', config('app.name', 'ERP')) ?: config('app.name'));
        $replyTo   = (string) ($tenant?->getSetting('company_email', $fromEmail) ?: $fromEmail);

        $pdfFilename = 'Devis-' . $quote->code . '.pdf';
        $pdfContent  = $this->pdf->output('pdf.quote', ['quote' => $quote]);

        $subject = $this->subject($quote, $type);
        $body    = $this->body($quote, $type, $tenant);

        return $this->composeMultipart(
            fromEmail: $fromEmail,
            fromName: $fromName,
            toEmail: $client->email,
            toName: $client->name,
            replyTo: $replyTo,
            subject: $subject,
            bodyText: $body,
            attachmentName: $pdfFilename,
            attachmentContent: $pdfContent,
            attachmentMime: 'application/pdf',
        );
    }

    public function suggestedFilename(Quote $quote, string $type = 'send'): string
    {
        $suffix = $type === 'reminder' ? 'relance' : 'envoi';

        return 'Devis-' . $quote->code . '-' . $suffix . '.eml';
    }

    private function subject(Quote $quote, string $type): string
    {
        $company = config('app.name', 'ERP');

        return match ($type) {
            'reminder' => __('Rappel : devis :code — :title', [
                'code' => $quote->code,
                'title' => $quote->title,
            ]),
            default => __('Devis :code — :title — :company', [
                'code' => $quote->code,
                'title' => $quote->title,
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
            $lines[] = __('Le PDF du devis est joint à ce message.');
            $lines[] = '';
            $lines[] = __('Cordialement,');
            $lines[] = $company;

            return implode("\r\n", $lines);
        }

        $lines = [
            __('Bonjour :name,', ['name' => $clientName]),
            '',
            __('Veuillez trouver ci-joint notre devis :code.', ['code' => $quote->code]),
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

        return implode("\r\n", $lines);
    }

    private function composeMultipart(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $replyTo,
        string $subject,
        string $bodyText,
        string $attachmentName,
        string $attachmentContent,
        string $attachmentMime,
    ): string {
        $boundary = '----=_Part_' . md5(uniqid((string) mt_rand(), true));
        $encodedSubject = $this->encodeHeader($subject);
        $fromHeader = $this->formatAddress($fromEmail, $fromName);
        $toHeader   = $this->formatAddress($toEmail, $toName);

        $headers = [
            'From: ' . $fromHeader,
            'To: ' . $toHeader,
            'Reply-To: ' . $replyTo,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'X-Unsent: 1',
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
        ];

        $body  = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($bodyText) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: ' . $attachmentMime . '; name="' . $this->escapeFilename($attachmentName) . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= 'Content-Disposition: attachment; filename="' . $this->escapeFilename($attachmentName) . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode($attachmentContent), 76, "\r\n");
        $body .= '--' . $boundary . "--\r\n";

        return $body;
    }

    private function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }

    private function escapeFilename(string $name): string
    {
        return str_replace(['"', "\r", "\n"], '', $name);
    }
}
