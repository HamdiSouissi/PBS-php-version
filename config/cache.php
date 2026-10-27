<?php

declare(strict_types=1);

return [
    'driver' => env('CACHE_DRIVER', 'file'),
    'file' => [
        'path' => storage_path('cache'),
        'ttl' => 3600,
    ],
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', '6379'),
        'database' => 0,
    ],
];
