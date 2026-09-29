<?php

return [
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        // Fixed host: outbound calls never go to a user-supplied URL (SSRF defence).
        'base_url' => 'https://graph.facebook.com/v20.0',
    ],
];
