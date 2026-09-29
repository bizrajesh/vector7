<?php

return [
    'channels' => [
        // Authentication failures, access denials, impersonation and webhook rejections.
        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'days' => 90,
        ],
    ],
];
