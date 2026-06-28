<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'netcommerce' => [
        'iPay' => [
            'merchant_nb' 	=> env('IPAY_NET_MERCHANT_NB'),
            'merchant_key' 	=> env('IPAY_NET_MERCHANT_KEY'),
            'url'	=> env('IPAY_NET_URL'),
            'mode' => env('IPAY_NET_MERCHANT_MODE'),
            'saop_url' => env('IPAY_NET_SOAP_URL'),
        ],

        'bill' => [
            'merchant_nb' 	=> env('BILL_NET_MERCHANT_NB'),
            'merchant_key' 	=> env('BILL_NET_MERCHANT_KEY'),
            'url'	=> env('BILL_NET_URL'),
            'mode' => env('BILL_NET_MERCHANT_MODE'),
            'saop_url' => env('BILL_NET_SOAP_URL'),

        ]
    ],

    'facebook' => [
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'token' => env('FACEBOOK_TOKEN'),
    ],

    'instagram' => [
        'user_id' => env('INSTAGRAM_USER_ID'),
        'token' => env('INSTAGRAM_TOKEN'),
    ],

];
