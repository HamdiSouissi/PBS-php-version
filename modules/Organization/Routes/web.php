<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Organization\Controllers\AuditLogController;
use Modules\Organization\Controllers\OrganizationController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/organization', static function (Router $router): void {
        $router->get('', [OrganizationController::class, 'index'])
            ->middleware('permission:organization.read')->name('organization.index');
        $router->post('/company/{id}', [OrganizationController::class, 'updateCompany'])
            ->middleware('permission:organization.write');
        $router->post('/establishments', [OrganizationController::class, 'storeEstablishment'])
            ->middleware('permission:organization.write');
        $router->post('/departments', [OrganizationController::class, 'storeDepartment'])
            ->middleware('permission:organization.write');
    });

    $router->get('/settings/audit', [AuditLogController::class, 'index'])
        ->middleware('auth', 'permission:audit.read')->name('audit.index');
};
