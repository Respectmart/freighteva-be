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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY') ?: env('STRIPE_PUBLIC_KEY') ?: env('STRIPE_PUBLISHABLE_KEY'),
        'secret' => env('STRIPE_SECRET') ?: env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'didit' => [
        'api_key' => env('DIDIT_API_KEY', 'sk_test_edysu5Nt7FdhcufsWOD6EtHov2eP50Qbq2fNLZ3XqSY'),
        'workflow_id' => env('DIDIT_WORKFLOW_ID', '2d10924a-63db-4b35-8419-47cd2bb545ae'),
        'base_url' => env('DIDIT_BASE_URL', 'https://verification.didit.me/v3'),
        'webhook_secret' => env('DIDIT_WEBHOOK_SECRET', ''),
    ],

];
