<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Mail\Controllers\MailAccountAdminController;
use Modules\Mail\Controllers\MailboxController;

return static function (Router $router): void {
    // Boîte mail personnelle : chaque utilisateur n'accède qu'à sa propre boîte.
    $router->group(['auth', 'csrf', 'permission:mail.read'], '/mail', static function (Router $router): void {
        $router->get('', [MailboxController::class, 'index'])->name('mail.index');
        $router->get('/message', [MailboxController::class, 'show'])->name('mail.message');
        $router->get('/compose', [MailboxController::class, 'compose'])->name('mail.compose');
        $router->post('/send', [MailboxController::class, 'send'])->name('mail.send');
        $router->post('/mark', [MailboxController::class, 'mark'])->name('mail.mark');
        $router->post('/flag', [MailboxController::class, 'flag'])->name('mail.flag');
        $router->post('/delete', [MailboxController::class, 'delete'])->name('mail.delete');
        $router->post('/move', [MailboxController::class, 'move'])->name('mail.move');
        $router->get('/attachment', [MailboxController::class, 'attachment'])->name('mail.attachment');
    });

    // Configuration des boîtes : réservée à l'administrateur (mail.configure).
    $router->group(['auth', 'csrf', 'permission:mail.configure'], '/mail/admin/accounts', static function (Router $router): void {
        $router->get('', [MailAccountAdminController::class, 'index'])->name('mail.admin.accounts');
        $router->get('/new', [MailAccountAdminController::class, 'create'])->name('mail.admin.create');
        $router->post('', [MailAccountAdminController::class, 'store'])->name('mail.admin.store');
        $router->get('/{id}/edit', [MailAccountAdminController::class, 'edit'])->name('mail.admin.edit');
        $router->post('/{id}', [MailAccountAdminController::class, 'update'])->name('mail.admin.update');
        $router->post('/{id}/delete', [MailAccountAdminController::class, 'destroy'])->name('mail.admin.delete');
    });
};
