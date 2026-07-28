<?php

namespace Pressing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use InovCom\Users\Models\User;
use Pressing\Models\PressingNotification;
use Pressing\Models\PressingNotificationLog;
use Pressing\Models\PressingOrder;
use Pressing\Support\PressingSettings;

class PressingNotificationDispatcher
{
    public function dispatch(string $event, PressingOrder $order, array $context = []): void
    {
        if (! PressingSettings::notificationsEnabled()) {
            $this->log($event, 'system', 'skipped', $order, null, null, 'Notifications désactivées');
            return;
        }

        $order->loadMissing(['client', 'agence', 'receptionist', 'assignee']);

        $message = $this->renderMessage($event, $order, $context);
        if ($message === '') {
            return;
        }

        if (PressingSettings::channelEnabled('in_app')) {
            $this->sendInApp($event, $order, $message, $context);
        }

        // Internal staff handoff — do not message the client
        if ($event === 'assigned_production') {
            return;
        }

        if (PressingSettings::channelEnabled('whatsapp')) {
            $this->sendWhatsApp($event, $order, $message);
        }

        if (PressingSettings::channelEnabled('sms')) {
            $this->sendSms($event, $order, $message);
        }

        if (PressingSettings::channelEnabled('email')) {
            $this->sendEmail($event, $order, $message);
        }
    }

    public function renderMessage(string $event, PressingOrder $order, array $context = []): string
    {
        $key = PressingSettings::eventMessageKey($event);
        if (! $key) {
            return (string) ($context['message'] ?? '');
        }

        $template = PressingSettings::message($key);
        $replacements = [
            '{{client}}' => $order->client?->full_name ?? 'Client',
            '{{number}}' => $order->number,
            '{{amount}}' => number_format((float) ($context['amount'] ?? $order->amount_paid), 0, ',', ' '),
            '{{balance}}' => number_format((float) ($context['balance'] ?? $order->balance), 0, ',', ' '),
            '{{agence}}' => $order->agence?->name ?? '',
        ];

        return strtr($template, $replacements);
    }

    protected function sendInApp(string $event, PressingOrder $order, string $message, array $context): void
    {
        if (! Schema::connection('tenant')->hasTable('pressing_notifications')) {
            $this->log($event, 'in_app', 'skipped', $order, null, $message, 'Table absente');
            return;
        }

        $titles = [
            'order_created' => 'Nouvelle commande',
            'order_ready' => 'Commande prête',
            'order_delivered' => 'Commande livrée',
            'payment_received' => 'Paiement reçu',
            'payment_reminder' => 'Relance paiement',
            'order_overdue' => 'Commande en retard',
            'fin_production' => 'Fin de production — CQ à faire',
            'assigned_production' => 'Commande assignée — production',
        ];

        $tenantCode = request()->query('tenant') ?? session('tenant_code');
        $defaultUrl = match ($event) {
            'fin_production' => route('tenant.pressing_fin_production.index', ['tenant' => $tenantCode]),
            'order_ready' => route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]),
            'assigned_production' => route('tenant.pressing_workflow.index', ['tenant' => $tenantCode]),
            default => route('tenant.pressing_orders.index', ['tenant' => $tenantCode]),
        };

        $userIds = collect([
            $order->receptionist_id,
            $order->assigned_user_id,
            ...($context['user_ids'] ?? []),
        ])->filter()->unique()->values();

        if ($userIds->isEmpty()) {
            $userIds = User::query()->where('is_active', true)->limit(20)->pluck('id');
        }

        foreach ($userIds as $userId) {
            try {
                PressingNotification::create([
                    'user_id' => $userId,
                    'type' => $event,
                    'title' => $titles[$event] ?? 'Notification pressing',
                    'body' => $message,
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->number,
                        'url' => $context['url'] ?? $defaultUrl,
                    ],
                    'order_id' => $order->id,
                ]);
                $this->log($event, 'in_app', 'sent', $order, (int) $userId, $message);
            } catch (\Throwable $e) {
                $this->log($event, 'in_app', 'failed', $order, (int) $userId, $message, $e->getMessage());
            }
        }
    }

    protected function sendWhatsApp(string $event, PressingOrder $order, string $message): void
    {
        $recipient = $order->client?->whatsapp;
        $url = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_WHATSAPP_URL, '');
        $key = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_WHATSAPP_KEY, '');

        if (! $recipient || $url === '' || $key === '') {
            $this->log($event, 'whatsapp', 'skipped', $order, null, $message, 'Clés ou destinataire manquants');
            return;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(12)
                ->post(rtrim($url, '/'), [
                    'to' => $recipient,
                    'message' => $message,
                    'event' => $event,
                    'order' => $order->number,
                ]);

            $this->log(
                $event,
                'whatsapp',
                $response->successful() ? 'sent' : 'failed',
                $order,
                null,
                $message,
                $response->successful() ? null : $response->body(),
                $recipient
            );
        } catch (\Throwable $e) {
            $this->log($event, 'whatsapp', 'failed', $order, null, $message, $e->getMessage(), $recipient);
        }
    }

    protected function sendSms(string $event, PressingOrder $order, string $message): void
    {
        $recipient = $order->client?->phone ?: $order->client?->whatsapp;
        $url = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_SMS_URL, '');
        $key = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_SMS_KEY, '');
        $sender = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_SMS_SENDER, '');

        if (! $recipient || $url === '' || $key === '') {
            $this->log($event, 'sms', 'skipped', $order, null, $message, 'Clés ou destinataire manquants');
            return;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(12)
                ->post(rtrim($url, '/'), [
                    'to' => $recipient,
                    'from' => $sender,
                    'message' => $message,
                    'event' => $event,
                ]);

            $this->log(
                $event,
                'sms',
                $response->successful() ? 'sent' : 'failed',
                $order,
                null,
                $message,
                $response->successful() ? null : $response->body(),
                $recipient
            );
        } catch (\Throwable $e) {
            $this->log($event, 'sms', 'failed', $order, null, $message, $e->getMessage(), $recipient);
        }
    }

    protected function sendEmail(string $event, PressingOrder $order, string $message): void
    {
        $recipient = $order->client?->email;
        if (! $recipient) {
            $this->log($event, 'email', 'skipped', $order, null, $message, 'Email client manquant');
            return;
        }

        $from = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_EMAIL_FROM, config('mail.from.address'));

        try {
            Mail::raw($message, function ($mail) use ($recipient, $from, $order, $event) {
                $mail->to($recipient)
                    ->subject('Pressing — ' . $order->number . ' (' . $event . ')');
                if ($from) {
                    $mail->from($from, config('app.name', 'Pressing'));
                }
            });
            $this->log($event, 'email', 'sent', $order, null, $message, null, $recipient);
        } catch (\Throwable $e) {
            Log::warning('Pressing email notification failed', ['error' => $e->getMessage()]);
            $this->log($event, 'email', 'failed', $order, null, $message, $e->getMessage(), $recipient);
        }
    }

    protected function log(
        string $event,
        string $channel,
        string $status,
        ?PressingOrder $order,
        ?int $userId,
        ?string $message,
        ?string $error = null,
        ?string $recipient = null
    ): void {
        if (! Schema::connection('tenant')->hasTable('pressing_notification_logs')) {
            return;
        }

        try {
            PressingNotificationLog::create([
                'event' => $event,
                'channel' => $channel,
                'status' => $status,
                'order_id' => $order?->id,
                'user_id' => $userId,
                'recipient' => $recipient,
                'message' => $message,
                'error' => $error,
            ]);
        } catch (\Throwable $e) {
            Log::debug('Pressing notification log failed: ' . $e->getMessage());
        }
    }
}
