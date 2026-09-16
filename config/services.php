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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'instance_name' => env('WHATSAPP_INSTANCE_NAME'),
        'api_key' => env('WHATSAPP_API_KEY'),
    ],

    // Private management API on the Go ADMS gateway. This is never a
    // device-facing URL and must be configured with a scoped secret.
    'device_gateway' => [
        'url' => env('DEVICE_GATEWAY_URL'),
        'token' => env('DEVICE_GATEWAY_TOKEN'),
        'device_host' => env('ADMS_DEVICE_HOST', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        'device_port' => (int) env('ADMS_DEVICE_PORT', env('ADMS_PORT', 8080)),
    ],

];
