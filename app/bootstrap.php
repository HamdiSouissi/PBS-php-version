<?php

declare(strict_types=1);

/**
 * Bootstrap unique de l'application (web et console).
 */

use App\Core\Application;
use App\Core\Autoloader;
use App\Core\Http\Kernel;
use App\Core\Exception\ExceptionHandler;
use App\Core\Http\Middleware\AuthMiddleware;
use App\Core\Http\Middleware\CsrfMiddleware;
use App\Core\Http\Middleware\ForcePasswordChangeMiddleware;
use App\Core\Http\Middleware\GuestMiddleware;
use App\Core\Http\Middleware\JwtAuthMiddleware;
use App\Core\Http\Middleware\RateLimitMiddleware;
use App\Core\Http\Middleware\RequirePermission;
use App\Core\Http\Middleware\RequireRole;
use App\Core\Http\Middleware\SecurityHeadersMiddleware;
use App\Core\Logger\Logger;
use App\Core\View\ViewRenderer;

require_once __DIR__ . '/Core/Autoloader.php';

Autoloader::bootstrap();
require_once __DIR__ . '/Helpers/functions.php';

$app = new Application(dirname(__DIR__));
$app->bootstrap();

$container = Application::container();

// Enregistrement des listeners d'événements et des commandes des modules.
(require __DIR__ . '/events.php')($container);

$exceptionHandler = new ExceptionHandler(
    $container->get(Logger::class),
    $container->get(ViewRenderer::class),
    $app->isDebug()
);

$kernel = new Kernel(
    $container,
    $container->get(\App\Core\Router\Router::class),
    $exceptionHandler
);

$kernel->setGlobalMiddleware([
    SecurityHeadersMiddleware::class,
    ForcePasswordChangeMiddleware::class,
]);

$kernel->setMiddlewareAliases([
    'auth' => AuthMiddleware::class,
    'guest' => GuestMiddleware::class,
    'csrf' => CsrfMiddleware::class,
    'jwt' => JwtAuthMiddleware::class,
    'permission' => RequirePermission::class,
    'role' => RequireRole::class,
    'throttle' => RateLimitMiddleware::class,
]);

// Chargement des routes web et API de tous les modules.
$router = $container->get(\App\Core\Router\Router::class);
(require dirname(__DIR__) . '/routes/web.php')($router);
(require dirname(__DIR__) . '/routes/api.php')($router);

return [$app, $kernel, $container];
