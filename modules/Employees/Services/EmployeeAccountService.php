<?php

declare(strict_types=1);

namespace Modules\Employees\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use App\Core\Security\Password;
use Modules\Authentication\Repositories\UserRepository;

/**
 * Création et rattachement du compte utilisateur d'une fiche salarié.
 *
 * À l'embauche, chaque salarié reçoit un compte de connexion portant le rôle
 * « employe » et un mot de passe temporaire qu'il doit obligatoirement
 * changer à la première connexion. Si un compte existe déjà avec la même
 * adresse e-mail, il est simplement rattaché (sans toucher à ses rôles ni
 * à son mot de passe).
 */
final class EmployeeAccountService
{
    /** Code du rôle salarié (cf. RbacSeeder). */
    public const EMPLOYEE_ROLE = 'employe';

    /** Mot de passe temporaire imposé à la première connexion. */
    public const TEMPORARY_PASSWORD = 'change_me@';

    public function __construct(
        private readonly Connection $db,
        private readonly UserRepository $users,
        private readonly Password $password
    ) {
    }

    /**
     * Provisionne le compte d'un salarié et l'enregistre sur sa fiche.
     *
     * @return int|null identifiant du compte, null si aucune adresse e-mail valide
     */
    public function provision(int $employeeId): ?int
    {
        $employee = $this->db->fetchOne('SELECT * FROM employees WHERE id = :id', ['id' => $employeeId]);
        if ($employee === null) {
            throw new NotFoundException("Salarié introuvable (#{$employeeId}).");
        }
        if (!empty($employee['user_id'])) {
            return (int) $employee['user_id'];
        }

        $email = $this->resolveEmail($employee);
        if ($email === null) {
            return null;
        }

        return $this->db->transaction(function (Connection $db) use ($employee, $email): int {
            $existing = $this->users->findByEmail($email);
            if ($existing === null) {
                // Insertion directe : le mot de passe temporaire ne respecte pas
                // les règles de force, mais must_change_password impose son
                // remplacement immédiat par un mot de passe fort.
                $userId = $this->users->create([
                    'email' => $email,
                    'password_hash' => $this->password->hash(self::TEMPORARY_PASSWORD),
                    'first_name' => (string) $employee['first_name'],
                    'last_name' => (string) $employee['last_name'],
                    'is_active' => true,
                    'must_change_password' => true,
                ]);
                audit('employee.account_create', 'User', $userId, [
                    'employee_id' => (int) $employee['id'],
                    'email' => $email,
                ]);
            } else {
                $userId = $existing->id;
                audit('employee.account_link', 'Employee', (int) $employee['id'], [
                    'user_id' => $userId,
                    'email' => $email,
                ]);
            }

            // Le rôle employe est ajouté sans retirer les rôles existants
            // (un responsable peut déjà disposer d'un rôle plus large).
            $db->insertIgnore(
                'role_user',
                ['role_id' => $this->employeeRoleId(), 'user_id' => $userId],
                ['role_id', 'user_id']
            );

            $db->execute(
                'UPDATE employees SET user_id = :user, updated_at = ' . $db->nowExpression() . ' WHERE id = :employee',
                ['user' => $userId, 'employee' => (int) $employee['id']]
            );

            return $userId;
        });
    }

    /**
     * Reprise de parc : rattache tous les salariés sans compte.
     *
     * @return array{created:int,linked:int,skipped:int}
     */
    public function provisionAll(): array
    {
        $stats = ['created' => 0, 'linked' => 0, 'skipped' => 0];
        $rows = $this->db->fetchAll('SELECT id FROM employees WHERE user_id IS NULL ORDER BY id');
        foreach ($rows as $row) {
            $employeeId = (int) $row['id'];
            $employee = $this->db->fetchOne('SELECT * FROM employees WHERE id = :id', ['id' => $employeeId]);
            $email = $employee === null ? null : $this->resolveEmail($employee);
            if ($email === null) {
                $stats['skipped']++;
                continue;
            }
            $existed = $this->users->findByEmail($email) !== null;
            $this->provision($employeeId);
            $existed ? $stats['linked']++ : $stats['created']++;
        }
        return $stats;
    }

    /**
     * Première adresse e-mail exploitable (pro puis perso).
     *
     * @param array<string,mixed> $employee
     */
    private function resolveEmail(array $employee): ?string
    {
        foreach (['professional_email', 'personal_email'] as $column) {
            $email = trim((string) ($employee[$column] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                return mb_strtolower($email);
            }
        }
        return null;
    }

    private function employeeRoleId(): int
    {
        $id = $this->db->fetchScalar(
            'SELECT id FROM roles WHERE code = :code',
            ['code' => self::EMPLOYEE_ROLE]
        );
        if ($id === false || $id === null) {
            throw new AppException("Rôle « " . self::EMPLOYEE_ROLE . " » introuvable : exécutez le peuplement RBAC.");
        }
        return (int) $id;
    }
}
