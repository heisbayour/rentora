<?php

declare(strict_types=1);

return [

    /*
     * Product name. To rename the product change APP_NAME in .env and the
     * strings below. Nothing else is hard-coded.
     */
    'name' => env('APP_NAME', 'Rentora'),
    'tagline' => 'Rental property management',

    'version' => '1.0.0',

    'multi_org' => (bool) env('RENTORA_MULTI_ORG', false),

    'defaults' => [
        'currency' => 'NGN',
        'timezone' => 'Africa/Lagos',
        'country' => 'Nigeria',
        'phone_country_code' => '234',
        'primary_color' => '#1A73E8',
    ],

    'scheduler' => [
        'mode' => env('RENTORA_SCHEDULER_MODE', 'lazy'),
        'tick_key' => env('RENTORA_TICK_KEY'),
        'tick_interval_minutes' => (int) env('RENTORA_TICK_INTERVAL_MINUTES', 5),
        'budget_seconds' => (int) env('RENTORA_TICK_BUDGET_SECONDS', 8),
        'lock_seconds' => 120,
    ],

    'outbox' => [
        'batch' => (int) env('RENTORA_OUTBOX_BATCH', 10),
        'max_attempts' => 4,
        'backoff_minutes' => [5, 15, 60],
    ],

    'install' => [
        'key' => env('RENTORA_INSTALL_KEY'),
        'lock_file' => 'installed.lock',
        'required_php' => '8.2.0',
        'required_extensions' => [
            'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype',
            'json', 'fileinfo', 'gd', 'curl', 'bcmath',
        ],
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'webhooks' => (bool) env('RENTORA_PAYSTACK_WEBHOOKS', false),
        'min_amount' => 10000,
        'reconcile_after_minutes' => 10,
        'reconcile_give_up_hours' => 48,
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'termii' => [
            'api_key' => env('TERMII_API_KEY'),
            'sender_id' => env('TERMII_SENDER_ID'),
            'channel' => env('TERMII_CHANNEL', 'generic'),
            'base_url' => env('TERMII_BASE_URL', 'https://v3.api.termii.com'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    'uploads' => [
        'max_kb' => 8192,
        'mimes' => 'jpg,jpeg,png,webp,pdf',
        'image_mimes' => 'jpg,jpeg,png,webp',
        'public_dir' => 'uploads',
    ],

    'links' => [
        'invite_days' => 7,
        'vendor_days' => 14,
    ],

    'per_page' => 25,

];
