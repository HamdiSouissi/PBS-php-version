<?php

declare(strict_types=1);

namespace Modules\Documents\Tests\Functional;

use App\Core\Application;
use App\Core\Auth\AuthManager;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfManager;
use Modules\Authentication\Models\User;
use Modules\Authentication\Repositories\UserRepository;
use Modules\Documents\Services\DocumentAccessService;
use Modules\Employees\Services\EmployeeService;
use Tests\Core\DatabaseTestCase;

final class DocumentAccessTest extends DatabaseTestCase
{
    private ?int $companyId = null;

    private function companyId(): int
    {
        if ($this->companyId !== null) {
            return $this->companyId;
        }
        $this->db->execute(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code)
             VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')"
        );
        return $this->companyId = (int) $this->db->lastInsertId();
    }

    /**
     * @return array{employee_id:int,user_id:int}
     */
    private function hireEmployee(string $email, string $lastName): array
    {
        $employeeId = Application::container()->get(EmployeeService::class)->create($this->companyId(), [
            'last_name' => $lastName,
            'first_name' => 'Test',
            'professional_email' => $email,
        ]);
        $userId = (int) $this->db->fetchScalar(
            'SELECT user_id FROM employees WHERE id = :id',
            ['id' => $employeeId]
        );
        return ['employee_id' => $employeeId, 'user_id' => $userId];
    }

    private function loginAs(int $userId): User
    {
        $user = Application::container()->get(UserRepository::class)->findWithRights($userId);
        self::assertNotNull($user);
        Application::container()->get(AuthManager::class)->setUser($user);
        return $user;
    }

    private function insertDocument(int $employeeId, string $name): int
    {
        $this->db->execute(
            'INSERT INTO documents (name, path, disk, mime, employee_id, visibility)
             VALUES (:name, :path, :disk, :mime, :employee, :visibility)',
            [
                'name' => $name,
                'path' => 'rh/2026/' . $employeeId . '-' . uniqid() . '.pdf',
                'disk' => 'documents',
                'mime' => 'application/pdf',
                'employee' => $employeeId,
                'visibility' => 'portal',
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    private function service(): DocumentAccessService
    {
        return Application::container()->get(DocumentAccessService::class);
    }

    public function testEmployeeSeesOnlyOwnDocuments(): void
    {
        $alice = $this->hireEmployee('alice@entreprise.fr', 'Alice');
        $bob = $this->hireEmployee('bob@entreprise.fr', 'Bob');
        $this->insertDocument($alice['employee_id'], 'Contrat Alice');
        $this->insertDocument($alice['employee_id'], 'Bulletin Alice');
        $this->insertDocument($bob['employee_id'], 'Contrat Bob');

        $this->loginAs($alice['user_id']);
        $names = array_column($this->service()->visibleDocuments(), 'name');
        self::assertCount(2, $names);
        self::assertTrue(in_array('Contrat Alice', $names, true));
        self::assertFalse(in_array('Contrat Bob', $names, true));
    }

    public function testManagerSeesEveryDocument(): void
    {
        $alice = $this->hireEmployee('alice@entreprise.fr', 'Alice');
        $bob = $this->hireEmployee('bob@entreprise.fr', 'Bob');
        $this->insertDocument($alice['employee_id'], 'Contrat Alice');
        $this->insertDocument($bob['employee_id'], 'Contrat Bob');

        // Administrateur d'usine (permission générique '*').
        $adminId = (int) $this->db->fetchScalar(
            "SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'"
        );
        $this->loginAs($adminId);
        self::assertTrue($this->service()->isManager());
        self::assertCount(2, $this->service()->visibleDocuments());
    }

    public function testEmployeeCannotAccessAnotherEmployeeDocument(): void
    {
        $alice = $this->hireEmployee('alice@entreprise.fr', 'Alice');
        $bob = $this->hireEmployee('bob@entreprise.fr', 'Bob');
        $bobDocId = $this->insertDocument($bob['employee_id'], 'Contrat Bob');

        $this->loginAs($alice['user_id']);
        $document = $this->db->fetchOne('SELECT * FROM documents WHERE id = :id', ['id' => $bobDocId]);
        $this->expectException(NotFoundException::class);
        $this->service()->assertCanAccess($document);
    }

    public function testEmployeeCanAccessOwnDocument(): void
    {
        $alice = $this->hireEmployee('alice@entreprise.fr', 'Alice');
        $docId = $this->insertDocument($alice['employee_id'], 'Contrat Alice');

        $this->loginAs($alice['user_id']);
        $document = $this->db->fetchOne('SELECT * FROM documents WHERE id = :id', ['id' => $docId]);
        $this->service()->assertCanAccess($document); // ne doit pas lever
        self::assertTrue(true);
    }

    public function testGeneratedContractPdfIsLinkedAndRegenerationAddsRow(): void
    {
        $alice = $this->hireEmployee('alice@entreprise.fr', 'Alice');

        $this->db->execute(
            "INSERT INTO employment_contracts (employee_id, type, start_date, monthly_gross, weekly_hours, status)
             VALUES ({$alice['employee_id']}, 'CDI', '2026-01-01', 3000, 35, 'active')"
        );
        $contractId = (int) $this->db->lastInsertId();

        $pdf = Application::container()->get(PdfManager::class);
        $first = $pdf->generate('employee_contract', $contractId, $alice['user_id']);
        self::assertSame($alice['employee_id'], (int) $first['employee_id']);
        self::assertSame('portal', $first['visibility']);

        // Une régénération historise une nouvelle ligne, également rattachée.
        $second = $pdf->generate('employee_contract', $contractId, $alice['user_id']);
        self::assertNotSame((int) $first['id'], (int) $second['id']);
        self::assertSame($alice['employee_id'], (int) $second['employee_id']);

        foreach ([$first, $second] as $document) {
            $path = storage_path('exports/' . $document['path']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function testGeneratedPayslipPdfIsLinkedToEmployee(): void
    {
        $alice = $this->hireEmployee('alice@entreprise.fr', 'Alice');
        $this->db->execute(
            "INSERT INTO payroll_periods (company_id, year, month, starts_on, ends_on)
             VALUES ({$this->companyId()}, 2026, 9, '2026-09-01', '2026-09-30')"
        );
        $periodId = (int) $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO payslips (period_id, employee_id, status, gross_salary, net_payable)
             VALUES ({$periodId}, {$alice['employee_id']}, 'validated', 3000, 2300)"
        );
        $payslipId = (int) $this->db->lastInsertId();

        $document = Application::container()->get(PdfManager::class)
            ->generate('payslip', $payslipId, $alice['user_id']);

        self::assertSame($alice['employee_id'], (int) $document['employee_id']);
        self::assertSame('portal', $document['visibility']);
        self::assertSame('payslip', $document['entity_type']);

        $this->loginAs($alice['user_id']);
        $visible = $this->service()->visibleDocuments();
        self::assertCount(1, $visible);
        self::assertSame((int) $document['id'], (int) $visible[0]['id']);

        $path = storage_path('exports/' . $document['path']);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function testGeneratedEmployeeSheetIsLinkedAndPortalVisible(): void
    {
        $alice = $this->hireEmployee('alice@entreprise.fr', 'Alice');
        $bob = $this->hireEmployee('bob@entreprise.fr', 'Bob');

        $document = Application::container()->get(PdfManager::class)
            ->generate('employee_sheet', $alice['employee_id'], $alice['user_id']);

        self::assertSame($alice['employee_id'], (int) $document['employee_id']);
        self::assertSame('portal', $document['visibility']);

        $this->loginAs($alice['user_id']);
        $visible = $this->service()->visibleDocuments();
        self::assertCount(1, $visible);
        self::assertSame((int) $document['id'], (int) $visible[0]['id']);

        $this->loginAs($bob['user_id']);
        self::assertSame([], $this->service()->visibleDocuments());

        // Nettoyage du fichier PDF écrit sur le disque d'export.
        $path = storage_path('exports/' . $document['path']);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
