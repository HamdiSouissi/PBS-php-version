<?php

declare(strict_types=1);

namespace Modules\Employees\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use App\Core\Security\Password;
use Modules\Authentication\Repositories\UserRepository;

/** Création explicite d'un compte pour une fiche existante, sans rattachement implicite. */
final class EmployeeAccountAdministrationService
{
    public function __construct(
        private readonly Connection $db,
        private readonly UserRepository $users,
        private readonly Password $password
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function roles(): array
    {
        return $this->db->fetchAll('SELECT id, code, label FROM roles ORDER BY label');
    }

    /** @return array<string,mixed> */
    private function employee(int $id, bool $lock = false): array
    {
        $row = $this->db->fetchOne('SELECT * FROM employees WHERE id = :id' . ($lock && !$this->db->isSqlite() ? ' FOR UPDATE' : ''), ['id' => $id]);
        if ($row === null) {
            throw new NotFoundException('Salarié introuvable.');
        }
        if ($row['status'] === 'terminated') {
            throw new AppException('Impossible de créer ou rattacher un compte pour un salarié sorti.');
        }
        if (!empty($row['user_id'])) {
            throw new AppException('Ce salarié possède déjà un compte rattaché.');
        }
        $email = mb_strtolower(trim((string) $row['professional_email']));
        if (strlen($email) > 180 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new AppException('Renseignez une adresse e-mail professionnelle valide sur la fiche avant de continuer.');
        }
        $row['professional_email'] = $email;
        return $row;
    }

    /** Crée le compte uniquement si l'adresse n'existe pas ; ne touche jamais aux comptes existants. */
    public function create(int $employeeId, int $roleId): array
    {
        try {
            return $this->db->transaction(function (Connection $db) use ($employeeId, $roleId): array {
                $employee = $this->employee($employeeId, true);
                $existing = $this->users->findByEmail($employee['professional_email']);
                if ($existing !== null) {
                    return ['status' => 'exists'];
                }
                $role = $db->fetchOne('SELECT id FROM roles WHERE id = :id', ['id' => $roleId]);
                if ($role === null) {
                    throw new AppException('Sélectionnez un type de compte valide.');
                }
                // Secret aléatoire inconnu de tous : aucun mot de passe initial n'est envoyé.
                $userId = $this->users->create([
                    'email' => $employee['professional_email'],
                    'password_hash' => $this->password->hash(bin2hex(random_bytes(32))),
                    'first_name' => $employee['first_name'],
                    'last_name' => $employee['last_name'],
                    'is_active' => true,
                    'must_change_password' => true,
                ]);
                $db->insertIgnore('role_user', ['role_id' => $roleId, 'user_id' => $userId], ['role_id', 'user_id']);
                $db->execute('UPDATE employees SET user_id = :user, updated_at = ' . $db->nowExpression() . ' WHERE id = :id', ['user' => $userId, 'id' => $employeeId]);
                audit('employee.account_create', 'User', $userId, ['employee_id' => $employeeId, 'role_id' => $roleId]);
                return ['status' => 'created', 'user_id' => $userId, 'employee' => $employee];
            });
        } catch (\PDOException $exception) {
            // Une création concurrente peut gagner la course sur la contrainte UNIQUE users.email.
            $employee = $this->employee($employeeId);
            if ($this->users->findByEmail($employee['professional_email']) !== null) {
                return ['status' => 'exists'];
            }
            throw $exception;
        }
    }

    /** Retrouve le compte correspondant à l'adresse actuelle, sans faire confiance au navigateur. */
    public function existing(int $employeeId): array
    {
        $employee = $this->employee($employeeId);
        $user = $this->users->findByEmail($employee['professional_email']);
        if ($user === null) {
            throw new AppException('Aucun compte ne correspond à cette adresse professionnelle.');
        }
        return ['employee' => $employee, 'user' => $user,
            'linked_elsewhere' => $this->db->fetchOne('SELECT id FROM employees WHERE user_id = :user AND id <> :employee', ['user' => $user->id, 'employee' => $employeeId]) !== null];
    }

    /** Rattachement volontaire ; les rôles et le mot de passe existants ne sont pas modifiés. */
    public function linkExisting(int $employeeId): void
    {
        $this->db->transaction(function (Connection $db) use ($employeeId): void {
            $employee = $this->employee($employeeId, true);
            $row = $db->fetchOne('SELECT id, is_active FROM users WHERE email = :email AND deleted_at IS NULL' . ($db->isSqlite() ? '' : ' FOR UPDATE'), ['email' => $employee['professional_email']]);
            if ($row === null || !(bool) $row['is_active']) {
                throw new AppException('Compte existant introuvable ou inactif.');
            }
            if ($db->fetchOne('SELECT id FROM employees WHERE user_id = :user AND id <> :employee', ['user' => $row['id'], 'employee' => $employeeId]) !== null) {
                throw new AppException('Ce compte est déjà rattaché à un autre salarié.');
            }
            $db->execute('UPDATE employees SET user_id = :user, updated_at = ' . $db->nowExpression() . ' WHERE id = :id', ['user' => $row['id'], 'id' => $employeeId]);
            audit('employee.account_link', 'Employee', $employeeId, ['user_id' => (int) $row['id']]);
        });
    }
}
