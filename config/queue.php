<?php

declare(strict_types=1);

return [
    'driver' => env('QUEUE_DRIVER', 'file'),
    'file' => [
        'path' => storage_path('queue'),
    ],
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', '6379'),
        'database' => 1,
    ],
    'default_queue' => 'default',
];
