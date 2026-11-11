<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Employees\Controllers\EmployeeController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf', 'permission:employees.read'], '/employees', static function (Router $router): void {
        $router->get('', [EmployeeController::class, 'index'])->name('employees.index');
        $router->get('/create', [EmployeeController::class, 'create'])->middleware('permission:employees.write');
        $router->post('', [EmployeeController::class, 'store'])->middleware('permission:employees.write');
        $router->get('/{id}', [EmployeeController::class, 'show'])->name('employees.show');
        $router->get('/{id}/contact', [EmployeeController::class, 'editContact'])->middleware('permission:employees.write');
        $router->post('/{id}/contact', [EmployeeController::class, 'updateContact'])->middleware('permission:employees.write');
        $router->get('/{id}/edit', [EmployeeController::class, 'edit'])->middleware('permission:employees.write');
        $router->post('/{id}/account', [EmployeeController::class, 'createAccount'])->middleware('role:admin', 'throttle:employee-account,10,900');
        $router->post('/{id}/account/existing-reset', [EmployeeController::class, 'resetExistingAccount'])->middleware('role:admin', 'throttle:employee-reset,10,900');
        $router->post('/{id}/account/link', [EmployeeController::class, 'linkAccount'])->middleware('role:admin');
        $router->post('/{id}/password-reset', [EmployeeController::class, 'sendPasswordReset'])->middleware('role:admin', 'throttle:employee-reset,10,900');
        $router->post('/{id}/update', [EmployeeController::class, 'update'])->middleware('permission:employees.write');
        $router->post('/{id}/terminate', [EmployeeController::class, 'terminate'])->middleware('permission:employees.write');
        $router->post('/{id}/portal', [EmployeeController::class, 'enablePortal'])->middleware('permission:employees.write');
        $router->post('/{id}/contracts', [EmployeeController::class, 'storeContract'])->middleware('permission:employees.write');
        $router->post('/{id}/contracts/{contractId}/activate', [EmployeeController::class, 'activateContract'])
            ->middleware('permission:employees.write');
        $router->post('/{id}/contracts/{contractId}/pdf', [EmployeeController::class, 'regenerateContractPdf'])
            ->middleware('permission:employees.write')->name('employees.contract.pdf');
        $router->post('/{id}/sheet-pdf', [EmployeeController::class, 'employeeSheetPdf'])
            ->middleware('permission:employees.read')->name('employees.sheet.pdf');
    });
};
