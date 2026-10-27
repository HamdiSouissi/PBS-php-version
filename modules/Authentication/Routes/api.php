<?php

declare(strict_types=1);

/**
 * Routes API du module Authentication (/api/v1 préfixé globalement).
 *
 * @var \App\Core\Router\Router $router
 */

use App\Core\Router\Router;
use Modules\Authentication\Controllers\Api\AuthApiController;

return static function (Router $router): void {
$router->post('/auth/login', [AuthApiController::class, 'login']);
$router->post('/auth/2fa', [AuthApiController::class, 'verifyTwoFactor']);
$router->post('/auth/refresh', [AuthApiController::class, 'refresh']);

$router->group(['jwt'], '', static function (Router $router): void {
    $router->get('/auth/me', [AuthApiController::class, 'me']);
});
};
