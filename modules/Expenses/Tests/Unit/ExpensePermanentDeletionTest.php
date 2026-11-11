<?php

declare(strict_types=1);

namespace Modules\Expenses\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\AppException;
use App\Core\Exception\AuthorizationException;
use Modules\Expenses\Services\ExpensePermanentDeletionService;
use Modules\Expenses\Services\ExpenseReportService;
use Tests\Core\DatabaseTestCase;

final class ExpensePermanentDeletionTest extends DatabaseTestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function report(bool $archive = true): int
    {
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, employee_number, last_name, first_name) VALUES (:company, 'EMP-F', 'Test', 'Anne')", ['company' => $company]);
        $employee = (int) $this->db->lastInsertId();
        $service = Application::container()->get(ExpenseReportService::class);
        $id = $service->create(['employee_id' => $employee, 'title' => 'Note', 'lines' => [['spent_on' => '2026-10-26', 'category' => 'meal', 'label' => 'Repas', 'amount' => 25]]], 1);
        if ($archive) {
            $service->archiveDeletable($id, 1);
        }
        return $id;
    }

    private function createFile(string $path): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, '%PDF-test');
        $this->files[] = $path;
    }

    public function testDeletesArchivedReportFilesAndDocuments(): void
    {
        $id = $this->report();
        $name = bin2hex(random_bytes(16)) . '.pdf';
        $receipt = storage_path('uploads/expense-receipts/' . $name);
        $pdfRel = 'note_frais/2099/note-frais-' . $id . '-' . bin2hex(random_bytes(8)) . '.pdf';
        $versionRel = 'note_frais/2099/version-' . bin2hex(random_bytes(8)) . '.pdf';
        $pdf = storage_path('exports/' . $pdfRel);
        $version = storage_path('exports/' . $versionRel);
        $this->createFile($receipt); $this->createFile($pdf); $this->createFile($version);
        $this->db->execute("INSERT INTO expense_receipts (report_id, original_name, storage_name, file_size) VALUES (:id, 'facture.pdf', :name, 9)", ['id' => $id, 'name' => $name]);
        $this->db->execute("INSERT INTO documents (name, path, disk, category, entity_type, entity_id, mime) VALUES ('Note', :path, 'exports', 'note_frais', 'expense_report', :id, 'application/pdf')", ['path' => $pdfRel, 'id' => $id]);
        $docId = (int) $this->db->lastInsertId();
        $this->db->execute('INSERT INTO document_versions (document_id, version, path, size) VALUES (:id, 1, :path, 9)', ['id' => $docId, 'path' => $versionRel]);
        self::assertSame(0, Application::container()->get(ExpensePermanentDeletionService::class)->deleteArchived($id));
        foreach ([$receipt, $pdf, $version] as $file) {
            self::assertFalse(file_exists($file));
        }
        foreach (['expense_reports' => 'id', 'expense_lines' => 'report_id', 'expense_receipts' => 'report_id'] as $table => $field) {
            self::assertSame(0, (int) $this->db->fetchScalar("SELECT COUNT(*) FROM {$table} WHERE {$field} = :id", ['id' => $id]));
        }
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM documents WHERE id = :id', ['id' => $docId]));
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM document_versions WHERE document_id = :id', ['id' => $docId]));
        self::assertSame(1, (int) $this->db->fetchScalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'expense.hard_delete' AND entity_id = :id", ['id' => $id]));
    }

    public function testActiveNoteCannotBePermanentlyDeleted(): void
    {
        $id = $this->report(false);
        $this->expectException(AppException::class);
        Application::container()->get(ExpensePermanentDeletionService::class)->deleteArchived($id);
    }

    public function testLegacyPathPreventsDeletion(): void
    {
        $id = $this->report();
        $this->db->execute("UPDATE expense_lines SET receipt_path = 'legacy.pdf' WHERE report_id = :id", ['id' => $id]);
        try {
            Application::container()->get(ExpensePermanentDeletionService::class)->deleteArchived($id);
            throw new \RuntimeException('Chemin historique non refusé');
        } catch (AppException $exception) {
            self::assertNotNull($this->db->fetchOne('SELECT id FROM expense_reports WHERE id = :id', ['id' => $id]));
        }
    }

    public function testSharedDocumentIsNotDeleted(): void
    {
        $id = $this->report();
        $path = 'note_frais/2099/shared-' . bin2hex(random_bytes(8)) . '.pdf';
        $file = storage_path('exports/' . $path); $this->createFile($file);
        $this->db->execute("INSERT INTO documents (name, path, disk, category, entity_type, entity_id, mime) VALUES ('Note', :path, 'exports', 'note_frais', 'expense_report', :id, 'application/pdf')", ['path' => $path, 'id' => $id]);
        $this->db->execute("INSERT INTO documents (name, path, disk, category, entity_type, entity_id, mime) VALUES ('Autre', :path, 'exports', 'autre', 'other', 1, 'application/pdf')", ['path' => $path]);
        try {
            Application::container()->get(ExpensePermanentDeletionService::class)->deleteArchived($id);
            throw new \RuntimeException('Fichier partagé non refusé');
        } catch (AppException $exception) {
            self::assertTrue(is_file($file));
            self::assertNotNull($this->db->fetchOne('SELECT id FROM expense_reports WHERE id = :id', ['id' => $id]));
        }
    }

    public function testNonAdminIsDenied(): void
    {
        $id = $this->report();
        $this->db->execute("INSERT INTO users (email, password_hash) VALUES ('accountant@example.test', 'hash')");
        $user = (int) $this->db->lastInsertId();
        $role = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'comptable'");
        $this->db->execute('INSERT INTO role_user (role_id, user_id) VALUES (:role, :user)', ['role' => $role, 'user' => $user]);
        $c = Application::container();
        $c->get(\App\Core\Auth\AuthManager::class)->setUser($c->get(\Modules\Authentication\Repositories\UserRepository::class)->findWithRights($user));
        $request = new \App\Core\Http\Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/expenses/archive/1/permanent-delete'], [], ['confirmation' => 'SUPPRIMER'], []);
        $this->expectException(AuthorizationException::class);
        $c->get(\Modules\Expenses\Controllers\ExpenseReportController::class)->permanentlyDelete($request, $id);
    }
    public function testFilesRestoredWhenDatabaseDeletionFails(): void
    {
        if (!$this->db->isSqlite()) {
            return; // Le trigger de simulation ci-dessous utilise la syntaxe SQLite.
        }
        $id = $this->report();
        $name = bin2hex(random_bytes(16)) . '.pdf';
        $file = storage_path('uploads/expense-receipts/' . $name);
        $this->createFile($file);
        $this->db->execute("INSERT INTO expense_receipts (report_id, original_name, storage_name, file_size) VALUES (:id, 'facture.pdf', :name, 9)", ['id' => $id, 'name' => $name]);
        $this->db->exec("CREATE TRIGGER block_expense_delete BEFORE DELETE ON expense_reports BEGIN SELECT RAISE(ABORT, 'blocked'); END");
        try {
            Application::container()->get(ExpensePermanentDeletionService::class)->deleteArchived($id);
            throw new \RuntimeException('La suppression aurait dû échouer');
        } catch (\PDOException $exception) {
            self::assertTrue(is_file($file));
            self::assertNotNull($this->db->fetchOne('SELECT id FROM expense_reports WHERE id = :id', ['id' => $id]));
            self::assertSame(1, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM expense_receipts WHERE report_id = :id', ['id' => $id]));
        } finally {
            $this->db->exec('DROP TRIGGER block_expense_delete');
        }
    }

    public function testAdminMustTypeConfirmation(): void
    {
        $id = $this->report();
        $c = Application::container();
        $admin = $c->get(\Modules\Authentication\Repositories\UserRepository::class)->findByEmail('admin@prime-vision-solutions.fr');
        $c->get(\App\Core\Auth\AuthManager::class)->setUser($admin);
        $request = new \App\Core\Http\Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/expenses/archive/1/permanent-delete'], [], ['confirmation' => 'non'], []);
        self::assertSame(302, $c->get(\Modules\Expenses\Controllers\ExpenseReportController::class)->permanentlyDelete($request, $id)->getStatus());
        self::assertNotNull($this->db->fetchOne('SELECT id FROM expense_reports WHERE id = :id', ['id' => $id]));
    }

    public function testArchivedRejectedCanBePermanentlyDeleted(): void
    {
        $id = $this->report(false);
        $reports = Application::container()->get(ExpenseReportService::class);
        $reports->transition($id, 'submit', 1);
        $reports->transition($id, 'reject', 1);
        $reports->archiveDeletable($id, 1);
        $name = bin2hex(random_bytes(16)) . '.pdf';
        $file = storage_path('uploads/expense-receipts/' . $name);
        $this->createFile($file);
        $this->db->execute("INSERT INTO expense_receipts (report_id, original_name, storage_name, file_size) VALUES (:id, 'facture.pdf', :name, 9)", ['id' => $id, 'name' => $name]);
        self::assertSame(0, Application::container()->get(ExpensePermanentDeletionService::class)->deleteArchived($id));
        self::assertFalse(file_exists($file));
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM expense_reports WHERE id = :id', ['id' => $id]));
    }

}
