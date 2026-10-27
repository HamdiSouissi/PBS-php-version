<?php

declare(strict_types=1);

use App\Core\Router\Router;
use Modules\Dividends\Controllers\DividendController;

return static function (Router $router): void {
    $router->group(['auth', 'csrf'], '/dividends', static function (Router $router): void {
        $router->get('', [DividendController::class, 'index'])
            ->middleware('permission:dividends.read')->name('dividends.index');
        $router->post('', [DividendController::class, 'store'])
            ->middleware('permission:dividends.write');
        $router->post('/{id}/decide', [DividendController::class, 'decide'])
            ->middleware('permission:dividends.write');
        $router->post('/{id}/pay', [DividendController::class, 'pay'])
            ->middleware('permission:dividends.write');
    });
};
