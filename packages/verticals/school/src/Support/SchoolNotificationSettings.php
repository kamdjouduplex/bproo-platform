<?php

namespace School\Support;

use School\Models\SchoolNotificationSetting;
use Illuminate\Support\Facades\Schema;

class SchoolNotificationSettings
{
    public const KEY_ENABLED = 'notifications_enabled';
    public const KEY_SMS = 'channel_sms';
    public const KEY_EMAIL = 'channel_email';
    public const KEY_SMS_DRIVER = 'sms_driver';
    public const KEY_SMS_URL = 'sms_url';
    public const KEY_SMS_KEY = 'sms_api_key';
    public const KEY_SMS_SENDER = 'sms_sender';
    public const KEY_TWILIO_SID = 'twilio_sid';
    public const KEY_TWILIO_TOKEN = 'twilio_token';
    public const KEY_TWILIO_FROM = 'twilio_from';
    public const KEY_EMAIL_FROM = 'email_from';

    public const KEY_MSG_ENROLLMENT = 'msg_enrollment';
    public const KEY_MSG_PAYMENT = 'msg_payment';
    public const KEY_MSG_RESULTS = 'msg_results';
    public const KEY_MSG_REPORT_CARD = 'msg_report_card';
    public const KEY_MSG_ANNOUNCEMENT = 'msg_announcement';

    public static function defaults(): array
    {
        return [
            self::KEY_ENABLED => '1',
            self::KEY_SMS => '1',
            self::KEY_EMAIL => '1',
            self::KEY_SMS_DRIVER => 'http',
            self::KEY_SMS_URL => '',
            self::KEY_SMS_KEY => '',
            self::KEY_SMS_SENDER => 'BprooSchool',
            self::KEY_TWILIO_SID => '',
            self::KEY_TWILIO_TOKEN => '',
            self::KEY_TWILIO_FROM => '',
            self::KEY_EMAIL_FROM => '',
            self::KEY_MSG_ENROLLMENT => 'Bonjour {{parent}}, {{student}} ({{code}}) est inscrit(e) pour {{year}} — classe {{class}}.',
            self::KEY_MSG_PAYMENT => 'Paiement de {{amount}} {{currency}} reçu pour {{student}} ({{code}}). Réf: {{reference}}.',
            self::KEY_MSG_RESULTS => 'Résultats publiés pour {{student}} ({{code}}) — moyenne {{average}}, mention {{mention}}.',
            self::KEY_MSG_REPORT_CARD => 'Bulletin disponible pour {{student}} ({{code}}) — année {{year}}.',
            self::KEY_MSG_ANNOUNCEMENT => '{{message}}',
        ];
    }

    public static function get(string $key, ?string $default = null): string
    {
        if (! self::tableReady()) {
            return $default ?? (self::defaults()[$key] ?? '');
        }

        $row = SchoolNotificationSetting::query()->where('key', $key)->first();
        if ($row && $row->value !== null) {
            return (string) $row->value;
        }

        return $default ?? (self::defaults()[$key] ?? '');
    }

    public static function set(string $key, ?string $value): void
    {
        if (! self::tableReady()) {
            return;
        }

        SchoolNotificationSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function enabled(): bool
    {
        return self::get(self::KEY_ENABLED, '1') === '1';
    }

    public static function channelEnabled(string $channel): bool
    {
        return match ($channel) {
            'sms' => self::get(self::KEY_SMS, '1') === '1',
            'email' => self::get(self::KEY_EMAIL, '1') === '1',
            default => false,
        };
    }

    public static function seedDefaults(): void
    {
        if (! self::tableReady()) {
            return;
        }

        foreach (self::defaults() as $key => $value) {
            SchoolNotificationSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    protected static function tableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_notification_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
