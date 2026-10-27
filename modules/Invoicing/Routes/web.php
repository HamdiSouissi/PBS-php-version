<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Invoicing\Controllers\InvoiceController;
use Modules\Invoicing\Controllers\QuoteController;
use Modules\Invoicing\Controllers\ThirdPartyController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/invoicing', static function (Router $router): void {
        // Tiers (clients / fournisseurs)
        $router->get('/third-parties', [ThirdPartyController::class, 'index'])
            ->middleware('permission:invoicing.read')->name('third_parties.index');
        $router->get('/third-parties/new', [ThirdPartyController::class, 'create'])
            ->middleware('permission:invoicing.write');
        $router->post('/third-parties', [ThirdPartyController::class, 'store'])
            ->middleware('permission:invoicing.write');

        // Factures
        $router->get('/invoices', [InvoiceController::class, 'index'])
            ->middleware('permission:invoicing.read')->name('invoices.index');
        $router->get('/invoices/new', [InvoiceController::class, 'create'])
            ->middleware('permission:invoicing.write');
        $router->post('/invoices', [InvoiceController::class, 'store'])
            ->middleware('permission:invoicing.write');
        $router->get('/invoices/{id}', [InvoiceController::class, 'show'])
            ->middleware('permission:invoicing.read')->name('invoices.show');
        $router->post('/invoices/{id}/issue', [InvoiceController::class, 'issue'])
            ->middleware('permission:invoicing.validate');
        $router->post('/invoices/{id}/payments', [InvoiceController::class, 'payment'])
            ->middleware('permission:invoicing.payments');
        $router->post('/invoices/{id}/credit-note', [InvoiceController::class, 'creditNote'])
            ->middleware('permission:invoicing.validate');
        $router->post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])
            ->middleware('permission:invoicing.validate');
        $router->post('/invoices/{id}/pdf', [InvoiceController::class, 'regeneratePdf'])
            ->middleware('permission:invoicing.read')->name('invoices.pdf');
        $router->post('/invoices/{id}/e-invoice', [InvoiceController::class, 'generateEInvoice'])
            ->middleware('permission:invoicing.einvoice');
        $router->get('/e-invoice/{outboxId}/download', [InvoiceController::class, 'downloadEInvoice'])
            ->middleware('permission:invoicing.einvoice');
        $router->post('/e-invoice/{outboxId}/exported', [InvoiceController::class, 'markExported'])
            ->middleware('permission:invoicing.einvoice');

        // Devis
        $router->get('/quotes', [QuoteController::class, 'index'])
            ->middleware('permission:invoicing.read')->name('quotes.index');
        $router->get('/quotes/new', [QuoteController::class, 'create'])
            ->middleware('permission:invoicing.write');
        $router->post('/quotes', [QuoteController::class, 'store'])
            ->middleware('permission:invoicing.write');
        $router->get('/quotes/{id}', [QuoteController::class, 'show'])
            ->middleware('permission:invoicing.read');
        $router->post('/quotes/{id}/sent', [QuoteController::class, 'markSent'])
            ->middleware('permission:invoicing.write');
        $router->post('/quotes/{id}/accept', [QuoteController::class, 'accept'])
            ->middleware('permission:invoicing.validate');
        $router->post('/quotes/{id}/reject', [QuoteController::class, 'reject'])
            ->middleware('permission:invoicing.validate');
        $router->post('/quotes/{id}/convert', [QuoteController::class, 'convert'])
            ->middleware('permission:invoicing.validate');
        $router->post('/quotes/{id}/pdf', [QuoteController::class, 'regeneratePdf'])
            ->middleware('permission:invoicing.read')->name('quotes.pdf');
    });
};
