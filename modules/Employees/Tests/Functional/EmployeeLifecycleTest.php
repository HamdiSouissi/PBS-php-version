<?php

declare(strict_types=1);

namespace Modules\Employees\Tests\Functional;

use App\Core\Database\Connection;
use App\Core\Application;
use Modules\Employees\Services\ContractService;
use Modules\Employees\Services\EmployeeService;
use Tests\Core\DatabaseTestCase;

final class EmployeeLifecycleTest extends DatabaseTestCase
{
    private function companyId(): int
    {
        // INSERT puis lastInsertId : portable SQLite (tests) comme MySQL.
        $this->db->execute(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code)
             VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')"
        );
        return (int) $this->db->lastInsertId();
    }

    public function testEmployeeCreationGeneratesSequentialNumber(): void
    {
        $service = Application::container()->get(EmployeeService::class);
        $companyId = $this->companyId();

        $first = $service->create($companyId, ['last_name' => 'Dupont', 'first_name' => 'Jean', 'job_title' => 'Dev']);
        $second = $service->create($companyId, ['last_name' => 'Martin', 'first_name' => 'Marie', 'job_title' => 'RH']);

        $row1 = $this->db->fetchOne('SELECT * FROM employees WHERE id = :id', ['id' => $first]);
        $row2 = $this->db->fetchOne('SELECT * FROM employees WHERE id = :id', ['id' => $second]);
        self::assertNotEmpty($row1['employee_number']);
        self::assertNotSame($row1['employee_number'], $row2['employee_number']);
    }

    public function testContractActivationClosesPreviousActiveContract(): void
    {
        $employeeService = Application::container()->get(EmployeeService::class);
        $contractService = Application::container()->get(ContractService::class);
        $employeeId = $employeeService->create($this->companyId(), [
            'last_name' => 'Durand', 'first_name' => 'Paul',
        ]);

        $first = $contractService->create($employeeId, [
            'type' => 'CDD', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30',
            'monthly_gross' => 3000, 'weekly_hours' => 35, 'status' => 'active',
        ]);
        $second = $contractService->create($employeeId, [
            'type' => 'CDI', 'start_date' => '2026-07-01',
            'monthly_gross' => 3400, 'weekly_hours' => 35, 'status' => 'active',
        ]);

        $old = $this->db->fetchOne('SELECT status, end_date FROM employment_contracts WHERE id = :id', ['id' => $first]);
        $new = $this->db->fetchOne('SELECT status FROM employment_contracts WHERE id = :id', ['id' => $second]);
        self::assertSame('ended', $old['status']);
        self::assertSame('active', $new['status']);

        $employee = $this->db->fetchOne('SELECT status FROM employees WHERE id = :id', ['id' => $employeeId]);
        self::assertSame('active', $employee['status']);
    }

    public function testTerminationClosesContractAndEmployee(): void
    {
        $employeeService = Application::container()->get(EmployeeService::class);
        $contractService = Application::container()->get(ContractService::class);
        $employeeId = $employeeService->create($this->companyId(), ['last_name' => 'Courant', 'first_name' => 'Élise']);
        $contractId = $contractService->create($employeeId, [
            'type' => 'CDI', 'start_date' => '2025-01-01',
            'monthly_gross' => 2800, 'weekly_hours' => 35, 'status' => 'active',
        ]);

        $employeeService->terminate($employeeId, '2026-09-30', 'demission');

        $employee = $this->db->fetchOne('SELECT * FROM employees WHERE id = :id', ['id' => $employeeId]);
        $contract = $this->db->fetchOne('SELECT * FROM employment_contracts WHERE id = :id', ['id' => $contractId]);
        self::assertSame('terminated', $employee['status']);
        self::assertSame('2026-09-30', $employee['termination_date']);
        self::assertSame('ended', $contract['status']);
    }
}
