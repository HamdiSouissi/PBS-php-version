<?php

declare(strict_types=1);

/**
 * Vérification de build : garantit que toutes les extensions PHP requises
 * par composer.json sont chargées dans l'image. Le build Docker s'arrête
 * ici avec un message explicite plutôt que d'échouer à l'exécution.
 */

$required = [
    'pdo_pgsql',
    'pgsql',
    'mbstring',
    'intl',
    'bcmath',
    'gd',
    'zip',
    'exif',
    'sodium',
    'opcache',
    'dom',
    'xml',
    'fileinfo',
    'openssl',
    'json',
    'mbstring',
];

$required = array_values(array_unique($required));
$missing = array_diff($required, get_loaded_extensions());

if ($missing !== []) {
    fwrite(STDERR, 'Extensions PHP manquantes : ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo 'Extensions PHP vérifiées : ' . count($required) . ' (' . implode(', ', $required) . ')' . PHP_EOL;
exit(0);
