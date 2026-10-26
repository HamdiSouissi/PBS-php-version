<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Employees\Controllers\Api\EmployeeApiController;

return static function (Router $router): void {
    $router->group(['jwt'], '/employees', static function (Router $router): void {
        $router->get('', [EmployeeApiController::class, 'index']);
        $router->get('/{id}', [EmployeeApiController::class, 'show']);
    });
};
