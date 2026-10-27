<?php

declare(strict_types=1);

namespace Modules\Expenses\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\AppException;
use Modules\Expenses\Services\ExpenseReportService;
use Tests\Core\DatabaseTestCase;

final class ExpenseReportServiceTest extends DatabaseTestCase
{
    private ExpenseReportService $service;
    private int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Application::container()->get(ExpenseReportService::class);

        $this->db->execute(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code, is_default)
             VALUES ('PBS Frais SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001', true)"
        );
        $companyId = (int) $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO employees (company_id, employee_number, last_name, first_name, status, hire_date)
             VALUES ({$companyId}, 'EMP-F1', 'Durand', 'Claire', 'active', '2026-01-01')"
        );
        $this->employeeId = (int) $this->db->lastInsertId();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function sampleLines(): array
    {
        return [
            ['category' => 'meal', 'label' => 'Restaurant', 'amount' => 82.50, 'vat_amount' => 8.25,
                'spent_on' => '2026-10-26', 'distance_km' => 0, 'km_rate' => 0],
            ['category' => 'kilometric', 'label' => 'Frais kilométriques', 'amount' => 35.70, 'vat_amount' => 0,
                'spent_on' => '2026-10-27', 'distance_km' => 42, 'km_rate' => 0.85],
        ];
    }

    public function testCreateStoresLinesAndComputesTotal(): void
    {
        $id = $this->service->create([
            'employee_id' => $this->employeeId,
            'title' => 'Note octobre',
            'notes' => '',
            'lines' => $this->sampleLines(),
        ], 1);

        $report = $this->db->fetchOne('SELECT * FROM expense_reports WHERE id = :id', ['id' => $id]);
        self::assertSame('draft', $report['status']);
        self::assertEqualsWithDelta(118.20, (float) $report['total_amount'], 0.001);

        $lines = $this->db->fetchAll('SELECT * FROM expense_lines WHERE report_id = :id ORDER BY id', ['id' => $id]);
        self::assertCount(2, $lines);
        foreach ($lines as $line) {
            self::assertSame('{}', $line['ocr_data']);
        }
        self::assertSame('kilometric', $lines[1]['category']);
    }

    public function testUnknownCategoryFallsBackToOther(): void
    {
        $lines = $this->sampleLines();
        $lines[0]['category'] = 'inexistante';

        $id = $this->service->create([
            'employee_id' => $this->employeeId,
            'title' => 'Note catégorie',
            'lines' => $lines,
        ], 1);

        $category = $this->db->fetchScalar('SELECT category FROM expense_lines WHERE report_id = :id ORDER BY id LIMIT 1', ['id' => $id]);
        self::assertSame('other', $category);
    }

    public function testFullWorkflowDraftToPaid(): void
    {
        $id = $this->service->create([
            'employee_id' => $this->employeeId,
            'title' => 'Note workflow',
            'lines' => $this->sampleLines(),
        ], 1);

        $this->service->transition($id, 'submit', 1);
        self::assertSame('submitted', $this->db->fetchScalar('SELECT status FROM expense_reports WHERE id = :id', ['id' => $id]));

        $this->service->transition($id, 'approve', 1);
        $report = $this->db->fetchOne('SELECT * FROM expense_reports WHERE id = :id', ['id' => $id]);
        self::assertSame('approved', $report['status']);
        self::assertSame('1', (string) $report['approved_by']);
        self::assertNotNull($report['approved_at']);

        $this->service->transition($id, 'pay', 1);
        $paid = $this->db->fetchOne('SELECT status, paid_on FROM expense_reports WHERE id = :id', ['id' => $id]);
        self::assertSame('paid', $paid['status']);
        self::assertSame(date('Y-m-d'), $paid['paid_on']);
    }

    public function testRejectPath(): void
    {
        $id = $this->service->create([
            'employee_id' => $this->employeeId,
            'title' => 'Note rejet',
            'lines' => $this->sampleLines(),
        ], 1);

        $this->service->transition($id, 'submit', 1);
        $this->service->transition($id, 'reject', 1);
        self::assertSame('rejected', $this->db->fetchScalar('SELECT status FROM expense_reports WHERE id = :id', ['id' => $id]));
    }

    public function testInvalidTransitionIsRejected(): void
    {
        $id = $this->service->create([
            'employee_id' => $this->employeeId,
            'title' => 'Note invalide',
            'lines' => $this->sampleLines(),
        ], 1);

        // Paiement impossible depuis un brouillon.
        $this->expectException(AppException::class);
        $this->service->transition($id, 'pay', 1);
    }
}
