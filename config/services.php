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

    'whatsapp_web' => [
        'base_url' => env('WHATSAPP_WEB_BASE_URL'),
        'api_key' => env('WHATSAPP_WEB_API_KEY'),
        'instance' => env('WHATSAPP_WEB_INSTANCE', 'refugio-agostino-rocca'),
        'webhook_secret' => env('WHATSAPP_WEB_WEBHOOK_SECRET'),
        'timeout' => env('WHATSAPP_WEB_TIMEOUT', 15),
    ],

    'bot_settings' => [
        'username' => env('BOT_SETTINGS_USERNAME', 'chatbot'),
        'password' => env('BOT_SETTINGS_PASSWORD', 'frias'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1/chat/completions'),
    ],

    'rag' => [
        'top_k' => (int) env('RAG_INITIAL_TOP_K', 12),
        'retrieval_min_score' => (float) env('RAG_RETRIEVAL_MIN_SCORE', 0.12),
        'classifier_confidence_threshold' => (float) env('CHATBOT_INTENT_MIN_CONFIDENCE', 0.65),
        // 0 means the complete catalog. Set only after measuring prompt size.
        'intent_catalog_limit' => (int) env('CHATBOT_INTENT_CATALOG_LIMIT', 0),
    ],

];
