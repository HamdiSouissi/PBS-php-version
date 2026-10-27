<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Compliance\Controllers\ComplianceController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/compliance', static function (Router $router): void {
        $router->get('', [ComplianceController::class, 'index'])
            ->middleware('permission:compliance.read')->name('compliance.index');
        $router->post('/obligations', [ComplianceController::class, 'storeObligation'])
            ->middleware('permission:compliance.write');
        $router->post('/obligations/{id}/complete', [ComplianceController::class, 'complete'])
            ->middleware('permission:compliance.write');
    });
};
