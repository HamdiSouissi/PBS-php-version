<?php

declare(strict_types=1);

namespace Modules\Employees\Services;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;

/**
 * Contrats de travail : un seul contrat actif par salarié (garanti en base logique).
 */
final class ContractService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(int $employeeId, array $data, ?int $userId = null): int
    {
        $employee = $this->db->fetchOne('SELECT id, status FROM employees WHERE id = :id', ['id' => $employeeId]);
        if ($employee === null) {
            throw new NotFoundException('Salarié introuvable.');
        }
        $status = $data['status'] ?? 'draft';
        return $this->db->transaction(function (Connection $db) use ($employeeId, $data, $status, $userId): int {
            if ($status === 'active') {
                $db->execute(
                    "UPDATE employment_contracts SET status = 'ended',
                        end_date = COALESCE(end_date, :start), updated_at = NOW()
                     WHERE employee_id = :e AND status = 'active'",
                    ['start' => $data['start_date'], 'e' => $employeeId]
                );
            }
            $id = $db->lastInsertId();
            $db->execute(
                'INSERT INTO employment_contracts
                    (employee_id, type, start_date, end_date, trial_period_end, weekly_hours, is_part_time,
                     part_time_pct, position, classification, coefficient, hourly_rate, monthly_gross,
                     qualification, status, signed_at, created_by)
                 VALUES
                    (:employee_id, :type, :start_date, :end_date, :trial, :hours, :part_time,
                     :part_pct, :position, :classification, :coefficient, :hourly, :monthly,
                     :qualification, :status, :signed, :created_by)',
                [
                    'employee_id' => $employeeId,
                    'type' => $data['type'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'] ?? null,
                    'trial' => $data['trial_period_end'] ?? null,
                    'hours' => $data['weekly_hours'] ?? 35,
                    'part_time' => (bool) ($data['is_part_time'] ?? false),
                    'part_pct' => $data['part_time_pct'] ?? 100,
                    'position' => $data['position'] ?? '',
                    'classification' => $data['classification'] ?? '',
                    'coefficient' => $data['coefficient'] ?? '',
                    'hourly' => $data['hourly_rate'] ?? 0,
                    'monthly' => $data['monthly_gross'] ?? 0,
                    'qualification' => $data['qualification'] ?? '',
                    'status' => $status,
                    'signed' => $data['signed_at'] ?? null,
                    'created_by' => $userId,
                ]
            );
            $newId = (int) $db->lastInsertId();
            if ($status === 'active') {
                $db->execute(
                    "UPDATE employees SET status = 'active', hire_date = COALESCE(hire_date, :d), updated_at = NOW()
                     WHERE id = :e",
                    ['d' => $data['start_date'], 'e' => $employeeId]
                );
            }
            audit('contract.create', 'EmploymentContract', $newId, ['employee' => $employeeId]);
            return $newId;
        });
    }

    public function activate(int $contractId): void
    {
        $contract = $this->db->fetchOne('SELECT * FROM employment_contracts WHERE id = :id', ['id' => $contractId]);
        if ($contract === null) {
            throw new NotFoundException('Contrat introuvable.');
        }
        $this->db->transaction(function (Connection $db) use ($contract): void {
            $db->execute(
                "UPDATE employment_contracts SET status = 'ended',
                    end_date = COALESCE(end_date, :start), updated_at = NOW()
                 WHERE employee_id = :e AND status = 'active' AND id <> :id",
                ['start' => $contract['start_date'], 'e' => $contract['employee_id'], 'id' => $contract['id']]
            );
            $db->execute(
                "UPDATE employment_contracts SET status = 'active', updated_at = NOW() WHERE id = :id",
                ['id' => $contract['id']]
            );
            $db->execute(
                "UPDATE employees SET status = 'active', hire_date = COALESCE(hire_date, :d), updated_at = NOW()
                 WHERE id = :e",
                ['d' => $contract['start_date'], 'e' => $contract['employee_id']]
            );
        });
        audit('contract.activate', 'EmploymentContract', $contractId);
    }
}
