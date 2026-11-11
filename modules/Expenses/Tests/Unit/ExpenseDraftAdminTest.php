<?php

declare(strict_types=1);

namespace Modules\Expenses\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\AppException;
use App\Core\Exception\AuthorizationException;
use App\Core\Exception\NotFoundException;
use Modules\Expenses\Services\ExpenseReportService;
use Tests\Core\DatabaseTestCase;

final class ExpenseDraftAdminTest extends DatabaseTestCase
{
    private function setupReport(): array
    {
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, employee_number, last_name, first_name) VALUES (:company, 'EMP-F', 'Test', 'Anne')", ['company' => $company]);
        $employee = (int) $this->db->lastInsertId();
        $service = Application::container()->get(ExpenseReportService::class);
        $report = $service->create(['employee_id' => $employee, 'title' => 'Avant', 'lines' => [['spent_on' => '2026-10-26', 'category' => 'kilometric', 'label' => 'Trajet', 'amount' => 25, 'vat_amount' => 0, 'distance_km' => 40, 'km_rate' => 0.625]]], 1);
        return [$service, $report, $employee];
    }

    public function testEditRecalculatesAndPreservesMetadataAndReceipts(): void
    {
        [$service, $report, $employee] = $this->setupReport();
        $line = $service->lines($report)[0];
        $this->db->execute("UPDATE expense_lines SET ocr_data = '{\"source\":1}', receipt_path = 'legacy.pdf' WHERE id = :id", ['id' => $line['id']]);
        $this->db->execute("INSERT INTO expense_receipts (report_id, original_name, storage_name, file_size) VALUES (:report, 'receipt.pdf', '0123456789abcdef0123456789abcdef.pdf', 100)", ['report' => $report]);
        $service->updateDraft($report, ['employee_id' => $employee, 'title' => 'Après', 'notes' => 'Test'], [
            ['id' => $line['id'], 'spent_on' => '2026-10-26', 'category' => 'kilometric', 'label' => 'Trajet modifié', 'amount' => '30.00', 'vat_amount' => '0'],
            ['id' => 0, 'spent_on' => '2026-10-27', 'category' => 'meal', 'label' => 'Repas', 'amount' => '12.50', 'vat_amount' => '1.00'],
        ]);
        $updated = $service->getOrFail($report);
        self::assertSame('Après', $updated['title']);
        self::assertEqualsWithDelta(42.50, (float) $updated['total_amount'], 0.001);
        $preserved = $this->db->fetchOne('SELECT * FROM expense_lines WHERE id = :id', ['id' => $line['id']]);
        self::assertSame('legacy.pdf', $preserved['receipt_path']);
        self::assertSame('{"source":1}', $preserved['ocr_data']);
        self::assertEqualsWithDelta(40, (float) $preserved['distance_km'], 0.001);
        self::assertSame(1, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM expense_receipts WHERE report_id = :id', ['id' => $report]));
    }

    public function testDeleteArchivesNoteAndPreservesEverything(): void
    {
        [$service, $report] = $this->setupReport();
        $this->db->execute("INSERT INTO expense_receipts (report_id, original_name, storage_name, file_size) VALUES (:report, 'receipt.pdf', '0123456789abcdef0123456789abcdef.pdf', 100)", ['report' => $report]);
        $service->archiveDeletable($report, 1);
        self::assertCount(0, $service->all());
        self::assertCount(1, $service->archived());
        self::assertCount(1, $service->lines($report));
        self::assertSame(1, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM expense_receipts WHERE report_id = :id', ['id' => $report]));
        self::assertSame(1, (int) $this->db->fetchScalar('SELECT deleted_by FROM expense_reports WHERE id = :id', ['id' => $report]));
        $this->expectException(NotFoundException::class);
        $service->getOrFail($report);
    }

    public function testSubmittedCannotBeEditedOrDeleted(): void
    {
        [$service, $report, $employee] = $this->setupReport();
        $service->transition($report, 'submit', 1);
        try {
            $service->updateDraft($report, ['employee_id' => $employee, 'title' => 'Non'], [['id' => 0]]);
            throw new \RuntimeException('Modification non refusée');
        } catch (AppException $exception) {
            self::assertSame('Avant', $service->getOrFail($report)['title']);
        }
        $this->expectException(AppException::class);
        $service->archiveDeletable($report, 1);
    }

    public function testForeignLineRejectedAndTransactionRolledBack(): void
    {
        [$service, $report, $employee] = $this->setupReport();
        try {
            $service->updateDraft($report, ['employee_id' => $employee, 'title' => 'Non'], [['id' => 9999, 'spent_on' => '2026-10-26', 'category' => 'meal', 'label' => 'Test', 'amount' => '10', 'vat_amount' => '0']]);
            throw new \RuntimeException('Ligne étrangère acceptée');
        } catch (AppException $exception) {
            self::assertSame('Avant', $service->getOrFail($report)['title']);
            self::assertCount(1, $service->lines($report));
        }
    }

    public function testNonAdminDeniedEvenWithExpensePermissions(): void
    {
        [, $report, $employee] = $this->setupReport();
        $this->db->execute("INSERT INTO users (email, password_hash) VALUES ('accountant@example.test', 'hash')");
        $user = (int) $this->db->lastInsertId();
        $role = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'comptable'");
        $this->db->execute('INSERT INTO role_user (role_id, user_id) VALUES (:role, :user)', ['role' => $role, 'user' => $user]);
        $c = Application::container();
        $c->get(\App\Core\Auth\AuthManager::class)->setUser($c->get(\Modules\Authentication\Repositories\UserRepository::class)->findWithRights($user));
        $controller = $c->get(\Modules\Expenses\Controllers\ExpenseReportController::class);
        $request = new \App\Core\Http\Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/expenses/1/delete'], [], [], []);
        foreach (['edit', 'delete', 'archiveShow', 'archiveReceipt'] as $method) {
            try {
                $method === 'archiveReceipt' ? $controller->$method($request, $report, 1) : $controller->$method($request, $report);
                throw new \RuntimeException('Accès non administrateur accepté : ' . $method);
            } catch (AuthorizationException $exception) {
                self::assertTrue(true);
            }
        }
        $this->expectException(AuthorizationException::class);
        $controller->update($request, $report);
    }
    public function testRejectedCanBeArchivedButNotEdited(): void
    {
        [$service, $id, $employee] = $this->setupReport();
        $service->transition($id, 'submit', 1);
        $service->transition($id, 'reject', 1);
        $this->db->execute("INSERT INTO expense_receipts (report_id, original_name, storage_name, file_size) VALUES (:id, 'preuve.pdf', '0123456789abcdef0123456789abcdef.pdf', 1)", ['id' => $id]);
        try {
            $service->updateDraft($id, ['employee_id' => $employee, 'title' => 'Non'], [['id' => 0]]);
            throw new \RuntimeException('Modification interdite');
        } catch (AppException $exception) {
            self::assertSame('rejected', $service->getOrFail($id)['status']);
        }
        $service->archiveDeletable($id, 1);
        self::assertSame('rejected', $service->archivedOrFail($id)['status']);
        self::assertCount(1, $service->lines($id));
        self::assertSame(1, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM expense_receipts WHERE report_id = :id', ['id' => $id]));
        self::assertCount(0, $service->all());
    }

    public function testApprovedAndPaidCannotBeArchived(): void
    {
        [$service, $id] = $this->setupReport();
        $service->transition($id, 'submit', 1);
        $service->transition($id, 'approve', 1);
        foreach (['approved', 'paid'] as $status) {
            if ($status === 'paid') {
                $service->transition($id, 'pay', 1);
            }
            try {
                $service->archiveDeletable($id, 1);
                throw new \RuntimeException('Suppression non refusée');
            } catch (AppException $exception) {
                self::assertSame($status, $service->getOrFail($id)['status']);
            }
        }
    }

    public function testRejectedDeleteButtonOnlyVisibleToAdmin(): void
    {
        [$service, $id] = $this->setupReport();
        $service->transition($id, 'submit', 1);
        $service->transition($id, 'reject', 1);
        $renderer = Application::container()->get(\App\Core\View\ViewRenderer::class);
        $data = ['report' => $service->getOrFail($id), 'lines' => $service->lines($id), 'pdfDoc' => null, 'receipts' => [], 'canAccessReceipts' => false, 'canUploadReceipt' => false, 'isAdmin' => true];
        $html = $renderer->renderView('Expenses::show', $data);
        self::assertTrue(str_contains($html, '/expenses/' . $id . '/delete'));
        self::assertFalse(str_contains($html, '/expenses/' . $id . '/edit'));
        $data['isAdmin'] = false;
        $html = $renderer->renderView('Expenses::show', $data);
        self::assertFalse(str_contains($html, '/expenses/' . $id . '/delete'));
    }

}
