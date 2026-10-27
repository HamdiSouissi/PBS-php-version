<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Payroll\Controllers\PayrollController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/payroll', static function (Router $router): void {
        $router->get('', [PayrollController::class, 'index'])
            ->middleware('permission:payroll.read')->name('payroll.index');
        $router->get('/generate', [PayrollController::class, 'generateForm'])
            ->middleware('permission:payroll.run');
        $router->post('/generate', [PayrollController::class, 'generate'])
            ->middleware('permission:payroll.run');
        $router->post('/periods', [PayrollController::class, 'createPeriod'])
            ->middleware('permission:payroll.run');
        $router->post('/periods/{id}/lock', [PayrollController::class, 'lockPeriod'])
            ->middleware('permission:payroll.lock');
        $router->get('/{id}', [PayrollController::class, 'show'])
            ->middleware('permission:payroll.read');
        $router->post('/{id}/validate', [PayrollController::class, 'validatePayslip'])
            ->middleware('permission:payroll.validate');
        $router->post('/{id}/pay', [PayrollController::class, 'markPaid'])
            ->middleware('permission:payroll.validate');
        $router->post('/{id}/pdf', [PayrollController::class, 'regeneratePdf'])
            ->middleware('permission:payroll.read');
    });
};
