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

    'telegram-bot-api' => [
        'token' => trim(env('TELEGRAM_BOT_TOKEN', 'YOUR BOT TOKEN HERE'), '"\'')
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH'),
        'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
    ],

    'ffprobe' => [
        'path' => env('FFPROBE_PATH'),
    ],

    'ffmpeg' => [
        'path' => env('FFMPEG_PATH'),
    ],

    'audio_silence' => [
        'threshold_db' => env('AUDIO_SILENCE_THRESHOLD_DB', -45),
        'minimum_seconds' => env('AUDIO_SILENCE_MIN_SECONDS', 2.0),
        'max_segments' => env('AUDIO_SILENCE_MAX_SEGMENTS', 24),
    ],

];
