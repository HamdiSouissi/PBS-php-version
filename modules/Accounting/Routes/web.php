<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;

return static function (Router $router): void {
    $router->group(['auth'], '/accounting', static function (Router $router): void {
        $router->get('', static function (Request $request) {
            $db = db();
            $years = $db->fetchAll('SELECT label, starts_on, ends_on, status FROM fiscal_years ORDER BY starts_on DESC');
            $entries = $db->fetchAll(
                'SELECT e.entry_date, j.code AS journal, e.reference, e.label, e.status
                 FROM journal_entries e JOIN journals j ON j.id = e.journal_id
                 ORDER BY e.entry_date DESC, e.id DESC LIMIT 50'
            );
            $balances = $db->fetchAll(
                'SELECT code, label, total_debit, total_credit FROM v_account_balances
                 WHERE total_debit <> 0 OR total_credit <> 0 ORDER BY code LIMIT 100'
            );
            return view('modules/list', [
                'title' => 'Comptabilité',
                'heading' => 'Comptabilité',
                'intro' => 'Exercices, écritures et balance des comptes (écritures comptabilisées).',
                'notice' => 'La saisie d\'écritures, la clôture et les déclarations nécessitent la relecture d\'un expert-comptable.',
                'sections' => [
                    ['title' => 'Exercices', 'rows' => $years, 'columns' => [
                        'label' => ['Exercice'], 'starts_on' => ['Début', 'date'], 'ends_on' => ['Fin', 'date'], 'status' => ['Statut', 'badge'],
                    ]],
                    ['title' => 'Dernières écritures', 'rows' => $entries, 'empty' => 'Aucune écriture.', 'columns' => [
                        'entry_date' => ['Date', 'date'], 'journal' => ['Journal', 'badge'], 'reference' => ['Réf.'], 'label' => ['Libellé'], 'status' => ['Statut', 'badge'],
                    ]],
                    ['title' => 'Balance', 'rows' => $balances, 'empty' => 'Aucun mouvement comptabilisé.', 'columns' => [
                        'code' => ['Compte'], 'label' => ['Libellé'], 'total_debit' => ['Débit', 'money'], 'total_credit' => ['Crédit', 'money'],
                    ]],
                ],
            ]);
        })->middleware('permission:accounting.read')->name('accounting.index');
    });
};
