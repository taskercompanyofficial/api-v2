<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for WhatsApp Business Platform
    | integration. Make sure to set up your credentials in the .env file.
    |
    */

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),

    'graph_api_url' => env('WHATSAPP_GRAPH_API_URL', 'https://graph.facebook.com'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | WhatsApp Business API has rate limits. Configure retry and timeout
    | settings here.
    |
    */

    'rate_limit' => [
        'max_retries' => 3,
        'retry_delay' => 1000, // milliseconds
        'timeout' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    |
    | Configure where WhatsApp media files should be stored.
    |
    */

    'media' => [
        'disk' => env('WHATSAPP_MEDIA_DISK', 'local'),
        'path' => 'whatsapp/media',
        'max_size' => 16 * 1024 * 1024, // 16MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Cache
    |--------------------------------------------------------------------------
    |
    | Cache settings for WhatsApp message templates.
    |
    */

    'template_cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configure webhook processing settings.
    |
    */

    'webhook' => [
        'queue' => env('WHATSAPP_WEBHOOK_QUEUE', 'default'),
        'verify_signature' => env('WHATSAPP_VERIFY_SIGNATURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for WhatsApp messages.
    |
    */

    'messages' => [
        'default_language' => 'en',
        'auto_mark_read' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Flows Configuration
    |--------------------------------------------------------------------------
    |
    | WhatsApp Flows allow interactive multi-step experiences. These settings
    | configure encryption keys for secure data exchange with Flow endpoints.
    |
    */

    'flows' => [
        'enabled' => env('WHATSAPP_FLOWS_ENABLED', false),
        'private_key_path' => env('WHATSAPP_FLOWS_PRIVATE_KEY_PATH', storage_path('keys/whatsapp_flows_private.pem')),
        'private_key_passphrase' => env('WHATSAPP_FLOWS_PRIVATE_KEY_PASSPHRASE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | App Secret
    |--------------------------------------------------------------------------
    |
    | The Meta App Secret used for webhook signature validation.
    | Required for secure Flow endpoint signature verification.
    |
    */

    'app_secret' => env('WHATSAPP_APP_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Bot Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the menu-based WhatsApp chatbot that handles customer
    | inquiries, work order status checks, and agent escalation.
    |
    */

    'bot' => [
        'enabled' => env('WHATSAPP_BOT_ENABLED', true),
        'session_ttl' => 1800, // 30 minutes
    ],

];
