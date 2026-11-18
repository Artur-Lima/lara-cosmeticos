<?php

return [

    'asaas' => [
        'key' => env('ASAAS_API_KEY'),
        'env' => env('ASAAS_ENV', 'sandbox'), // sandbox | production
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],

    // Conector de WhatsApp (n8n + Evolution API): recebe eventos da loja e envia as mensagens.
    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_ENABLED', false),
        'connector_url' => env('WHATSAPP_CONNECTOR_URL'),
        'connector_token' => env('WHATSAPP_CONNECTOR_TOKEN'),
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

];
