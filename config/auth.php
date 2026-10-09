<?php

/*
| Two separate logins:
|  - staff (App users + tenant users) → guard "web",      /workspace/login
|  - marketplace customers           → guard "customer", /login
| Password-only login in this release (no two-factor / OTP).
*/
return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        'customer' => ['driver' => 'session', 'provider' => 'customers'],
    ],

    'providers' => [
        'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class],
        'customers' => ['driver' => 'eloquent', 'model' => App\Models\Customer::class],
    ],

    // Reset links are valid 60 minutes and single use.
    'passwords' => [
        'users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'customers' => ['provider' => 'customers', 'table' => 'customer_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
    ],

    'password_timeout' => 10800,

    // Account lockout after repeated wrong passwords
    'lockout' => ['attempts' => 5, 'minutes' => 15],
];
