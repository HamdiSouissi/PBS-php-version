<?php

declare(strict_types=1);

return [
    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'algo' => 'HS256',
        'ttl' => (int) env('JWT_TTL', '3600'),
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', (string) (14 * 86400)),
        'issuer' => env('APP_URL', 'http://localhost:8080'),
    ],
    'password' => [
        'algo' => PASSWORD_ARGON2ID,
        'options' => [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2,
        ],
        'min_length' => 12,
    ],
    'rate_limit' => [
        'enabled' => filter_var(env('RATE_LIMIT_ENABLED', 'true'), FILTER_VALIDATE_BOOL),
        'max' => (int) env('RATE_LIMIT_MAX', '60'),
        'window' => (int) env('RATE_LIMIT_WINDOW', '60'),
        'login_max' => 5,
    ],
    'totp' => [
        'issuer' => env('APP_NAME', 'Prime Business Suite'),
        'digits' => 6,
        'period' => 30,
        'window' => 1,
    ],
    'upload' => [
        'max_size' => 10 * 1024 * 1024,
        'allowed_mimes' => [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
        ],
        'allowed_extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'docx', 'xlsx', 'csv', 'txt'],
    ],
];
