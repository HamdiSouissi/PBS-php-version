<?php

declare(strict_types=1);

/**
 * Front controller unique de Prime Business Suite.
 */

use App\Core\Exception\HttpException;
use App\Core\Http\Kernel;
use App\Core\Http\Request;
use App\Core\Session\SessionManager;

/** @var array{0:\App\Core\Application,1:Kernel,2:\App\Core\Container} $bootstrap */
$bootstrap = require dirname(__DIR__) . '/app/bootstrap.php';
[$app, $kernel, $container] = $bootstrap;

// La session n'est requise que pour le trafic web (pas l'API JWT).
$request = Request::capture();
if (!$request->isApi()) {
    $container->get(SessionManager::class)->start();
}

try {
    $response = $kernel->handle($request);
} catch (Throwable $exception) {
    $handler = new \App\Core\Exception\ExceptionHandler(
        $container->get(\App\Core\Logger\Logger::class),
        $container->get(\App\Core\View\ViewRenderer::class),
        $app->isDebug()
    );
    $response = $handler->handle($exception, $request);
}

$response->send();
