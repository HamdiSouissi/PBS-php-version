<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Subscriptions\Controllers\SubscriptionController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/subscriptions', static function (Router $router): void {
        $router->get('', [SubscriptionController::class, 'index'])
            ->middleware('permission:subscriptions.read')->name('subscriptions.index');
        $router->post('/plans', [SubscriptionController::class, 'storePlan'])
            ->middleware('permission:subscriptions.write');
        $router->post('/plans/{id}/toggle', [SubscriptionController::class, 'togglePlan'])
            ->middleware('permission:subscriptions.write');
        $router->post('', [SubscriptionController::class, 'storeSubscription'])
            ->middleware('permission:subscriptions.write');
        $router->post('/{id}/status/{status}', [SubscriptionController::class, 'setStatus'])
            ->middleware('permission:subscriptions.write');
        $router->post('/run', [SubscriptionController::class, 'runNow'])
            ->middleware('permission:subscriptions.write')->name('subscriptions.run');
    });
};
