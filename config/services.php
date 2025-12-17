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

    'facebook_capi' => [
        'pixel_id'      => env('PIXEL_ID'),
        'access_token'  => env('FACEBOOK_ACCESS_TOKEN'),
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
        'api_key'=> env('UTMFY_API_KEY'),
    ],

    'utmify_susan_pet_rescue' => [
        'url'    => env('UTMFY_URL_SUSAN_PET_RESCUE'),
        'api_key'=> env('UTMFY_API_KEY_SUSAN_PET_RESCUE'),
    ],

    'lytex' => [
        'api_key' => env('LYTEX_API_TOKEN')
    ],

    'transfeera' => [
        'api_key' => env('TRANSFEERA_API_KEY'),
        'client_id' => env('TRANSFEERA_CLIENT_ID'),
        'client_secret' => env('TRANSFEERA_CLIENT_SECRET') 
    ],

    'givewp' => [
        'secret' => env('GIVEWP_SECRET'),
    ],
];
