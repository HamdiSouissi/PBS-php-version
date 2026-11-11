<?php

declare(strict_types=1);

namespace Modules\Employees\Services;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use Modules\Employees\Repositories\EmployeeRepository;

/**
 * Cycle de vie des salariés : embauche, modification, sortie, portail.
 */
final class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepository $repository,
        private readonly Connection $db,
        private readonly EmployeeAccountService $accounts
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(int $companyId, array $data): int
    {
        $this->assertUniqueEmail($companyId, $data, null);
        $number = $this->repository->nextEmployeeNumber($companyId);
        return $this->db->transaction(function (Connection $db) use ($companyId, $data, $number): int {
            $employeeId = $this->repository->insert('employees', [
                'company_id' => $companyId,
                'establishment_id' => $data['establishment_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'employee_number' => $number,
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'birth_date' => $data['birth_date'] ?? null,
                'birth_place' => $data['birth_place'] ?? '',
                'gender' => $data['gender'] ?? 'non_renseigne',
                'social_security_number' => $this->normalizeNir((string) ($data['social_security_number'] ?? '')),
                'nationality' => $data['nationality'] ?? 'Française',
                'personal_email' => $data['personal_email'] ?? '',
                'professional_email' => $data['professional_email'] ?? '',
                'phone' => $data['phone'] ?? '',
                'address' => $data['address'] ?? '',
                'postal_code' => $data['postal_code'] ?? '',
                'city' => $data['city'] ?? '',
                'iban' => $data['iban'] ?? '',
                'bic' => $data['bic'] ?? '',
                'job_title' => $data['job_title'] ?? '',
                'qualification' => $data['qualification'] ?? '',
                'coefficient' => $data['coefficient'] ?? '',
                'status' => $data['status'] ?? 'candidate',
                'hire_date' => $data['hire_date'] ?? null,
            ]);
            audit('employee.create', 'Employee', $employeeId, ['number' => $number]);

            // Création automatique du compte de connexion (rôle employe,
            // mot de passe temporaire). Sans e-mail exploitable, la fiche est
            // conservée et la reprise se fait via la commande dédiée.
            $this->accounts->provision($employeeId);

            return $employeeId;
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $employee = $this->repository->findOrFail($id);
        $allowed = [
            'establishment_id', 'department_id', 'manager_id', 'last_name', 'first_name', 'birth_date',
            'birth_place', 'gender', 'social_security_number', 'nationality', 'personal_email',
            'professional_email', 'phone', 'address', 'postal_code', 'city', 'iban', 'bic',
            'job_title', 'qualification', 'coefficient', 'status', 'hire_date', 'termination_date',
            'termination_reason',
        ];
        $update = array_intersect_key($data, array_flip($allowed));
        if ($update === []) {
            return;
        }
        if (isset($update['social_security_number'])) {
            $update['social_security_number'] = $this->normalizeNir((string) $update['social_security_number']);
        }
        $this->db->transaction(function (Connection $db) use ($id, $employee, $update): void {
            $userId = (int) ($employee['user_id'] ?? 0);
            $changes = $update;
            if ($userId > 0 && array_key_exists('professional_email', $changes)) {
                $email = mb_strtolower(trim((string) $changes['professional_email']));
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    throw new ValidationException(['professional_email' => 'Une adresse professionnelle valide est obligatoire pour le compte lié.']);
                }
                $changes['professional_email'] = $email;
                $user = $db->fetchOne('SELECT email FROM users WHERE id = :id AND deleted_at IS NULL' . ($db->isSqlite() ? '' : ' FOR UPDATE'), ['id' => $userId]);
                if ($user === null) {
                    throw new ValidationException(['professional_email' => 'Le compte lié est introuvable.']);
                }
                if (mb_strtolower((string) $user['email']) !== $email) {
                    if ($db->fetchOne('SELECT id FROM users WHERE LOWER(email) = :email AND id <> :id', ['email' => $email, 'id' => $userId]) !== null) {
                        throw new ValidationException(['professional_email' => 'Cette adresse est déjà utilisée par un compte de connexion.']);
                    }
                    if ($db->fetchOne('SELECT id FROM employees WHERE user_id = :user AND id <> :id', ['user' => $userId, 'id' => $id]) !== null) {
                        throw new ValidationException(['professional_email' => 'Ce compte est lié à plusieurs salariés : corrigez les rattachements avant de modifier son identifiant.']);
                    }
                    $db->execute('UPDATE users SET email = :email, updated_at = ' . $db->nowExpression() . ' WHERE id = :id', ['email' => $email, 'id' => $userId]);
                    $db->execute('DELETE FROM password_resets WHERE user_id = :id', ['id' => $userId]);
                    $db->execute('DELETE FROM remember_tokens WHERE user_id = :id', ['id' => $userId]);
                }
            }
            $changes['updated_at'] = date('Y-m-d H:i:s');
            $this->repository->update('employees', $changes, ['id' => $id]);
            audit('employee.update', 'Employee', $id);
        });
    }

    /**
     * Sortie d'un salarié : clotûre du contrat actif et mise à jour du statut.
     */
    public function terminate(int $id, string $date, string $reason): void
    {
        $this->repository->findOrFail($id);
        $this->db->transaction(function (Connection $db) use ($id, $date, $reason): void {
            $db->execute(
                "UPDATE employees SET status = 'terminated', termination_date = :d,
                    termination_reason = :r, updated_at = {$db->nowExpression()} WHERE id = :id",
                ['d' => $date, 'r' => $reason, 'id' => $id]
            );
            $db->execute(
                "UPDATE employment_contracts SET status = 'ended', end_date = :d, updated_at = {$db->nowExpression()}
                 WHERE employee_id = :id AND status = 'active'",
                ['d' => $date, 'id' => $id]
            );
        });
        audit('employee.terminate', 'Employee', $id, ['date' => $date, 'reason' => $reason]);
    }

    public function enablePortal(int $id): string
    {
        $this->repository->findOrFail($id);
        $token = bin2hex(random_bytes(24));
        $this->db->execute(
            'UPDATE employees SET is_portal_enabled = TRUE, portal_token = :t, updated_at = ' . $this->db->nowExpression() . ' WHERE id = :id',
            ['t' => hash('sha256', $token), 'id' => $id]
        );
        audit('employee.portal_enable', 'Employee', $id);
        return $token;
    }

    public function getOrFail(int $id): array
    {
        $row = $this->repository->findDetailed($id);
        if ($row === null) {
            throw new NotFoundException('Salarié introuvable.');
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function assertUniqueEmail(int $companyId, array $data, ?int $exceptId): void
    {
        foreach (['personal_email', 'professional_email'] as $column) {
            $email = trim((string) ($data[$column] ?? ''));
            if ($email === '') {
                continue;
            }
            $sql = 'SELECT id FROM employees
                    WHERE company_id = :c AND (personal_email = :e OR professional_email = :e)';
            $params = ['c' => $companyId, 'e' => $email];
            if ($exceptId !== null) {
                $sql .= ' AND id <> :id';
                $params['id'] = $exceptId;
            }
            if ($this->db->fetchOne($sql, $params) !== null) {
                throw new ValidationException([$column => 'Un salarié utilise déjà cette adresse e-mail.']);
            }
        }
    }

    /**
     * Nettoie le NIR (numéro de sécurité sociale) : 15 chiffres max.
     */
    private function normalizeNir(string $nir): string
    {
        $clean = preg_replace('/\s+/', '', $nir) ?? '';
        return $clean !== '' && !preg_match('/^[123][0-9]{14}$/', $clean) && strlen($clean) === 15
            ? $clean
            : $clean;
    }
}
