<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Accounting\Controllers\AccountingController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/accounting', static function (Router $router): void {
        $router->get('', [AccountingController::class, 'index'])
            ->middleware('permission:accounting.read')->name('accounting.index');
        $router->get('/entries/new', [AccountingController::class, 'createForm'])
            ->middleware('permission:accounting.write');
        $router->post('/entries', [AccountingController::class, 'store'])
            ->middleware('permission:accounting.write');
        $router->get('/entries/{id}', [AccountingController::class, 'show'])
            ->middleware('permission:accounting.read');
        $router->post('/entries/{id}/cancel', [AccountingController::class, 'cancel'])
            ->middleware('permission:accounting.close');
    });
};
