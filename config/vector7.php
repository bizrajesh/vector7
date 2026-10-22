<?php

return [
    /*
    | Preview gate (testing on a live server): one shared email + password in front of the whole site.
    | Store only the bcrypt hash of the password (php artisan v7:gate-hash). Turn off with PREVIEW_GATE=false
    | when the marketplace goes public — the marketplace home page then becomes the landing page again.
    */
    'preview_gate' => [
        'enabled' => (bool) env('PREVIEW_GATE', false),
        'email' => strtolower(trim((string) env('PREVIEW_GATE_EMAIL', ''))),
        'password_hash' => (string) env('PREVIEW_GATE_PASSWORD_HASH', ''),
        'remember_days' => (int) env('PREVIEW_GATE_REMEMBER_DAYS', 30),
    ],

    /*
    | Self sign-up for buyers, property listers and layout promoters. false = nobody can create an account
    | from the website (existing accounts still sign in; the App team and promoters can still add users).
    */
    'registration_open' => (bool) env('REGISTRATION_OPEN', true),
];
