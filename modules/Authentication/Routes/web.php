<?php

declare(strict_types=1);

/**
 * Routes web du module Authentication.
 *
 * @var \App\Core\Router\Router $router
 */

use Modules\Authentication\Controllers\AuthController;
use Modules\Authentication\Controllers\UserController;

return static function (\App\Core\Router\Router $router): void {
$router->group(['guest', 'csrf'], '', static function (\App\Core\Router\Router $router): void {
    $router->get('/login', [AuthController::class, 'showLogin'])->name('login');
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/two-factor', [AuthController::class, 'showTwoFactor']);
    $router->post('/two-factor', [AuthController::class, 'verifyTwoFactor']);
    $router->get('/forgot-password', [AuthController::class, 'showForgot']);
    $router->post('/forgot-password', [AuthController::class, 'sendResetLink']);
    $router->get('/reset-password', [AuthController::class, 'showReset']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
});

$router->post('/logout', [AuthController::class, 'logout'])->middleware('auth', 'csrf')->name('logout');

$router->group(['auth', 'csrf'], '', static function (\App\Core\Router\Router $router): void {
    $router->get('/profile', [UserController::class, 'profile'])->name('profile');
    $router->post('/profile', [UserController::class, 'updateProfile']);
    $router->post('/profile/password', [UserController::class, 'updatePassword']);
    $router->post('/profile/totp/begin', [UserController::class, 'beginTotp']);
    $router->post('/profile/totp/confirm', [UserController::class, 'confirmTotp']);

    $router->get('/settings/users', [UserController::class, 'index'])
        ->middleware('permission:users.read')->name('users.index');
    $router->post('/settings/users', [UserController::class, 'store'])
        ->middleware('permission:users.write');
    $router->post('/settings/users/{id}/update', [UserController::class, 'update'])
        ->middleware('permission:users.write');
    $router->post('/settings/users/{id}/totp/disable', [UserController::class, 'disableTotp'])
        ->middleware('permission:users.write');
});
};
