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

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'apple' => [
        'issuer_id' => env('APPLE_ISSUER_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH', 'app/apple/AuthKey.p8'),
        'bundle_id' => env('APPLE_BUNDLE_ID'),
        'sandbox' => env('APPLE_SANDBOX', true),
    ],

    'google_auth' => [
        'ios_client_id' => env('GOOGLE_AUTH_IOS_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_AUTH_ANDROID_CLIENT_ID'),
        'server_client_id' => env('GOOGLE_AUTH_SERVER_CLIENT_ID'),
        'allowed_client_ids' => array_values(array_filter([
            env('GOOGLE_AUTH_IOS_CLIENT_ID'),
            env('GOOGLE_AUTH_ANDROID_CLIENT_ID'),
            env('GOOGLE_AUTH_SERVER_CLIENT_ID'),
        ])),
    ],

    'google_play' => [
        'paket_adi' => env('GOOGLE_PLAY_PAKET_ADI'),
        'service_account_path' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_PATH', 'app/google/service-account.json'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', 'app/firebase/service-account.json'),
    ],

];
