<?php

declare(strict_types=1);

return [
    'name' => env('SESSION_NAME', 'pbs_session'),
    'lifetime' => (int) env('SESSION_LIFETIME', '7200'),
    'remember_lifetime' => (int) env('REMEMBER_LIFETIME', (string) (30 * 86400)),
    'secure' => filter_var(env('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOL),
    'httponly' => true,
    'samesite' => 'Lax',
    'save_path' => storage_path('sessions'),
];
