<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Clients\Controllers\ClientController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/clients', static function (Router $router): void {
        $router->get('', [ClientController::class, 'index'])
            ->middleware('permission:clients.read')->name('clients.index');
        $router->get('/new', [ClientController::class, 'create'])
            ->middleware('permission:clients.write');
        $router->post('', [ClientController::class, 'store'])
            ->middleware('permission:clients.write');
        $router->get('/{id}', [ClientController::class, 'show'])
            ->middleware('permission:clients.read')->name('clients.show');
        $router->get('/{id}/edit', [ClientController::class, 'edit'])
            ->middleware('permission:clients.write');
        $router->post('/{id}', [ClientController::class, 'update'])
            ->middleware('permission:clients.write');
        $router->post('/{id}/pdf', [ClientController::class, 'generatePdf'])
            ->middleware('permission:clients.read')->name('clients.pdf');
    });
};
