<?php

declare(strict_types=1);

/**
 * Configuration IA. L'application reste 100 % fonctionnelle si tout est désactivé.
 * Les clés API sont chiffrées au repos (service SecretsManager).
 */
return [
    'ollama' => [
        'enabled' => filter_var(env('OLLAMA_ENABLED', 'false'), FILTER_VALIDATE_BOOL),
        'url' => rtrim(env('OLLAMA_URL', 'http://127.0.0.1:11434'), '/'),
        'model' => env('OLLAMA_MODEL', 'llama3.1'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', '60'),
        'temperature' => (float) env('OLLAMA_TEMPERATURE', '0.7'),
    ],
    'codecraft' => [
        'enabled' => filter_var(env('CODECRAFT_ENABLED', 'false'), FILTER_VALIDATE_BOOL),
        'url' => rtrim(env('CODECRAFT_URL', 'https://codecraftapi.com/v1'), '/'),
        // La clé n'est PAS lue depuis .env en production : elle est saisie
        // dans l'interface et chiffrée en base. .env reste un secours de setup.
        'api_key_env' => 'CODECRAFT_API_KEY',
        'model' => env('CODECRAFT_MODEL', ''),
        'timeout' => 30,
    ],
];
