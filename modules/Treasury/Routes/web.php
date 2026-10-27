<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;

return static function (Router $router): void {
    $router->group(['auth'], '/treasury', static function (Router $router): void {
        $router->get('', static function (Request $request) {
            $db = db();
            $accounts = $db->fetchAll('SELECT label, bank_name, iban, current_balance FROM bank_accounts WHERE is_active = TRUE ORDER BY label');
            $transactions = array_map(
                static fn (array $r): array => $r + ['signed' => $r['direction'] === 'credit' ? $r['amount'] : -((float) $r['amount']), 'reconciled' => (int) $r['is_reconciled'] === 1 ? 'Oui' : 'Non'],
                $db->fetchAll(
                    'SELECT t.transaction_date, t.label, t.amount, t.direction, t.is_reconciled, b.label AS account
                     FROM bank_transactions t JOIN bank_accounts b ON b.id = t.bank_account_id
                     ORDER BY t.transaction_date DESC, t.id DESC LIMIT 50'
                )
            );
            return view('modules/list', [
                'title' => 'Trésorerie',
                'heading' => 'Trésorerie',
                'intro' => 'Comptes bancaires et derniers mouvements.',
                'sections' => [
                    ['title' => 'Comptes bancaires', 'rows' => $accounts, 'empty' => 'Aucun compte.', 'columns' => [
                        'label' => ['Compte'], 'bank_name' => ['Banque'], 'iban' => ['IBAN'], 'current_balance' => ['Solde', 'money'],
                    ]],
                    ['title' => '50 derniers mouvements', 'rows' => $transactions, 'empty' => 'Aucun mouvement.', 'columns' => [
                        'transaction_date' => ['Date', 'date'], 'account' => ['Compte'], 'label' => ['Libellé'], 'signed' => ['Montant', 'money'], 'reconciled' => ['Rapproché'],
                    ]],
                ],
            ]);
        })->middleware('permission:treasury.read')->name('treasury.index');
    });
};
