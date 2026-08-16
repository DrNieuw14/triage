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

    'triage_api' => [
        'url' => env('TRIAGE_API_URL', 'http://127.0.0.1:5055'),
        'train_secret' => env('TRAIN_SHARED_SECRET', 'change-this-shared-secret'),
    ],

    'training_page' => [
        // Gates the /train (dataset upload + retrain) page only — separate
        // from train_secret above, which authenticates Laravel to the
        // Python API itself.
        'password' => env('TRAIN_PAGE_PASSWORD', 'changeme123'),
    ],

];
