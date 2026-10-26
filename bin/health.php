<?php

declare(strict_types=1);

/**
 * Sonde de santé CLI pour le HEALTHCHECK du conteneur PHP-FPM.
 * Code de sortie 0 si la base répond, 1 sinon.
 *
 * Usage : php bin/health.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Réservé au mode CLI.\n");
}

/** @var array{0:\App\Core\Application,1:mixed,2:\App\Core\Container} $bootstrap */
$bootstrap = require dirname(__DIR__) . '/app/bootstrap.php';
$container = $bootstrap[2];

try {
    $container->get(\App\Core\Database\Connection::class)->fetchScalar('SELECT 1');
    fwrite(STDOUT, "ok: database reachable\n");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'error: ' . $e->getMessage() . "\n");
    exit(1);
}
