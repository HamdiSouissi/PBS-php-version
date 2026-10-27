<?php

declare(strict_types=1);

namespace Modules\Accounting\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Accounting\Services\JournalEntryService;

/**
 * Comptabilité : exercices, balance, saisie et annulation d'écritures.
 */
final class AccountingController extends Controller
{
    public function __construct(
        private readonly Connection $db,
        private readonly JournalEntryService $entries
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('accounting.read');
        return $this->view('Accounting::index', [
            'title' => 'Comptabilité',
            'years' => $this->db->fetchAll('SELECT * FROM fiscal_years ORDER BY starts_on DESC'),
            'entries' => $this->db->fetchAll(
                'SELECT e.*, j.code AS journal, j.label AS journal_label
                 FROM journal_entries e JOIN journals j ON j.id = e.journal_id
                 ORDER BY e.entry_date DESC, e.id DESC LIMIT 50'
            ),
            'balances' => $this->db->fetchAll(
                'SELECT code, label, total_debit, total_credit, solde_debit, solde_credit
                 FROM v_account_balances
                 WHERE total_debit <> 0 OR total_credit <> 0 ORDER BY code LIMIT 100'
            ),
        ]);
    }

    public function createForm(Request $request): Response
    {
        $this->auth()->requirePermission('accounting.write');
        return $this->view('Accounting::form', [
            'title' => 'Nouvelle écriture',
            'journals' => $this->db->fetchAll('SELECT id, code, label FROM journals ORDER BY code'),
            'fiscalYears' => $this->db->fetchAll("SELECT id, label, starts_on, ends_on FROM fiscal_years WHERE status = 'open' ORDER BY starts_on DESC"),
            'accounts' => $this->db->fetchAll(
                'SELECT code, label FROM chart_accounts WHERE allow_entries = 1 ORDER BY code'
            ),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('accounting.write');
        $data = $this->validator()->validate($request->all(), [
            'journal_id' => 'required|integer',
            'fiscal_year_id' => 'required|integer',
            'entry_date' => 'required|date',
            'label' => 'required|string|max:255',
            'reference' => 'nullable|string|max:60',
        ]);
        $data['lines'] = $request->input('lines', []);
        $entryId = $this->entries->createEntry($data, (int) $this->auth()->id());
        audit('accounting.entry.create', 'JournalEntry', $entryId);
        flash('success', 'Écriture comptabilisée.');
        return new RedirectResponse('/accounting/entries/' . $entryId);
    }

    public function show(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('accounting.read');
        return $this->view('Accounting::show', [
            'title' => 'Écriture comptable',
            'entry' => $this->entries->getOrFail($id),
            'lines' => $this->entries->lines($id),
        ]);
    }

    public function cancel(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('accounting.close');
        $this->entries->cancel($id);
        flash('success', 'Écriture annulée, elle ne figure plus dans la balance.');
        return new RedirectResponse('/accounting/entries/' . $id);
    }
}
