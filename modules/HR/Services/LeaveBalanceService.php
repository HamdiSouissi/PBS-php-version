<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use App\Core\Database\Connection;

/**
 * Calcul du solde de congés payés d'un salarié à partir de sa date
 * d'embauche, du taux d'acquisition mensuel réglable et des congés pris.
 *
 * Méthode : les jours acquis s'accumulent mois par mois de travail
 * effectif, avec proratisation au jour près des mois d'entrée et de sortie.
 * Les jours pris correspondent aux demandes validées (status 'approved')
 * de l'année civile de la demande.
 */
final class LeaveBalanceService
{
    public function __construct(
        private readonly Connection $db,
        private readonly LeaveSettingsService $settings
    ) {
    }

    /**
     * Nombre de mois (fraction inclus) entre deux dates, bornes comprises.
     *
     * - mois identique : fraction du mois couverte par la plage ;
     * - mois distincts : fraction du mois de départ + mois pleins intermédiaires
     *   + fraction du mois de fin.
     */
    public function accruedMonths(\DateTimeImmutable $start, \DateTimeImmutable $end): float
    {
        if ($end < $start) {
            return 0.0;
        }

        $startMonth = (int) $start->format('n');
        $endMonth = (int) $end->format('n');
        if ((int) $start->format('Y') === (int) $end->format('Y') && $startMonth === $endMonth) {
            $days = (int) $end->format('j') - (int) $start->format('j') + 1;
            return $days / (int) $start->format('t');
        }

        $monthSpan = ((int) $end->format('Y') - (int) $start->format('Y')) * 12
            + $endMonth - $startMonth;
        $startFraction = ((int) $start->format('t') - (int) $start->format('j') + 1)
            / (int) $start->format('t');
        $endFraction = (int) $end->format('j') / (int) $end->format('t');

        return $startFraction + ($monthSpan - 1) + $endFraction;
    }

    /**
     * Synthèse du solde de congés payés pour une année civile.
     *
     * @return array{
     *     year:int, hire_date:?string, months:float, monthly_rate:float,
     *     accrued_days:float, taken_days:float, remaining_days:float
     * }
     */
    public function summary(int $employeeId, ?int $year = null): array
    {
        $year ??= (int) date('Y');
        $employee = $this->db->fetchOne(
            'SELECT hire_date, termination_date FROM employees WHERE id = :id',
            ['id' => $employeeId]
        );

        $rate = $this->settings->monthlyAccrual();
        $base = [
            'year' => $year,
            'hire_date' => $employee['hire_date'] ?? null,
            'months' => 0.0,
            'monthly_rate' => $rate,
            'accrued_days' => 0.0,
            'taken_days' => 0.0,
            'remaining_days' => 0.0,
        ];

        if ($employee === null || empty($employee['hire_date'])) {
            return $base;
        }

        $hire = new \DateTimeImmutable((string) $employee['hire_date']);
        $yearStart = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $yearEnd = new \DateTimeImmutable(sprintf('%04d-12-31', $year));

        $periodStart = $hire > $yearStart ? $hire : $yearStart;
        $upperBound = $yearEnd;
        if (!empty($employee['termination_date'])) {
            $termination = new \DateTimeImmutable((string) $employee['termination_date']);
            if ($termination < $upperBound) {
                $upperBound = $termination;
            }
        }
        $today = new \DateTimeImmutable('today');
        if ($upperBound > $today) {
            $upperBound = $today;
        }

        if ($upperBound < $periodStart) {
            return $base;
        }

        $months = $this->accruedMonths($periodStart, $upperBound);
        $accrued = round($months * $rate, 2);
        $taken = $this->takenDays($employeeId, $year);
        $remaining = round($accrued - $taken, 2);

        return [
            'year' => $year,
            'hire_date' => (string) $employee['hire_date'],
            'months' => round($months, 4),
            'monthly_rate' => $rate,
            'accrued_days' => $accrued,
            'taken_days' => $taken,
            'remaining_days' => $remaining,
        ];
    }

    /**
     * Jours de congés payés validés et consommés sur l'année civile.
     */
    public function takenDays(int $employeeId, int $year): float
    {
        $rows = $this->db->fetchAll(
            "SELECT lr.starts_on, lr.days_count
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = :employee
               AND lt.code = :code
               AND lr.status = 'approved'",
            ['employee' => $employeeId, 'code' => LeaveSettingsService::PAID_LEAVE_CODE]
        );
        $total = 0.0;
        foreach ($rows as $row) {
            if ((int) substr((string) $row['starts_on'], 0, 4) === $year) {
                $total += (float) $row['days_count'];
            }
        }
        return $total;
    }
}
