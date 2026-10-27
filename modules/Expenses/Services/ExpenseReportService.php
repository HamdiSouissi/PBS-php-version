<?php

declare(strict_types=1);

namespace Modules\Expenses\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;

/**
 * Cycle de vie des notes de frais : brouillon, soumission, validation,
 * rejet et paiement. Les totaux sont recalculés à partir des lignes.
 */
final class ExpenseReportService
{
    public const CATEGORIES = ['transport', 'meal', 'accommodation', 'fuel', 'kilometric', 'supplies', 'other'];

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, e.employee_number, e.first_name, e.last_name
             FROM expense_reports r JOIN employees e ON e.id = r.employee_id
             ORDER BY r.created_at DESC, r.id DESC LIMIT 300'
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function getOrFail(int $id): array
    {
        $report = $this->db->fetchOne(
            'SELECT r.*, e.employee_number, e.first_name, e.last_name
             FROM expense_reports r JOIN employees e ON e.id = r.employee_id WHERE r.id = :id',
            ['id' => $id]
        );
        if ($report === null) {
            throw new NotFoundException('Note de frais introuvable.');
        }
        return $report;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function lines(int $reportId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM expense_lines WHERE report_id = :id ORDER BY spent_on, id',
            ['id' => $reportId]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $userId): int
    {
        return $this->db->transaction(function (Connection $db) use ($data, $userId): int {
            $db->execute(
                'INSERT INTO expense_reports (employee_id, title, status, total_amount, notes, created_at, updated_at)
                 VALUES (:employee, :title, \'draft\', 0, :notes, ' . $db->nowExpression() . ', ' . $db->nowExpression() . ')',
                [
                    'employee' => (int) $data['employee_id'],
                    'title' => $data['title'],
                    'notes' => $data['notes'] ?? '',
                ]
            );
            $reportId = (int) $db->lastInsertId();
            $this->replaceLines($db, $reportId, $data['lines'] ?? []);
            $this->refreshTotal($db, $reportId);
            audit('expense.create', 'ExpenseReport', $reportId);
            return $reportId;
        });
    }

    public function transition(int $id, string $target, int $userId): void
    {
        $report = $this->getOrFail($id);
        $allowed = [
            'submit' => ['from' => 'draft', 'to' => 'submitted'],
            'approve' => ['from' => 'submitted', 'to' => 'approved'],
            'reject' => ['from' => 'submitted', 'to' => 'rejected'],
            'pay' => ['from' => 'approved', 'to' => 'paid'],
        ];
        if (!isset($allowed[$target]) || $report['status'] !== $allowed[$target]['from']) {
            throw new AppException("Transition impossible : le statut « {$report['status']} » ne permet pas cette action.");
        }
        $extra = '';
        $params = ['id' => $id, 'status' => $allowed[$target]['to']];
        if ($target === 'submit') {
            $extra = ', submitted_at = ' . $this->db->nowExpression();
        } elseif ($target === 'approve') {
            $extra = ', approved_by = :user, approved_at = ' . $this->db->nowExpression();
            $params['user'] = $userId;
        } elseif ($target === 'pay') {
            $extra = ', paid_on = :paid';
            $params['paid'] = date('Y-m-d');
        }
        $this->db->execute(
            "UPDATE expense_reports SET status = :status {$extra}, updated_at = " . $this->db->nowExpression() . ' WHERE id = :id',
            $params
        );
        audit('expense.' . $target, 'ExpenseReport', $id);
    }

    /**
     * @param array<int,array<string,mixed>> $lines
     */
    private function replaceLines(Connection $db, int $reportId, array $lines): void
    {
        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $category = in_array($line['category'] ?? '', self::CATEGORIES, true) ? $line['category'] : 'other';
            $db->execute(
                'INSERT INTO expense_lines (report_id, spent_on, category, label, amount, vat_amount, distance_km, km_rate, ocr_data)
                 VALUES (:report, :spent, :category, :label, :amount, :vat, :distance, :rate, \'{}\')',
                [
                    'report' => $reportId,
                    'spent' => (string) ($line['spent_on'] ?? date('Y-m-d')),
                    'category' => $category,
                    'label' => mb_substr($label, 0, 255),
                    'amount' => $amount,
                    'vat' => round((float) ($line['vat_amount'] ?? 0), 2),
                    'distance' => (float) ($line['distance_km'] ?? 0),
                    'rate' => (float) ($line['km_rate'] ?? 0),
                ]
            );
        }
    }

    private function refreshTotal(Connection $db, int $reportId): void
    {
        $total = (float) $db->fetchScalar(
            'SELECT COALESCE(SUM(amount), 0) FROM expense_lines WHERE report_id = :id',
            ['id' => $reportId]
        );
        $db->execute('UPDATE expense_reports SET total_amount = :total WHERE id = :id', ['total' => $total, 'id' => $reportId]);
    }
}
