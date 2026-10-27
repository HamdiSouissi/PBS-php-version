<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;

return static function (Router $router): void {
    $router->group(['auth'], '/expenses', static function (Router $router): void {
        $router->get('', static function (Request $request) {
            $rows = array_map(
                static fn (array $r): array => $r + ['employee' => mb_strtoupper((string) $r['last_name']) . ' ' . $r['first_name']],
                db()->fetchAll(
                    'SELECT r.*, e.first_name, e.last_name
                     FROM expense_reports r
                     JOIN employees e ON e.id = r.employee_id
                     ORDER BY r.created_at DESC LIMIT 200'
                )
            );
            return view('modules/list', [
                'title' => 'Notes de frais',
                'heading' => 'Notes de frais',
                'intro' => 'Suivi des notes de frais des salariés.',
                'notice' => 'La saisie, la validation et l\'OCR des justificatifs ne sont pas encore implémentés.',
                'sections' => [
                    ['title' => 'Notes de frais', 'rows' => $rows, 'empty' => 'Aucune note de frais.', 'columns' => [
                        'title' => ['Titre'], 'employee' => ['Salarié'], 'status' => ['Statut', 'badge'], 'total_amount' => ['Total', 'money'],
                        'submitted_at' => ['Soumise', 'datetime'], 'paid_on' => ['Payée le', 'date'],
                    ]],
                ],
            ]);
        })->middleware('permission:expenses.read')->name('expenses.index');
    });
};
