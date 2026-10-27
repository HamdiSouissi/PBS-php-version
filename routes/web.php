<?php

declare(strict_types=1);

/**
 * Routes web de tous les modules + pages transversales.
 *
 * @var \App\Core\Router\Router $router
 */

use App\Controllers\DashboardController;
use App\Controllers\GeneratedDocumentController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Core\Router\Router;
use Modules\Documents\Controllers\DocumentController;

return static function (Router $router): void {
// Routes publiques (les modules déclarent leurs propres routes invitées).
foreach (glob(base_path('modules/*/Routes/web.php')) ?: [] as $file) {
    (require $file)($router);
}

// Sonde de vivacité (healthcheck Docker / reverse proxy), sans authentification.
$router->get('/health', [HealthController::class, 'show'])->name('health');

// Pages transversales.
$router->get('/', [HomeController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

// Espace documents (centre documentaire unifié).
$router->group(['auth', 'permission:documents.read'], '/documents', static function (\App\Core\Router\Router $router): void {
    $router->get('', [DocumentController::class, 'index'])->name('documents.index');
    $router->get('/download/{id}', [DocumentController::class, 'download'])->name('documents.download');
    $router->post('/upload', [DocumentController::class, 'upload'])
        ->middleware('csrf', 'permission:documents.write')
        ->name('documents.upload');
    $router->post('/{id}/delete', [DocumentController::class, 'delete'])
        ->middleware('csrf', 'permission:documents.write')
        ->name('documents.delete');
});

// Documents PDF générés (aperçu inline / export).
$router->group(['auth'], '/pdf', static function (Router $router): void {
    $router->get('/{id}/preview', [GeneratedDocumentController::class, 'preview'])->name('pdf.preview');
    $router->get('/{id}/download', [GeneratedDocumentController::class, 'download'])->name('pdf.download');
});
};
