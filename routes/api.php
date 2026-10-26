<?php

declare(strict_types=1);

/**
 * Routes API REST v1 (JWT Bearer, réponses JSON).
 */

use App\Core\Router\Router;

return static function (Router $router): void {
$router->group(['throttle:api,120,60'], '/api/v1', static function (Router $router): void {
    foreach (glob(base_path('modules/*/Routes/api.php')) ?: [] as $file) {
        (require $file)($router);
    }
});
};
