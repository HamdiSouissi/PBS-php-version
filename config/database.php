<?php

declare(strict_types=1);

/**
 * Configuration base de données. Cible officielle : PostgreSQL via PDO.
 * Le driver sqlite n'existe que pour la suite de tests en environnement clos.
 */
return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', '5432'),
            'dbname' => env('DB_DATABASE', 'pbs'),
            'user' => env('DB_USERNAME', 'pbs'),
            'password' => env('DB_PASSWORD', ''),
            'schema' => env('DB_SCHEMA', 'public'),
            'charset' => 'UTF8',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'path' => env('DB_DATABASE', ':memory:'),
        ],
    ],
    'migrations_table' => 'schema_migrations',
];
