<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servicios externos
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', true),
        'default_country' => env('WHATSAPP_DEFAULT_COUNTRY', 'EC'),
        'reminder_hours' => env('WHATSAPP_REMINDER_HOURS', 6),
        'reminder_window_minutes' => env('WHATSAPP_REMINDER_WINDOW_MINUTES', 10),
    ],

    'face' => [
        // Umbral ajustado para reducir falsos negativos en cambios moderados (ej. corte de cabello).
        'threshold'    => env('FACE_MATCH_THRESHOLD', 0.60),
        'max_failures' => env('FACE_MAX_FAILURES', 5),
    ],

];
