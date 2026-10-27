<?php

declare(strict_types=1);

/**
 * Front controller unique de Prime Business Suite.
 */

use App\Core\Exception\ExceptionHandler;
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

$makeHandler = static fn (): ExceptionHandler => new ExceptionHandler(
    $container->get(\App\Core\Logger\Logger::class),
    $container->get(\App\Core\View\ViewRenderer::class),
    $app->isDebug()
);

/*
 * CORRECTIF : View::send() rend la vue au moment de l'envoi. Une erreur dans
 * une vue (ou le layout) échappait donc au gestionnaire d'exceptions et
 * produisait une erreur fatale PHP brute. Le rendu est maintenant protégé.
 */
try {
    $response = $kernel->handle($request);
    $response->send();
} catch (Throwable $exception) {
    try {
        $makeHandler()->handle($exception, $request)->send();
    } catch (Throwable $fatal) {
        http_response_code(500);
        echo 'Erreur interne.' . ($app->isDebug() ? ' ' . htmlspecialchars($fatal->getMessage(), ENT_QUOTES) : '');
    }
}
