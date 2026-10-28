<?php

declare(strict_types=1);

namespace Modules\HR\Tests\Functional;

use App\Core\Application;
use App\Core\Exception\ValidationException;
use Modules\HR\Services\LeaveBalanceService;
use Modules\HR\Services\LeaveSettingsService;
use Tests\Core\DatabaseTestCase;

final class LeaveBalanceServiceTest extends DatabaseTestCase
{
    private function companyId(): int
    {
        $this->db->execute(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code)
             VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')"
        );
        return (int) $this->db->lastInsertId();
    }

    private function hireEmployee(?string $hireDate, ?string $terminationDate = null): int
    {
        $this->db->execute(
            'INSERT INTO employees (company_id, employee_number, last_name, first_name, status, hire_date, termination_date)
             VALUES (:company, :number, :last, :first, :status, :hire, :termination)',
            [
                'company' => $this->companyId(),
                'number' => 'EMP-B-' . uniqid(),
                'last' => 'Solde',
                'first' => 'Test',
                'status' => 'active',
                'hire' => $hireDate,
                'termination' => $terminationDate,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    private function service(): LeaveBalanceService
    {
        return Application::container()->get(LeaveBalanceService::class);
    }

    public function testDefaultAccrualFollowsFrenchStandard(): void
    {
        $settings = Application::container()->get(LeaveSettingsService::class);
        // Le seeder de référence pose la valeur par défaut.
        self::assertEqualsWithDelta(25 / 12, $settings->monthlyAccrual(), 0.0001);
        self::assertEqualsWithDelta(25.0, $settings->annualEquivalent(), 0.01);
    }

    public function testAccruedMonthsProration(): void
    {
        $service = $this->service();
        // Mois complet, du premier au dernier jour.
        self::assertEqualsWithDelta(1.0, $service->accruedMonths(
            new \DateTimeImmutable('2026-10-01'),
            new \DateTimeImmutable('2026-10-31')
        ), 0.001);
        // Année civile complète.
        self::assertEqualsWithDelta(12.0, $service->accruedMonths(
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31')
        ), 0.001);
        // Embauche mars 2025 jusqu'à fin octobre 2026 = 20 mois pleins.
        self::assertEqualsWithDelta(20.0, $service->accruedMonths(
            new \DateTimeImmutable('2025-03-01'),
            new \DateTimeImmutable('2026-10-31')
        ), 0.001);
        // Entrée en cours de mois : 16 jours d'avril sur 30, puis mai à décembre.
        self::assertEqualsWithDelta(16 / 30 + 7 + 1, $service->accruedMonths(
            new \DateTimeImmutable('2026-04-15'),
            new \DateTimeImmutable('2026-12-31')
        ), 0.001);
        // Plage inversée.
        self::assertSame(0.0, $service->accruedMonths(
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-01-01')
        ));
    }

    public function testSummaryForFullYearHire(): void
    {
        $employeeId = $this->hireEmployee('2026-01-01');
        $summary = $this->service()->summary($employeeId, 2026);

        // Janvier jusqu'à la date courante (28 octobre 2026) : 9 mois pleins
        // (janvier→septembre) plus la fraction d'octobre.
        self::assertEqualsWithDelta(9 + 28 / 31, $summary['months'], 0.001);
        self::assertEqualsWithDelta(
            $summary['months'] * (25 / 12),
            $summary['accrued_days'],
            0.02
        );
        self::assertSame(0.0, $summary['taken_days']);
        self::assertEqualsWithDelta($summary['accrued_days'], $summary['remaining_days'], 0.001);
    }

    public function testSummaryDeductsOnlyApprovedPaidLeaveOfTheYear(): void
    {
        $employeeId = $this->hireEmployee('2026-01-01');
        $cpId = (int) $this->db->fetchScalar("SELECT id FROM leave_types WHERE code = 'CP'");
        $rttId = (int) $this->db->fetchScalar("SELECT id FROM leave_types WHERE code = 'RTT'");

        // Demande validée 2026 (5 jours) : défalquée.
        $this->db->execute(
            'INSERT INTO leave_requests (employee_id, leave_type_id, starts_on, ends_on, days_count, status)
             VALUES (:e, :t, :s, :en, :d, :status)',
            ['e' => $employeeId, 't' => $cpId, 's' => '2026-08-03', 'en' => '2026-08-07', 'd' => 5, 'status' => 'approved']
        );
        // Demande rejetée : ignorée.
        $this->db->execute(
            'INSERT INTO leave_requests (employee_id, leave_type_id, starts_on, ends_on, days_count, status)
             VALUES (:e, :t, :s, :en, :d, :status)',
            ['e' => $employeeId, 't' => $cpId, 's' => '2026-09-01', 'en' => '2026-09-02', 'd' => 2, 'status' => 'rejected']
        );
        // Demande validée d'une autre année : ignorée.
        $this->db->execute(
            'INSERT INTO leave_requests (employee_id, leave_type_id, starts_on, ends_on, days_count, status)
             VALUES (:e, :t, :s, :en, :d, :status)',
            ['e' => $employeeId, 't' => $cpId, 's' => '2025-12-22', 'en' => '2025-12-23', 'd' => 2, 'status' => 'approved']
        );
        // RTT validé : non comptabilisé dans les congés payés.
        $this->db->execute(
            'INSERT INTO leave_requests (employee_id, leave_type_id, starts_on, ends_on, days_count, status)
             VALUES (:e, :t, :s, :en, :d, :status)',
            ['e' => $employeeId, 't' => $rttId, 's' => '2026-07-01', 'en' => '2026-07-01', 'd' => 1, 'status' => 'approved']
        );

        $summary = $this->service()->summary($employeeId, 2026);
        self::assertSame(5.0, $summary['taken_days']);
        self::assertEqualsWithDelta($summary['accrued_days'] - 5, $summary['remaining_days'], 0.001);
    }

    public function testTerminatedEmployeeStopsAccruingAtTermination(): void
    {
        $employeeId = $this->hireEmployee('2026-01-01', '2026-06-30');
        $summary = $this->service()->summary($employeeId, 2026);
        // Six mois pleins, janvier à juin.
        self::assertEqualsWithDelta(6.0, $summary['months'], 0.001);
    }

    public function testEmployeeWithoutHireDateHasZeroBalance(): void
    {
        $employeeId = $this->hireEmployee(null);
        $summary = $this->service()->summary($employeeId, 2026);
        self::assertNull($summary['hire_date']);
        self::assertSame(0.0, $summary['accrued_days']);
        self::assertSame(0.0, $summary['remaining_days']);
    }

    public function testCustomAccrualRateIsUsedAndValidated(): void
    {
        $settings = Application::container()->get(LeaveSettingsService::class);
        $settings->setMonthlyAccrual(2.5);
        self::assertEqualsWithDelta(2.5, $settings->monthlyAccrual(), 0.0001);

        $employeeId = $this->hireEmployee('2026-01-01');
        $summary = $this->service()->summary($employeeId, 2026);
        self::assertEqualsWithDelta(2.5, $summary['monthly_rate'], 0.0001);

        $this->expectException(ValidationException::class);
        $settings->setMonthlyAccrual(99.0);
    }
}
