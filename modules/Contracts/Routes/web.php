<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Contracts\Controllers\ClientContractController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/contracts', static function (Router $router): void {
        $router->get('', [ClientContractController::class, 'index'])
            ->middleware('permission:contracts.read')->name('contracts.index');
        $router->get('/new', [ClientContractController::class, 'create'])
            ->middleware('permission:contracts.write');
        $router->post('', [ClientContractController::class, 'store'])
            ->middleware('permission:contracts.write');
        $router->get('/{id}', [ClientContractController::class, 'show'])
            ->middleware('permission:contracts.read')->name('contracts.show');
        $router->post('/{id}/pdf', [ClientContractController::class, 'regeneratePdf'])
            ->middleware('permission:contracts.write')->name('contracts.pdf');
    });
};
