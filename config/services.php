<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Pengiriman OTP lewat WhatsApp (lihat App\Services\AuthService)
    'whatsapp' => [
        'token' => env('WHATSAPP_META_TOKEN'),
        'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID', '244876815379879'),
        'webhook_url' => env('WHATSAPP_WEBHOOK_URL', 'https://webhook.lembaharafah.com/webhook.php'),
        'webhook_sender' => env('WHATSAPP_WEBHOOK_SENDER', 'ginougi'),
    ],

    // Push notification Firebase Cloud Messaging (HTTP v1).
    // FCM_CREDENTIALS = path file JSON service account dari Firebase Console.
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
