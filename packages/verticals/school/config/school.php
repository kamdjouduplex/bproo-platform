<?php

return [
    'sms' => [
        'driver' => env('SCHOOL_SMS_DRIVER', 'http'), // http | twilio
        'http' => [
            'url' => env('SCHOOL_SMS_URL', ''),
            'key' => env('SCHOOL_SMS_API_KEY', ''),
            'sender' => env('SCHOOL_SMS_SENDER', 'BprooSchool'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_ACCOUNT_SID', ''),
            'token' => env('TWILIO_AUTH_TOKEN', ''),
            'from' => env('TWILIO_FROM', ''),
        ],
    ],
];
