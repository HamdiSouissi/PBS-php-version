<?php

declare(strict_types=1);

/**
 * Vérification de build : garantit que toutes les extensions PHP requises
 * par composer.json et par l'application sont chargées dans l'image.
 * Le build Docker s'arrête ici avec un message explicite plutôt que
 * d'échouer plus tard à l'exécution.
 *
 * Note : les Zend Extensions ne portent pas le même nom que les extensions
 * ordinaires dans get_loaded_extensions() (ex. "Zend OPcache" et non
 * "opcache"). D'où la table d'alias ci-dessous.
 */

// Nom logique (celui de composer.json) => nom réel vu par extension_loaded().
$aliases = [
    'opcache' => 'Zend OPcache',
];

// Extensions exigées par composer.json + simplexml (génération/lecture XML
// e-facturation) et json (réponse API, activé par défaut).
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
    'simplexml',
    'fileinfo',
    'openssl',
    'json',
];

$missing = [];
foreach ($required as $extension) {
    $actualName = $aliases[$extension] ?? $extension;
    if (!extension_loaded($actualName)) {
        $missing[] = $extension . ($actualName !== $extension ? " (nom runtime : {$actualName})" : '');
    }
}

if ($missing !== []) {
    fwrite(STDERR, 'Extensions PHP manquantes : ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo 'Extensions PHP vérifiées : ' . count($required) . ' (' . implode(', ', $required) . ')' . PHP_EOL;
exit(0);
