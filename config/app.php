<?php

declare(strict_types=1);

/**
 * Configuration générale de l'application.
 */
return [
    'name' => env('APP_NAME', 'Prime Business Suite'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => rtrim(env('APP_URL', 'http://localhost:8080'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Paris'),
    'locale' => env('APP_LOCALE', 'fr'),
    'key' => env('APP_KEY', ''),
    'version' => '1.0.0',
    'locale_currency' => 'EUR',
    'locale_country' => 'FR',
    'pagination' => [
        'per_page' => 25,
        'max_per_page' => 200,
    ],
];
