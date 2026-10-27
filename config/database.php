<?php

declare(strict_types=1);

/**
 * Configuration base de données. Cible officielle : MySQL 8 / MariaDB via PDO.
 * Le driver sqlite n'existe que pour la suite de tests en environnement clos.
 */
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', '3306'),
            'dbname' => env('DB_DATABASE', 'pbs'),
            'user' => env('DB_USERNAME', 'pbs'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'path' => env('DB_DATABASE', ':memory:'),
        ],
    ],
    'migrations_table' => 'schema_migrations',
];
