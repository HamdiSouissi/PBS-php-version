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

// La suite par défaut tourne sur SQLite en mémoire : aucun serveur MySQL requis.
// Pour tester contre MySQL, définir DB_CONNECTION=mysql et PBS_TEST_DB avant lancement.
if (getenv('DB_CONNECTION') === false) {
    putenv('DB_CONNECTION=sqlite');
    $_ENV['DB_CONNECTION'] = 'sqlite';
}
if (getenv('DB_DATABASE') === false) {
    putenv('DB_DATABASE=:memory:');
    $_ENV['DB_DATABASE'] = ':memory:';
}
