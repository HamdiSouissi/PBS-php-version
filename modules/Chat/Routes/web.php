<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Chat\Controllers\ChatController;

return static function (Router $router): void {
    // Messagerie interne : tous les collaborateurs authentifiés (chat.use).
    $router->group(['auth', 'csrf', 'permission:chat.use'], '/chat', static function (Router $router): void {
        $router->get('', [ChatController::class, 'index'])->name('chat.index');
        $router->get('/users', [ChatController::class, 'users'])->name('chat.users');
        $router->get('/unread', [ChatController::class, 'unread'])->name('chat.unread');
        $router->get('/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
        $router->post('/conversations/direct', [ChatController::class, 'startDirect'])->name('chat.direct');
        $router->post('/conversations/group', [ChatController::class, 'createGroup'])->name('chat.group');
        $router->get('/messages', [ChatController::class, 'messages'])->name('chat.messages');
        $router->post('/messages', [ChatController::class, 'send'])->name('chat.send');
        $router->post('/read', [ChatController::class, 'read'])->name('chat.read');
        $router->post('/conversations/{id}/rename', [ChatController::class, 'rename'])->name('chat.rename');
        $router->post('/conversations/{id}/members', [ChatController::class, 'addMembers'])->name('chat.members');
        $router->post('/conversations/{id}/leave', [ChatController::class, 'leave'])->name('chat.leave');
    });
};
