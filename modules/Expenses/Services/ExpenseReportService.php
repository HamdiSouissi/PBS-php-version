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
             WHERE r.deleted_at IS NULL ORDER BY r.created_at DESC, r.id DESC LIMIT 300'
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function getOrFail(int $id): array
    {
        $report = $this->db->fetchOne(
            'SELECT r.*, e.employee_number, e.first_name, e.last_name
             FROM expense_reports r JOIN employees e ON e.id = r.employee_id WHERE r.id = :id AND r.deleted_at IS NULL',
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

    /** @return list<array<string,mixed>> */
    public function archived(): array
    {
        return $this->db->fetchAll('SELECT r.*, e.first_name, e.last_name FROM expense_reports r JOIN employees e ON e.id = r.employee_id WHERE r.deleted_at IS NOT NULL ORDER BY r.deleted_at DESC, r.id DESC');
    }

    public function archivedOrFail(int $id): array
    {
        $row = $this->db->fetchOne('SELECT r.*, e.first_name, e.last_name FROM expense_reports r JOIN employees e ON e.id = r.employee_id WHERE r.id = :id AND r.deleted_at IS NOT NULL', ['id' => $id]);
        if ($row === null) {
            throw new NotFoundException('Note archivée introuvable.');
        }
        return $row;
    }

    private function lockDraft(Connection $db, int $id): array
    {
        $row = $db->fetchOne('SELECT * FROM expense_reports WHERE id = :id AND deleted_at IS NULL' . ($db->isSqlite() ? '' : ' FOR UPDATE'), ['id' => $id]);
        if ($row === null) {
            throw new NotFoundException('Note de frais introuvable.');
        }
        if ($row['status'] !== 'draft') {
            throw new AppException('Seuls les brouillons peuvent être modifiés ou supprimés.');
        }
        return $row;
    }

    /** @param array<string,mixed> $data @param list<array<string,mixed>> $lines */
    public function updateDraft(int $id, array $data, array $lines): void
    {
        if ($lines === []) {
            throw new AppException('Ajoutez au moins une ligne de dépense.');
        }
        $this->db->transaction(function (Connection $db) use ($id, $data, $lines): void {
            $old = $this->lockDraft($db, $id);
            if ($db->fetchOne('SELECT id FROM employees WHERE id = :id', ['id' => $data['employee_id']]) === null) {
                throw new AppException('Salarié introuvable.');
            }
            $existing = [];
            foreach ($db->fetchAll('SELECT * FROM expense_lines WHERE report_id = :id', ['id' => $id]) as $line) {
                $existing[(int) $line['id']] = $line;
            }
            $seen = [];
            foreach ($lines as $line) {
                $lineId = (int) ($line['id'] ?? 0);
                $label = trim((string) ($line['label'] ?? ''));
                $date = (string) ($line['spent_on'] ?? '');
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                $amount = (string) ($line['amount'] ?? '');
                $vat = (string) ($line['vat_amount'] ?? '0');
                if ($label === '' || mb_strlen($label) > 255 || $parsed === false || $parsed->format('Y-m-d') !== $date
                    || !in_array($line['category'] ?? '', self::CATEGORIES, true)
                    || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount) || (float) $amount <= 0
                    || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $vat) || (float) $vat > (float) $amount) {
                    throw new AppException('Vérifiez les lignes : date, catégorie, libellé et montants valides sont obligatoires ; la TVA ne peut dépasser le TTC.');
                }
                if ($lineId !== 0 && (!isset($existing[$lineId]) || isset($seen[$lineId]))) {
                    throw new AppException('Une ligne ne correspond pas à cette note.');
                }
                $distance = (string) ($line['distance_km'] ?? ($existing[$lineId]['distance_km'] ?? '0'));
                $rate = (string) ($line['km_rate'] ?? ($existing[$lineId]['km_rate'] ?? '0'));
                if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $distance) || !preg_match('/^\d{1,5}(?:\.\d{1,3})?$/D', $rate)) {
                    throw new AppException('Vérifiez la distance et le taux kilométrique.');
                }
                $params = ['report' => $id, 'spent' => $date, 'category' => $line['category'], 'label' => $label, 'amount' => $amount, 'vat' => $vat, 'distance' => $distance, 'rate' => $rate];
                if ($lineId !== 0) {
                    $seen[$lineId] = true;
                    $params['id'] = $lineId;
                    $db->execute('UPDATE expense_lines SET spent_on = :spent, category = :category, label = :label, amount = :amount, vat_amount = :vat, distance_km = :distance, km_rate = :rate WHERE id = :id AND report_id = :report', $params);
                } else {
                    $db->execute("INSERT INTO expense_lines (report_id, spent_on, category, label, amount, vat_amount, distance_km, km_rate, ocr_data) VALUES (:report, :spent, :category, :label, :amount, :vat, :distance, :rate, '{}')", $params);
                }
            }
            foreach ($existing as $lineId => $line) {
                if (!isset($seen[$lineId])) {
                    if (trim((string) $line['receipt_path']) !== '') {
                        throw new AppException('Une ligne possède un justificatif historique et ne peut pas être supprimée.');
                    }
                    $db->execute('DELETE FROM expense_lines WHERE id = :id AND report_id = :report', ['id' => $lineId, 'report' => $id]);
                }
            }
            $db->execute('UPDATE expense_reports SET employee_id = :employee, title = :title, notes = :notes, updated_at = ' . $db->nowExpression() . ' WHERE id = :id', ['employee' => (int) $data['employee_id'], 'title' => $data['title'], 'notes' => $data['notes'] ?? '', 'id' => $id]);
            $this->refreshTotal($db, $id);
            audit('expense.update', 'ExpenseReport', $id, ['previous_title' => $old['title'], 'previous_total' => $old['total_amount'], 'previous_employee_id' => $old['employee_id']]);
        });
    }

    public function deleteDraft(int $id, int $userId): void
    {
        $this->db->transaction(function (Connection $db) use ($id, $userId): void {
            $row = $this->lockDraft($db, $id);
            $db->execute('UPDATE expense_reports SET deleted_at = ' . $db->nowExpression() . ', deleted_by = :user, updated_at = ' . $db->nowExpression() . ' WHERE id = :id', ['user' => $userId, 'id' => $id]);
            audit('expense.delete', 'ExpenseReport', $id, ['title' => $row['title'], 'total' => $row['total_amount'], 'employee_id' => $row['employee_id']]);
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
        $params = ['id' => $id, 'status' => $allowed[$target]['to'], 'expected' => $allowed[$target]['from']];
        if ($target === 'submit') {
            $extra = ', submitted_at = ' . $this->db->nowExpression();
        } elseif ($target === 'approve') {
            $extra = ', approved_by = :user, approved_at = ' . $this->db->nowExpression();
            $params['user'] = $userId;
        } elseif ($target === 'pay') {
            $extra = ', paid_on = :paid';
            $params['paid'] = date('Y-m-d');
        }
        $changed = $this->db->execute(
            "UPDATE expense_reports SET status = :status {$extra}, updated_at = " . $this->db->nowExpression() . ' WHERE id = :id AND deleted_at IS NULL AND status = :expected',
            $params
        );
        if ($changed !== 1) {
            throw new AppException('La note a changé de statut ou a été supprimée. Actualisez la page.');
        }
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
