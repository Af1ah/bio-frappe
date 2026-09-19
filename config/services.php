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

    'frappe' => [
        'url' => env('FRAPPE_API_URL', 'https://hrm.secumaxtech.com'),
        'api_key' => env('FRAPPE_API_KEY'),
        'api_secret' => env('FRAPPE_API_SECRET'),
        'employee_fieldname' => env('FRAPPE_EMPLOYEE_FIELDNAME', 'attendance_device_id'),
    ],

];
