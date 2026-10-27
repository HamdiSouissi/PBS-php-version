<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;

return static function (Router $router): void {
    $router->group(['auth'], '/contracts', static function (Router $router): void {
        $router->get('', static function (Request $request) {
            $rows = db()->fetchAll(
                'SELECT c.*, tp.name AS client
                 FROM client_contracts c
                 JOIN third_parties tp ON tp.id = c.third_party_id
                 ORDER BY c.starts_on DESC LIMIT 200'
            );
            return view('modules/list', [
                'title' => 'Contrats clients',
                'heading' => 'Contrats clients',
                'intro' => 'Contrats commerciaux, renouvellement et préavis.',
                'sections' => [
                    ['title' => 'Contrats', 'rows' => $rows, 'empty' => 'Aucun contrat.', 'columns' => [
                        'contract_number' => ['N°'], 'title' => ['Intitulé'], 'client' => ['Client'], 'starts_on' => ['Début', 'date'], 'ends_on' => ['Fin', 'date'],
                        'amount_ht' => ['Montant HT', 'money'], 'billing_cycle' => ['Cycle', 'badge'], 'renewal_mode' => ['Renouvellement', 'badge'], 'status' => ['Statut', 'badge'],
                    ]],
                ],
            ]);
        })->middleware('permission:contracts.read')->name('contracts.index');
    });
};
