<?php

declare(strict_types=1);

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'root' => storage_path(''),
        ],
        'uploads' => [
            'root' => storage_path('uploads'),
        ],
        'documents' => [
            'root' => storage_path('documents'),
        ],
        'exports' => [
            'root' => storage_path('exports'),
        ],
        'backups' => [
            'root' => storage_path('backups'),
        ],
    ],
];
