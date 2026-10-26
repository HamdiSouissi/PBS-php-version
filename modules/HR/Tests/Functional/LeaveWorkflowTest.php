<?php

declare(strict_types=1);

namespace Modules\HR\Tests\Functional;

use App\Core\Application;
use App\Core\Exception\AppException;
use Modules\HR\Services\LeaveService;
use Tests\Core\DatabaseTestCase;

final class LeaveWorkflowTest extends DatabaseTestCase
{
    private function createEmployee(): int
    {
        $companyId = (int) $this->db->fetchScalar(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code)
             VALUES ('Test', '123456789', '12345678901234', 'SAS', 'Paris', '75001') RETURNING id"
        );
        return (int) $this->db->fetchScalar(
            "INSERT INTO employees (company_id, employee_number, last_name, first_name, status)
             VALUES ({$companyId}, 'EMP-T-0001', 'Test', 'User', 'active') RETURNING id"
        );
    }

    public function testWorkingDaysExcludeWeekends(): void
    {
        $service = Application::container()->get(LeaveService::class);
        // Lundi 12 octobre 2026 au vendredi 16 octobre 2026 = 5 jours ouvrés.
        $days = $service->workingDaysBetween(
            new \DateTimeImmutable('2026-10-12'),
            new \DateTimeImmutable('2026-10-16')
        );
        self::assertEqualsWithDelta(5.0, $days, 0.001);

        // Inclure un week-end (12 → 18 octobre) = 5 ouvrés.
        $daysWithWeekend = $service->workingDaysBetween(
            new \DateTimeImmutable('2026-10-12'),
            new \DateTimeImmutable('2026-10-18')
        );
        self::assertEqualsWithDelta(5.0, $daysWithWeekend, 0.001);
    }

    public function testSubmitApproveConsumesBalance(): void
    {
        $service = Application::container()->get(LeaveService::class);
        $employeeId = $this->createEmployee();
        $typeId = (int) $this->db->fetchScalar("SELECT id FROM leave_types WHERE code = 'CP'");
        $service->initializeBalances($employeeId, 2026);

        $requestId = $service->submit($employeeId, [
            'leave_type_id' => $typeId,
            'starts_on' => '2026-07-06',
            'ends_on' => '2026-07-10',
            'reason' => 'Vacances',
        ], 1);

        $request = $this->db->fetchOne('SELECT * FROM leave_requests WHERE id = :id', ['id' => $requestId]);
        self::assertSame('submitted', $request['status']);
        self::assertEqualsWithDelta(5.0, (float) $request['days_count'], 0.001);

        $service->review($requestId, true, 1);
        $balance = $this->db->fetchOne(
            'SELECT * FROM leave_balances WHERE employee_id = :e AND leave_type_id = :t AND period_year = 2026',
            ['e' => $employeeId, 't' => $typeId]
        );
        self::assertEqualsWithDelta(5.0, (float) $balance['taken_days'], 0.001);
        self::assertEqualsWithDelta(20.0, (float) $balance['remaining_days'], 0.001);
    }

    public function testOverBalanceIsRejected(): void
    {
        $service = Application::container()->get(LeaveService::class);
        $employeeId = $this->createEmployee();
        $typeId = (int) $this->db->fetchScalar("SELECT id FROM leave_types WHERE code = 'CP'");
        $service->initializeBalances($employeeId, 2026);

        $this->expectException(AppException::class);
        $service->submit($employeeId, [
            'leave_type_id' => $typeId,
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-09-30',
        ], 1);
    }
}
