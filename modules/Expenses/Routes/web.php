<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Expenses\Controllers\ExpenseReportController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/expenses', static function (Router $router): void {
        $router->get('', [ExpenseReportController::class, 'index'])
            ->middleware('permission:expenses.read')->name('expenses.index');
        $router->get('/archive', [ExpenseReportController::class, 'archive'])->middleware('role:admin');
        $router->get('/archive/{id}', [ExpenseReportController::class, 'archiveShow'])->middleware('role:admin');
        $router->post('/archive/{id}/permanent-delete', [ExpenseReportController::class, 'permanentlyDelete'])->middleware('role:admin');
        $router->get('/archive/{id}/receipts/{receiptId}', [ExpenseReportController::class, 'archiveReceipt'])->middleware('role:admin');
        $router->get('/new', [ExpenseReportController::class, 'create'])
            ->middleware('permission:expenses.request');
        $router->post('', [ExpenseReportController::class, 'store'])
            ->middleware('permission:expenses.request');
        $router->get('/{id}', [ExpenseReportController::class, 'show'])
            ->middleware('permission:expenses.read')->name('expenses.show');
        $router->get('/{id}/edit', [ExpenseReportController::class, 'edit'])->middleware('role:admin');
        $router->post('/{id}/update', [ExpenseReportController::class, 'update'])->middleware('role:admin');
        $router->post('/{id}/delete', [ExpenseReportController::class, 'delete'])->middleware('role:admin');
        $router->post('/{id}/receipts', [ExpenseReportController::class, 'uploadReceipt'])->middleware('permission:expenses.read');
        $router->get('/{id}/receipts/{receiptId}', [ExpenseReportController::class, 'downloadReceipt'])->middleware('permission:expenses.read');
        $router->post('/{id}/submit', [ExpenseReportController::class, 'submit'])
            ->middleware('permission:expenses.request');
        $router->post('/{id}/approve', [ExpenseReportController::class, 'approve'])
            ->middleware('permission:expenses.approve');
        $router->post('/{id}/reject', [ExpenseReportController::class, 'reject'])
            ->middleware('permission:expenses.approve');
        $router->post('/{id}/pay', [ExpenseReportController::class, 'pay'])
            ->middleware('permission:expenses.pay');
        $router->post('/{id}/pdf', [ExpenseReportController::class, 'generatePdf'])
            ->middleware('permission:expenses.read')->name('expenses.pdf');
    });
};
