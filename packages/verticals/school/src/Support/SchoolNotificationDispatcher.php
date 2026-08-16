<?php

namespace School\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use School\Models\SchoolNotificationLog;
use School\Models\SchoolStudent;
use School\Support\Sms\SmsGatewayFactory;

class SchoolNotificationDispatcher
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function dispatch(string $event, ?SchoolStudent $student = null, array $context = []): void
    {
        if (! SchoolNotificationSettings::enabled()) {
            $this->log($event, 'system', 'skipped', $student, null, null, 'Notifications désactivées');

            return;
        }

        $message = $this->renderMessage($event, $student, $context);
        if ($message === '') {
            return;
        }

        if (SchoolNotificationSettings::channelEnabled('sms')) {
            $this->sendSms($event, $student, $message, $context);
        }

        if (SchoolNotificationSettings::channelEnabled('email')) {
            $this->sendEmail($event, $student, $message, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderMessage(string $event, ?SchoolStudent $student, array $context = []): string
    {
        $templateKey = match ($event) {
            'enrollment' => SchoolNotificationSettings::KEY_MSG_ENROLLMENT,
            'payment' => SchoolNotificationSettings::KEY_MSG_PAYMENT,
            'results' => SchoolNotificationSettings::KEY_MSG_RESULTS,
            'report_card' => SchoolNotificationSettings::KEY_MSG_REPORT_CARD,
            'announcement' => SchoolNotificationSettings::KEY_MSG_ANNOUNCEMENT,
            default => null,
        };

        $template = $templateKey
            ? SchoolNotificationSettings::get($templateKey)
            : (string) ($context['message'] ?? '');

        $replacements = [
            '{{student}}' => $student?->full_name ?? ($context['student'] ?? 'Élève'),
            '{{code}}' => $student?->student_code ?? ($context['code'] ?? ''),
            '{{parent}}' => $student?->parent_full_name ?? 'Parent',
            '{{year}}' => (string) ($context['year'] ?? ''),
            '{{class}}' => (string) ($context['class'] ?? ''),
            '{{amount}}' => (string) ($context['amount'] ?? ''),
            '{{currency}}' => (string) ($context['currency'] ?? 'XOF'),
            '{{reference}}' => (string) ($context['reference'] ?? ''),
            '{{average}}' => (string) ($context['average'] ?? ''),
            '{{mention}}' => (string) ($context['mention'] ?? ''),
            '{{message}}' => (string) ($context['message'] ?? ''),
        ];

        return trim(strtr($template, $replacements));
    }

    protected function sendSms(string $event, ?SchoolStudent $student, string $message, array $context): void
    {
        $recipient = $student?->parent_phone ?: ($context['phone'] ?? null);
        $sender = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_SENDER);
        $gateway = SmsGatewayFactory::make();

        if (! $recipient) {
            $this->log($event, 'sms', 'skipped', $student, null, $message, 'Téléphone manquant');

            return;
        }

        if (! $gateway->configured()) {
            $this->log(
                $event,
                'sms',
                'skipped',
                $student,
                $recipient,
                $message,
                'Provider SMS « '.$gateway->name().' » non configuré — renseignez Twilio ou le webhook'
            );

            return;
        }

        $result = $gateway->send((string) $recipient, $message, $sender ?: null);

        if (! empty($result['skipped'])) {
            $this->log($event, 'sms', 'skipped', $student, $recipient, $message, $result['error'] ?? 'skipped');

            return;
        }

        $this->log(
            $event,
            'sms',
            ! empty($result['ok']) ? 'sent' : 'failed',
            $student,
            $recipient,
            $message,
            $result['error'] ?? null
        );
    }

    protected function sendEmail(string $event, ?SchoolStudent $student, string $message, array $context): void
    {
        $recipient = $student?->parent_email ?: ($context['email'] ?? null);
        if (! $recipient) {
            $this->log($event, 'email', 'skipped', $student, null, $message, 'Email parent manquant');

            return;
        }

        $from = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_EMAIL_FROM)
            ?: (string) config('mail.from.address');

        $subjects = [
            'enrollment' => 'Inscription scolaire',
            'payment' => 'Paiement reçu',
            'results' => 'Résultats publiés',
            'report_card' => 'Bulletin scolaire',
            'announcement' => 'Annonce école',
        ];

        try {
            Mail::raw($message, function ($mail) use ($recipient, $from, $event, $subjects) {
                $mail->to($recipient)->subject('École — '.($subjects[$event] ?? $event));
                if ($from) {
                    $mail->from($from, config('app.name', 'Bproo School'));
                }
            });
            $this->log($event, 'email', 'sent', $student, $recipient, $message);
        } catch (\Throwable $e) {
            Log::warning('School email notification failed', ['error' => $e->getMessage()]);
            $this->log($event, 'email', 'failed', $student, $recipient, $message, $e->getMessage());
        }
    }

    protected function log(
        string $event,
        string $channel,
        string $status,
        ?SchoolStudent $student,
        ?string $recipient,
        ?string $message,
        ?string $error = null
    ): void {
        try {
            if (! Schema::connection('tenant')->hasTable('school_notification_logs')) {
                return;
            }

            SchoolNotificationLog::query()->create([
                'event' => $event,
                'channel' => $channel,
                'status' => $status,
                'student_id' => $student?->id,
                'recipient' => $recipient,
                'message' => $message,
                'error' => $error,
            ]);
        } catch (\Throwable $e) {
            Log::debug('School notification log failed: '.$e->getMessage());
        }
    }
}
