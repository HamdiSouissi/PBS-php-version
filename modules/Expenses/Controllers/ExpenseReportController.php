<?php

declare(strict_types=1);

namespace Modules\Expenses\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Pdf\PdfManager;
use Modules\Expenses\Services\ExpenseReportService;

/**
 * Notes de frais : saisie, workflow de validation et export PDF.
 */
final class ExpenseReportController extends Controller
{
    public function __construct(
        private readonly ExpenseReportService $reports,
        private readonly Connection $db,
        private readonly PdfManager $pdf
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('expenses.read');
        return $this->view('Expenses::index', [
            'title' => 'Notes de frais',
            'reports' => $this->reports->all(),
            'employees' => $this->activeEmployees(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->auth()->requireAnyPermission(['expenses.request', 'expenses.approve']);
        return $this->view('Expenses::form', [
            'title' => 'Nouvelle note de frais',
            'employees' => $this->activeEmployees(),
            'categories' => ExpenseReportService::CATEGORIES,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requireAnyPermission(['expenses.request', 'expenses.approve']);
        $data = $this->validator()->validate($request->all(), [
            'employee_id' => 'required|integer',
            'title' => 'required|string|max:180',
            'notes' => 'nullable|string|max:1000',
        ]);
        $rawLines = (array) $request->input('lines', []);
        $lines = [];
        foreach ((array) ($rawLines['label'] ?? []) as $i => $label) {
            if (trim((string) $label) === '') {
                continue;
            }
            $lines[] = [
                'spent_on' => (string) ($rawLines['spent_on'][$i] ?? date('Y-m-d')),
                'category' => (string) ($rawLines['category'][$i] ?? 'other'),
                'label' => (string) $label,
                'amount' => (float) ($rawLines['amount'][$i] ?? 0),
                'vat_amount' => (float) ($rawLines['vat_amount'][$i] ?? 0),
                'distance_km' => (float) ($rawLines['distance_km'][$i] ?? 0),
                'km_rate' => (float) ($rawLines['km_rate'][$i] ?? 0),
            ];
        }
        if ($lines === []) {
            flash('error', 'Ajoutez au moins une ligne de dépense valide.');
            return new RedirectResponse('/expenses/new');
        }
        $data['lines'] = $lines;
        $id = $this->reports->create($data, $this->auth()->id());
        flash('success', 'Note de frais enregistrée en brouillon.');
        return new RedirectResponse('/expenses/' . $id);
    }

    public function show(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('expenses.read');
        return $this->view('Expenses::show', [
            'title' => 'Note de frais n°' . $id,
            'report' => $this->reports->getOrFail($id),
            'lines' => $this->reports->lines($id),
            'pdfDoc' => $this->pdf->latestFor('expense_report', $id),
        ]);
    }

    public function submit(Request $request, int $id): Response
    {
        return $this->transition($id, 'submit', 'expenses.request');
    }

    public function approve(Request $request, int $id): Response
    {
        return $this->transition($id, 'approve', 'expenses.approve');
    }

    public function reject(Request $request, int $id): Response
    {
        return $this->transition($id, 'reject', 'expenses.approve');
    }

    public function pay(Request $request, int $id): Response
    {
        return $this->transition($id, 'pay', 'expenses.pay');
    }

    private function transition(int $id, string $action, string $permission): RedirectResponse
    {
        $this->auth()->requirePermission($permission);
        $this->reports->transition($id, $action, (int) $this->auth()->id());
        flash('success', 'Statut de la note de frais mis à jour.');
        return new RedirectResponse('/expenses/' . $id);
    }

    public function generatePdf(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('expenses.read');
        $this->reports->getOrFail($id);
        $this->pdf->generate('expense_report', $id, $this->auth()->id());
        flash('success', 'PDF de la note de frais généré.');
        return new RedirectResponse('/expenses/' . $id);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function activeEmployees(): array
    {
        return $this->db->fetchAll(
            "SELECT id, employee_number, first_name, last_name FROM employees
             WHERE status IN ('active','on_leave') ORDER BY last_name, first_name"
        );
    }
}
