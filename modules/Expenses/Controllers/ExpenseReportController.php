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
use Modules\Expenses\Services\ExpenseReceiptService;

/**
 * Notes de frais : saisie, workflow de validation et export PDF.
 */
final class ExpenseReportController extends Controller
{
    public function __construct(
        private readonly ExpenseReportService $reports,
        private readonly Connection $db,
        private readonly PdfManager $pdf,
        private readonly ExpenseReceiptService $receipts
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('expenses.read');
        return $this->view('Expenses::index', [
            'title' => 'Notes de frais',
            'reports' => $this->reports->all(),
            'employees' => $this->activeEmployees(),
            'isAdmin' => $this->auth()->hasRole('admin'),
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
        $report = $this->reports->getOrFail($id);
        $canAccessReceipts = $this->canAccessReceipts($report);
        return $this->view('Expenses::show', [
            'title' => 'Note de frais n°' . $id,
            'report' => $report,
            'receipts' => $canAccessReceipts ? $this->receipts->forReport($id) : [],
            'canAccessReceipts' => $canAccessReceipts,
            'canUploadReceipt' => $canAccessReceipts && $this->auth()->hasAnyPermission(['expenses.request', 'expenses.approve']),
            'lines' => $this->reports->lines($id),
            'pdfDoc' => $this->pdf->latestFor('expense_report', $id),
            'isAdmin' => $this->auth()->hasRole('admin'),
        ]);
    }

    private function requireAdmin(): void
    {
        if (!$this->auth()->hasRole('admin')) {
            throw new \App\Core\Exception\AuthorizationException('Réservé aux administrateurs.');
        }
    }

    public function edit(Request $request, int $id): Response
    {
        $this->requireAdmin();
        $report = $this->reports->getOrFail($id);
        if ($report['status'] !== 'draft') {
            throw new \App\Core\Exception\AppException('Seuls les brouillons peuvent être modifiés.');
        }
        return $this->view('Expenses::edit', [
            'title' => 'Modifier la note de frais',
            'report' => $report,
            'lines' => $this->reports->lines($id),
            'employees' => $this->db->fetchAll('SELECT id, employee_number, first_name, last_name FROM employees ORDER BY last_name, first_name'),
            'categories' => ExpenseReportService::CATEGORIES,
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->requireAdmin();
        $data = $this->validator()->validate($request->all(), [
            'employee_id' => 'required|integer', 'title' => 'required|string|max:180', 'notes' => 'nullable|string|max:1000',
        ]);
        $data['title'] = trim($data['title']);
        if ($data['title'] === '') {
            throw new \App\Core\Exception\AppException('L’intitulé est obligatoire.');
        }
        $raw = $request->input('lines', []);
        if (!is_array($raw) || count($raw) > 500) {
            throw new \App\Core\Exception\AppException('Lignes de dépense invalides.');
        }
        foreach ($raw as $line) {
            if (!is_array($line)) {
                throw new \App\Core\Exception\AppException('Lignes de dépense invalides.');
            }
            foreach (['id', 'spent_on', 'category', 'label', 'amount', 'vat_amount', 'distance_km', 'km_rate'] as $key) {
                if (isset($line[$key]) && !is_scalar($line[$key])) {
                    throw new \App\Core\Exception\AppException('Lignes de dépense invalides.');
                }
            }
        }
        try {
            $this->reports->updateDraft($id, $data, array_values($raw));
        } catch (\App\Core\Exception\AppException $exception) {
            flash('danger', $exception->getMessage());
            return new RedirectResponse('/expenses/' . $id . '/edit');
        }
        flash('success', 'Brouillon modifié. Si un PDF avait été généré, régénérez-le pour refléter les modifications.');
        return new RedirectResponse('/expenses/' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $this->requireAdmin();
        try {
            $this->reports->archiveDeletable($id, (int) $this->auth()->id());
        } catch (\App\Core\Exception\AppException $exception) {
            flash('danger', $exception->getMessage());
            return new RedirectResponse('/expenses/' . $id);
        }
        flash('success', 'Note supprimée de la liste et conservée dans les archives avec ses justificatifs.');
        return new RedirectResponse('/expenses');
    }

    public function archive(Request $request): Response
    {
        $this->requireAdmin();
        return $this->view('Expenses::archive', ['title' => 'Notes de frais archivées', 'reports' => $this->reports->archived()]);
    }

    public function archiveShow(Request $request, int $id): Response
    {
        $this->requireAdmin();
        return $this->view('Expenses::archive-show', [
            'title' => 'Note de frais archivée', 'report' => $this->reports->archivedOrFail($id),
            'lines' => $this->reports->lines($id), 'receipts' => $this->receipts->forReport($id),
            'pdfDoc' => $this->pdf->latestFor('expense_report', $id),
        ]);
    }

    public function permanentlyDelete(Request $request, int $id): Response
    {
        $this->requireAdmin();
        if ($request->input('confirmation') !== 'SUPPRIMER') {
            flash('danger', 'Saisissez SUPPRIMER pour confirmer la suppression définitive.');
            return new RedirectResponse('/expenses/archive/' . $id);
        }
        try {
            $failed = \App\Core\Application::container()->get(\Modules\Expenses\Services\ExpensePermanentDeletionService::class)->deleteArchived($id);
        } catch (\App\Core\Exception\AppException $exception) {
            flash('danger', $exception->getMessage());
            return new RedirectResponse('/expenses/archive/' . $id);
        }
        if ($failed > 0) {
            flash('warning', 'La note a été supprimée, mais certains fichiers restent à effacer manuellement. Consultez le journal serveur.');
        } else {
            flash('success', 'Note et fichiers associés supprimés définitivement.');
        }
        return new RedirectResponse('/expenses/archive');
    }

    public function archiveReceipt(Request $request, int $id, int $receiptId): Response
    {
        $this->requireAdmin();
        $this->reports->archivedOrFail($id);
        $file = $this->receipts->download($id, $receiptId);
        return Response::download($file['path'], 'application/pdf')
            ->withHeader('Content-Disposition', "attachment; filename=\"justificatif.pdf\"; filename*=UTF-8''" . rawurlencode($file['name']))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    private function canAccessReceipts(array $report): bool
    {
        if ($this->auth()->hasAnyPermission(['expenses.approve', 'expenses.pay'])) {
            return true;
        }
        return $this->db->fetchOne('SELECT id FROM employees WHERE id = :employee AND user_id = :user', [
            'employee' => $report['employee_id'], 'user' => $this->auth()->id(),
        ]) !== null;
    }

    private function requireReceiptAccess(int $id): void
    {
        $this->auth()->requirePermission('expenses.read');
        if (!$this->canAccessReceipts($this->reports->getOrFail($id))) {
            throw new \App\Core\Exception\AuthorizationException('Accès au justificatif refusé.');
        }
    }

    public function uploadReceipt(Request $request, int $id): Response
    {
        $this->requireReceiptAccess($id);
        $this->auth()->requireAnyPermission(['expenses.request', 'expenses.approve']);
        $file = $request->file('receipt');
        if ($file === null) {
            throw new \App\Core\Exception\ValidationException(['receipt' => 'Sélectionnez un fichier PDF.']);
        }
        $this->receipts->upload($id, $file, (int) $this->auth()->id());
        flash('success', 'Justificatif ajouté.');
        return new RedirectResponse('/expenses/' . $id);
    }

    public function downloadReceipt(Request $request, int $id, int $receiptId): Response
    {
        $this->requireReceiptAccess($id);
        $file = $this->receipts->download($id, $receiptId);
        return Response::download($file['path'], 'application/pdf')
            ->withHeader('Content-Disposition', "attachment; filename=\"justificatif.pdf\"; filename*=UTF-8''" . rawurlencode($file['name']))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
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
        $this->db->transaction(function (Connection $db) use ($id): void {
            $report = $db->fetchOne('SELECT id FROM expense_reports WHERE id = :id AND deleted_at IS NULL' . ($db->isSqlite() ? '' : ' FOR UPDATE'), ['id' => $id]);
            if ($report === null) {
                throw new \App\Core\Exception\NotFoundException('Note de frais introuvable.');
            }
            $this->pdf->generate('expense_report', $id, $this->auth()->id());
        });
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
