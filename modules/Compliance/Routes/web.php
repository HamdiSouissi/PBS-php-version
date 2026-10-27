<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;
use Modules\Compliance\Services\BackupService;

return static function (Router $router): void {
    $router->group(['auth'], '/compliance', static function (Router $router): void {
        $router->get('', static function (Request $request) {
            $obligations = db()->fetchAll(
                'SELECT title, authority, frequency, due_on, status FROM compliance_obligations ORDER BY (due_on IS NULL), due_on, title'
            );
            try {
                $backups = array_map(
                    static fn (array $b): array => [
                        'file' => $b['file'],
                        'size' => number_format(((float) $b['size']) / 1024, 1, ',', ' ') . ' Ko',
                        'created_at' => $b['created_at'],
                    ],
                    container()->get(BackupService::class)->listBackups()
                );
            } catch (\Throwable) {
                $backups = [];
            }
            return view('modules/list', [
                'title' => 'Conformité',
                'heading' => 'Conformité',
                'intro' => 'Obligations légales, échéances et sauvegardes.',
                'sections' => [
                    ['title' => 'Obligations', 'rows' => $obligations, 'columns' => [
                        'title' => ['Obligation'], 'authority' => ['Organisme', 'badge'], 'frequency' => ['Fréquence', 'badge'], 'due_on' => ['Échéance', 'date'], 'status' => ['Statut', 'badge'],
                    ]],
                    ['title' => 'Sauvegardes (php bin/console backup:run)', 'rows' => $backups, 'empty' => 'Aucune sauvegarde.', 'columns' => [
                        'file' => ['Fichier'], 'size' => ['Taille'], 'created_at' => ['Créée le', 'datetime'],
                    ]],
                ],
            ]);
        })->middleware('permission:compliance.read')->name('compliance.index');
    });
};
