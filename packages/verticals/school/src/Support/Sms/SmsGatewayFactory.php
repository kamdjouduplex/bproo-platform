<?php

namespace School\Support\Sms;

use School\Support\SchoolNotificationSettings;

class SmsGatewayFactory
{
    public static function make(): SmsGateway
    {
        $driver = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_DRIVER)
            ?: (string) config('school.sms.driver', env('SCHOOL_SMS_DRIVER', 'http'));

        $driver = strtolower(trim($driver));

        return match ($driver) {
            'twilio' => new TwilioSmsGateway(
                SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_TWILIO_SID)
                    ?: (string) config('school.sms.twilio.sid', env('TWILIO_ACCOUNT_SID', '')),
                SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_TWILIO_TOKEN)
                    ?: (string) config('school.sms.twilio.token', env('TWILIO_AUTH_TOKEN', '')),
                SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_TWILIO_FROM)
                    ?: (string) config('school.sms.twilio.from', env('TWILIO_FROM', ''))
            ),
            default => new HttpWebhookSmsGateway(
                SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_URL)
                    ?: (string) config('school.sms.http.url', env('SCHOOL_SMS_URL', '')),
                SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_KEY)
                    ?: (string) config('school.sms.http.key', env('SCHOOL_SMS_API_KEY', '')),
                SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_SENDER)
                    ?: (string) config('school.sms.http.sender', env('SCHOOL_SMS_SENDER', 'BprooSchool'))
            ),
        };
    }
}
