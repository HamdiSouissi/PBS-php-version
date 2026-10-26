<?php

declare(strict_types=1);

/**
 * Bootstrap des tests (PHPUnit ou moteur maison).
 */

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::bootstrap();
require_once __DIR__ . '/../app/Helpers/functions.php';

// En environnement de test, surcharge éventuelle via variables d'environnement.
if (getenv('APP_ENV') === false) {
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
}
