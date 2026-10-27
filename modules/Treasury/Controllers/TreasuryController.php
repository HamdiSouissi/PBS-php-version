<?php

declare(strict_types=1);

namespace Modules\Treasury\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Trésorerie : comptes bancaires, saisie de mouvements et rapprochement.
 */
final class TreasuryController extends Controller
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('treasury.read');
        return $this->view('Treasury::index', [
            'title' => 'Trésorerie',
            'accounts' => $this->db->fetchAll(
                'SELECT * FROM bank_accounts WHERE is_active = TRUE ORDER BY label'
            ),
            'transactions' => $this->db->fetchAll(
                'SELECT t.*, b.label AS account FROM bank_transactions t
                 JOIN bank_accounts b ON b.id = t.bank_account_id
                 ORDER BY t.transaction_date DESC, t.id DESC LIMIT 100'
            ),
        ]);
    }

    public function storeTransaction(Request $request): Response
    {
        $this->auth()->requirePermission('treasury.write');
        $data = $this->validator()->validate($request->all(), [
            'bank_account_id' => 'required|integer',
            'transaction_date' => 'required|date',
            'label' => 'required|string|max:180',
            'amount' => 'required|decimal:2',
            'direction' => 'required|in:credit,debit',
            'reference' => 'nullable|string|max:120',
        ]);
        $amount = round((float) $data['amount'], 2);
        $this->db->transaction(function (Connection $db) use ($data, $amount): void {
            $db->execute(
                'INSERT INTO bank_transactions (bank_account_id, transaction_date, value_date, label, amount, direction, reference, source_type)
                 VALUES (:account, :date, :date, :label, :amount, :direction, :ref, \'manual\')',
                [
                    'account' => (int) $data['bank_account_id'],
                    'date' => $data['transaction_date'],
                    'label' => $data['label'],
                    'amount' => $amount,
                    'direction' => $data['direction'],
                    'ref' => $data['reference'] ?? '',
                ]
            );
            $signed = $data['direction'] === 'credit' ? $amount : -$amount;
            $db->execute(
                'UPDATE bank_accounts SET current_balance = current_balance + :delta WHERE id = :id',
                ['delta' => $signed, 'id' => (int) $data['bank_account_id']]
            );
        });
        audit('treasury.transaction.create', 'BankTransaction', (int) $this->db->lastInsertId(), ['amount' => $amount]);
        flash('success', 'Mouvement bancaire enregistré.');
        return new RedirectResponse('/treasury');
    }

    public function toggleReconcile(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('treasury.write');
        $transaction = $this->db->fetchOne('SELECT * FROM bank_transactions WHERE id = :id', ['id' => $id]);
        if ($transaction === null) {
            flash('error', 'Mouvement introuvable.');
            return new RedirectResponse('/treasury');
        }
        $reconciled = (int) $transaction['is_reconciled'] === 1 ? 0 : 1;
        $this->db->execute(
            'UPDATE bank_transactions SET is_reconciled = :r, reconciled_at = :at WHERE id = :id',
            ['r' => $reconciled, 'at' => $reconciled === 1 ? date('Y-m-d H:i:s') : null, 'id' => $id]
        );
        audit('treasury.transaction.reconcile', 'BankTransaction', $id, ['reconciled' => $reconciled]);
        flash('success', $reconciled === 1 ? 'Mouvement rapproché.' : 'Rapprochement annulé.');
        return new RedirectResponse('/treasury');
    }
}
