<?php

declare(strict_types=1);

/**
 * Passerelle console de DÉVELOPPEMENT UNIQUEMENT.
 *
 * Certains environnements clos ne fournissent pas de SAPI CLI (ex : runtime
 * FrankenPHP personnalisé). Ce point d'entrée permet d'exécuter les mêmes
 * commandes que bin/console via HTTP, en localhost et uniquement quand
 * APP_ENV=development. Il est inactif (404) en production.
 *
 * Usage (sandbox) :
 *   curl -s -X POST http://127.0.0.1:PORT/console-gateway.php \
 *        -H 'X-Gateway-Token: ...' -d '{"cmd":"migrate:run","args":[]}'
 */

use App\Core\Application;
use App\Core\Console\ConsoleKernel;
use App\Core\Console\Input;
use App\Core\Console\Output;

require dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::bootstrap();
require dirname(__DIR__) . '/app/Helpers/functions.php';

$app = new Application(dirname(__DIR__));
$app->bootstrap();

header('Content-Type: application/json; charset=utf-8');

// Désactivée par défaut ; ne jamais l'exposer via un proxy inverse.
$secret = (string) env('CONSOLE_GATEWAY_TOKEN', '');
$provided = (string) ($_SERVER['HTTP_X_GATEWAY_TOKEN'] ?? '');
if (config('app.env') !== 'development'
    || env('CONSOLE_GATEWAY_ENABLED', 'false') !== true
    || strlen($secret) < 32
    || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    || isset($_SERVER['HTTP_FORWARDED'])
    || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !hash_equals($secret, $provided)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
$cmd = (string) ($payload['cmd'] ?? 'list');
$args = array_map('strval', $payload['args'] ?? []);

// Capture de la sortie console.
ob_start();
$kernel = new ConsoleKernel(Application::container());
(require dirname(__DIR__) . '/app/commands.php')($kernel);
$exit = $kernel->run(new Input(array_merge(['bin/console', $cmd], $args)), new Output());
$outputText = (string) ob_get_clean();

echo json_encode([
    'success' => $exit === 0,
    'exit_code' => $exit,
    'command' => $cmd,
    'output' => strip_tags($outputText),
], JSON_UNESCAPED_UNICODE);
