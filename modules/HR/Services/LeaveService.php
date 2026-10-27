<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;

/**
 * Demandes de congés : calcul des jours ouvrés, workflow de validation,
 * mise à jour des soldes.
 */
final class LeaveService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Compte les jours ouvrés (lundi-vendredi) entre deux dates incluses.
     * Les jours fériés légaux ne sont pas défalqués automatiquement (voir
     * guide RH) : ils doivent être gérés via un type de congé spécifique.
     */
    public function workingDaysBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): float
    {
        if ($end < $start) {
            throw new ValidationException(['ends_on' => 'La date de fin précède la date de début.']);
        }
        $days = 0.0;
        $cursor = $start;
        while ($cursor <= $end) {
            if ($cursor->format('N') < 6) {
                $days++;
            }
            $cursor = $cursor->modify('+1 day');
        }
        return $days;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function submit(int $employeeId, array $data, ?int $userId = null): int
    {
        $start = new \DateTimeImmutable((string) $data['starts_on']);
        $end = new \DateTimeImmutable((string) $data['ends_on']);
        $days = $this->workingDaysBetween($start, $end);

        $type = $this->db->fetchOne('SELECT * FROM leave_types WHERE id = :id', ['id' => $data['leave_type_id']]);
        if ($type === null) {
            throw new NotFoundException('Type de congé introuvable.');
        }
        if ((bool) $type['is_paid']) {
            $balance = $this->db->fetchOne(
                'SELECT * FROM leave_balances
                 WHERE employee_id = :e AND leave_type_id = :t AND period_year = :y',
                ['e' => $employeeId, 't' => $type['id'], 'y' => (int) $start->format('Y')]
            );
            $remaining = (float) ($balance['remaining_days'] ?? (float) $type['default_days']);
            if ($days > $remaining) {
                throw new AppException("Solde insuffisant : {$remaining} jour(s) restant(s), demande de {$days} jour(s).");
            }
        }

        return $this->db->transaction(function (Connection $db) use ($employeeId, $data, $start, $end, $days, $userId): int {
            $db->execute(
                'INSERT INTO leave_requests
                    (employee_id, leave_type_id, starts_on, ends_on, days_count, reason, status, created_by)
                 VALUES (:e, :t, :s, :en, :d, :r, :status, :u)',
                [
                    'e' => $employeeId,
                    't' => $data['leave_type_id'],
                    's' => $start->format('Y-m-d'),
                    'en' => $end->format('Y-m-d'),
                    'd' => $days,
                    'r' => $data['reason'] ?? '',
                    'status' => 'submitted',
                    'u' => $userId,
                ]
            );
            $id = (int) $db->lastInsertId();
            audit('leave.submit', 'LeaveRequest', $id, ['days' => $days]);
            return $id;
        });
    }

    public function review(int $requestId, bool $approved, int $reviewerId, string $note = ''): void
    {
        $request = $this->db->fetchOne('SELECT * FROM leave_requests WHERE id = :id', ['id' => $requestId]);
        if ($request === null) {
            throw new NotFoundException('Demande de congé introuvable.');
        }
        if ($request['status'] !== 'submitted') {
            throw new AppException('Cette demande a déjà été traitée.');
        }
        $this->db->transaction(function (Connection $db) use ($request, $approved, $reviewerId, $note): void {
            $status = $approved ? 'approved' : 'rejected';
            $db->execute(
                'UPDATE leave_requests SET status = :s, reviewed_by = :r, reviewed_at = ' . $db->nowExpression() . ', review_note = :n
                 WHERE id = :id',
                ['s' => $status, 'r' => $reviewerId, 'n' => $note, 'id' => $request['id']]
            );
            if ($approved) {
                $this->consumeBalance($db, $request);
            }
            audit($approved ? 'leave.approve' : 'leave.reject', 'LeaveRequest', (int) $request['id']);
        });
    }

    public function cancel(int $requestId): void
    {
        $request = $this->db->fetchOne('SELECT * FROM leave_requests WHERE id = :id', ['id' => $requestId]);
        if ($request === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        if (!in_array($request['status'], ['draft', 'submitted'], true)) {
            throw new AppException('Une demande validée ne peut être annulée (régularisation comptable requise).');
        }
        $this->db->execute("UPDATE leave_requests SET status = 'cancelled' WHERE id = :id", ['id' => $requestId]);
        audit('leave.cancel', 'LeaveRequest', $requestId);
    }

    /**
     * Initialise les soldes annuels d'un salarié depuis les types de congés.
     */
    public function initializeBalances(int $employeeId, int $year): void
    {
        $types = $this->db->fetchAll('SELECT * FROM leave_types WHERE is_paid = TRUE');
        foreach ($types as $type) {
            $this->db->insertIgnore(
                'leave_balances',
                [
                    'employee_id' => $employeeId,
                    'leave_type_id' => $type['id'],
                    'period_year' => $year,
                    'initial_days' => $type['default_days'],
                    'acquired_days' => 0,
                    'taken_days' => 0,
                    'remaining_days' => $type['default_days'],
                ],
                ['employee_id', 'leave_type_id', 'period_year']
            );
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pendingRequests(): array
    {
        return $this->db->fetchAll(
            "SELECT lr.*, e.first_name, e.last_name, e.employee_number, lt.label AS type_label
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.status = 'submitted'
             ORDER BY lr.created_at"
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function employeeRequests(int $employeeId): array
    {
        return $this->db->fetchAll(
            "SELECT lr.*, lt.label AS type_label, lt.color
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = :e
             ORDER BY lr.created_at DESC",
            ['e' => $employeeId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function employeeBalances(int $employeeId, int $year): array
    {
        return $this->db->fetchAll(
            'SELECT lb.*, lt.label, lt.code FROM leave_balances lb
             JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.employee_id = :e AND period_year = :y',
            ['e' => $employeeId, 'y' => $year]
        );
    }

    /**
     * @param array<string,mixed> $request
     */
    private function consumeBalance(Connection $db, array $request): void
    {
        $year = (int) (new \DateTimeImmutable((string) $request['starts_on']))->format('Y');
        $days = (float) $request['days_count'];
        // En conflit (solde déjà initialisé), on ajoute la consommation ;
        // sinon la ligne est créée avec les valeurs entrantes.
        $db->upsert(
            'leave_balances',
            [
                'employee_id' => $request['employee_id'],
                'leave_type_id' => $request['leave_type_id'],
                'period_year' => $year,
                'initial_days' => 0,
                'acquired_days' => 0,
                'taken_days' => $days,
                'remaining_days' => -$days,
            ],
            ['employee_id', 'leave_type_id', 'period_year'],
            [],
            [
                'taken_days' => 'taken_days + ' . $db->conflictValue('taken_days'),
                'remaining_days' => 'remaining_days + ' . $db->conflictValue('remaining_days'),
            ]
        );
    }
}
