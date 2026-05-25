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

    'avatax' => [
        'account_number' => env('AVATAX_ACCOUNT_NUMBER'),
        'license_key' => env('AVATAX_LICENSE_KEY'),
        'company_code' => env('AVATAX_COMPANY_CODE', 'DEFAULT'),
        'environment' => env('AVATAX_ENVIRONMENT', 'sandbox'),
        'enabled' => env('AVATAX_ENABLED', false),
        'tax_code' => env('AVATAX_TAX_CODE', 'P0000000'),
        'ship_from' => [
            'street' => env('AVATAX_SHIP_FROM_STREET', ''),
            'city' => env('AVATAX_SHIP_FROM_CITY', ''),
            'state' => env('AVATAX_SHIP_FROM_STATE', ''),
            'zip' => env('AVATAX_SHIP_FROM_ZIP', ''),
            'country' => env('AVATAX_SHIP_FROM_COUNTRY', 'US'),
        ],
    ],

];
