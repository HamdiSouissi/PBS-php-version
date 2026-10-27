<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Treasury\Controllers\TreasuryController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/treasury', static function (Router $router): void {
        $router->get('', [TreasuryController::class, 'index'])
            ->middleware('permission:treasury.read')->name('treasury.index');
        $router->post('/transactions', [TreasuryController::class, 'storeTransaction'])
            ->middleware('permission:treasury.write');
        $router->post('/transactions/{id}/reconcile', [TreasuryController::class, 'toggleReconcile'])
            ->middleware('permission:treasury.write');
    });
};
