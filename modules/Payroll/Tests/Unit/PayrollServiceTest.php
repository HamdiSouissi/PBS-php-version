<?php

declare(strict_types=1);

namespace Modules\Payroll\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\AppException;
use Modules\Payroll\Services\PayrollService;
use Tests\Core\DatabaseTestCase;

final class PayrollServiceTest extends DatabaseTestCase
{
    private int $companyId;
    private int $employeeId;
    private int $periodId;
    private PayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Application::container()->get(PayrollService::class);

        $this->db->execute(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code, is_default)
             VALUES ('PBS Paie SAS', '123456789', '12345678901234', 'SAS', 'Lyon', '69000', true)"
        );
        $this->companyId = (int) $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO employees (company_id, employee_number, last_name, first_name, status, hire_date)
             VALUES ({$this->companyId}, 'EMP-001', 'Dupont', 'Jean', 'active', '2026-01-01')"
        );
        $this->employeeId = (int) $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO employment_contracts
                (employee_id, type, start_date, weekly_hours, is_part_time, part_time_pct,
                 hourly_rate, monthly_gross, status)
             VALUES ({$this->employeeId}, 'CDI', '2026-01-01', 35, 0, 100, 0, 3000, 'active')"
        );
        $period = $this->service->ensurePeriod($this->companyId, 2026, 10);
        $this->periodId = (int) $period['id'];

        // Règles maîtrisées pour des assertions exactes.
        $this->db->execute(
            "INSERT INTO payroll_rule_versions
                (code, label, category, base_type, rate_employee, rate_employer, ceiling_month,
                 is_deductible, effective_from, source, notes)
             VALUES
                ('BRUT1', 'Cotisation sur brut', 'sante', 'brut', 10, 20, NULL, 1, '2026-01-01', 'TEST', ''),
                ('PLAF1', 'Cotisation plafonnée', 'vieillesse', 'plafond', 5, 5, 1000, 1, '2026-01-01', 'TEST', ''),
                ('CSG1', 'CSG test', 'csg_crds', 'csg', 9.2, 0, NULL, 1, '2026-01-01', 'TEST', '')"
        );
    }

    public function testGeneratePayslipComputesBasesLinesAndTotals(): void
    {
        $payslipId = $this->service->generatePayslip($this->periodId, $this->employeeId, 500.0, 1);

        $payslip = $this->db->fetchOne('SELECT * FROM payslips WHERE id = :id', ['id' => $payslipId]);
        self::assertSame('draft', $payslip['status']);
        self::assertEqualsWithDelta(3500.0, (float) $payslip['gross_salary'], 0.001);

        $lines = $this->db->fetchAll('SELECT * FROM payslip_lines WHERE payslip_id = :id ORDER BY sort_order', ['id' => $payslipId]);
        self::assertCount(3, $lines);

        // Base brute = 3500 ; base plafonnée = min(3500, 1000) ; base CSG = 3500 × 98,25 %.
        self::assertEqualsWithDelta(3500.0, (float) $lines[0]['base_amount'], 0.001);
        self::assertEqualsWithDelta(1000.0, (float) $lines[1]['base_amount'], 0.001);
        self::assertEqualsWithDelta(3438.75, (float) $lines[2]['base_amount'], 0.001);

        // Salarial : 10 % de 3500 + 5 % de 1000 + 9,2 % de 3438,75.
        $expectedEmployee = 3500 * 0.10 + 1000 * 0.05 + 3438.75 * 0.092;
        self::assertEqualsWithDelta($expectedEmployee, (float) $payslip['total_employee'], 0.02);
        // Patronal : 20 % de 3500 + 5 % de 1000.
        self::assertEqualsWithDelta(3500 * 0.20 + 1000 * 0.05, (float) $payslip['total_employer'], 0.02);
        self::assertEqualsWithDelta(3500.0 - $expectedEmployee, (float) $payslip['net_payable'], 0.02);
    }

    public function testDraftPayslipCanBeRegeneratedButValidatedCannot(): void
    {
        $payslipId = $this->service->generatePayslip($this->periodId, $this->employeeId, 0.0, 1);
        // Recalcul du brouillon : pas de doublon (contrainte unique période/salarié).
        $this->service->generatePayslip($this->periodId, $this->employeeId, 100.0, 1);
        $count = (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM payslips WHERE period_id = :p AND employee_id = :e',
            ['p' => $this->periodId, 'e' => $this->employeeId]
        );
        self::assertSame(1, $count);

        $this->service->validate($payslipId, 1);
        $this->expectException(AppException::class);
        $this->service->generatePayslip($this->periodId, $this->employeeId, 0.0, 1);
    }

    public function testLockedPeriodRejectsGeneration(): void
    {
        $payslipId = $this->service->generatePayslip($this->periodId, $this->employeeId, 0.0, 1);
        $this->service->validate($payslipId, 1);
        $this->service->lockPeriod($this->periodId, 1);

        $this->expectException(AppException::class);
        $this->service->generatePayslip($this->periodId, $this->employeeId, 0.0, 1);
    }

    public function testLockPeriodFailsWhileDraftRemains(): void
    {
        // Un second salarié avec un bulletin brouillon non validé.
        $this->db->execute(
            "INSERT INTO employees (company_id, employee_number, last_name, first_name, status, hire_date)
             VALUES ({$this->companyId}, 'EMP-002', 'Martin', 'Alice', 'active', '2026-01-01')"
        );
        $secondId = (int) $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO employment_contracts
                (employee_id, type, start_date, weekly_hours, is_part_time, part_time_pct,
                 hourly_rate, monthly_gross, status)
             VALUES ({$secondId}, 'CDI', '2026-01-01', 35, 0, 100, 0, 2500, 'active')"
        );
        $firstId = $this->service->generatePayslip($this->periodId, $this->employeeId, 0.0, 1);
        $this->service->generatePayslip($this->periodId, $secondId, 0.0, 1);
        $this->service->validate($firstId, 1);

        $this->expectException(AppException::class);
        $this->service->lockPeriod($this->periodId, 1);
    }

    public function testMarkPaidRequiresValidation(): void
    {
        $payslipId = $this->service->generatePayslip($this->periodId, $this->employeeId, 0.0, 1);
        $this->expectException(AppException::class);
        $this->service->markPaid($payslipId);
    }
}
