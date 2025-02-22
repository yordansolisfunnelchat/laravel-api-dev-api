<?php
# serf
return [
    'python' => [
        'url' => env('PYTHON_SERVICE_URL', 'http://127.0.0.1:7070'),
        'timeout' => env('PYTHON_SERVICE_TIMEOUT', 60),
    ],

    
    'evolution_api' => [
        'base_url' => env('EVOLUTION_API_BASE_URL', 'https://ezcala-ai-evolution-api.3bx9yv.easypanel.host'),
    ],

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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],


    'fastapi' => [
        'base_url' => env('FASTAPI_BASE_URL', 'http://host.docker.internal:7070'),
        'shared_secret' => env('FASTAPI_SHARED_SECRET', 'mi_secreto_compartido'),
    ],


];
