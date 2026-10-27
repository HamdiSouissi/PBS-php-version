<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Invoicing\Controllers\Api\InvoiceApiController;

return static function (Router $router): void {
    $router->group(['jwt'], '/invoices', static function (Router $router): void {
        $router->get('', [InvoiceApiController::class, 'index']);
        $router->get('/{id}', [InvoiceApiController::class, 'show']);
        $router->post('', [InvoiceApiController::class, 'store']);
        $router->post('/{id}/issue', [InvoiceApiController::class, 'issue']);
        $router->post('/{id}/payments', [InvoiceApiController::class, 'payment']);
    });
};
