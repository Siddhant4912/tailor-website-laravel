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

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
    ],

    'gooadvert' => [
        'api_key' => env('GOOADVERT_API_KEY'),
        'user' => env('GOOADVERT_USER'),
        'password' => env('GOOADVERT_PASSWORD'),
        'sender_id' => env('GOOADVERT_SENDER_ID'),
        'peid' => env('GOOADVERT_PEID'),
        'template_id' => env('GOOADVERT_TEMPLATE_ID'),
        'route' => env('GOOADVERT_ROUTE'),
        'channel' => env('GOOADVERT_CHANNEL'),
        'template_text' => env('GOOADVERT_TEMPLATE_TEXT'),
    ],


];
