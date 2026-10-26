<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\HR\Controllers\LeaveController;
use Modules\HR\Controllers\PortalController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/hr/leaves', static function (Router $router): void {
        $router->get('', [LeaveController::class, 'index'])
            ->middleware('permission:hr.leaves.read')->name('leaves.index');
        $router->post('/{id}/approve', [LeaveController::class, 'approve'])
            ->middleware('permission:hr.leaves.approve');
        $router->post('/{id}/reject', [LeaveController::class, 'reject'])
            ->middleware('permission:hr.leaves.approve');
    });

    $router->group(['auth', 'csrf', 'permission:portal.self'], '/portal', static function (Router $router): void {
        $router->get('', [PortalController::class, 'index'])->name('portal.index');
        $router->post('/leave', [PortalController::class, 'requestLeave']);
        $router->post('/leave/{id}/cancel', [PortalController::class, 'cancelLeave']);
    });
};
