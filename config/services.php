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

    'facebook_capi_siulsan_resgate_b1s' => [
        'pixel_id'      => env('PIXEL_ID_SIULSAN_RESGATE_B1S'),
        'access_token'  => env('FACEBOOK_ACCESS_TOKEN_SIULSAN_RESGATE_B1S'),
    ],

    'facebook_capi_siulsan_resgate_b2s' => [
        'pixel_id'      => env('PIXEL_ID_SIULSAN_RESGATE_B2S'),
        'access_token'  => env('FACEBOOK_ACCESS_TOKEN_SIULSAN_RESGATE_B2S'),
    ],

    'facebook_capi_susan_pet_rescue_b1s' => [
        'pixel_id'      => env('PIXEL_ID_SUSAN_PET_RESCUE_B1S'),
        'access_token'  => env('FACEBOOK_ACCESS_TOKEN_SUSAN_PET_RESCUE_B1S'),
    ],

    'facebook_capi_susan_pet_rescue_b2s' => [
        'pixel_id'      => env('PIXEL_ID_SUSAN_PET_RESCUE_B2S'),
        'access_token'  => env('FACEBOOK_ACCESS_TOKEN_SUSAN_PET_RESCUE_B2S'),
    ],

    'backfill' => [
        'secret' => env('BACKFILL_SECRET'),
    ],

    'utmify' => [
        'url'    => env('UTMFY_URL'),
        'api_key' => env('UTMFY_API_KEY'),
    ],

    'utmify_susan_pet_rescue' => [
        'url'    => env('UTMFY_URL_SUSAN_PET_RESCUE'),
        'api_key' => env('UTMFY_API_KEY_SUSAN_PET_RESCUE'),
    ],

    'lytex' => [
        'api_key' => env('LYTEX_API_TOKEN'),
        'client_id' => env('LYTEX_CLIENT_ID'),
        'client_secret' => env('LYTEX_CLIENT_SECRET'),
        'auth_url' => env('LYTEX_AUTH_URL', 'https://sandbox-api-pay.lytex.com.br/v2/auth/obtain_token'),
        'api_url' => env('LYTEX_API_URL', 'https://sandbox-api-pay.lytex.com.br/v2/invoices'),
        'subscriptions_url' => env('LYTEX_SUBSCRIPTIONS_URL', 'https://sandbox-api-pay.lytex.com.br/v2/subscriptions')
    ],

    'transfeera' => [
        'api_key' => env('TRANSFEERA_API_KEY'),
        'client_id' => env('TRANSFEERA_CLIENT_ID'),
        'client_secret' => env('TRANSFEERA_CLIENT_SECRET'),
        'pix_key' => env('TRANSFEERA_PIX_KEY')
    ],

    'givewp' => [
        'secret' => env('GIVEWP_SECRET'),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),

        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'verify_webhook'=> env('PAYPAL_VERIFY_WEBHOOK', true),

        'test_secret'   => env('PAYPAL_WEBHOOK_TEST_SECRET', '')
    ]
];
